<?php
session_start();
require_once 'includes/config.php';

// Disable error display for JSON response
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to send a request']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Log the received input for debugging
error_log("Received contact request input: " . print_r($input, true));

// Validate input
if (!$input || !isset($input['owner_id']) || !isset($input['start_date']) || !isset($input['end_date'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Sanitize and validate data
$owner_id = filter_var($input['owner_id'], FILTER_VALIDATE_INT);
$start_date = filter_var($input['start_date'], FILTER_SANITIZE_STRING);
$end_date = filter_var($input['end_date'], FILTER_SANITIZE_STRING);
$message = isset($input['message']) ? filter_var($input['message'], FILTER_SANITIZE_STRING) : '';

// Additional validation
if (!$owner_id || !$start_date || !$end_date) {
    echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
    exit;
}

try {
    // Check if the owner exists
    $stmt = $pdo->prepare("SELECT id FROM drivers WHERE id = ?");
    $stmt->execute([$owner_id]);
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Owner not found']);
        exit;
    }

    // Insert the contact request
    $stmt = $pdo->prepare("INSERT INTO contact_requests 
                          (passenger_id, driver_id, start_date, end_date, message, status, created_at) 
                          VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([
        $_SESSION['user_id'],
        $owner_id,
        $start_date,
        $end_date,
        $message
    ]);

    // Get the request ID
    $request_id = $pdo->lastInsertId();

    // Insert the initial message into the messages table
    if ($message) {
        $stmt = $pdo->prepare("INSERT INTO messages 
                              (contact_request_id, sender_id, sender_type, receiver_id, content, created_at) 
                              VALUES (?, ?, 'passenger', ?, ?, NOW())");
        $stmt->execute([
            $request_id,
            $_SESSION['user_id'],
            $owner_id,
            $message
        ]);
    }

    // You could also send a notification email to the owner here

    echo json_encode(['success' => true, 'request_id' => $request_id]);
    
} catch (PDOException $e) {
    error_log("Database error in contact request: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error. Please try again.',
        'error_details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("General error in contact request: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred. Please try again.',
        'error_details' => $e->getMessage()
    ]);
}