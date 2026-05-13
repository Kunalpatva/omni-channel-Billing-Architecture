<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'db_connect.php';

// Decode the incoming JSON from the Android App
$data = json_decode(file_get_contents('php://input'), true);

$id_token       = $data['id_token'] ?? '';
$creator_uid    = $data['creator_uid'] ?? '';
$purchase_token = $data['purchase_token'] ?? '';

if (empty($id_token) || empty($purchase_token) || empty($creator_uid)) {
    die(json_encode(["success" => false, "error" => "Missing required data."]));
}

// 1. LIGHTWEIGHT FIREBASE TOKEN DECODER
$token_parts = explode('.', $id_token);
if (count($token_parts) !== 3) {
    die(json_encode(["success" => false, "error" => "Invalid Firebase token structure."]));
}

$payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);
$student_uid = $payload['user_id'] ?? $payload['sub'] ?? '';

if (empty($student_uid)) {
    die(json_encode(["success" => false, "error" => "Could not authenticate user."]));
}

// 2. FETCH CREATOR SUBSCRIPTION PRICE (Needed to construct Google Product ID)
$stmt = $conn->prepare("SELECT subscription_price FROM creators WHERE uid = ?");
$stmt->execute([$creator_uid]);
$creator = $stmt->fetch(PDO::FETCH_ASSOC);

$gross_price = $creator ? (int)$creator['subscription_price'] : 0;

if ($gross_price <= 0) {
    die(json_encode(["success" => false, "error" => "Invalid creator subscription price."]));
}

// Construct the Product ID expected by Google Play (e.g., "creator_sub_99")
$product_id = "creator_sub_" . $gross_price;

// =======================================================================
// 3. GOOGLE PLAY SERVER-TO-SERVER VERIFICATION
// =======================================================================
$packageName = 'com.mcq.prep.app'; 

function getGoogleAccessToken() {
    $keyFile = '/home/u855588073/domains/mcqbazaar.com/config/mcq_bazaar_service_key.json';
    if (!file_exists($keyFile)) return false;

    $keyData = json_decode(file_get_contents($keyFile), true);
    
    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claim = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/androidpublisher',
        'aud' => $keyData['token_uri'],
        'exp' => $now + 3600,
        'iat' => $now
    ]);

    $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claim));

    $signature = '';
    openssl_sign($base64UrlHeader . "." . $base64UrlClaim, $signature, $keyData['private_key'], 'SHA256');
    $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

    $jwt = $base64UrlHeader . "." . $base64UrlClaim . "." . $base64UrlSignature;

    $ch = curl_init($keyData['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));

    $response = curl_exec($ch);
    curl_close($ch);

    $json = json_decode($response, true);
    return $json['access_token'] ?? false;
}

$accessToken = getGoogleAccessToken();
if (!$accessToken) {
    die(json_encode(["success" => false, "error" => "Critical: Google API Auth Failed. Check your JSON path."]));
}

// Ping Google to verify the purchase token
$verifyUrl = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/products/{$product_id}/tokens/{$purchase_token}";
$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
$googleResponseStr = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die(json_encode(["success" => false, "error" => "Google Play rejected this transaction.", "debug" => $googleResponseStr]));
}

$googleData = json_decode($googleResponseStr, true);

// purchaseState == 0 means PURCHASED.
if (!isset($googleData['purchaseState']) || $googleData['purchaseState'] !== 0) {
    die(json_encode(["success" => false, "error" => "Transaction is not marked as successfully paid by Google."]));
}
// =======================================================================
// END GOOGLE VERIFICATION. PROCEED TO DATABASE!
// =======================================================================

try {
    // === START SECURE FINANCIAL TRANSACTION ===
    $conn->beginTransaction();

    // 4. PREVENT REPLAY ATTACKS
    $check_token = $conn->prepare("SELECT id FROM creator_subscriptions WHERE play_purchase_token = ?");
    $check_token->execute([$purchase_token]);
    if ($check_token->fetch()) {
        die(json_encode(["success" => false, "error" => "Purchase token already claimed."]));
    }

    // === 5. FINANCIAL MATH (GST REGISTERED) ===
    $price_before_tax = $gross_price / 1.18;
    $net_payout = $price_before_tax * 0.85;

    $creator_cut = round($net_payout * 0.70, 2);
    $platform_cut = round($net_payout * 0.30, 2);

    $balance_before = 0.00;
    $balance_after = 0.00;

    // === 6. SECURE WALLET UPDATE ===
    $stmt_wallet = $conn->prepare("SELECT wallet_balance FROM creators WHERE uid = ? FOR UPDATE");
    $stmt_wallet->execute([$creator_uid]);
    $wallet_data = $stmt_wallet->fetch(PDO::FETCH_ASSOC);

    if ($wallet_data) {
        $balance_before = (float)$wallet_data['wallet_balance'];
        $balance_after = $balance_before + $creator_cut;

        $update_wallet = $conn->prepare("UPDATE creators SET wallet_balance = ? WHERE uid = ?");
        $update_wallet->execute([$balance_after, $creator_uid]);
    } else {
        throw new Exception("Creator wallet not found.");
    }

    // === 7. INSERT INTO LEDGER ===
    $insert = $conn->prepare("INSERT INTO creator_subscriptions 
        (student_uid, creator_uid, play_purchase_token, price_paid, creator_cut, platform_cut, balance_before, balance_after, purchased_at, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
    
    $insert->execute([
        $student_uid, 
        $creator_uid, 
        $purchase_token, 
        $gross_price,
        $creator_cut,
        $platform_cut,
        $balance_before,
        $balance_after
    ]);

    // === 8. COMMIT TRANSACTION ===
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Google Play Creator Pass verified, GST accounted, and wallet credited!"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "error" => "Transaction Error: " . $e->getMessage()]);
}
?>