<?php
require_once '../../config.php';
require_once '../Advertisement.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    // Get all advertisements for the driver
    $advertisement = new Advertisement($conn);
    $result = $advertisement->getAllByOwner($_SESSION['driver_id']);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 