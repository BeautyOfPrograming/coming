<?php
ini_set('display_errors', 0);
error_reporting(0);

session_start();
require_once 'includes/config.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit;
}

// Get driver's info from database
try {
    $stmt = $pdo->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->execute([$_SESSION['driver_id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching driver info: " . $e->getMessage());
}

// Get pending contact requests for the driver
$pendingRequests = [];
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
                AND m.is_read = 0) as unread_count,
               (SELECT MAX(created_at)
                FROM messages m
                WHERE m.contact_request_id = cr.id
                AND m.receiver_id = ?
                AND m.sender_type = 'passenger'
                AND m.is_read = 0) as latest_unread
        FROM contact_requests cr
        JOIN registered_users u ON cr.passenger_id = u.id
        WHERE cr.driver_id = ? AND cr.status = 'pending'
        ORDER BY 
            CASE 
                WHEN latest_unread IS NOT NULL THEN 0
                ELSE 1
            END,
            latest_unread DESC,
            cr.created_at DESC
    ");
    $stmt->execute([$_SESSION['driver_id'], $_SESSION['driver_id'], $_SESSION['driver_id']]);
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching pending requests: " . $e->getMessage());
    $pendingRequests = [];
}

// Get active and completed contact requests
$activeRequests = [];
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
        WHERE cr.driver_id = ? AND cr.status IN ('active', 'completed')
        ORDER BY cr.last_activity DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['driver_id'], $_SESSION['driver_id']]);
    $activeRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching active requests: " . $e->getMessage());
}

// Mark messages as read when viewing a conversation
if (isset($_GET['user_id']) && isset($_GET['request_id'])) {
    try {
        $stmt = $pdo->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE contact_request_id = ? 
            AND receiver_id = ? 
            AND sender_type = 'passenger'
            AND is_read = 0
        ");
        $stmt->execute([$_GET['request_id'], $_SESSION['driver_id']]);
    } catch (PDOException $e) {
        error_log("Error marking messages as read: " . $e->getMessage());
    }
}

// Get completed trips
$completedTrips = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u.name as passenger_name, 
               u.photo as passenger_photo,
               (SELECT COUNT(*) 
                FROM messages m 
                WHERE m.contact_request_id = t.contact_request_id 
                AND m.receiver_id = ? 
                AND m.sender_type = 'passenger'
                AND m.is_read = 0) as unread_count
        FROM trips t
        JOIN registered_users u ON t.passenger_id = u.id
        WHERE t.driver_id = ? AND t.status = 'completed'
        ORDER BY t.trip_date DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['driver_id'], $_SESSION['driver_id']]);
    $completedTrips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching completed trips: " . $e->getMessage());
}

// Get messages if a specific user is selected
$messages = [];
$currentUser = null;

if (isset($_GET['user_id']) && isset($_GET['request_id'])) {
    $user_id = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
    $request_id = filter_var($_GET['request_id'], FILTER_VALIDATE_INT);
    if ($user_id && $request_id) {
        // Get the user info
        try {
            $stmt = $pdo->prepare("SELECT id, name, photo FROM registered_users WHERE id = ?");
            $stmt->execute([$user_id]);
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($currentUser) {
                // Get messages for this specific contact request
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

if (isset($_SESSION['user_id'])) {
    $current_user_id = $_SESSION['user_id'];
    $current_user_type = 'passenger';
} elseif (isset($_SESSION['driver_id'])) {
    $current_user_id = $_SESSION['driver_id'];
    $current_user_type = 'driver';
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Session expired. Please log in again.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Dashboard - WhoIsComing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/driver_dashboard.css">
</head>
<body>
    <div class="notification-container"></div>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <i class="fas fa-car"></i>
                    <span>CarShare Driver</span>
                </div>
                <div class="driver-menu">
                    <a href="driver_profile.php" class="menu-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="driver_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <h1 class="dashboard-title">Driver Dashboard</h1>
        
        <!-- <div class="driver-status">
            <div>
                <h3>Online Status</h3>
                <p>Toggle to make yourself available for rides</p>
            </div>
            <label class="status-toggle">
                <input type="checkbox" checked>
                <span class="slider"></span>
            </label>
            <span class="status-label status-active">Available</span>
        </div> -->
        
        <div class="dashboard-grid">
            <div class="trips-section">
                <div class="section-header">
                    <h2 class="section-title">Pending Requests</h2>
                </div>
                
                <?php if (!empty($pendingRequests)): ?>
                    <?php foreach ($pendingRequests as $request): ?>
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
                                </div>
                                <?php if ($request['unread_count'] > 0): ?>
                                    <div class="trip-notification">
                                        <?= $request['unread_count'] ?> new
                                    </div>
                                <?php endif; ?>
                                <div class="trip-actions">
                                    <form method="POST" action="contact_response.php" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                        <button type="submit" name="response" value="accepted" class="chat-button accept-button">
                                            <i class="fas fa-check"></i> Accept
                                        </button>
                                        <button type="submit" name="response" value="rejected" class="chat-button reject-button">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                    <div class="chat-button-wrapper">
                                        <a href="driver_dashboard.php?user_id=<?= $request['passenger_id'] ?>&request_id=<?= $request['id'] ?>" class="chat-button">
                                            <i class="fas fa-comment"></i> Message
                                        </a>
                                        <?php if ($request['unread_count'] > 0): ?>
                                            <span class="notification-badge"><?= $request['unread_count'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-trips">
                        <p>No pending ride requests at this time.</p>
                    </div>
                <?php endif; ?>
                
                <div class="section-header">
                    <h2 class="section-title">Your Recent Trips</h2>
                </div>
                
                <?php
                // Get non-pending contact requests
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
                        LIMIT 2
                    ");
                    $stmt->execute([$_SESSION['driver_id'], $_SESSION['driver_id']]);
                    $pastRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    // Get total count for pagination
                    $countStmt = $pdo->prepare("
                        SELECT COUNT(*) as total
                        FROM contact_requests cr
                        WHERE cr.driver_id = ? AND cr.status IN ('accepted', 'rejected', 'cancelled')
                    ");
                    $countStmt->execute([$_SESSION['driver_id']]);
                    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                } catch (PDOException $e) {
                    error_log("Error fetching past requests: " . $e->getMessage());
                }
                
                if (empty($pastRequests)): ?>
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
                                        <a href="driver_dashboard.php?user_id=<?= $request['passenger_id'] ?>&request_id=<?= $request['id'] ?>&is_past=true" class="chat-button">
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

                    <?php if ($totalCount > 2): ?>
                        <div class="view-more-container">
                            <a href="past_trips.php" class="view-more-button">
                                <i class="fas fa-chevron-right"></i> View All Past Trips (<?= $totalCount ?>)
                            </a>
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
                                <?php if (isset($_GET['is_past'])): ?>
                                    <div style="font-size: 12px; color: #666;">(Past Request - Chat History Only)</div>
                                <?php endif; ?>
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
                        
                        <?php if (!isset($_GET['is_past'])): ?>
                        <form class="chat-input" id="messageForm">
                            <input type="text" name="message" placeholder="Type your message..." required>
                                <input type="hidden" name="receiver_id" value="<?= $currentUser['id'] ?>">
                                <input type="hidden" name="receiver_type" value="passenger">
                                <input type="hidden" name="sender_type" value="driver">
                                <input type="hidden" name="contact_request_id" value="<?= $_GET['request_id'] ?>">
                            <button type="submit"><i class="fas fa-paper-plane"></i></button>
                        </form>
                        <?php else: ?>
                            <div class="chat-input" style="justify-content: center; background-color: #f5f5f5;">
                                <span style="color: #666;">Chat is disabled for past requests</span>
                            </div>
                        <?php endif; ?>
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

        <!-- Add new Advertisements Section -->
        <div class="advertisements-section">
            <div class="section-header">
                <h2 class="section-title">My Advertisements</h2>
                <button class="add-ad-button" onclick="showAdForm()">
                    <i class="fas fa-plus"></i> New Advertisement
                </button>
            </div>

            <div class="advertisements-list">
                <?php
                // Fetch driver's advertisements
                try {
                    $stmt = $pdo->prepare("
                        SELECT * FROM advertisements 
                        WHERE owner_id = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$_SESSION['driver_id']]);
                    $advertisements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($advertisements)) {
                        echo '<div class="no-ads">
                            <p>You haven\'t created any advertisements yet.</p>
                            <button class="add-ad-button" onclick="showAdForm()">
                                <i class="fas fa-plus"></i> Create Your First Advertisement
                            </button>
                        </div>';
                    } else {
                        foreach ($advertisements as $ad) {
                            echo '<div class="ad-item">
                                <div class="ad-header">
                                    <h3>' . htmlspecialchars($ad['car_model']) . ' (' . $ad['car_year'] . ')</h3>
                                    <div class="ad-status ' . ($ad['is_active'] ? 'active' : 'inactive') . '">
                                        ' . ($ad['is_active'] ? 'Active' : 'Inactive') . '
                                    </div>
                                </div>
                                <div class="ad-details">
                                    <div class="ad-location">
                                        <i class="fas fa-map-marker-alt"></i>
                                        ' . htmlspecialchars($ad['pickup_location']) . ' → ' . htmlspecialchars($ad['destination']) . '
                                    </div>
                                    <div class="ad-dates">
                                        <i class="fas fa-calendar-alt"></i>
                                        ' . date('M j, Y', strtotime($ad['available_from'])) . ' - ' . date('M j, Y', strtotime($ad['available_to'])) . '
                                    </div>
                                    <div class="ad-price">
                                        <i class="fas fa-pound-sign"></i>
                                        £' . number_format($ad['price_per_day'], 2) . ' per day
                                    </div>
                                </div>
                                <div class="ad-actions">
                                    <button class="edit-ad" onclick="editAd(' . $ad['id'] . ')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="toggle-ad" onclick="toggleAd(' . $ad['id'] . ', ' . ($ad['is_active'] ? '0' : '1') . ')">
                                        <i class="fas fa-power-off"></i> ' . ($ad['is_active'] ? 'Deactivate' : 'Activate') . '
                                    </button>
                                    <button class="delete-ad" onclick="deleteAd(' . $ad['id'] . ')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>';
                        }
                    }
                } catch (PDOException $e) {
                    error_log("Error fetching advertisements: " . $e->getMessage());
                    echo '<div class="error">Error loading advertisements. Please try again later.</div>';
                }
                ?>
            </div>
        </div>

        <!-- Advertisement Form Modal -->
        <div id="adFormModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>New Advertisement</h2>
                    <span class="close" onclick="closeAdForm()">&times;</span>
                </div>
                <form id="advertisementForm" onsubmit="return handleAdSubmit(event)">
                    <input type="hidden" id="ad_id" name="ad_id">
                    
                    <div class="form-group">
                        <label for="car_model">Car Model</label>
                        <input type="text" id="car_model" name="car_model" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_year">Car Year</label>
                        <input type="number" id="car_year" name="car_year" min="2000" max="2025" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_photo">Car Photo URL</label>
                        <input type="url" id="car_photo" name="car_photo">
                    </div>
                    
                    <div class="form-group">
                        <label for="pickup_location">Pickup Location</label>
                        <input type="text" id="pickup_location" name="pickup_location" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="destination">Destination</label>
                        <input type="text" id="destination" name="destination" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="available_from">Available From</label>
                        <input type="datetime-local" id="available_from" name="available_from" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="available_to">Available To</label>
                        <input type="datetime-local" id="available_to" name="available_to" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="price_per_day">Price Per Day (£)</label>
                        <input type="number" id="price_per_day" name="price_per_day" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_features">Car Features (comma separated)</label>
                        <input type="text" id="car_features" name="car_features" placeholder="e.g., Air Conditioning, GPS, Bluetooth">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="submit-button">Save Advertisement</button>
                        <button type="button" class="cancel-button" onclick="closeAdForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Function to fetch and update messages
        function fetchMessages() {
            console.log('Fetching messages...');
            const chatMessages = document.getElementById('chatMessages');
            if (!chatMessages) {
                console.log('Chat messages container not found');
                return;
            }

            const receiverId = document.querySelector('input[name="receiver_id"]')?.value;
            const requestId = document.querySelector('input[name="contact_request_id"]')?.value;
            
            if (!receiverId || !requestId) {
                console.log('Missing receiverId or requestId:', { receiverId, requestId });
                return;
            }

            console.log('Fetching messages for:', { receiverId, requestId });

            fetch(`get_messages.php?user_id=${receiverId}&request_id=${requestId}`)
                .then(response => {
                    console.log('Message response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Message data received:', data);
                    if (data.success && data.messages) {
                        // Clear existing messages
                        chatMessages.innerHTML = '';
                        
                        if (data.messages.length === 0) {
                            chatMessages.innerHTML = `
                                <div style="text-align: center; color: var(--medium-gray); padding: 20px;">
                                    No messages in this conversation.
                                </div>
                            `;
                            return;
                        }

                        // Add each message to the chat
                        data.messages.forEach(message => {
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
                    } else {
                        console.error('Error in message data:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error fetching messages:', error);
                });
        }

        // Auto-scroll to bottom of chat
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Handle message submission
        document.getElementById('messageForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const messageInput = form.querySelector('input[name="message"]');
            const messageContent = messageInput.value.trim();
            
            if (!messageContent) {
                return;
            }
            
            const formData = {
                message: messageContent,
                receiver_id: form.querySelector('input[name="receiver_id"]').value,
                receiver_type: form.querySelector('input[name="receiver_type"]').value,
                sender_type: 'driver',
                contact_request_id: form.querySelector('input[name="contact_request_id"]').value
            };
            
            fetch('send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Add the new message immediately to the chat
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
                    
                    // Fetch messages to ensure we have the latest state
                    fetchMessages();
                } else {
                    alert('Error: ' + (data.message || 'Failed to send message'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending your message');
            });
        });

        // Set up periodic message fetching
        let messageInterval;
        window.addEventListener('load', () => {
            console.log('Setting up message interval...');
            scrollToBottom();
            // Initial fetch of messages
            fetchMessages();
            // Set up periodic refresh every 5 seconds
            messageInterval = setInterval(fetchMessages, 5000);
        });

        // Clean up intervals when leaving the page
        window.addEventListener('beforeunload', () => {
            console.log('Cleaning up intervals...');
            if (messageInterval) {
                clearInterval(messageInterval);
            }
        });

        function checkNotifications() {
            console.log('Checking notifications...');
            fetch('includes/check_notifications.php')
                .then(response => {
                    console.log('Notification response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Notification data received:', data);
                    if (data.success) {
                        if (data.debug) {
                            console.log('Debug info:', {
                                total_unread: data.debug.total_unread,
                                notification_count: data.debug.notification_count,
                                raw_messages: data.debug.raw_messages
                            });
                        }
                        if (data.notifications && data.notifications.length > 0) {
                            updateNotifications(data.notifications);
                        } else {
                            console.log('No new notifications to display');
                        }
                    } else {
                        console.error('Error in notifications:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error checking notifications:', error);
                });
        }

        function updateNotifications(notifications) {
            console.log('Updating notifications:', notifications);
            const container = document.querySelector('.notification-container');
            if (!container) {
                console.log('Notification container not found');
                return;
            }

            // Clear existing notifications
            container.innerHTML = '';

            if (!notifications || notifications.length === 0) {
                console.log('No new notifications');
                return;
            }

            notifications.forEach(notification => {
                console.log('Creating notification for:', notification);
                const card = document.createElement('div');
                card.className = 'notification-card';
                card.onclick = () => {
                    window.location.href = `driver_dashboard.php?user_id=${notification.user_id}&request_id=${notification.request_id}`;
                };
                
                card.innerHTML = `
                    <img src="includes/images/${notification.user_photo}" 
                         alt="${notification.user_name}" 
                         class="notification-photo">
                    <div class="notification-content">
                        <div class="notification-name">${notification.user_name}</div>
                        <div class="notification-message">New message</div>
                    </div>
                    <span class="notification-count">${notification.unread_count}</span>
                `;
                
                container.appendChild(card);

                // Add animation class
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateX(0)';
                }, 100);
            });

            // Update unread badges in the main chat list
            notifications.forEach(notification => {
                const chatButtons = document.querySelectorAll('.chat-button-wrapper');
                chatButtons.forEach(button => {
                    const buttonUrl = button.querySelector('a').getAttribute('href');
                    if (buttonUrl.includes(`user_id=${notification.user_id}`) && 
                        buttonUrl.includes(`request_id=${notification.request_id}`)) {
                        let badge = button.querySelector('.notification-badge');
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'notification-badge';
                            button.appendChild(badge);
                        }
                        badge.textContent = notification.unread_count;
                    }
                });
            });
        }

        // Add CSS for notification animations
        const style = document.createElement('style');
        style.textContent = `
            .notification-card {
                opacity: 0;
                transform: translateX(100%);
                transition: all 0.3s ease-out;
            }
            
            .notification-container {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .notification-card {
                background: white;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                padding: 15px;
                width: 300px;
                display: flex;
                align-items: center;
                cursor: pointer;
            }
            
            .notification-photo {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                margin-right: 10px;
                object-fit: cover;
            }
            
            .notification-content {
                flex: 1;
            }
            
            .notification-name {
                font-weight: 600;
                margin-bottom: 5px;
            }
            
            .notification-message {
                color: #666;
                font-size: 14px;
            }
            
            .notification-count {
                background: #ea4335;
                color: white;
                border-radius: 50%;
                padding: 2px 6px;
                font-size: 12px;
                margin-left: 10px;
            }
        `;
        document.head.appendChild(style);

        // Set up notification checking
        let notificationInterval;
        window.addEventListener('load', () => {
            console.log('Setting up notification interval...');
            // Initial check
            checkNotifications();
            // Set up periodic check every 5 seconds
            notificationInterval = setInterval(checkNotifications, 5000);
        });

        // Clean up interval when leaving the page
        window.addEventListener('beforeunload', () => {
            console.log('Cleaning up intervals...');
            if (notificationInterval) {
                clearInterval(notificationInterval);
            }
        });

        // Handle status toggle
        const statusToggle = document.querySelector('.status-toggle input');
        const statusLabel = document.querySelector('.status-label');
        
        statusToggle?.addEventListener('change', function() {
            if (this.checked) {
                statusLabel.textContent = 'Available';
                statusLabel.classList.remove('status-inactive');
                statusLabel.classList.add('status-active');
                
                // Update status in database
                fetch('update_driver_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: 'available' })
                });
            } else {
                statusLabel.textContent = 'Offline';
                statusLabel.classList.remove('status-active');
                statusLabel.classList.add('status-inactive');
                
                // Update status in database
                fetch('update_driver_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ status: 'offline' })
                });
            }
        });

        // Advertisement management functions
        function showAdForm(adId = null) {
            const modal = document.getElementById('adFormModal');
            const form = document.getElementById('advertisementForm');
            const modalTitle = document.querySelector('.modal-header h2');
            
            if (adId) {
                // Show loading state
                modalTitle.textContent = 'Loading...';
                form.reset();
                
                // Load existing ad data
                fetch(`includes/advertisements/api/get.php?id=${adId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success && data.advertisement) {
                            const ad = data.advertisement;
                            modalTitle.textContent = 'Edit Advertisement';
                            document.getElementById('ad_id').value = ad.id;
                            document.getElementById('car_model').value = ad.car_model;
                            document.getElementById('car_year').value = ad.car_year;
                            document.getElementById('car_photo').value = ad.car_photo || '';
                            document.getElementById('pickup_location').value = ad.pickup_location;
                            document.getElementById('destination').value = ad.destination;
                            
                            // Format dates for datetime-local input
                            const formatDate = (dateStr) => {
                                const date = new Date(dateStr);
                                return date.toISOString().slice(0, 16);
                            };
                            
                            document.getElementById('available_from').value = formatDate(ad.available_from);
                            document.getElementById('available_to').value = formatDate(ad.available_to);
                            
                            document.getElementById('price_per_day').value = ad.price_per_day;
                            document.getElementById('description').value = ad.description || '';
                            document.getElementById('car_features').value = ad.car_features || '';
                        } else {
                            throw new Error(data.error || 'Failed to load advertisement');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error: ' + error.message);
                        closeAdForm();
                    });
            } else {
                // Reset form for new ad
                modalTitle.textContent = 'New Advertisement';
                form.reset();
                document.getElementById('ad_id').value = '';
            }
            
            modal.style.display = 'block';
        }

        function closeAdForm() {
            document.getElementById('adFormModal').style.display = 'none';
        }

        function handleAdSubmit(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            // Add owner_id to the data
            data.owner_id = <?php echo $_SESSION['driver_id']; ?>;
            
            // Convert datetime-local inputs to proper format
            const formatDateTime = (dateStr) => {
                const date = new Date(dateStr);
                return date.toISOString().slice(0, 19).replace('T', ' ');
            };
            
            data.available_from = formatDateTime(data.available_from);
            data.available_to = formatDateTime(data.available_to);
            
            // Determine if this is an update or create operation
            const isUpdate = data.ad_id && data.ad_id !== '';
            const endpoint = isUpdate ? 'includes/advertisements/api/update.php' : 'includes/advertisements/api/create.php';
            
            // For update, ensure id is included
            if (isUpdate) {
                data.id = data.ad_id;
            }
            
            fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeAdForm();
                    location.reload(); // Refresh page to show updated ads
                } else {
                    throw new Error(data.error || 'Failed to save advertisement');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error: ' + error.message);
            });
            
            return false;
        }

        function editAd(adId) {
            showAdForm(adId);
        }

        function toggleAd(adId, newStatus) {
            if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this advertisement?')) {
                fetch('includes/advertisements/api/toggle.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        id: adId,
                        is_active: newStatus
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        location.reload(); // Refresh page to show updated status
                    } else {
                        throw new Error(data.error || 'Failed to update advertisement status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            }
        }

        function deleteAd(adId) {
            if (confirm('Are you sure you want to delete this advertisement? This action cannot be undone.')) {
                fetch('includes/advertisements/api/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: adId })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        location.reload(); // Refresh page to show updated list
                    } else {
                        throw new Error(data.error || 'Failed to delete advertisement');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>