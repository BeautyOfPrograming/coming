<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['id']) || !isset($data['is_active'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$ad_id = intval($data['id']);
$is_active = intval($data['is_active']);

try {
    // Update advertisement status
    $query = "UPDATE advertisements SET is_active = ? WHERE id = ? AND owner_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $is_active, $ad_id, $_SESSION['driver_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 