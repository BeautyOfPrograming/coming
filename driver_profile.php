<?php
session_start();
require_once 'includes/config.php';

// Check if driver is logged in
if (!isset($_SESSION['driver_id'])) {
    header("Location: driver_login.php");
    exit;
}

// Get driver information
try {
    // Get basic driver info
    $stmt = $pdo->prepare("
        SELECT *
        FROM drivers
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['driver_id']]);
    $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$driver) {
        throw new Exception("Driver profile not found");
    }

    // Get total trips count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_trips
        FROM contact_requests
        WHERE driver_id = ? AND status IN ('completed', 'accepted')
    ");
    $stmt->execute([$_SESSION['driver_id']]);
    $tripStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTrips = $tripStats['total_trips'] ?? 0;

} catch (Exception $e) {
    error_log("Error fetching driver profile: " . $e->getMessage());
    $error = "Error loading profile. Please try again later.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $car_model = trim($_POST['car_model'] ?? '');
    $car_year = trim($_POST['car_year'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $car_plate = trim($_POST['car_plate'] ?? '');
    $car_color = trim($_POST['car_color'] ?? '');
    $car_features = trim($_POST['car_features'] ?? '');
    
    try {
        // Handle photo upload
        $photo = $driver['photo']; // Keep existing photo by default
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'includes/images/';
            $fileExtension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $newFileName = uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                    // Delete old photo if it exists and is not the default
                    if (!empty($driver['photo']) && $driver['photo'] !== 'default.jpg') {
                        $oldPhotoPath = $uploadDir . $driver['photo'];
                        if (file_exists($oldPhotoPath)) {
                            unlink($oldPhotoPath);
                        }
                    }
                    $photo = $newFileName;
                }
            }
        }
        
        // Update drivers table
        $stmt = $pdo->prepare("
            UPDATE drivers 
            SET name = ?, 
                email = ?, 
                phone = ?, 
                car_model = ?, 
                car_year = ?, 
                license_number = ?,
                car_plate = ?,
                car_color = ?,
                car_features = ?,
                photo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $name, 
            $email, 
            $phone, 
            $car_model, 
            $car_year, 
            $license_number,
            $car_plate,
            $car_color,
            $car_features,
            $photo,
            $_SESSION['driver_id']
        ]);
        
        $success = "Profile updated successfully!";
        
        // Refresh driver data
        $stmt = $pdo->prepare("
            SELECT *
            FROM drivers
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['driver_id']]);
        $driver = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error updating driver profile: " . $e->getMessage());
        $error = "Error updating profile. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Profile - CarShare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/driver_profile.css">
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
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($driver)): ?>
            <div class="profile-container">
                <div class="profile-sidebar">
                    <img src="includes/images/<?= htmlspecialchars($driver['photo']) ?>" 
                         alt="<?= htmlspecialchars($driver['name']) ?>" 
                         class="profile-photo">
                    <h2 class="profile-name"><?= htmlspecialchars($driver['name']) ?></h2>
                    <div class="profile-email"><?= htmlspecialchars($driver['email']) ?></div>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $totalTrips ?></div>
                            <div class="stat-label">Total Trips</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $driver['rating'] ?? '0.0' ?></div>
                            <div class="stat-label">Rating</div>
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
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Profile Photo</label>
                                <input type="file" 
                                       name="photo" 
                                       class="form-input" 
                                       accept="image/*">
                                <small class="form-text">Leave empty to keep current photo</small>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Full Name</label>
                                <input type="text" 
                                       name="name" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['name']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" 
                                       name="email" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['email']) ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['phone']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Car Model</label>
                                <input type="text" 
                                       name="car_model" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['car_model']) ?>" 
                                       required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Car Year</label>
                                <input type="number" 
                                       name="car_year" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['car_year']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">License Number</label>
                                <input type="text" 
                                       name="license_number" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['license_number']) ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Car Plate Number</label>
                                <input type="text" 
                                       name="car_plate" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['car_plate']) ?>" 
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Car Color</label>
                                <input type="text" 
                                       name="car_color" 
                                       class="form-input" 
                                       value="<?= htmlspecialchars($driver['car_color']) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Car Features</label>
                            <textarea name="car_features" 
                                      class="form-input" 
                                      rows="3"><?= htmlspecialchars($driver['car_features']) ?></textarea>
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