<?php
// api/v1/get_purchase_history.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/../config/db_credentials.php';

$student_uid = $_GET['student_uid'] ?? '';

if (empty($student_uid)) {
    die(json_encode(["success" => false, "error" => "No UID provided"]));
}

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch active and expired creator passes
    // We join with the 'creators' table so we can show the creator's actual name!
    $sql = "SELECT cs.creator_uid, cs.expires_at, c.name as creator_name 
            FROM creator_subscriptions cs
            JOIN creators c ON cs.creator_uid = c.uid
            WHERE cs.student_uid = :suid
            ORDER BY cs.expires_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute(['suid' => $student_uid]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "success" => true,
        "data" => $history
    ]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}
?>