<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

include 'db_connect.php';

$config_path = __DIR__ . '/../../../config/razorpay_config.php';
if (!file_exists($config_path)) {
    die(json_encode(["success" => false, "message" => "Security Error: Config missing."]));
}
require_once $config_path;

$data = json_decode(file_get_contents('php://input'), true);
$set_id = $data['set_id'] ?? '';
$tier = $data['tier'] ?? ''; 
$uid = $data['uid'] ?? '';
$target_creator = $data['creator_uid'] ?? '';

// --- NEW: DEFINE B2B SEAT PACKAGES ---
$seat_packages = [
    '50_seats' => 99,   // ₹99
    '100_seats' => 149, // ₹149
    '500_seats' => 499  // ₹499
];
$is_seat_package = array_key_exists($tier, $seat_packages);

// --- SMART VALIDATION ---
if (empty($uid) || empty($tier)) {
    die(json_encode(["success" => false, "message" => "Missing required data: UID or Tier."]));
}

// B2C: If buying a quiz plan (1m, 3m, 6m), set_id is mandatory
if (!$is_seat_package && $tier !== 'creator_sub' && empty($set_id)) {
    die(json_encode(["success" => false, "message" => "Missing required data: Quiz Set ID."]));
}

// B2C: If buying a creator pass from the profile, creator_uid or set_id is mandatory
if (!$is_seat_package && $tier === 'creator_sub' && empty($target_creator) && empty($set_id)) {
    die(json_encode(["success" => false, "message" => "Missing required data: Creator ID."]));
}

try {
    $amount_in_rupees = 0;
    $discounted_amount = 0;
    
    // ==========================================
    // PATH A: B2B INSTITUTE SEAT PACKAGES
    // ==========================================
    if ($is_seat_package) {
        $amount_in_rupees = $seat_packages[$tier];
        $discounted_amount = $amount_in_rupees; // NO 10% discount for B2B seats
    } 
    // ==========================================
    // PATH B: B2C STUDENT PURCHASES
    // ==========================================
    else {
        if ($tier === 'creator_sub') {
            // Fetch creator sub price
            if (empty($target_creator)) {
                $stmt = $conn->prepare("SELECT creator_id FROM mcq_sets WHERE set_id = :set_id LIMIT 1");
                $stmt->execute([':set_id' => $set_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['creator_id'])) {
                    $target_creator = $row['creator_id'];
                } else {
                    die(json_encode(["success" => false, "message" => "Could not locate creator for this quiz."]));
                }
            }
            $stmt = $conn->prepare("SELECT subscription_price FROM creators WHERE uid = :uid LIMIT 1");
            $stmt->execute([':uid' => $target_creator]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['subscription_price'])) {
                $amount_in_rupees = (int)$row['subscription_price'];
            }
        } else {
            // Fetch 1m, 3m, 6m price
            $allowed_tiers = ['1m', '3m', '6m'];
            if (!in_array($tier, $allowed_tiers)) {
                die(json_encode(["success" => false, "message" => "Invalid tier specified."]));
            }
            $column = "price_" . $tier; 
            $stmt = $conn->prepare("SELECT $column as price FROM mcq_sets WHERE set_id = :set_id LIMIT 1");
            $stmt->execute([':set_id' => $set_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['price'])) {
                $amount_in_rupees = (int)$row['price'];
            }
        }

        // Apply the B2C 10% WEB EXCLUSIVE DISCOUNT
        $discounted_amount = round($amount_in_rupees * 0.90);
    }

    // --- FINAL PRICE CHECKS ---
    if ($amount_in_rupees <= 0) {
        die(json_encode(["success" => false, "message" => "Invalid price configuration for this item."]));
    }
    if ($discounted_amount < 1) { $discounted_amount = 1; }
    
    $amount_in_paise = $discounted_amount * 100;

    // --- RAZORPAY API CALL ---
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    
    $payload = [
        "amount" => $amount_in_paise,
        "currency" => "INR",
        "receipt" => "rcpt_" . substr(md5(uniqid(rand(), true)), 0, 10), 
        "notes" => [
            "set_id" => $set_id,
            "tier" => $tier,
            "student_uid" => $uid,
            "creator_uid" => $target_creator,
            "type" => $is_seat_package ? "b2b_seats" : "b2c_quiz" // Flag for webhook
        ]
    ];
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_USERPWD, RZP_KEY_ID . ':' . RZP_KEY_SECRET);
    
    $result = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $order_response = json_decode($result, true);

    if ($http_status == 200 && isset($order_response['id'])) {
        echo json_encode([
            "success" => true,
            "order_id" => $order_response['id'],
            "amount" => $amount_in_paise,
            "original_price" => $amount_in_rupees,
            "key_id" => RZP_KEY_ID 
        ]);
    } else {
        $error_msg = $order_response['error']['description'] ?? "Failed to create order at payment gateway.";
        echo json_encode(["success" => false, "message" => "Razorpay Error: " . $error_msg]);
    }

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "DB Error: " . $e->getMessage()]);
}
?>