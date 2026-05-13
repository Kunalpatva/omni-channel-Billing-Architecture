<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/../config/db_credentials.php';

$student_uid = $_POST['student_uid'] ?? '';
$set_id = $_POST['set_id'] ?? '';

// The Revenue Split (80% to Creator, 20% to MCQ Bazaar)
$CREATOR_PERCENTAGE = 0.80; 

if (empty($student_uid) || empty($set_id)) {
    die(json_encode(["success" => false, "error" => "Missing data"]));
}

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Get the Quiz details (Price and Creator ID)
    $stmt = $conn->prepare("SELECT creator_id, price FROM mcq_sets WHERE set_id = :set_id LIMIT 1");
    $stmt->execute(['set_id' => $set_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        die(json_encode(["success" => false, "error" => "Quiz not found"]));
    }

    $amount_paid = floatval($quiz['price']);
    $creator_uid = $quiz['creator_id'];

    // 2. Calculate the Split
    $creator_cut = round($amount_paid * $CREATOR_PERCENTAGE, 2);
    $platform_cut = round($amount_paid - $creator_cut, 2);

    // --- START FINANCIAL TRANSACTION ---
    $conn->beginTransaction();

    // 3. Log the Purchase
    $ins = $conn->prepare("INSERT INTO purchases (student_uid, set_id, creator_uid, amount_paid, creator_cut, platform_cut) 
                           VALUES (:suid, :set_id, :cuid, :paid, :ccut, :pcut)");
    $ins->execute([
        'suid' => $student_uid,
        'set_id' => $set_id,
        'cuid' => $creator_uid,
        'paid' => $amount_paid,
        'ccut' => $creator_cut,
        'pcut' => $platform_cut
    ]);

    // 4. Add the money to the Creator's Wallet!
    $wallet = $conn->prepare("UPDATE creators SET wallet_balance = wallet_balance + :ccut WHERE uid = :cuid");
    $wallet->execute([
        'ccut' => $creator_cut,
        'cuid' => $creator_uid
    ]);

    // --- COMMIT TRANSACTION ---
    $conn->commit();
    echo json_encode(["success" => true, "message" => "Purchase successful!"]);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack(); // If anything fails, undo the whole transaction!
    }
    // Duplicate entry 1062 means they already bought it
    if ($e->getCode() == 23000) {
        echo json_encode(["success" => false, "error" => "You already own this quiz."]);
    } else {
        echo json_encode(["success" => false, "error" => "Transaction failed."]);
    }
}
?>