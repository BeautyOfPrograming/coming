<?php
session_start();
require_once 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user information
try {
    $stmt = $pdo->prepare("
        SELECT id, name, photo, username, registration_datetime, created_at
        FROM registered_users
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User profile not found");
    }
} catch (Exception $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    $error = "Error loading profile. Please try again later.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    try {
        // Handle photo upload
        $photo = $user['photo']; // Keep existing photo by default
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'includes/images/';
            $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                    // Delete old photo if it exists and is not the default
                    if (!empty($user['photo']) && $user['photo'] !== 'default.jpg') {
                        $oldPhotoPath = $uploadDir . $user['photo'];
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    $photo = $newFileName;
                }
            }
        }
        
        // Update user table
        $stmt = $pdo->prepare("
            UPDATE registered_users 
            SET name = ?, 
                photo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name,
            $photo,
            $_SESSION['user_id']
        ]);
        
        $success = "Profile updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("
            SELECT id, name, photo, username, registration_datetime, created_at
            FROM registered_users
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error updating user profile: " . $e->getMessage());
        $error = "Error updating profile. Please try again.";
    }
}

// Get total trips count from contact_requests
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_trips
        FROM contact_requests
        WHERE passenger_id = ? AND status IN ('completed', 'accepted')
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tripStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTrips = $tripStats['total_trips'] ?? 0;
} catch (Exception $e) {
    error_log("Error fetching trip stats: " . $e->getMessage());
    $totalTrips = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - WhoIsComing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/user_profile.css">
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
                    <a href="dashboard.php" class="back-button">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($user)): ?>
            <div class="profile-container">
                <div class="profile-sidebar">
                    <img src="includes/images/<?= htmlspecialchars($user['photo']) ?>" 
                         alt="<?= htmlspecialchars($user['name']) ?>" 
                         class="profile-photo">
                    <h2 class="profile-name"><?= htmlspecialchars($user['name']) ?></h2>
                    <div class="profile-email"><?= htmlspecialchars($user['username']) ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $totalTrips ?></div>
                            <div class="stat-label">Total Trips</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= date('d M Y', strtotime($user['created_at'])) ?></div>
                            <div class="stat-label">Member Since</div>
                        </div>
                    </div>
                </div>
                
                <div class="profile-form">
                    <h2 class="form-title">Edit Profile</h2>
                    
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" 
                                   name="name" 
                                   class="form-input" 
                                   value="<?= htmlspecialchars($user['name']) ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Profile Photo</label>
                            <input type="file" 
                                   name="photo" 
                                   class="form-input" 
                                   accept="image/*">
                            <small class="form-text">Leave empty to keep current photo</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Registration Date</label>
                            <input type="text" 
                                   class="form-input" 
                                   value="<?= date('F j, Y', strtotime($user['registration_datetime'])) ?>" 
                                   disabled>
                        </div>
                        
                        <button type="submit" class="submit-button">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                Unable to load profile information. Please try again later.
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 