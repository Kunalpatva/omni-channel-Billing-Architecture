<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'db_connect.php';

// Secure config
$config_path = __DIR__ . '/../../../config/razorpay_config.php';
if (!file_exists($config_path)) {
    die(json_encode(["success" => false, "message" => "Security Error: Config missing."]));
}
require_once $config_path;

$data = json_decode(file_get_contents('php://input'), true);

$rzp_payment_id = $data['razorpay_payment_id'] ?? '';
$rzp_order_id   = $data['razorpay_order_id'] ?? '';
$rzp_signature  = $data['razorpay_signature'] ?? '';
$set_id         = $data['set_id'] ?? '';
$tier           = $data['tier'] ?? '';
$student_uid    = $data['student_uid'] ?? '';
$target_creator = $data['creator_uid'] ?? ''; 
$price_paid     = (float)($data['price_paid'] ?? 0); 

if (!$rzp_payment_id || !$rzp_signature || !$student_uid || $price_paid <= 0) {
    die(json_encode(["success" => false, "message" => "Missing or invalid payment data."]));
}

try {
    // 1. VERIFY THE CRYPTOGRAPHIC SIGNATURE
    $generated_signature = hash_hmac('sha256', $rzp_order_id . '|' . $rzp_payment_id, RZP_KEY_SECRET);

    if (hash_equals($generated_signature, $rzp_signature)) {
        
        // --- PAYMENT IS 100% AUTHENTIC ---
        
        // === START SECURE FINANCIAL TRANSACTION ===
        $conn->beginTransaction();

        // 2. PREVENT REPLAY ATTACKS
        // Check both tables to ensure this Razorpay receipt hasn't been logged already
        $check_quiz = $conn->prepare("SELECT id FROM quiz_purchases WHERE razorpay_payment_id = ?");
        $check_quiz->execute([$rzp_payment_id]);
        $check_sub = $conn->prepare("SELECT id FROM creator_subscriptions WHERE razorpay_payment_id = ?");
        $check_sub->execute([$rzp_payment_id]);

        if ($check_quiz->fetch() || $check_sub->fetch()) {
            throw new Exception("Payment already verified and claimed.");
        }

        // 3. DETERMINE INTERVAL & CREATOR UID
        $interval = '1 MONTH';
        if ($tier === '3m') $interval = '3 MONTH';
        if ($tier === '6m') $interval = '6 MONTH';

        $creator_uid = 'unknown';
        if ($tier === 'creator_sub' && !empty($target_creator)) {
            $creator_uid = $target_creator;
        } else if (!empty($set_id)) {
            $stmt = $conn->prepare("SELECT creator_id FROM mcq_sets WHERE set_id = ?");
            $stmt->execute([$set_id]);
            $creator = $stmt->fetch();
            if ($creator) $creator_uid = $creator['creator_id'];
        }

        // === 4. FINANCIAL MATH (RAZORPAY SPECIFIC) ===
        // Razorpay leaves you with the GST burden. Extract 18% inclusive GST for the government.
        $price_before_tax = $price_paid / 1.18;
        
        // Razorpay takes approx 2% of the gross price as a processing fee.
        $razorpay_fee = $price_paid * 0.02;

        // Net Revenue to be split
        $net_payout = $price_paid - ($price_paid - $price_before_tax) - $razorpay_fee;

        // Apply the 70/30 Split
        $creator_cut = round($net_payout * 0.70, 2);
        $platform_cut = round($net_payout * 0.30, 2);

        $balance_before = 0.00;
        $balance_after = 0.00;

        // === 5. SECURE WALLET UPDATE ===
        if ($creator_uid !== 'unknown') {
            // Lock the creator's row using FOR UPDATE
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

        // === 6. INSERT INTO PROPER LEDGER ===
        if ($tier === 'creator_sub') {
            $insert = $conn->prepare("INSERT INTO creator_subscriptions 
                (student_uid, creator_uid, razorpay_order_id, razorpay_payment_id, price_paid, creator_cut, platform_cut, balance_before, balance_after, purchased_at, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH))");
            $insert->execute([$student_uid, $creator_uid, $rzp_order_id, $rzp_payment_id, $price_paid, $creator_cut, $platform_cut, $balance_before, $balance_after]);
        } else {
            $insert = $conn->prepare("INSERT INTO quiz_purchases 
                (student_uid, creator_uid, set_id, razorpay_order_id, razorpay_payment_id, price_paid, creator_cut, platform_cut, balance_before, balance_after, purchased_at, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL $interval))");
            $insert->execute([$student_uid, $creator_uid, $set_id, $rzp_order_id, $rzp_payment_id, $price_paid, $creator_cut, $platform_cut, $balance_before, $balance_after]);
        }

        // === 7. COMMIT TRANSACTION ===
        $conn->commit();

        echo json_encode(["success" => true, "message" => "Payment verified, ledger updated, and access granted!"]);

    } else {
        // HACKER ALERT: Signatures did not match
        echo json_encode(["success" => false, "message" => "Invalid payment signature."]);
    }

} catch (Exception $e) {
    // Catch PDO and general exceptions, then rollback
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Server error: " . $e->getMessage()]);
}
?>