<?php
require_once '../../config.php';
require_once '../Advertisement.php';

header('Content-Type: application/json');

// Get search filters from GET parameters
$filters = [
    'pickup_location' => $_GET['pickup_location'] ?? '',
    'destination' => $_GET['destination'] ?? '',
    'available_from' => $_GET['available_from'] ?? '',
    'available_to' => $_GET['available_to'] ?? '',
    'max_price' => $_GET['max_price'] ?? '',
    'car_model' => $_GET['car_model'] ?? '',
    'min_year' => $_GET['min_year'] ?? ''
];

try {
    // Search advertisements
    $advertisement = new Advertisement($pdo);
    $result = $advertisement->search($filters);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'advertisements' => $result['advertisements']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to search advertisements'
        ]);
    }
} catch (Exception $e) {
    error_log("Advertisement search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while searching advertisements'
    ]);
} 