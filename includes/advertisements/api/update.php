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
$required_fields = ['id', 'car_model', 'car_year', 'pickup_location', 'destination', 'available_from', 'available_to', 'price_per_day'];
foreach ($required_fields as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM advertisements WHERE id = ? AND owner_id = ?");
    $stmt->execute([$data['id'], $_SESSION['driver_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Advertisement not found or unauthorized']);
        exit;
    }
    
    // Update advertisement
    $advertisement = new Advertisement($pdo);
    $result = $advertisement->update($data['id'], $data);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Advertisement updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update advertisement'
        ]);
    }
} catch (Exception $e) {
    error_log("Advertisement update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating the advertisement'
    ]);
} 