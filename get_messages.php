<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Enable error logging
error_log("get_messages.php called");

// Check if user is logged in
if (!isset($_SESSION['driver_id'])) {
    error_log("User not authenticated in get_messages.php");
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get parameters
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$request_id = isset($_GET['request_id']) ? intval($_GET['request_id']) : 0;

error_log("Parameters received - user_id: $user_id, request_id: $request_id");

if (!$user_id || !$request_id) {
    error_log("Invalid parameters in get_messages.php");
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    // Fetch messages for the conversation
    $query = "SELECT m.*, 
              CASE 
                  WHEN m.sender_id = ? THEN 'sent'
                  ELSE 'received'
              END as message_type
              FROM messages m
              WHERE m.contact_request_id = ?
              AND ((m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?))
              ORDER BY m.created_at ASC";
    
    error_log("Executing query: $query");
    error_log("Parameters: " . json_encode([
        $_SESSION['driver_id'],
        $request_id,
        $_SESSION['driver_id'],
        $user_id,
        $user_id,
        $_SESSION['driver_id']
    ]));
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $_SESSION['driver_id'],  // For message_type calculation
        $request_id,           // contact_request_id
        $_SESSION['driver_id'],  // sender_id in first condition
        $user_id,             // receiver_id in first condition
        $user_id,             // sender_id in second condition
        $_SESSION['driver_id']   // receiver_id in second condition
    ]);
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Messages fetched: " . count($messages));
    
    // Mark messages as read
    $update_query = "UPDATE messages 
                    SET is_read = 1 
                    WHERE contact_request_id = ? 
                    AND receiver_id = ? 
                    AND is_read = 0";
    
    $update_stmt = $pdo->prepare($update_query);
    $update_stmt->execute([$request_id, $_SESSION['driver_id']]);
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in get_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("General error in get_messages.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred'
    ]);
} 