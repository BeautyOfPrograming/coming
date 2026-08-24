<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in as either passenger or driver
$is_passenger = isset($_SESSION['user_id']);
$is_driver = isset($_SESSION['driver_id']);

if (!$is_passenger && !$is_driver) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send messages']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input
$data = json_decode(file_get_contents('php://input'), true);

$message = isset($data['message']) ? trim(strip_tags($data['message'])) : null;
$receiver_id = isset($data['receiver_id']) ? filter_var($data['receiver_id'], FILTER_VALIDATE_INT) : null;
$receiver_type = isset($data['receiver_type']) ? trim(strip_tags($data['receiver_type'])) : null;
$contact_request_id = isset($data['contact_request_id']) ? filter_var($data['contact_request_id'], FILTER_VALIDATE_INT) : null;
$sender_type = isset($data['sender_type']) ? trim(strip_tags($data['sender_type'])) : null;

// Validate required fields
if (!$message || !$receiver_id || !$receiver_type || !$sender_type || !$contact_request_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'All fields are required',
        'errors' => [
            'message' => !$message ? 'Message is required' : null,
            'receiver_id' => !$receiver_id ? 'Receiver ID is required' : null,
            'receiver_type' => !$receiver_type ? 'Receiver type is required' : null,
            'sender_type' => !$sender_type ? 'Sender type is required' : null,
            'contact_request_id' => !$contact_request_id ? 'Contact request ID is required' : null
        ]
    ]);
    exit;
}

// Validate sender type matches session
if (($sender_type === 'driver' && !isset($_SESSION['driver_id'])) || 
    ($sender_type === 'passenger' && !isset($_SESSION['user_id']))) {
    echo json_encode(['success' => false, 'message' => 'Invalid sender type for current session']);
    exit;
}

// Validate contact request exists and belongs to the sender
try {
    $stmt = $pdo->prepare("
        SELECT id FROM contact_requests 
        WHERE id = ? AND (
            (? = 'driver' AND driver_id = ?) OR 
            (? = 'passenger' AND passenger_id = ?)
        )
    ");
    $stmt->execute([
        $contact_request_id, 
        $sender_type, 
        $sender_type === 'driver' ? $_SESSION['driver_id'] : null,
        $sender_type,
        $sender_type === 'passenger' ? $_SESSION['user_id'] : null
    ]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Invalid contact request']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Error validating contact request: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to validate contact request']);
    exit;
}

// Additional validation
if (strlen(trim($message)) === 0) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

if (strlen($message) > 1000) {
    echo json_encode(['success' => false, 'message' => 'Message is too long (max 1000 characters)']);
    exit;
}

try {
    // Determine sender ID based on sender type
    $sender_id = $sender_type === 'driver' ? $_SESSION['driver_id'] : $_SESSION['user_id'];
    
    // Insert the message
    $stmt = $pdo->prepare("
        INSERT INTO messages 
        (contact_request_id, sender_id, sender_type, receiver_id, content, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $contact_request_id,
        $sender_id,
        $sender_type,
        $receiver_id,
        $message
    ]);
    
    // Get the inserted message with additional details
    $message_id = $pdo->lastInsertId();
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE 
                   WHEN m.sender_type = ? THEN 'sent'
                   ELSE 'received'
               END as message_type
        FROM messages m
        WHERE m.id = ?
    ");
    $stmt->execute([$sender_type, $message_id]);
    $new_message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update contact request last activity
    $stmt = $pdo->prepare("
        UPDATE contact_requests 
        SET last_activity = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$contact_request_id]);
    
    echo json_encode([
        'success' => true,
        'message' => $new_message
    ]);
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    error_log("Failed query: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to send message',
        'error' => $e->getMessage()
    ]);
}