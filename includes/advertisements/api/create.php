<?php
require_once '../../config.php';
require_once '../Advertisement.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

// Validate required fields
$required_fields = ['car_model', 'car_year', 'pickup_location', 'destination', 'available_from', 'available_to', 'price_per_day'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Add owner_id to data
    $data['owner_id'] = $_SESSION['driver_id'];
    
    // Create advertisement
    $advertisement = new Advertisement($pdo);
    $result = $advertisement->create($data);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Advertisement created successfully',
            'id' => $result['id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to create advertisement'
        ]);
    }
} catch (Exception $e) {
    error_log("Advertisement creation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while creating the advertisement'
    ]);
} 