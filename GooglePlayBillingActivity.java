package name;

import android.app.Activity;
import android.app.AlertDialog;
import android.view.Menu;
import android.view.MenuItem;
import android.content.DialogInterface;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ArrayAdapter;
import android.widget.BaseAdapter;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ListView;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.widget.Toolbar;
import androidx.cardview.widget.CardView;
import androidx.coordinatorlayout.widget.CoordinatorLayout;

import com.android.billingclient.api.BillingClient;
import com.android.billingclient.api.BillingClientStateListener;
import com.android.billingclient.api.BillingFlowParams;
import com.android.billingclient.api.BillingResult;
import com.android.billingclient.api.ConsumeParams;
import com.android.billingclient.api.ProductDetails;
import com.android.billingclient.api.Purchase;
import com.android.billingclient.api.PurchasesUpdatedListener;
import com.android.billingclient.api.QueryProductDetailsParams;
import com.android.billingclient.api.QueryPurchasesParams;
import com.google.android.gms.tasks.OnCompleteListener;
import com.google.android.gms.tasks.Task;
import com.google.firebase.auth.GetTokenResult;
import com.google.android.material.appbar.AppBarLayout;
import com.google.android.material.floatingactionbutton.FloatingActionButton;
import com.google.firebase.FirebaseApp;
import com.google.firebase.auth.FirebaseAuth;
// 🔥 NEW: Firebase Analytics Import
import com.google.firebase.analytics.FirebaseAnalytics;
import com.google.gson.Gson;
import com.google.gson.reflect.TypeToken;

import org.json.JSONObject;

import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;
import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;

public class QuizdetailActivity extends AppCompatActivity {

	private Toolbar _toolbar;
	private String setId = "";

	// 🔥 NEW: Analytics Instance
	private FirebaseAnalytics mFirebaseAnalytics;

	private String creatorUid = "";

	// Prices from MySQL (Default to -1 so failures don't auto-unlock)
	private int price1M = -1;
	private int price3M = -1;
	private int price6M = -1;
	private boolean isPurchased = false; // Flag if user already owns it
	private String quizDescription = "";

	// Selected Google Play Product ID
	private String selectedProductId = "";

	private ArrayList<HashMap<String, Object>> score = new ArrayList<>();
	private ArrayList<String> ss = new ArrayList<>();

	private TextView textview2, button1, textview5;
	private ListView listview1, listview2;
	private LinearLayout linearCreatorProfile, ll_pricing_section;
	private TextView tvCreatorBadgeName;
	private RadioGroup rg_plans;
	private RadioButton rb_1m, rb_3m, rb_6m;

	private FloatingActionButton fab_share;

	private Intent i = new Intent();
	private FirebaseAuth auth;
	private SharedPreferences sp, scores;
	private AlertDialog.Builder d;

	// Google Play Billing
	private BillingClient billingClient;

	@Override
	protected void onCreate(Bundle _savedInstanceState) {
		super.onCreate(_savedInstanceState);
		setContentView(R.layout.quizdetail);
		initialize(_savedInstanceState);
		FirebaseApp.initializeApp(this);

		// 🔥 NEW: Initialize Firebase Analytics
		mFirebaseAnalytics = FirebaseAnalytics.getInstance(this);

		sp.edit().putString("current_set_id", setId).apply();
		setupBillingClient();
		initializeLogic();
	}

	private void initialize(Bundle _savedInstanceState) {
		_toolbar = findViewById(R.id._toolbar);
		setSupportActionBar(_toolbar);
		getSupportActionBar().setDisplayHomeAsUpEnabled(true);
		_toolbar.setNavigationOnClickListener(v -> onBackPressed());

		textview2 = findViewById(R.id.textview2);
		button1 = findViewById(R.id.button1);
		listview1 = findViewById(R.id.listview1);
		listview2 = findViewById(R.id.listview2);

		linearCreatorProfile = findViewById(R.id.linear_creator_profile);
		tvCreatorBadgeName = findViewById(R.id.tv_creator_badge_name);

		fab_share = findViewById(R.id.fab_share);

		// Pricing UI
		ll_pricing_section = findViewById(R.id.ll_pricing_section);
		rg_plans = findViewById(R.id.rg_plans);
		rb_1m = findViewById(R.id.rb_1m);
		rb_3m = findViewById(R.id.rb_3m);
		rb_6m = findViewById(R.id.rb_6m);

		auth = FirebaseAuth.getInstance();
		sp = getSharedPreferences("sp", Activity.MODE_PRIVATE);
		scores = getSharedPreferences("scores", Activity.MODE_PRIVATE);
		d = new AlertDialog.Builder(this);

		// Share Logic (Viral Loop)
		fab_share.setOnClickListener(v -> {
			// 🔥 ANALYTICS: Track Quiz Share
			Bundle shareBundle = new Bundle();
			shareBundle.putString(FirebaseAnalytics.Param.CONTENT_TYPE, "quiz");
			shareBundle.putString(FirebaseAnalytics.Param.ITEM_ID, setId);
			mFirebaseAnalytics.logEvent(FirebaseAnalytics.Event.SHARE, shareBundle);

			String quizName = textview2.getText().toString();
			String deepLink = "https://yourdomain.com/quiz?id=" + setId;

			String shareMessage = "Hey! I challenge you to score higher than me on the '" + quizName + "' quiz.\n\n"
					+ "Download the app to take the test and check out other top creators!\n"
					+ "Link: " + deepLink;

			Intent shareIntent = new Intent(Intent.ACTION_SEND);
			shareIntent.setType("text/plain");
			shareIntent.putExtra(Intent.EXTRA_SUBJECT, "Challenge: " + quizName);
			shareIntent.putExtra(Intent.EXTRA_TEXT, shareMessage);
			startActivity(Intent.createChooser(shareIntent, "Share Quiz via"));
		});

		// Update button text when different plan is selected
		rg_plans.setOnCheckedChangeListener(new RadioGroup.OnCheckedChangeListener() {
			@Override
			public void onCheckedChanged(RadioGroup group, int checkedId) {
				if (checkedId == R.id.rb_1m) {
					selectedProductId = getTierForPrice(price1M);
					button1.setText("Pay ₹" + price1M + " (1 Month)");
				} else if (checkedId == R.id.rb_3m) {
					selectedProductId = getTierForPrice(price3M);
					button1.setText("Pay ₹" + price3M + " (3 Months)");
				} else if (checkedId == R.id.rb_6m) {
					selectedProductId = getTierForPrice(price6M);
					button1.setText("Pay ₹" + price6M + " (6 Months)");
				}
			}
		});

		button1.setOnClickListener(v -> {
			if (setId.isEmpty()) return;

			// SAFEGUARD: Prevent playing if price failed to load
			if (price1M == -1 && !isPurchased) {
				Toast.makeText(getApplicationContext(), "Still loading quiz details, please check your internet.", Toast.LENGTH_SHORT).show();
				return;
			}

			// 1. If Free or Already Purchased -> Play Quiz
			if (isPurchased || price1M == 0) {
				// 🔥 ANALYTICS: Track Quiz Started
				Bundle startBundle = new Bundle();
				startBundle.putString("set_id", setId);
				startBundle.putString("quiz_title", textview2.getText().toString());
				mFirebaseAnalytics.logEvent("quiz_started", startBundle);

				i.setClass(getApplicationContext(), QuizplayActivity.class);
				i.putExtra("set_id", setId);
				i.putExtra("title", textview2.getText().toString());
				i.putExtra("des",quizDescription);
				startActivity(i);
				finish();
			}
			// 2. If Paid -> Trigger Google Play Billing
			else {
				if (selectedProductId.isEmpty()) {
					Toast.makeText(getApplicationContext(), "Please select a plan.", Toast.LENGTH_SHORT).show();
					return;
				}

				// 🔥 ANALYTICS: Track Checkout Initiated
				Bundle bundle = new Bundle();
				bundle.putString("tier", selectedProductId);
				bundle.putString("creator_uid", creatorUid);
				mFirebaseAnalytics.logEvent("checkout_initiated", bundle);

				button1.setText("Connecting to Google Play...");
				button1.setEnabled(false);
				launchGooglePlayBilling(selectedProductId);
			}
		});

		if (linearCreatorProfile != null) {
			linearCreatorProfile.setOnClickListener(v -> {
				if (!creatorUid.isEmpty()) {
					Intent profileIntent = new Intent(QuizdetailActivity.this, PublicProfileActivity.class);
					profileIntent.putExtra("creator_uid", creatorUid);
					startActivity(profileIntent);
				}
			});
		}
	}

	private void initializeLogic() {
		setTitle("Quiz Details");
		button1.setBackground(new GradientDrawable() { public GradientDrawable getIns(int a, int b) { this.setCornerRadius(a); this.setColor(b); return this; } }.getIns((int)15, 0xFF2196F3));

		if (getIntent().hasExtra("set_id")) {
			setId = getIntent().getStringExtra("set_id");
		} else if (sp.contains("current_set_id")) {
			setId = sp.getString("current_set_id", "");
		}

		if (!setId.isEmpty()) {
			fetchQuizDetails();
			verifyUserAccessWithServer();
			_load_history();
		} else {
			Toast.makeText(getApplicationContext(), "Error: Missing Quiz ID", Toast.LENGTH_LONG).show();
			finish();
		}
	}

	// --- 1. SETUP GOOGLE PLAY BILLING ---
	private PurchasesUpdatedListener purchasesUpdatedListener = new PurchasesUpdatedListener() {
		@Override
		public void onPurchasesUpdated(BillingResult billingResult, List<Purchase> purchases) {
			button1.setEnabled(true);
			if (billingResult.getResponseCode() == BillingClient.BillingResponseCode.OK && purchases != null) {
				for (Purchase purchase : purchases) {
					handlePurchaseSuccess(purchase);
				}
			} else if (billingResult.getResponseCode() == BillingClient.BillingResponseCode.USER_CANCELED) {
				Toast.makeText(QuizdetailActivity.this, "Purchase canceled.", Toast.LENGTH_SHORT).show();
				button1.setText("Unlock Quiz"); // Reset text
			} else {
				Toast.makeText(QuizdetailActivity.this, "Error: " + billingResult.getDebugMessage(), Toast.LENGTH_SHORT).show();
				button1.setText("Unlock Quiz"); // Reset text
			}
		}
	};

	private void setupBillingClient() {
		billingClient = BillingClient.newBuilder(this)
				.setListener(purchasesUpdatedListener)
				.enablePendingPurchases()
				.build();

		billingClient.startConnection(new BillingClientStateListener() {
			@Override
			public void onBillingSetupFinished(BillingResult billingResult) {
				if (billingResult.getResponseCode() == BillingClient.BillingResponseCode.OK) {
					// 🔥 THE FIX: RECOVER GHOST PURCHASES PROPERLY
					billingClient.queryPurchasesAsync(
							QueryPurchasesParams.newBuilder()
									.setProductType(BillingClient.ProductType.INAPP)
									.build(),
							(result, purchases) -> {
								if (result.getResponseCode() == BillingClient.BillingResponseCode.OK && purchases != null) {
									for (Purchase purchase : purchases) {
										if (purchase.getPurchaseState() == Purchase.PurchaseState.PURCHASED) {
											// Ensure we only recover ghost purchases for THIS specific quiz
											String linkedSetId = purchase.getAccountIdentifiers() != null ? purchase.getAccountIdentifiers().getObfuscatedAccountId() : null;
											if (linkedSetId == null || linkedSetId.equals(setId)) {
												Log.d("BILLING_LOG", "Ghost Purchase Found! Resuming server verification...");
												runOnUiThread(() -> {
													button1.setText("Verifying Purchase...");
													button1.setEnabled(false);
												});
												verifyQuizPurchaseWithServer(purchase.getPurchaseToken(), purchase);
											}
										}
									}
								}
							}
					);
				}
			}
			@Override
			public void onBillingServiceDisconnected() {
				// Automatically retries connection if needed later
			}
		});
	}

	// --- 2. LAUNCH GOOGLE PLAY PAYMENT SHEET ---
	private void launchGooglePlayBilling(String productId) {
		if (!billingClient.isReady()) {
			Toast.makeText(this, "Google Play is not ready. Try again.", Toast.LENGTH_SHORT).show();
			button1.setText("Unlock Quiz");
			button1.setEnabled(true);
			return;
		}

		List<QueryProductDetailsParams.Product> productList = new ArrayList<>();
		productList.add(QueryProductDetailsParams.Product.newBuilder()
				.setProductId(productId)
				.setProductType(BillingClient.ProductType.INAPP) // Treat as consumable pass
				.build());

		QueryProductDetailsParams params = QueryProductDetailsParams.newBuilder()
				.setProductList(productList)
				.build();

		billingClient.queryProductDetailsAsync(params, (billingResult, productDetailsList) -> {
			if (billingResult.getResponseCode() == BillingClient.BillingResponseCode.OK && productDetailsList != null && !productDetailsList.isEmpty()) {
				ProductDetails productDetails = productDetailsList.get(0);

				List<BillingFlowParams.ProductDetailsParams> flowParamsList = new ArrayList<>();
				flowParamsList.add(BillingFlowParams.ProductDetailsParams.newBuilder()
						.setProductDetails(productDetails)
						.build());

				BillingFlowParams billingFlowParams = BillingFlowParams.newBuilder()
						.setProductDetailsParamsList(flowParamsList)
						.setObfuscatedAccountId(setId) // 🔥 STRICT LINK: Ties Google receipt to this Quiz ID
						.build();

				billingClient.launchBillingFlow(QuizdetailActivity.this, billingFlowParams);
			} else {
				runOnUiThread(() -> {
					Toast.makeText(QuizdetailActivity.this, "Error: Product Tier not found in Play Console (" + productId + ")", Toast.LENGTH_LONG).show();
					button1.setText("Unlock Quiz");
					button1.setEnabled(true);
				});
			}
		});
	}

	// --- 3. HANDLE SUCCESS (DO NOT CONSUME YET) ---
	private void handlePurchaseSuccess(Purchase purchase) {
		// FIRST, send to your PHP server to verify with Google Cloud
		verifyQuizPurchaseWithServer(purchase.getPurchaseToken(), purchase);
	}

	// --- 4. VERIFY WITH SERVER & CONSUME ---
	private void verifyQuizPurchaseWithServer(String purchaseToken, Purchase purchase) {
		if (auth.getCurrentUser() == null) return;

		// Extract the actual product ID that was bought (Safe fallback for ghost purchases)
		String actualProductId = selectedProductId;
		if (purchase.getProducts() != null && !purchase.getProducts().isEmpty()) {
			actualProductId = purchase.getProducts().get(0);
		}
		final String finalProductId = actualProductId;

		// 🔥 CRASH-PROOF FIREBASE TOKEN RETRIEVAL
		auth.getCurrentUser().getIdToken(true).addOnCompleteListener(new OnCompleteListener<GetTokenResult>() {
			@Override
			public void onComplete(@NonNull Task<GetTokenResult> task) {
				if (task.isSuccessful()) {
					String idToken = task.getResult().getToken();

					new Thread(() -> {
						String rawResponse = "No response";
						try {
							URL url = new URL("https://yourdomain.com/api/v1/verify_quiz_purchase.php");
							HttpURLConnection conn = (HttpURLConnection) url.openConnection();
							conn.setRequestMethod("POST");
							conn.setRequestProperty("Content-Type", "application/json");
							conn.setDoOutput(true);

							JSONObject jsonParam = new JSONObject();
							jsonParam.put("id_token", idToken);
							jsonParam.put("set_id", setId);
							jsonParam.put("purchase_token", purchaseToken);
							jsonParam.put("product_id", finalProductId);

							conn.getOutputStream().write(jsonParam.toString().getBytes("UTF-8"));

							int responseCode = conn.getResponseCode();
							java.io.InputStream inputStream;
							if (responseCode >= 200 && responseCode <= 299) {
								inputStream = conn.getInputStream();
							} else {
								inputStream = conn.getErrorStream();
							}

							BufferedReader br = new BufferedReader(new InputStreamReader(inputStream));
							StringBuilder response = new StringBuilder();
							String line;
							while ((line = br.readLine()) != null) response.append(line);
							br.close();

							rawResponse = response.toString();
							Log.d("BILLING_LOG", "Server Response: " + rawResponse);

							JSONObject resObj = new JSONObject(rawResponse);

							runOnUiThread(() -> {
								if (resObj.optBoolean("success")) {
									Bundle successBundle = new Bundle();
									successBundle.putString("tier", finalProductId);
									successBundle.putString("purchase_token", purchaseToken);
									mFirebaseAnalytics.logEvent("purchase_success", successBundle);

									ConsumeParams consumeParams = ConsumeParams.newBuilder()
											.setPurchaseToken(purchaseToken)
											.build();

									billingClient.consumeAsync(consumeParams, (billingResult, token) -> {
										if (billingResult.getResponseCode() == BillingClient.BillingResponseCode.OK) {
											Toast.makeText(QuizdetailActivity.this, "Quiz Unlocked! 🎉", Toast.LENGTH_SHORT).show();
											isPurchased = true;

											Bundle startBundle = new Bundle();
											startBundle.putString("set_id", setId);
											startBundle.putString("quiz_title", textview2.getText().toString());
											mFirebaseAnalytics.logEvent("quiz_started", startBundle);

											i.setClass(getApplicationContext(), QuizplayActivity.class);
											i.putExtra("set_id", setId);
											i.putExtra("title", textview2.getText().toString());
											i.putExtra("des", quizDescription);
											startActivity(i);
											finish();
										} else {
											Toast.makeText(QuizdetailActivity.this, "Local error, but purchase saved. Refreshing...", Toast.LENGTH_LONG).show();
											fetchQuizDetails();
										}
									});
								} else {
									// --- THE CORRECT ELSE BLOCK FOR SERVER REJECTIONS ---
									Toast.makeText(QuizdetailActivity.this, "Verification Error: " + resObj.optString("error"), Toast.LENGTH_LONG).show();
									button1.setText("Unlock Quiz");
									button1.setEnabled(true);

									// 🔥 THE SAFETY VALVE (Properly placed here where resObj exists!)
									if (resObj.optString("error").contains("rejected") || resObj.optString("error").contains("already claimed")) {
										ConsumeParams trashParams = ConsumeParams.newBuilder()
												.setPurchaseToken(purchaseToken)
												.build();
										billingClient.consumeAsync(trashParams, (bResult, t) -> {
											Log.d("BILLING_LOG", "Dead token successfully cleared from device cache.");
										});
									}
								}
							});

						} catch (Exception e) {
							final String finalRawResponse = rawResponse;
							Log.e("BILLING_LOG", "Exception caught: " + e.getMessage());
							runOnUiThread(() -> {
								Toast.makeText(QuizdetailActivity.this, "Server Crash/Network: " + finalRawResponse, Toast.LENGTH_LONG).show();
								button1.setText("Unlock Quiz");
								button1.setEnabled(true);
							});
						}
					}).start();
				} else {
					// --- FIREBASE AUTH ERROR BLOCK ---
					runOnUiThread(() -> {
						Toast.makeText(QuizdetailActivity.this, "Auth Error: Could not verify user connection.", Toast.LENGTH_SHORT).show();
						button1.setText("Unlock Quiz");
						button1.setEnabled(true);
					});
				}
			}
		});
	}
	// --- 4. MAP DYNAMIC PRICES TO FIXED TIERS ---
	private String getTierForPrice(int price) {
		return "tier_" + price;
	}


	// --- FETCH DATA FROM HOSTINGER ---
	private void fetchQuizDetails() {
		new Thread(new Runnable() {
			@Override
			public void run() {
				try {
					URL url = new URL("https://yourdomain.com/api/v1/quiz_details.php?set_id=" + setId);
					HttpURLConnection conn = (HttpURLConnection) url.openConnection();
					conn.setRequestMethod("GET");
					BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
					StringBuilder response = new StringBuilder();
					String line;
					while ((line = in.readLine()) != null) response.append(line);
					in.close();

					final String jsonResponse = response.toString();

					runOnUiThread(new Runnable() {
						@Override
						public void run() {
							try {
								JSONObject obj = new JSONObject(jsonResponse);
								if (obj.getBoolean("success")) {
									JSONObject data = obj.getJSONObject("data");

									String title = data.optString("title", "Untitled Quiz");
									String desc = data.optString("description", "No description provided.");
									quizDescription = data.optString("description", "No description provided.");

									creatorUid = data.optString("creator_id", "");

									// Get Prices (If it succeeds, it updates from -1 to the real price)
									price1M = data.optInt("price_1m", data.optInt("price", 0));
									price3M = data.optInt("price_3m", 0);
									price6M = data.optInt("price_6m", 0);

									textview2.setText(title);

									ss.clear(); ss.add(desc);
									listview2.setAdapter(new ArrayAdapter<String>(getBaseContext(), android.R.layout.simple_list_item_1, ss));

									if (price1M == 0 || isPurchased) {
										ll_pricing_section.setVisibility(View.GONE);
										button1.setText("Practice Now");
									} else {
										ll_pricing_section.setVisibility(View.VISIBLE);
										button1.setText("Unlock Quiz");

										rb_1m.setText("1 Month Access - ₹" + price1M);
										rb_3m.setText("3 Months Access - ₹" + price3M + " (Save!)");
										rb_6m.setText("6 Months Access - ₹" + price6M + " (Best Value!)");

										rb_1m.setChecked(true);
									}

									if (!creatorUid.isEmpty()) {
										linearCreatorProfile.setVisibility(View.VISIBLE);
										android.graphics.drawable.GradientDrawable gd = new android.graphics.drawable.GradientDrawable();
										gd.setColor(android.graphics.Color.parseColor("#E3F2FD"));
										gd.setCornerRadius(20f);
										linearCreatorProfile.setBackground(gd);
									}
								} else {
									Toast.makeText(QuizdetailActivity.this, "Failed to load details from server.", Toast.LENGTH_SHORT).show();
								}
							} catch (Exception e) { e.printStackTrace(); }
						}
					});
				} catch (Exception e) {
					e.printStackTrace();
					runOnUiThread(() -> Toast.makeText(QuizdetailActivity.this, "Network Error loading details.", Toast.LENGTH_SHORT).show());
				}
			}
		}).start();
	}
	// --- CHECK IF USER ALREADY BOUGHT THIS (SINGLE OR PASS) ---
	private void verifyUserAccessWithServer() {
		if (auth.getCurrentUser() == null) return;
		String currentUid = auth.getCurrentUser().getUid();

		new Thread(() -> {
			try {
				// Calling the EXACT same file your web platform uses!
				URL url = new URL("https://yourdomain.com/api/v1/check_subscription.php?uid=" + currentUid + "&set_id=" + setId);
				HttpURLConnection conn = (HttpURLConnection) url.openConnection();
				conn.setRequestMethod("GET");

				BufferedReader in = new BufferedReader(new InputStreamReader(conn.getInputStream()));
				StringBuilder response = new StringBuilder();
				String line;
				while ((line = in.readLine()) != null) response.append(line);
				in.close();

				JSONObject resObj = new JSONObject(response.toString());

				runOnUiThread(() -> {
					// If the server says they have access (via Quiz Purchase OR Creator Pass)
					if (resObj.optBoolean("success") && resObj.optBoolean("hasAccess")) {
						isPurchased = true; // Tell the app they own it

						// Magically hide the prices and change the button to Practice!
						if (ll_pricing_section != null) ll_pricing_section.setVisibility(View.GONE);
						if (button1 != null) {
							button1.setText("Practice Now");
							button1.setEnabled(true);
						}
					}
				});
			} catch (Exception e) {
				e.printStackTrace();
			}
		}).start();
	}
	public void _load_history() {
		if (auth.getCurrentUser() == null) return;
		String historyKey = auth.getCurrentUser().getUid() + "/" + setId;
		if (scores.contains(historyKey)) {
			score = new Gson().fromJson(scores.getString(historyKey, ""), new TypeToken<ArrayList<HashMap<String, Object>>>(){}.getType());
			listview1.setAdapter(new Listview1Adapter(score));
			listview1.setVisibility(View.VISIBLE);
		} else {
			listview1.setVisibility(View.GONE);
		}
	}

	@Override
	public void onBackPressed() { finish(); }

	// --- 1. Creates a Settings Icon in the Top Right Toolbar ---
	@Override
	public boolean onCreateOptionsMenu(Menu menu) {
		menu.add(0, 1, 0, "Quiz Mode").setIcon(android.R.drawable.ic_menu_preferences).setShowAsAction(MenuItem.SHOW_AS_ACTION_ALWAYS);
		return true;
	}

	// --- 2. Listens for the Click ---
	@Override
	public boolean onOptionsItemSelected(MenuItem item) {
		if (item.getItemId() == 1) {
			showModeDialog();
			return true;
		}
		return super.onOptionsItemSelected(item);
	}

	// --- 3. The Rebuilt Mode Dialog ---
	private void showModeDialog() {
		AlertDialog.Builder fabshow = new AlertDialog.Builder(this);
		fabshow.setTitle("Select Quiz Mode");
		fabshow.setMessage("Switch to mode: \n\nPractice : Show explanation, no time boundation.\n\nExam: No explanation while taking test, Limited Time to complete.");

		fabshow.setPositiveButton("Practice", (dialog, which) -> {
			sp.edit().putString("exammode", "practice").apply();
			Toast.makeText(getApplicationContext(), "Practice Mode Activated! ✅", Toast.LENGTH_SHORT).show();
		});

		fabshow.setNegativeButton("Exam", (dialog, which) -> {
			sp.edit().putString("exammode", "exam").apply();
			Toast.makeText(getApplicationContext(), "Exam Mode Activated! ⏱️", Toast.LENGTH_SHORT).show();
		});

		fabshow.create().show();
	}

	public class Listview1Adapter extends BaseAdapter {
		ArrayList<HashMap<String, Object>> _data;
		public Listview1Adapter(ArrayList<HashMap<String, Object>> _arr) { _data = _arr; }
		@Override public int getCount() { return _data.size(); }
		@Override public HashMap<String, Object> getItem(int _index) { return _data.get(_index); }
		@Override public long getItemId(int _index) { return _index; }

		@Override
		public View getView(final int _position, View _v, ViewGroup _container) {
			LayoutInflater _inflater = getLayoutInflater();
			View _view = _v;
			if (_view == null) { _view = _inflater.inflate(R.layout.lead, null); }

			final LinearLayout linear1 = _view.findViewById(R.id.linear1);
			final TextView textview4 = _view.findViewById(R.id.textview4);
			final TextView textview1 = _view.findViewById(R.id.textview1);
			final TextView textview2 = _view.findViewById(R.id.textview2);
			final TextView textview3 = _view.findViewById(R.id.textview3);

			textview2.setVisibility(View.INVISIBLE);
			textview4.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/bold.ttf"), 0);
			textview1.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/bold.ttf"), 0);
			textview3.setTypeface(Typeface.createFromAsset(getAssets(),"fonts/bold.ttf"), 0);

			if (_data.get((int)_position).containsKey("date")) { textview1.setText(_data.get((int)_position).get("date").toString()); }
			if (_data.get((int)_position).containsKey("point")) { textview3.setText(_data.get((int)_position).get("point").toString()); }
			textview4.setText(String.valueOf((long)(_position + 1)));

			linear1.setOnClickListener(v -> {
				d.setTitle("Delete Score");
				d.setMessage("Proceed to delete this score?");
				d.setPositiveButton("Yes", (dialog, which) -> {
					if (_position < score.size()) {
						score.remove(_position);
						notifyDataSetChanged();
						scores.edit().putString(auth.getCurrentUser().getUid() + "/" + setId, new Gson().toJson(score)).apply();
						Toast.makeText(getApplicationContext(), "Data deleted", Toast.LENGTH_SHORT).show();
					}
				});
				d.setNegativeButton("Go back", null);
				d.create().show();
			});
			return _view;
		}
	}
}
