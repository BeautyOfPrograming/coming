<?php
session_start();
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['driver_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$status = isset($data['status']) ? $data['status'] : null;

if (!$status || !in_array($status, ['available', 'offline'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE drivers SET status = ? WHERE id = ?");
    $stmt->execute([$status, $_SESSION['driver_id']]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    error_log("Error updating driver status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}