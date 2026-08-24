<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$db = getDatabaseConnection();
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? $_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// Keep the connection open
while (true) {
    // Check for new messages
    $stmt = $db->prepare("
        SELECT COUNT(*) as new_messages 
        FROM messages 
        WHERE receiver_id = ? 
          AND is_read = FALSE
          AND id > ?
    ");
    $stmt->execute([$_SESSION['user_id'], $lastEventId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['new_messages'] > 0) {
        // Get the actual new messages
        $stmt = $db->prepare("
            SELECT m.*, 
                   u.name as sender_name,
                   u.photo as sender_photo,
                   u.user_type as sender_type
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.receiver_id = ?
              AND m.is_read = FALSE
              AND m.id > ?
            ORDER BY m.sent_at ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $lastEventId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as $message) {
            echo "event: new_message\n";
            echo "id: " . $message['id'] . "\n";
            echo "data: " . json_encode($message) . "\n\n";
            ob_flush();
            flush();
            
            // Update last event ID
            $lastEventId = $message['id'];
            
            // Mark as read
            $stmt = $db->prepare("
                UPDATE messages 
                SET is_read = TRUE 
                WHERE id = ?
            ");
            $stmt->execute([$message['id']]);
        }
    }

    // Check every second
    sleep(1);
}