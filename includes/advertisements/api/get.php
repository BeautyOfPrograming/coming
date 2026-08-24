<?php
require_once '../../config.php';
require_once '../Advertisement.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get advertisement ID from query parameter
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid advertisement ID']);
    exit;
}

try {
    // Get advertisement
    $advertisement = new Advertisement($pdo);
    $result = $advertisement->getById($id, $_SESSION['driver_id']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'advertisement' => $result['advertisement']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Advertisement not found'
        ]);
    }
} catch (Exception $e) {
    error_log("Advertisement fetch error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching the advertisement'
    ]);
} 