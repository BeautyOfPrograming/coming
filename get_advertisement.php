<?php
session_start();
require_once 'includes/db_connect.php';

header('Content-Type: application/json');

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Get advertisement ID
$ad_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$ad_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid advertisement ID']);
    exit;
}

try {
    // Fetch the advertisement
    $query = "SELECT * FROM advertisements WHERE id = ? AND owner_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $ad_id, $_SESSION['driver_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Advertisement not found']);
        exit;
    }
    
    $advertisement = $result->fetch_assoc();
    
    // Convert car_features from JSON to comma-separated string if it exists
    if (!empty($advertisement['car_features'])) {
        $features = json_decode($advertisement['car_features'], true);
        $advertisement['car_features'] = implode(', ', $features);
    }
    
    echo json_encode([
        'success' => true,
        'advertisement' => $advertisement
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 