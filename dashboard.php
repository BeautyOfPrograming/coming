<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_messages':
            if (isset($_GET['driver_id']) && isset($_GET['request_id'])) {
                $driver_id = filter_var($_GET['driver_id'], FILTER_VALIDATE_INT);
                $request_id = filter_var($_GET['request_id'], FILTER_VALIDATE_INT);
                
                if ($driver_id && $request_id) {
                    try {
                        $stmt = $pdo->prepare("
                            SELECT m.*, 
                                   CASE 
                                       WHEN m.sender_id = ? AND m.sender_type = 'passenger' THEN 'sent'
                                       ELSE 'received'
                                   END as message_type
                            FROM messages m
                            WHERE m.contact_request_id = ?
                            ORDER BY m.created_at ASC
                        ");
                        $stmt->execute([$_SESSION['user_id'], $request_id]);
                        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo json_encode([
                            'success' => true,
                            'messages' => $messages
                        ]);
                    } catch (PDOException $e) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Error fetching messages'
                        ]);
                    }
                }
            }
            exit;
            
        case 'get_notifications':
            try {
                // Get unread messages count and latest message for each contact request
                $stmt = $pdo->prepare("
                    SELECT 
                        cr.id,
                        cr.driver_id,
                        d.name,
                        d.photo,
                        m.content as last_message,
                        COUNT(CASE WHEN m.is_read = 0 AND m.receiver_id = ? AND m.sender_type = 'driver' THEN 1 END) as unread_count,
                        MAX(m.created_at) as latest_message_time
                    FROM contact_requests cr
                    JOIN drivers d ON cr.driver_id = d.id
                    LEFT JOIN messages m ON m.contact_request_id = cr.id
                    WHERE cr.passenger_id = ?
                    GROUP BY cr.id, cr.driver_id, d.name, d.photo
                    HAVING unread_count > 0
                    ORDER BY latest_message_time DESC
                ");
                $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get user's name for the header
                $stmt = $pdo->prepare("SELECT name FROM registered_users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'notifications' => $notifications,
                    'user' => [
                        'name' => $user['name'],
                        'total_unread' => array_sum(array_column($notifications, 'unread_count'))
                    ]
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Error fetching notifications'
                ]);
            }
            exit;
    }
}

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    header('Content-Type: application/json');
    
    $message = trim(strip_tags($_POST['message']));
    $receiver_id = filter_var($_POST['receiver_id'], FILTER_VALIDATE_INT);
    $contact_request_id = filter_var($_POST['contact_request_id'], FILTER_VALIDATE_INT);
    
    if ($message && $receiver_id && $contact_request_id) {
        try {
            // Insert the message
            $stmt = $pdo->prepare("
                INSERT INTO messages 
                (contact_request_id, sender_id, sender_type, receiver_id, content, created_at) 
                VALUES (?, ?, 'passenger', ?, ?, NOW())
            ");
            
            $stmt->execute([
                $contact_request_id,
                $_SESSION['user_id'],
                $receiver_id,
                $message
            ]);
            
            // Update contact request last activity
            $stmt = $pdo->prepare("
                UPDATE contact_requests 
                SET last_activity = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$contact_request_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Message sent successfully'
            ]);
        } catch (PDOException $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Error sending message'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid message data'
        ]);
    }
    exit;
}

// Get user's contact requests from database
$contactRequests = [];
$totalRequests = 0;
try {
    // First get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM contact_requests cr
        WHERE cr.passenger_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRequests = $result['total'];

    // Then get limited contact requests
    $stmt = $pdo->prepare("
        SELECT cr.*, 
               d.name as driver_name, 
               d.photo as driver_photo, 
               d.car_model,
               (SELECT COUNT(*) 
                FROM messages m 
                WHERE m.contact_request_id = cr.id 
                AND m.receiver_id = ? 
                AND m.sender_type = 'driver'
                AND m.is_read = 0) as unread_count,
               (SELECT MAX(created_at)
                FROM messages m
                WHERE m.contact_request_id = cr.id
                AND m.receiver_id = ?
                AND m.sender_type = 'driver'
                AND m.is_read = 0) as latest_unread
        FROM contact_requests cr
        JOIN drivers d ON cr.driver_id = d.id
        WHERE cr.passenger_id = ?
        ORDER BY 
            CASE 
                WHEN latest_unread IS NOT NULL THEN 0
                ELSE 1
            END,
            latest_unread DESC,
            cr.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $contactRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching contact requests: " . $e->getMessage());
}

// Get user's past trips from database
$pastTrips = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, d.name as driver_name, d.photo as driver_photo, d.car_model 
        FROM trips t
        JOIN drivers d ON t.driver_id = d.id
        WHERE t.passenger_id = ? AND t.status = 'completed'
        ORDER BY t.trip_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pastTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching past trips: " . $e->getMessage());
}

// Get messages if a specific driver is selected
$messages = [];
$currentDriver = null;

if (isset($_GET['driver_id']) && isset($_GET['request_id'])) {
    $driver_id = filter_var($_GET['driver_id'], FILTER_VALIDATE_INT);
    $request_id = filter_var($_GET['request_id'], FILTER_VALIDATE_INT);
    if ($driver_id && $request_id) {
        // Get the driver info from database
        try {
            $stmt = $pdo->prepare("SELECT id, name, photo, car_model FROM drivers WHERE id = ?");
            $stmt->execute([$driver_id]);
            $currentDriver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentDriver) {
                // Get messages for this driver and specific contact request
                $stmt = $pdo->prepare("
                    SELECT m.*, 
                           CASE 
                               WHEN m.sender_id = ? AND m.sender_type = 'passenger' THEN 'sent'
                               ELSE 'received'
                           END as message_type
                    FROM messages m
                    WHERE m.contact_request_id = ?
                    ORDER BY m.created_at ASC
                ");
                $stmt->execute([
                    $_SESSION['user_id'],
                    $request_id
                ]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error fetching messages: " . $e->getMessage());
        }
    }
}

// Mark messages as read when viewing a conversation
if (isset($_GET['driver_id']) && isset($_GET['request_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE contact_request_id = ? 
            AND receiver_id = ? 
            AND sender_type = 'driver'
            AND is_read = 0
        ");
        $stmt->execute([$_GET['request_id'], $_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error marking messages as read: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WhoIsComing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-car"></i>
                    <span>CarShare</span>
                </div>
                <div class="user-menu">
                    <a href="index.php"><i class="fas fa-search"></i> Find Drivers</a>
                    <a href="user_profile.php">
                        <i class="fas fa-user"></i> Profile
                        <span id="notification-badge" class="notification-badge" style="display: none;"></span>
                    </a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <h1 class="dashboard-title">Your Dashboard</h1>
        
        <div class="dashboard-grid">
            <div class="trips-section">
                <?php if (!empty($contactRequests)): ?>
                    <div class="section-header">
                        <h2 class="section-title">Your Contact Requests</h2>
                        <?php if ($totalRequests > 3): ?>
                            <a href="all_chats.php" class="view-all-button">
                                View All (<?php echo $totalRequests; ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($contactRequests as $request): ?>
                        <div class="trip-item">
                            <img src="includes/images/<?= htmlspecialchars($request['driver_photo']) ?>" 
                                 alt="<?= htmlspecialchars($request['driver_name']) ?>" 
                                 class="driver-photo">
                            <div class="trip-info">
                                <div class="trip-driver"><?= htmlspecialchars($request['driver_name']) ?></div>
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
                                <?php if ($request['unread_count'] > 0): ?>
                                    <div class="trip-notification">
                                        <?= $request['unread_count'] ?> new
                                    </div>
                                <?php endif; ?>
                                <div class="trip-actions">
                                    <div class="chat-button-wrapper">
                                        <a href="dashboard.php?driver_id=<?= $request['driver_id'] ?>&request_id=<?= $request['id'] ?>" class="chat-button">
                                            <i class="fas fa-comment"></i> Message Driver
                                        </a>
                                        <?php if ($request['unread_count'] > 0): ?>
                                            <span class="notification-badge"><?= $request['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <div class="section-header">
                    <!-- <h2 class="section-title">Your Past Trips</h2> -->
                </div>
                
                <?php if (empty($pastTrips)): ?>
                    <div class="no-trips">
                        <p>Take your next trip!</p>
                        <p><a href="index.php">Find a driver</a> to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pastTrips as $trip): ?>
                        <div class="trip-item">
                            <img src="includes/images/<?= htmlspecialchars($trip['driver_photo']) ?>" 
                                 alt="<?= htmlspecialchars($trip['driver_name']) ?>" 
                                 class="driver-photo">
                            <div class="trip-info">
                                <div class="trip-driver"><?= htmlspecialchars($trip['driver_name']) ?></div>
                                <div class="trip-details">
                                    <div class="trip-detail">
                                        <i class="fas fa-calendar-day"></i>
                                        <?= date('M j, Y', strtotime($trip['trip_date'])) ?>
                                    </div>
                                    <div class="trip-detail">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= htmlspecialchars($trip['pickup_location']) ?> to <?= htmlspecialchars($trip['destination']) ?>
                                    </div>
                                    <div class="trip-detail">
                                        <i class="fas fa-pound-sign"></i>
                                        £<?= number_format($trip['price'], 2) ?>
                                    </div>
                                </div>
                                <div class="trip-actions">
                                    <a href="dashboard.php?driver_id=<?= $trip['driver_id'] ?>" class="chat-button">
                                        <i class="fas fa-comment"></i> Message Driver
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="chat-section">
                <div class="section-header">
                    <h2 class="section-title">Messages</h2>
                </div>
                
                <div class="chat-container">
                    <?php if ($currentDriver): ?>
                        <div class="chat-header">
                            <img src="includes/images/<?= htmlspecialchars($currentDriver['photo']) ?>" 
                                 alt="<?= htmlspecialchars($currentDriver['name']) ?>" 
                                 class="chat-driver-photo">
                            <div>
                                <div><?= htmlspecialchars($currentDriver['name']) ?></div>
                                <div style="font-size: 12px;"><?= htmlspecialchars($currentDriver['car_model']) ?></div>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div style="text-align: center; color: var(--medium-gray); padding: 20px;">
                                    No messages yet. Start your conversation!
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
                        
                        <form class="chat-input" method="POST" action="dashboard.php">
                            <input type="text" name="message" placeholder="Type your message..." required>
                            <input type="hidden" name="receiver_id" value="<?= $currentDriver['id'] ?>">
                            <input type="hidden" name="contact_request_id" value="<?= $request_id ?>">
                            <input type="hidden" name="action" value="send_message">
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    <?php else: ?>
                        <div class="no-chat-selected">
                            <i class="fas fa-comments"></i>
                            <h3>No chat selected</h3>
                            <p>Select a driver from your trips to start chatting</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="notification-container"></div>

    <script>
        // Function to fetch and update messages
        function fetchMessages() {
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) return;

            const driverId = document.querySelector('input[name="receiver_id"]')?.value;
            const requestId = document.querySelector('input[name="contact_request_id"]')?.value;
            
            if (!driverId || !requestId) return;

            // Create a new XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'dashboard.php?action=get_messages&driver_id=' + driverId + '&request_id=' + requestId, true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success && data.messages) {
                            updateChatMessages(data.messages);
                        }
                    } catch (e) {
                        console.error('Error parsing messages:', e);
                    }
                }
            };
            
            xhr.send();
        }

        function updateChatMessages(messages) {
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) return;

            // Clear existing messages
            chatMessages.innerHTML = '';
            
            if (messages.length === 0) {
                chatMessages.innerHTML = `
                    <div style="text-align: center; color: var(--medium-gray); padding: 20px;">
                        No messages in this conversation.
                    </div>
                `;
                return;
            }

            // Add each message to the chat
            messages.forEach(message => {
                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${message.message_type}`;
                messageDiv.innerHTML = `
                    <div class="message-content">
                        ${message.content}
                    </div>
                    <div class="message-time">
                        ${new Date(message.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                    </div>
                `;
                chatMessages.appendChild(messageDiv);
            });
            scrollToBottom();
        }

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Handle message submission
        document.querySelector('.chat-input')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const messageInput = form.querySelector('input[name="message"]');
            const messageContent = messageInput.value.trim();
            
            if (!messageContent) return;
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Create a new XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'dashboard.php', true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            // Add the new message to chat
                            const chatMessages = document.getElementById('chatMessages');
                            const messageDiv = document.createElement('div');
                            messageDiv.className = 'message sent';
                            messageDiv.innerHTML = `
                                <div class="message-content">
                                    ${messageContent}
                                </div>
                                <div class="message-time">
                                    ${new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}
                                </div>
                            `;
                            
                            // Remove "no messages" message if it exists
                            const noMessagesDiv = chatMessages.querySelector('div[style*="text-align: center"]');
                            if (noMessagesDiv) {
                                chatMessages.removeChild(noMessagesDiv);
                            }
                            
                            chatMessages.appendChild(messageDiv);
                            messageInput.value = '';
                            scrollToBottom();
                        } else {
                            console.error('Error:', data.message);
                            alert('Failed to send message: ' + (data.message || 'Unknown error'));
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
            };
            
            xhr.send(formData);
        });

        // Check for notifications
        function checkNotifications() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'dashboard.php?action=get_notifications', true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            updateNotifications(data.notifications);
                            updateUserInfo(data.user);
                        }
                    } catch (e) {
                        console.error('Error parsing notifications:', e);
                    }
                }
            };
            
            xhr.send();
        }

        function updateUserInfo(user) {
            // Update user name in header
            const profileLink = document.querySelector('.user-menu');
            if (profileLink && user.name) {
                const nameSpan = document.createElement('span');
                nameSpan.textContent = user.name;
                nameSpan.style.marginLeft = '5px';
                const existingName = profileLink.querySelector('span:not(.notification-badge)');
                if (existingName) {
                    existingName.textContent = user.name;
                } else {
                    profileLink.appendChild(nameSpan);
                }
            }

            // Update notification badge
            const badge = document.getElementById('notification-badge');
            if (badge) {
                if (user.total_unread > 0) {
                    badge.textContent = user.total_unread;
                    badge.style.display = 'inline-flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        function updateNotifications(notifications) {
            const container = document.querySelector('.notification-container');
            if (!container) return;

            // Clear existing notifications
            container.innerHTML = '';

            // Add new notifications
            notifications.forEach(notification => {
                const notificationDiv = document.createElement('div');
                notificationDiv.className = 'notification-card';
                notificationDiv.innerHTML = `
                    <img src="includes/images/${notification.photo}" alt="${notification.name}" class="notification-photo">
                    <div class="notification-content">
                        <div class="notification-name">${notification.name}</div>
                        <div class="notification-message">
                            ${notification.unread_count} new message${notification.unread_count > 1 ? 's' : ''}
                        </div>
                    </div>
                    <span class="notification-count">${notification.unread_count}</span>
                `;
                
                // Add click handler to navigate to chat
                notificationDiv.addEventListener('click', () => {
                    window.location.href = `dashboard.php?driver_id=${notification.driver_id}&request_id=${notification.id}`;
                });
                
                container.appendChild(notificationDiv);
            });
        }

        // Initialize chat updates and notifications
        document.addEventListener('DOMContentLoaded', function() {
            // Set up message polling if chat is active
            if (document.getElementById('chatMessages')) {
                // Fetch messages immediately
                fetchMessages();
                // Then set up interval for updates
                setInterval(fetchMessages, 5000); // Check every 5 seconds
            }

            // Set up notification polling
            checkNotifications();
            setInterval(checkNotifications, 10000); // Check every 10 seconds
        });
    </script>
</body>
</html>