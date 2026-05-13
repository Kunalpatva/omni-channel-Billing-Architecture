// -----------------------------------------------------
// firebase-auth-v12.js (COMPLETE & FIXED)
// -----------------------------------------------------

import { initializeApp } from "https://www.gstatic.com/firebasejs/11.6.0/firebase-app.js";

import { 
  getAuth, 
  signInWithCustomToken,
  onIdTokenChanged,
  initializeAuth, 
  indexedDBLocalPersistence 
} from "https://www.gstatic.com/firebasejs/11.6.0/firebase-auth.js";

// ✅ ADDED: getToken
import { getMessaging, getToken } 
from "https://www.gstatic.com/firebasejs/11.6.0/firebase-messaging.js";

// ✅ ADDED: ref, get, update
import { getDatabase, ref, get, update } 
from "https://www.gstatic.com/firebasejs/11.6.0/firebase-database.js";

import { getStorage } 
from "https://www.gstatic.com/firebasejs/11.6.0/firebase-storage.js";

import { getAnalytics } 
from "https://www.gstatic.com/firebasejs/11.6.0/firebase-analytics.js";


// -----------------------------------------------------
// 1️⃣ INITIALIZE FIREBASE (ONCE GLOBALLY)
// -----------------------------------------------------
const firebaseConfig = {
  apiKey: "api_key",
  authDomain: "auth.your-domain.com",
  databaseURL: "https://firebase-app.firebaseio.com",
  projectId: "firebase-app",
  storageBucket: "firebase-app.appspot.com",
  messagingSenderId: "id_value",
  appId: "app_id_value",
  measurementId: "measurement_id_value"
};

const app = initializeApp(firebaseConfig);

const auth = initializeAuth(app, {
  persistence: indexedDBLocalPersistence
});

const db = getDatabase(app);
const storage = getStorage(app);
const analytics = getAnalytics(app);

let messaging = null;
try { messaging = getMessaging(app); } 
catch (err) { console.warn("Messaging not supported:", err.message); }

window.firebaseApp = app;
window.auth = auth;
window.db = db;
window.storage = storage;
window.messaging = messaging;

window.__firebaseInitReady = true;


// -----------------------------------------------------
// 2️⃣ SESSION COOKIE CREATION (FIXED URL)
// -----------------------------------------------------
window.signInUsingCustomTokenAndCreateSession = async function(customToken) {
  try {
    const userCredential = await signInWithCustomToken(auth, customToken);
    const idToken = await userCredential.user.getIdToken();

    // Use absolute Firebase API function URL (Local WP Endpoint)
    const resp = await fetch('/wp-json/auth/v1/session', {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      body: JSON.stringify({ idToken })
    });

    const json = await resp.json();

    if (!json.success) throw new Error("Session cookie creation failed");

    return true;

  } catch (err) {
    console.error("Session cookie login error:", err);
    throw err;
  }
};


// -----------------------------------------------------
// 3️⃣ LOGOUT (FIXED DOUBLE LOGOUT)
// -----------------------------------------------------
window.convoLogout = async function() {
    try {
        // 1. Sign out of Firebase (Client side)
        await auth.signOut();
        
        // 2. Sign out of WordPress (Server side)
        window.location.href = "/?glogin=logout"; 
    } catch (err) {
        console.error("Logout failed:", err);
        window.location.reload(); 
    }
}


// -----------------------------------------------------
// 4️⃣ AUTH STATE LISTENER (UPDATED FOR NOTIFICATIONS)
// -----------------------------------------------------
onIdTokenChanged(auth, (user) => {
  window.__cdUser = user;

  if (user) {
    
    // ✅ NEW: Silently sync tokens for returning users in the background
    // if they previously allowed notifications.
    if (Notification.permission === 'granted' && window.syncNotificationData) {
        window.syncNotificationData(user);
    }
    
    if (window.onConvoLogin) window.onConvoLogin(user);
  } else {
    
    if (window.onConvoLogout && !window.__CD_SUPPRESS_AUTO_LOGOUT) {
      window.onConvoLogout();
    }
  }
});


// -----------------------------------------------------
// 5️⃣ GOOGLE CUSTOM TOKEN LOGIN (RESUME HANDLER)
// -----------------------------------------------------
// This logic finds the token passed by PHP and logs the user 
// into Firebase Client-Side.

const params = new URLSearchParams(window.location.search);
let googleToken = params.get("fbtoken");

// FALLBACK: If token is not in URL, check LocalStorage (where PHP saved it)
if (!googleToken) {
    googleToken = localStorage.getItem('fbtoken');
}

if (googleToken) {
    
    // 1. Clean up storage so we don't re-login unnecessarily
    localStorage.removeItem('fbtoken');

    // 2. Sign In
    signInWithCustomToken(auth, googleToken)
        .then(() => {
            // If we are on dashboard, refresh to update UI state
             if (window.location.pathname.includes("dashboard")) {
                 setTimeout(() => window.location.reload(), 500);
             }
        })
        .catch((err) => {
            console.error("❌ Token Login Failed:", err);
        });
}


window.syncNotificationData = async function(user) {
    if (!window.messaging) {
        
        return;
    }

    try {
        
        
        if (Notification.permission !== 'granted') {
        
            return;
        }

        const sw = await navigator.serviceWorker.register("/firebase-messaging-sw.js");
        const token = await getToken(window.messaging, {
            vapidKey: "vapid_key_id",
            serviceWorkerRegistration: sw
        });

        if (!token) {
        
            return;
        }

        const profileSnap = await get(ref(window.db, `db_path`));
        
        if (profileSnap.exists()) {
            const phoneId = profileSnap.val();
            
            // ✅ Use await here to ensure it finishes before the page changes
            await update(ref(window.db, `creator_notifications/${phoneId}/data`), {
                email: user.email,
                web_token: token
              
            });
            
        } else {
            console.error("❌ No phone_id found in  db location");
        }
    } catch (err) {
        console.error("❌ Token sync failed:", err);
    }
};