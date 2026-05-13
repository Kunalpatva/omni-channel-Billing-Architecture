<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select a Plan | MCQ Bazaar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.8.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.8.1/firebase-auth-compat.js"></script>

    <style>
      #plans-wrapper {
        position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
        z-index: 9999; 
        background-color: #F4F6F9; overflow-y: auto;
      }
      body.admin-bar #plans-wrapper { top: 32px; height: calc(100vh - 32px); }
      body { margin: 0; overflow: hidden !important; }
      .gold-gradient { background: linear-gradient(135deg, #FFD700 0%, #FDB931 100%); }
    </style>
</head>
<body>

<div id="plans-wrapper" class="font-sans text-gray-800">
    <header class="bg-blue-600 text-white shadow-md p-4 sticky top-0 z-10 flex items-center justify-between">
        <div class="flex items-center">
            <button onclick="window.history.back()" class="mr-4 hover:bg-blue-700 p-2 rounded-full transition">
                <i class="fa fa-arrow-left text-xl"></i>
            </button>
            <h1 class="text-xl font-bold">Unlock Access</h1>
        </div>
        <div class="bg-green-500 text-white text-xs font-black px-3 py-1 rounded-full uppercase tracking-wide border border-green-400 shadow-sm animate-pulse">
            Web Exclusive Prices
        </div>
    </header>

    <div class="max-w-5xl mx-auto p-6 mt-4">
        <div id="loading-state" class="text-center py-10">
            <i class="fa fa-spinner fa-spin text-4xl text-blue-500 mb-4"></i>
            <p class="text-gray-500 font-medium">Fetching exclusive web plans...</p>
        </div>

        <div id="error-state" class="hidden text-center py-10">
            <i class="fa fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
            <p id="error-text" class="text-gray-700 font-medium">Failed to load plans.</p>
            <button onclick="window.history.back()" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg">Go Back</button>
        </div>

        <div id="plans-content" class="hidden">
            <h2 class="text-2xl font-extrabold mb-2 text-center" id="quiz-title-display">Quiz Title</h2>
            <p class="text-center text-gray-500 mb-8">You save <strong class="text-green-600">10%</strong> by purchasing directly on our website instead of the app!</p>

            <div id="cards-container" class="grid grid-cols-1 md:grid-cols-3 gap-6"></div>
            <div id="creator-sub-container" class="mt-8"></div>
        </div>
    </div>
</div>

<script>
    // --- UI Setup ---
    document.addEventListener("DOMContentLoaded", function() {
        var wrapper = document.getElementById("plans-wrapper");
        if (wrapper && wrapper.parentNode !== document.body) {
            document.body.appendChild(wrapper);
        }
    });

    var urlParams = new URLSearchParams(window.location.search);
    var setId = urlParams.get("set_id");
    if (!setId) setId = localStorage.getItem("current_set_id");

    // --- Firebase Auth Setup ---
    var firebaseConfig = {
        apiKey: "api_key_id",
        authDomain: "firebase_project_name.firebaseapp.com",
        projectId: "project_id",
    };
    if (firebase.apps.length === 0) {
        firebase.initializeApp(firebaseConfig);
    }

    var currentUid = null;
    var currentEmail = "student@example.com";
    var currentName = "Student";

    firebase.auth().onAuthStateChanged(function(user) {
        if (user) {
            currentUid = user.uid;
            currentEmail = user.email;
            currentName = localStorage.getItem("name") || user.displayName || "Student";
        }
    });

    // --- Fetch Plans ---
    if (setId) {
        fetch("https://yourdomain.com/api/v1/get_plans.php?set_id=" + setId)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                document.getElementById("loading-state").style.display = "none";
                if (data.success) renderPlans(data);
                else showError(data.message);
            })
            .catch(function(err) {
                document.getElementById("loading-state").style.display = "none";
                showError("Network connection failed.");
            });
    } else {
        document.getElementById("loading-state").style.display = "none";
        showError("No quiz selected.");
    }

    function showError(msg) {
        document.getElementById("error-state").style.display = "block";
        document.getElementById("error-text").innerText = msg;
    }

    // --- HELPER: Calculate 10% Visual Discount ---
    function getDiscountedPrice(price) {
        var discounted = Math.round(price * 0.90);
        return discounted < 1 ? 1 : discounted;
    }

    function renderPlans(data) {
        document.getElementById("plans-content").style.display = "block";
        document.getElementById("quiz-title-display").innerText = data.quiz_title;
        var container = document.getElementById("cards-container");
        var html = "";

        if (data.price_1m !== 0) {
            var newPrice1m = getDiscountedPrice(data.price_1m);
            html += "<div class='bg-white rounded-2xl p-6 shadow-sm border border-gray-200 flex flex-col hover:shadow-md transition relative overflow-hidden'>" +
                    "<div class='absolute top-0 right-0 bg-green-100 text-green-700 text-[10px] font-black px-3 py-1 uppercase tracking-wide rounded-bl-lg border-b border-l border-green-200'>10% OFF</div>" +
                    "<h3 class='text-lg font-bold text-gray-700 mt-2'>1 Month Access</h3>" +
                    "<div class='my-4 flex items-baseline gap-2'><span class='text-4xl font-black text-blue-600'>₹" + newPrice1m + "</span><span class='text-lg font-medium text-gray-400 line-through'>₹" + data.price_1m + "</span></div>" +
                    "<ul class='text-sm text-gray-600 space-y-2 mb-6 flex-1'><li><i class='fa fa-check text-green-500 mr-2'></i> 30 Days Validity</li><li><i class='fa fa-check text-green-500 mr-2'></i> Full Explanations</li></ul>" +
                    "<button onclick='processPayment(\"1m\")' class='w-full bg-blue-50 text-blue-600 font-bold py-3 rounded-xl hover:bg-blue-600 hover:text-white transition'>Select Plan</button></div>";
        }
        if (data.price_3m !== 0) {
            var newPrice3m = getDiscountedPrice(data.price_3m);
            html += "<div class='bg-white rounded-2xl p-6 shadow-lg border-2 border-blue-500 flex flex-col relative transform md:scale-105 z-10 overflow-hidden'>" +
                    "<div class='absolute top-0 left-1/2 transform -translate-x-1/2 bg-blue-500 text-white text-[10px] font-black px-4 py-1 rounded-b-lg uppercase tracking-widest'>MOST POPULAR</div>" +
                    "<div class='absolute top-0 right-0 bg-green-100 text-green-700 text-[10px] font-black px-3 py-1 uppercase tracking-wide rounded-bl-lg border-b border-l border-green-200 mt-6'>10% OFF</div>" +
                    "<h3 class='text-lg font-bold text-gray-700 mt-6'>3 Months Access</h3>" +
                    "<div class='my-4 flex items-baseline gap-2'><span class='text-4xl font-black text-blue-600'>₹" + newPrice3m + "</span><span class='text-lg font-medium text-gray-400 line-through'>₹" + data.price_3m + "</span></div>" +
                    "<ul class='text-sm text-gray-600 space-y-2 mb-6 flex-1'><li><i class='fa fa-check text-green-500 mr-2'></i> 90 Days Validity</li><li><i class='fa fa-check text-green-500 mr-2'></i> Best Value per Month</li></ul>" +
                    "<button onclick='processPayment(\"3m\")' class='w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-md'>Select Plan</button></div>";
        }
        if (data.price_6m !== 0) {
            var newPrice6m = getDiscountedPrice(data.price_6m);
            html += "<div class='bg-white rounded-2xl p-6 shadow-sm border border-gray-200 flex flex-col hover:shadow-md transition relative overflow-hidden'>" +
                    "<div class='absolute top-0 right-0 bg-green-100 text-green-700 text-[10px] font-black px-3 py-1 uppercase tracking-wide rounded-bl-lg border-b border-l border-green-200'>10% OFF</div>" +
                    "<h3 class='text-lg font-bold text-gray-700 mt-2'>6 Months Access</h3>" +
                    "<div class='my-4 flex items-baseline gap-2'><span class='text-4xl font-black text-blue-600'>₹" + newPrice6m + "</span><span class='text-lg font-medium text-gray-400 line-through'>₹" + data.price_6m + "</span></div>" +
                    "<ul class='text-sm text-gray-600 space-y-2 mb-6 flex-1'><li><i class='fa fa-check text-green-500 mr-2'></i> 180 Days Validity</li><li><i class='fa fa-check text-green-500 mr-2'></i> Long-Term Prep</li></ul>" +
                    "<button onclick='processPayment(\"6m\")' class='w-full bg-blue-50 text-blue-600 font-bold py-3 rounded-xl hover:bg-blue-600 hover:text-white transition'>Select Plan</button></div>";
        }
        container.innerHTML = html;

        if (data.creator_sub_price !== 0) {
            var newSubPrice = getDiscountedPrice(data.creator_sub_price);
            var subHtml = "<div class='gold-gradient rounded-2xl p-6 shadow-xl text-white flex flex-col md:flex-row items-center md:justify-between text-center md:text-left relative overflow-hidden'>" +
                    "<div class='absolute top-0 right-0 bg-red-600 text-white text-[10px] font-black px-4 py-1 uppercase tracking-widest rounded-bl-lg shadow-md'>Web Exclusive - 10% Off</div>" +
                    "<div class='mb-6 md:mb-0 mt-4 md:mt-0'><h3 class='text-2xl font-black mb-1'><i class='fa fa-crown mr-2'></i> Creator Pass</h3><p class='opacity-90 font-medium'>Unlock EVERY quiz published by <strong>" + data.creator_name + "</strong> for 30 days!</p></div>" +
                    "<div class='flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto justify-center md:justify-end'>" +
                    "<div class='flex items-baseline gap-2'><span class='text-4xl font-black text-white'>₹" + newSubPrice + "</span><span class='text-xl font-medium opacity-70 line-through'>₹" + data.creator_sub_price + "</span><span class='text-sm font-normal opacity-80'>/mo</span></div>" +
                    "<button onclick='processPayment(\"creator_sub\")' class='w-full sm:w-auto bg-white text-yellow-600 font-black px-8 py-3 rounded-xl shadow-md hover:bg-gray-50 transition transform hover:scale-105'>Get Pass</button></div></div>";
            document.getElementById("creator-sub-container").innerHTML = subHtml;
        }
    }

    // --- RAZORPAY INTEGRATION ---
    function processPayment(tier) {
        if (!currentUid) {
            alert("Please log in to purchase a plan.");
            return;
        }

        // 1. Ask your PHP backend to create a Razorpay Order ID securely
        fetch("https://yourdomain.com/api/v1/create_razorpay_order.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ set_id: setId, tier: tier, uid: currentUid })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                console.log("Successfully created Razorpay Order ID:", data.order_id);
                
                // 2. Open the Razorpay Checkout popup
                var options = {
                    "key": data.key_id, 
                    "amount": data.amount, // Amount in paise (This is the backend-discounted amount)
                    "currency": "INR",
                    "name": "App name",
                    "description": "Unlock Premium Access",
                    "order_id": data.order_id, 
                    "prefill": {
                        "name": currentName,
                        "email": currentEmail
                    },
                    "theme": { "color": "#2563EB" },
                "handler": function (response) {
                        // Payment complete at Razorpay, now verify it on our server
                        document.getElementById("plans-content").style.display = "none";
                        document.getElementById("loading-state").style.display = "block";
                        document.querySelector("#loading-state p").innerText = "Verifying secure payment...";

                        fetch("https://yourdomain.com/api/v1/verify_razorpay_payment.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify({
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_signature: response.razorpay_signature,
                                set_id: setId,
                                tier: tier,
                                student_uid: currentUid,
                                price_paid: data.amount / 100 // ACCURATE LEDGER: Converts discounted paise back to exact rupees for DB
                            })
                        })
                        .then(res => res.json())
                        .then(verifyData => {
                            if (verifyData.success) {
                                alert("Success! Your plan is activated.");
                                // Redirect the user back to the quiz!
                                window.location.href = "https://yourdomain.com/quiz-details/?id=" + setId;
                            } else {
                                alert("Verification Failed: " + verifyData.message);
                                window.location.reload();
                            }
                        })
                        .catch(err => {
                            alert("Error verifying payment. If money was deducted, please contact support.");
                        });
                    }
                };
                
                var rzp1 = new Razorpay(options);
                rzp1.on('payment.failed', function (response){
                    alert("Payment Failed: " + response.error.description);
                });
                rzp1.open();

            } else {
                alert("Payment initiation failed: " + data.message);
                console.error("Backend Error:", data.message);
            }
        })
        .catch(function(err) {
            console.error(err);
            alert("Network error while connecting to payment gateway.");
        });
    }
</script>
</body>
</html>
