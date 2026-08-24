<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter settings
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query conditions
$conditions = ["cr.passenger_id = :passenger_id"]; 
$params = [':passenger_id' => $_SESSION['user_id']];

if ($status_filter) {
    $conditions[] = "cr.status = :status";
    $params[':status'] = $status_filter;
}

if ($search) {
    $conditions[] = "(d.name LIKE :search OR cr.pickup_location LIKE :search OR cr.destination LIKE :search)";
    $search_param = "%$search%";
    $params[':search'] = $search_param;
}

$where_clause = implode(" AND ", $conditions);

// Get total count for pagination
try {
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM contact_requests cr
        JOIN drivers d ON cr.driver_id = d.id
        WHERE $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total_rows = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_rows / $per_page);

    // Debug output
    error_log("Total rows found: " . $total_rows);
} catch (PDOException $e) {
    error_log("Error getting total count: " . $e->getMessage());
    $total_pages = 0;
}

// Get contact requests with a simpler query first
try {
    $sql = "
        SELECT 
            cr.*,
            d.name as driver_name,
            d.photo as driver_photo,
            d.car_model,
            (
                SELECT COUNT(*) 
                FROM messages m 
                WHERE m.contact_request_id = cr.id 
                AND m.receiver_id = :user_id 
                AND m.sender_type = 'driver'
                AND m.is_read = 0
            ) as unread_count,
            (
                SELECT MAX(created_at)
                FROM messages m
                WHERE m.contact_request_id = cr.id
            ) as last_message_time,
            (
                SELECT content 
                FROM messages m 
                WHERE m.contact_request_id = cr.id 
                ORDER BY created_at DESC 
                LIMIT 1
            ) as last_message
        FROM contact_requests cr
        JOIN drivers d ON cr.driver_id = d.id
        WHERE $where_clause
        ORDER BY cr.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $contactRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Debug output
    error_log("Number of requests found: " . count($contactRequests));
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    error_log("User ID: " . $_SESSION['user_id']);
} catch (PDOException $e) {
    error_log("Error fetching contact requests: " . $e->getMessage());
    error_log("SQL Query: " . $sql);
    error_log("Parameters: " . print_r($params, true));
    $contactRequests = [];
}

// Add debugging output at the top of the page
echo "<!--\n";
echo "Debug Info:\n";
echo "Total Rows: " . $total_rows . "\n";
echo "Contact Requests Found: " . count($contactRequests) . "\n";
echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "-->\n";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Chats - CarShare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/allchats.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Back to Dashboard
                </a>
                <h1>All Chats</h1>
                <div></div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <form class="filters" method="GET">
            <input type="text" 
                   name="search" 
                   placeholder="Search drivers, locations..." 
                   class="filter-input"
                   value="<?= htmlspecialchars($search) ?>">
            
            <select name="status" class="filter-select">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="accepted" <?= $status_filter === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
            
            <button type="submit" class="filter-input">Apply Filters</button>
        </form>
        
        <div class="chat-list">
            <?php if (empty($contactRequests)): ?>
                <div style="text-align: center; padding: 40px;">
                    <p>No chat requests found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($contactRequests as $request): ?>
                    <a href="dashboard.php?driver_id=<?= $request['driver_id'] ?>&request_id=<?= $request['id'] ?>" 
                       class="chat-item">
                        <img src="includes/images/<?= htmlspecialchars($request['driver_photo']) ?>" 
                             alt="<?= htmlspecialchars($request['driver_name']) ?>" 
                             class="driver-photo">
                        <div class="chat-info">
                            <div class="chat-header">
                                <div class="driver-name">
                                    <?= htmlspecialchars($request['driver_name']) ?>
                                    <?php if ($request['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $request['unread_count'] ?> new</span>
                                    <?php endif; ?>
                                </div>
                                <div class="chat-time">
                                    <?= $request['last_message_time'] ? date('M j, Y g:i A', strtotime($request['last_message_time'])) : date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                                </div>
                            </div>
                            <div class="chat-preview">
                                <?php if ($request['last_message']): ?>
                                    <div class="last-message"><?= htmlspecialchars(substr($request['last_message'], 0, 50)) . (strlen($request['last_message']) > 50 ? '...' : '') ?></div>
                                <?php endif; ?>
                                <div class="trip-details">
                                    Status: <?= ucfirst($request['status']) ?>
                                    • <?= htmlspecialchars($request['pickup_location']) ?> to <?= htmlspecialchars($request['destination']) ?>
                                </div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" 
                       class="page-link <?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page+1 ?>&status=<?= $status_filter ?>&search=<?= urlencode($search) ?>" 
                       class="page-link">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 