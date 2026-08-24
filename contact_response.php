<?php
session_start();
require_once 'includes/config.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id']) || !isset($_SESSION['is_driver'])) {
    header("Location: driver_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $response = filter_input(INPUT_POST, 'response', FILTER_SANITIZE_STRING);
    
    if (!$request_id || !in_array($response, ['accepted', 'rejected'])) {
        $_SESSION['error'] = "Invalid request";
        header("Location: driver_dashboard.php");
        exit;
    }
    
    try {
        // Update the contact request status
        $stmt = $pdo->prepare("UPDATE contact_requests SET status = ?, updated_at = NOW() WHERE id = ? AND driver_id = ?");
        $stmt->execute([$response, $request_id, $_SESSION['driver_id']]);
        
        if ($stmt->rowCount() > 0) {
            // If accepted, create a trip record
            if ($response == 'accepted') {
                $request_stmt = $pdo->prepare("SELECT * FROM contact_requests WHERE id = ?");
                $request_stmt->execute([$request_id]);
                $request = $request_stmt->fetch();
                
                if ($request) {
                    $trip_stmt = $pdo->prepare("
                        INSERT INTO trips 
                        (driver_id, passenger_id, passenger_name, pickup_location, destination, 
                         pickup_time, price, status, created_at)
                        SELECT 
                            cr.driver_id, 
                            cr.passenger_id, 
                            u.name, 
                            'To be determined' as pickup_location,
                            'To be determined' as destination,
                            cr.start_date as pickup_time,
                            0 as price,  -- You might want to calculate this based on your pricing model
                            'pending' as status,
                            NOW() as created_at
                        FROM contact_requests cr
                        JOIN registered_users u ON cr.passenger_id = u.id
                        WHERE cr.id = ?
                    ");
                    $trip_stmt->execute([$request_id]);
                }
            }
            
            $_SESSION['success'] = "Request has been " . $response;
        } else {
            $_SESSION['error'] = "Request not found or already processed";
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Database error. Please try again.";
    }
}

header("Location: driver_dashboard.php");
exit;
?>