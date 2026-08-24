<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Enable error logging
error_log("check_notifications.php called");

// Initialize response array
$response = [
    'success' => false,
    'notifications' => [],
    'error' => null
];

try {
    // Check if user is logged in
    if (!isset($_SESSION['driver_id'])) {
        error_log("User not authenticated in check_notifications.php");
        throw new Exception('User not authenticated');
    }

    $userId = $_SESSION['driver_id'];
    error_log("Checking notifications for driver_id: " . $userId);

    // First, let's check if there are any unread messages at all
    $checkQuery = "SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([$userId]);
    $totalUnread = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
    error_log("Total unread messages: " . $totalUnread);

    // Let's also check the raw messages to see what we have
    $rawQuery = "SELECT * FROM messages WHERE receiver_id = ? AND is_read = 0 LIMIT 5";
    $rawStmt = $pdo->prepare($rawQuery);
    $rawStmt->execute([$userId]);
    $rawMessages = $rawStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Raw unread messages: " . json_encode($rawMessages));

    // Query for driver notifications (messages from passengers)
    $query = "
        SELECT 
            m.contact_request_id as request_id,
            cr.passenger_id as user_id,
            u.name as user_name,
            u.photo as user_photo,
            COUNT(m.id) as unread_count,
            MAX(m.created_at) as latest_message
        FROM messages m
        INNER JOIN contact_requests cr ON m.contact_request_id = cr.id
        INNER JOIN registered_users u ON cr.passenger_id = u.id
        WHERE 
            m.receiver_id = ?
            AND m.sender_type = 'passenger'
            AND m.is_read = 0
            AND cr.driver_id = ?
        GROUP BY 
            m.contact_request_id,
            cr.passenger_id,
            u.name,
            u.photo
        ORDER BY 
            MAX(m.created_at) DESC";

    error_log("Executing notifications query: " . $query);
    error_log("Parameters: " . json_encode([$userId, $userId]));

    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $userId]);
    
    // Fetch all notifications
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Notifications fetched: " . count($notifications));

    // Process each notification
    foreach ($notifications as &$notification) {
        // Format the timestamp
        if (isset($notification['latest_message'])) {
            $notification['latest_message'] = date('Y-m-d H:i:s', strtotime($notification['latest_message']));
        }
        
        // Ensure integer values
        $notification['unread_count'] = (int)$notification['unread_count'];
        $notification['user_id'] = (int)$notification['user_id'];
        $notification['request_id'] = (int)$notification['request_id'];
        
        // Ensure photo path exists
        if (empty($notification['user_photo'])) {
            $notification['user_photo'] = 'default.jpg';
        }
    }

    // Update response
    $response['success'] = true;
    $response['notifications'] = $notifications;
    $response['user_type'] = 'driver';
    
    // Add debug information
    $response['debug'] = [
        'user_id' => $userId,
        'query' => $query,
        'timestamp' => date('Y-m-d H:i:s'),
        'notification_count' => count($notifications),
        'total_unread' => $totalUnread,
        'raw_messages' => $rawMessages
    ];

} catch (Exception $e) {
    error_log("Error in check_notifications.php: " . $e->getMessage());
    $response['error'] = $e->getMessage();
}

// Log the response for debugging
error_log("Notifications Response: " . json_encode($response));

// Send response
echo json_encode($response); 