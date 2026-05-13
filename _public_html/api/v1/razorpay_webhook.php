<?php
// /public_html/api/v1/razorpay_webhook.php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Content-Type: application/json; charset=UTF-8");

include 'db_connect.php'; // Your standard DB connection
$config_path = __DIR__ . '/../../../config/razorpay_config.php';
if (file_exists($config_path)) { require_once $config_path; }

// 1. Capture the raw POST data and Razorpay Signature Header
$webhook_payload = file_get_contents('php://input');
$razorpay_signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($webhook_payload) || empty($razorpay_signature)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid Payload']));
}

// 2. Verify the Signature to ensure it's actually from Razorpay
$expected_signature = hash_hmac('sha256', $webhook_payload, RZP_WEBHOOK_SECRET);

if (!hash_equals($expected_signature, $razorpay_signature)) {
    http_response_code(400); // 400 tells Razorpay "Bad Request"
    die(json_encode(['error' => 'Signature verification failed.']));
}

// 3. Decode the payload
$data = json_decode($webhook_payload, true);
$event_type = $data['event'] ?? '';

// We only care about successfully captured payments
if ($event_type === 'payment.captured') {
    
    $payment_entity = $data['payload']['payment']['entity'];
    $notes = $payment_entity['notes'] ?? [];
    
    $purchase_type = $notes['type'] ?? ''; // 'b2b_seats' or 'b2c_quiz'
    $creator_uid = $notes['creator_uid'] ?? '';
    $tier = $notes['tier'] ?? '';

    try {
        // ==========================================
        // PATH A: B2B INSTITUTE SEATS (Top-Up)
        // ==========================================
        if ($purchase_type === 'b2b_seats') {
            $seats_to_add = 0;
            if ($tier === '50_seats') $seats_to_add = 50;
            if ($tier === '100_seats') $seats_to_add = 100;
            if ($tier === '500_seats') $seats_to_add = 500;

            if ($seats_to_add > 0 && !empty($creator_uid)) {
                // Add seats to the Institute Profile
                $sql = "UPDATE coaching_profiles SET free_tests_left = free_tests_left + :seats WHERE creator_uid = :uid";
                $stmt = $conn->prepare($sql);
                $stmt->execute([':seats' => $seats_to_add, ':uid' => $creator_uid]);
            }
        }
        // ==========================================
        // PATH B: B2C STUDENT QUIZ PURCHASES
        // ==========================================
        else if ($purchase_type === 'b2c_quiz') {
            $student_uid = $notes['student_uid'] ?? '';
            $set_id = $notes['set_id'] ?? '';
            
            // Write your logic here to grant the student access to the quiz/subscription.
            // e.g., INSERT INTO user_subscriptions (user_uid, set_id, tier) VALUES (...)
        }

        // Return 200 OK so Razorpay knows we processed it successfully
        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (PDOException $e) {
        // Log the error but return 500 so Razorpay retries later
        error_log("Webhook DB Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
} else {
    // Acknowledge other events without processing
    http_response_code(200);
    echo json_encode(['status' => 'ignored']);
}
?>