<?php
require_once 'includes/config.php';
header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    
    if (!$requestId || !in_array($status, ['accepted', 'rejected'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        exit;
    }
    
    // Verify the driver owns this request
    $stmt = $pdo->prepare("SELECT * FROM contact_requests WHERE id = ? AND driver_id = ?");
    $stmt->execute([$requestId, $_SESSION['driver_id']]);
    $request = $stmt->fetch();
    
    if (!$request) {
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update status
        $stmt = $pdo->prepare("UPDATE contact_requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $requestId]);
        
        // Add system message about status change
        $message = "Driver has " . $status . " the ride request";
        $stmt = $pdo->prepare("INSERT INTO messages (contact_request_id, sender_type, sender_id, receiver_id, content, is_system) 
                              VALUES (?, 'system', ?, ?, ?, 1)");
        $stmt->execute([$requestId, $_SESSION['driver_id'], $request['passenger_id'], $message]);
        
        $pdo->commit();
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);