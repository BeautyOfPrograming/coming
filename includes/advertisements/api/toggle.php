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

if (!$data || !isset($data['id']) || !isset($data['is_active'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request data']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM advertisements WHERE id = ? AND owner_id = ?");
    $stmt->execute([$data['id'], $_SESSION['driver_id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Advertisement not found or unauthorized']);
        exit;
    }
    
    // Toggle advertisement status
    $advertisement = new Advertisement($pdo);
    $result = $advertisement->toggleStatus($data['id'], $_SESSION['driver_id'], $data['is_active']);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Advertisement status updated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to update advertisement status'
        ]);
    }
} catch (Exception $e) {
    error_log("Advertisement status toggle error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while updating the advertisement status'
    ]);
} 