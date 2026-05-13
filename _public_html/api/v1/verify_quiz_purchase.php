<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'db_connect.php';

$data = json_decode(file_get_contents('php://input'), true);

$id_token       = $data['id_token'] ?? '';
$set_id         = $data['set_id'] ?? '';
$purchase_token = $data['purchase_token'] ?? '';
$product_id     = $data['product_id'] ?? ''; // e.g., "tier_99"

if (!$id_token || !$purchase_token || !$set_id) {
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

// =======================================================================
// 2. GOOGLE PLAY SERVER-TO-SERVER VERIFICATION
// =======================================================================
$packageName = 'com.mcq.prep.app'; 

function getGoogleAccessToken() {
    // Pointing to your secure folder outside public_html.
    // Adjust the '../' based on how deep this PHP file is relative to the config folder.
    $keyFile ='/home/u855588073/domains/mcqbazaar.com/config/mcq_bazaar_service_key.json';
    
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

// Ping Google to verify the purchase token is legitimate and paid
$verifyUrl = "https://androidpublisher.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/products/{$product_id}/tokens/{$purchase_token}";
$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
$googleResponseStr = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    die(json_encode(["success" => false, "error" => "Google Play rejected this transaction. It may be invalid or refunded.", "debug" => $googleResponseStr]));
}

$googleData = json_decode($googleResponseStr, true);

// purchaseState == 0 means PURCHASED. Anything else means cancelled/pending.
if (!isset($googleData['purchaseState']) || $googleData['purchaseState'] !== 0) {
    die(json_encode(["success" => false, "error" => "Transaction is not marked as successfully paid by Google."]));
}
// =======================================================================
// END GOOGLE VERIFICATION. IF WE REACH HERE, THE MONEY IS REAL!
// =======================================================================

try {
    // === START SECURE FINANCIAL TRANSACTION ===
    $conn->beginTransaction();

    // 3. PREVENT REPLAY ATTACKS
    $check_token = $conn->prepare("SELECT id FROM quiz_purchases WHERE play_purchase_token = ?");
    $check_token->execute([$purchase_token]);
    if ($check_token->fetch()) {
        die(json_encode(["success" => false, "error" => "Purchase token already claimed."]));
    }

    // 4. FETCH QUIZ & CREATOR DETAILS
    $stmt = $conn->prepare("SELECT creator_id, price_1m FROM mcq_sets WHERE set_id = ?");
    $stmt->execute([$set_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $creator_uid = $quiz ? $quiz['creator_id'] : 'unknown';
    
    // Extract price from the product_id (e.g., "tier_99" -> 99)
    $gross_price = 0;
    if (preg_match('/tier_(\d+)/', $product_id, $matches)) {
        $gross_price = (int)$matches[1];
    } else {
        $gross_price = (int)($quiz['price_1m'] ?? 0);
    }

    // === 5. FINANCIAL MATH (GST REGISTERED) ===
    $price_before_tax = $gross_price / 1.18;
    $net_payout = $price_before_tax * 0.85;

    $creator_cut = round($net_payout * 0.70, 2);
    $platform_cut = round($net_payout * 0.30, 2);

    $balance_before = 0.00;
    $balance_after = 0.00;

    // === 6. SECURE WALLET UPDATE ===
    if ($creator_uid !== 'unknown') {
        $stmt_wallet = $conn->prepare("SELECT wallet_balance FROM creators WHERE uid = ? FOR UPDATE");
        $stmt_wallet->execute([$creator_uid]);
        $wallet_data = $stmt_wallet->fetch(PDO::FETCH_ASSOC);

        if ($wallet_data) {
            $balance_before = (float)$wallet_data['wallet_balance'];
            $balance_after = $balance_before + $creator_cut;

            $update_wallet = $conn->prepare("UPDATE creators SET wallet_balance = ? WHERE uid = ?");
            $update_wallet->execute([$balance_after, $creator_uid]);
        }
    }

    // === 7. INSERT INTO LEDGER ===
    $insert = $conn->prepare("INSERT INTO quiz_purchases 
        (student_uid, creator_uid, set_id, play_purchase_token, price_paid, creator_cut, platform_cut, balance_before, balance_after, purchased_at, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
    
    $insert->execute([
        $student_uid, 
        $creator_uid, 
        $set_id, 
        $purchase_token, 
        $gross_price, 
        $creator_cut, 
        $platform_cut, 
        $balance_before, 
        $balance_after
    ]);

    // === 8. COMMIT TRANSACTION ===
    $conn->commit();

    echo json_encode(["success" => true, "message" => "Google Play purchase verified, GST accounted, and wallet credited!"]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "error" => "Database Error: " . $e->getMessage()]);
}
?>