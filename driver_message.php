<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || !isset($_SESSION['is_driver'])) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Validate input
$passenger_id = filter_input(INPUT_POST, 'passenger_id', FILTER_VALIDATE_INT);
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);

if (!$passenger_id || !$message) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

try {
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO driver_messages 
        (sender_id, receiver_id, sender_type, message, created_at)
        VALUES (?, ?, 'driver', ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['driver_id'],
        $passenger_id,
        $message
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>