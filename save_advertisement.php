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
$required_fields = ['car_model', 'car_year', 'pickup_location', 'destination', 'available_from', 'available_to', 'price_per_day'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Prepare the data
    $car_features = !empty($data['car_features']) ? json_encode(explode(',', $data['car_features'])) : null;
    
    if (!empty($data['ad_id'])) {
        // Update existing advertisement
        $query = "UPDATE advertisements SET 
            car_model = ?,
            car_year = ?,
            car_photo = ?,
            pickup_location = ?,
            destination = ?,
            available_from = ?,
            available_to = ?,
            price_per_day = ?,
            description = ?,
            car_features = ?,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = ? AND owner_id = ?";
            
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssdssii", 
            $data['car_model'],
            $data['car_year'],
            $data['car_photo'],
            $data['pickup_location'],
            $data['destination'],
            $data['available_from'],
            $data['available_to'],
            $data['price_per_day'],
            $data['description'],
            $car_features,
            $data['ad_id'],
            $_SESSION['driver_id']
        );
    } else {
        // Insert new advertisement
        $query = "INSERT INTO advertisements (
            owner_id,
            car_model,
            car_year,
            car_photo,
            pickup_location,
            destination,
            available_from,
            available_to,
            price_per_day,
            description,
            car_features,
            is_active
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssssssdss", 
            $_SESSION['driver_id'],
            $data['car_model'],
            $data['car_year'],
            $data['car_photo'],
            $data['pickup_location'],
            $data['destination'],
            $data['available_from'],
            $data['available_to'],
            $data['price_per_day'],
            $data['description'],
            $car_features
        );
    }
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} 