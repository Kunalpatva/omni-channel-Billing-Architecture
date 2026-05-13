<?php
// /secure_config/ai_magic_service.php
if (!defined('SECURE_ACCESS')) die('Direct access not permitted');

define('AI_API_KEY', 'key_value');
define('DAILY_LIMIT', 20);

function processMagicImport($raw_text, $creator_uid, $conn) {
    // 1. CHECK RATE LIMIT (Rolling 24 Hours)
    $stmt = $conn->prepare("SELECT COUNT(*) as attempt_count FROM ai_usage_logs WHERE creator_uid = :uid AND used_at > NOW() - INTERVAL 24 HOUR");
    $stmt->execute([':uid' => $creator_uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row['attempt_count'] >= DAILY_LIMIT) {
        return ["success" => false, "error" => "You have reached your daily limit of " . DAILY_LIMIT . " AI imports. Please try again tomorrow."];
    }

    // 2. CALL THE AI API (Example using OpenAI GPT-4o-mini)
    $system_prompt = "You are a JSON formatter. Extract multiple-choice questions from the user's messy text. Identify the question, 4 options (A,B,C,D), and the correct answer. If no explanation exists, generate a brief educational explanation. Return strictly a JSON array of objects with keys: question, a, b, c, d, answer, explain. No markdown, no conversational text.";
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user', 'content' => $raw_text]
        ],
        'temperature' => 0.2
    ]));

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode !== 200) {
        return ["success" => false, "error" => "AI Service is temporarily unavailable."];
    }

    // Extract the JSON from the AI response
    $ai_data = json_decode($response, true);
    $ai_output = $ai_data['choices'][0]['message']['content'];
    
    // Clean up any accidental markdown the AI might return (e.g. ```json)
    $ai_output = preg_replace('/```json|```/', '', $ai_output);
    $formatted_questions = json_decode(trim($ai_output), true);

    if (!$formatted_questions) {
        return ["success" => false, "error" => "AI failed to format the text properly. Please ensure it looks somewhat like a quiz."];
    }

    // 3. LOG THE USAGE IN THE DATABASE
    $log_stmt = $conn->prepare("INSERT INTO ai_usage_logs (creator_uid) VALUES (:uid)");
    $log_stmt->execute([':uid' => $creator_uid]);

    // 4. RETURN SUCCESS
    return [
        "success" => true,
        "questions" => $formatted_questions,
        "attempts_remaining" => (DAILY_LIMIT - $row['attempt_count'] - 1)
    ];
}
?>