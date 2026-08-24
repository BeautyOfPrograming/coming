<?php
session_start();
require_once 'includes/config.php';

// Debug: Check session
error_log("Session driver_id: " . (isset($_SESSION['driver_id']) ? $_SESSION['driver_id'] : 'not set'));

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit;
}

// Debug: Check all contact requests for this driver
try {
    $debugStmt = $pdo->prepare("
        SELECT id, status, driver_id, passenger_id, created_at, updated_at
        FROM contact_requests
        WHERE driver_id = ?
        ORDER BY created_at DESC
    ");
    $debugStmt->execute([$_SESSION['driver_id']]);
    $allRequests = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All contact requests for driver: " . print_r($allRequests, true));
} catch (PDOException $e) {
    error_log("Debug query error: " . $e->getMessage());
}

// Pagination settings
$itemsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $itemsPerPage;

// Get total count of past trips
try {
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM contact_requests cr
        WHERE cr.driver_id = ? AND cr.status IN ('accepted', 'rejected', 'cancelled')
    ");
    $countStmt->execute([$_SESSION['driver_id']]);
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalCount / $itemsPerPage);
    
    // Debug: Log total count
    error_log("Total past trips count: " . $totalCount);
} catch (PDOException $e) {
    error_log("Error counting past trips: " . $e->getMessage());
    $totalCount = 0;
    $totalPages = 1;
}

// Get past trips with pagination
$pastRequests = [];
try {
    $stmt = $pdo->prepare("
        SELECT cr.*, 
               u.name as passenger_name, 
               u.photo as passenger_photo,
               (SELECT COUNT(*) 
                FROM messages m 
                WHERE m.contact_request_id = cr.id 
                AND m.receiver_id = ? 
                AND m.sender_type = 'passenger'
                AND m.is_read = 0) as unread_count
        FROM contact_requests cr
        JOIN registered_users u ON cr.passenger_id = u.id
        WHERE cr.driver_id = ? AND cr.status IN ('accepted', 'rejected', 'cancelled')
        ORDER BY cr.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $_SESSION['driver_id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $_SESSION['driver_id'], PDO::PARAM_INT);
    $stmt->bindValue(3, $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(4, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pastRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log query results
    error_log("Number of past requests fetched: " . count($pastRequests));
    if (count($pastRequests) > 0) {
        error_log("First request details: " . print_r($pastRequests[0], true));
    }
} catch (PDOException $e) {
    error_log("Error fetching past trips: " . $e->getMessage());
}

// Get messages if a specific user is selected
$messages = [];
$currentUser = null;

if (isset($_GET['user_id']) && isset($_GET['request_id'])) {
    $user_id = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
    $request_id = filter_var($_GET['request_id'], FILTER_VALIDATE_INT);
    if ($user_id && $request_id) {
        try {
            $stmt = $pdo->prepare("SELECT id, name, photo FROM registered_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentUser) {
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                           CASE 
                               WHEN m.sender_id = ? AND m.sender_type = 'driver' THEN 'sent'
                               ELSE 'received'
                           END as message_type
                    FROM messages m
                    WHERE m.contact_request_id = ?
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([
                    $_SESSION['driver_id'],
                    $request_id
                ]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Past Trips - WhoIsComing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/past_trips.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-car"></i>
                    <span>CarShare</span>
                </div>
                <div class="driver-menu">
                    <a href="driver_dashboard.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="driver_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <h1 class="page-title">All Past Trips</h1>
        
        <div class="trips-grid">
            <div class="trips-section">
                <?php if (empty($pastRequests)): ?>
                    <div class="no-trips">
                        <p>You haven't completed any trips yet.</p>
                        <p>When you complete rides, they'll appear here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pastRequests as $request): ?>
                        <div class="trip-item">
                            <img src="includes/images/<?= htmlspecialchars($request['passenger_photo']) ?>" 
                                 alt="<?= htmlspecialchars($request['passenger_name']) ?>" 
                                 class="user-photo">
                            <div class="trip-info">
                                <div class="trip-user"><?= htmlspecialchars($request['passenger_name']) ?></div>
                                <div class="trip-details">
                                    <div class="trip-detail">
                                        <i class="fas fa-calendar-day"></i>
                                        <?= date('M j, Y', strtotime($request['start_date'])) ?>
                                    </div>
                                    <div class="trip-detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($request['pickup_location']) ?> to <?= htmlspecialchars($request['destination']) ?>
                                    </div>
                                    <div class="trip-detail">
                                        <i class="fas fa-pound-sign"></i>
                                        £<?= number_format($request['price'], 2) ?>
                                    </div>
                                    <div class="trip-detail">
                                        <i class="fas fa-info-circle"></i>
                                        Status: <?= ucfirst($request['status']) ?>
                                    </div>
                                </div>
                                <div class="trip-actions">
                                    <div class="chat-button-wrapper">
                                        <a href="past_trips.php?user_id=<?= $request['passenger_id'] ?>&request_id=<?= $request['id'] ?>&is_past=true&page=<?= $page ?>" class="chat-button">
                                            <i class="fas fa-comment"></i> View Chat History
                                        </a>
                                        <?php if ($request['unread_count'] > 0): ?>
                                            <span class="notification-badge"><?= $request['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <a href="?page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?= $page + 1 ?>">Next <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="chat-section">
                <div class="section-header">
                    <h2 class="section-title">Messages</h2>
                </div>
                
                <div class="chat-container">
                    <?php if ($currentUser): ?>
                        <div class="chat-header">
                            <img src="includes/images/<?= htmlspecialchars($currentUser['photo']) ?>" 
                                 alt="<?= htmlspecialchars($currentUser['name']) ?>" 
                                 class="chat-user-photo">
                            <div>
                                <div><?= htmlspecialchars($currentUser['name']) ?></div>
                                <div style="font-size: 12px;">Passenger</div>
                                <div style="font-size: 12px; color: #666;">(Past Request - Chat History Only)</div>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div style="text-align: center; color: var(--medium-gray); padding: 20px;">
                                    No messages in this conversation.
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?= $message['message_type'] ?>">
                                        <div class="message-content">
                                            <?= htmlspecialchars($message['content']) ?>
                                        </div>
                                        <div class="message-time">
                                            <?= date('h:i A', strtotime($message['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chat-input" style="justify-content: center; background-color: #f5f5f5;">
                            <span style="color: #666;">Chat is disabled for past requests</span>
                        </div>
                    <?php else: ?>
                        <div class="no-chat-selected">
                            <i class="fas fa-comments"></i>
                            <h3>No chat selected</h3>
                            <p>Select a passenger from your trips to view chat history</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Scroll to bottom when page loads
        window.addEventListener('load', scrollToBottom);
    </script>
</body>
</html> 