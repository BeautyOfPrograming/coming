<?php
session_start();
require_once 'includes/config.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Redirect if already logged in
if (isset($_SESSION['driver_id'])) {
    header("Location: driver_dashboard.php");
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_driver_" . $ip;
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key . '_time']) ? $_SESSION[$rate_limit_key . '_time'] : 0;

if ($rate_limit_count >= 5 && (time() - $rate_limit_time) < 300) {
    $_SESSION['error'] = "Too many registration attempts. Please try again in 5 minutes.";
    header("Location: driver_register.php");
    exit;
}

// Function to validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    

    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

// Function to validate phone number
function validatePhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if the phone number has a valid length (adjust based on your requirements)
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        return false;
    }
    
    return true;
}

// Function to validate license plate
function validateLicensePlate($plate) {
    // Remove any spaces
    $plate = str_replace(' ', '', $plate);
    
    // Check if the plate is more than 4 characters
    if (strlen($plate) <= 4) {
        return false;
    }
    
    return true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("location: driver_register.php");
        exit;
    }

    // Validate and sanitize inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $license_number = filter_input(INPUT_POST, 'license_number', FILTER_SANITIZE_STRING);
    $car_model = filter_input(INPUT_POST, 'car_model', FILTER_SANITIZE_STRING);
    $car_year = filter_input(INPUT_POST, 'car_year', FILTER_VALIDATE_INT);
    $car_plate = filter_input(INPUT_POST, 'car_plate', FILTER_SANITIZE_STRING);

    // Validate inputs
    $errors = [];
    
    // Name validation
    if (empty($name) || strlen($name) < 2 || strlen($name) > 50) {
        $errors[] = "Full name must be between 2 and 50 characters";
    }
    
    // Username validation
    if (empty($username) || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3-20 characters (letters, numbers, underscores)";
    }
    
    // Email validation
    if (!$email) {
        $errors[] = "Valid email is required";
    }
    
    // Phone validation
    if (empty($phone) || !validatePhoneNumber($phone)) {
        $errors[] = "Valid phone number is required";
    }
    
    // Password validation
    $password_errors = validatePasswordStrength($password);
    if (!empty($password_errors)) {
        $errors = array_merge($errors, $password_errors);
    }
    
    // Confirm password
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // License number validation
    if (empty($license_number) || strlen($license_number) < 5) {
        $errors[] = "Valid driver license number is required";
    }
    
    // Car model validation
    if (empty($car_model) || strlen($car_model) < 2) {
        $errors[] = "Valid car model is required";
    }
    
    // Car year validation
    if (!$car_year || $car_year < 2000 || $car_year > date('Y')) {
        $errors[] = "Valid car year is required (2000-" . date('Y') . ")";
    }
    
    // License plate validation
    if (empty($car_plate)) {
        $errors[] = "License plate is required";
    } elseif (!validateLicensePlate($car_plate)) {
        $errors[] = "License plate must be more than 4 characters";
    }

    // Handle file upload (driver photo)
    $photo_path = 'includes/images/drivers/default.jpg';
    if (empty($errors) && isset($_FILES['photo']) && $_FILES['photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = 'includes/images/drivers/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES['photo']['tmp_name']);
        
        if (!in_array($mime_type, $allowed_types)) {
            $errors[] = "Only JPG, PNG, and GIF images are allowed";
        } elseif ($_FILES['photo']['size'] > $max_file_size) {
            $errors[] = "File size must be less than 5MB";
        } else {
            $file_ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('driver_', true) . '.' . $file_ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo_path = 'includes/images/drivers/' . $filename;
            } else {
                $errors[] = "Failed to upload photo";
            }
        }
    }

    // If no errors, create driver account
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Log the data being inserted (excluding password)
            error_log("Attempting to insert driver with data: " . print_r([
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'license_number' => $license_number,
                'car_model' => $car_model,
                'car_year' => $car_year,
                'car_plate' => $car_plate,
                'photo' => $photo_path
            ], true));
            
            // First check if username or email already exists
            $check_stmt = $pdo->prepare("SELECT id FROM drivers WHERE username = :username OR email = :email");
            $check_stmt->bindParam(":username", $username);
            $check_stmt->bindParam(":email", $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists";
            } else {
                $stmt = $pdo->prepare("INSERT INTO drivers (
                    name, username, email, phone, password, 
                    license_number, car_model, car_year, car_plate, 
                    photo, is_verified, is_active, created_at, updated_at
                ) VALUES (
                    :name, :username, :email, :phone, :password,
                    :license_number, :car_model, :car_year, :car_plate,
                    :photo, 0, 1, NOW(), NOW()
                )");
                
                $stmt->bindParam(":name", $name);
                $stmt->bindParam(":username", $username);
                $stmt->bindParam(":email", $email);
                $stmt->bindParam(":phone", $phone);
                $stmt->bindParam(":password", $hashed_password);
                $stmt->bindParam(":license_number", $license_number);
                $stmt->bindParam(":car_model", $car_model);
                $stmt->bindParam(":car_year", $car_year);
                $stmt->bindParam(":car_plate", $car_plate);
                $stmt->bindParam(":photo", $photo_path);
                
                if ($stmt->execute()) {
                    // Update rate limiting
                    $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
                    $_SESSION[$rate_limit_key . '_time'] = time();
                    
                    $_SESSION['success'] = "Registration successful! Please login.";
                    header("Location: driver_login.php");
                    exit;
                } else {
                    $errorInfo = $stmt->errorInfo();
                    error_log("Database error details: " . print_r($errorInfo, true));
                    $errors[] = "Database error: " . $errorInfo[2];
                }
            }
        } catch (PDOException $e) {
            error_log("Database exception: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Error Code: " . $e->errorInfo[1]);
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // Store form data and errors in session to display after redirect
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header("Location: driver_register.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register as a driver with WhoIsComing - Join our trusted network of drivers. Earn money by sharing your car. Quick registration process with secure verification.">
    <meta name="keywords" content="driver registration, car sharing, become a driver, driver signup, car rental driver">
    <meta name="robots" content="index, follow">
    <meta name="author" content="WhoIsComing">
    <meta property="og:title" content="Become a Driver - WhoIsComing Car Sharing Platform">
    <meta property="og:description" content="Join WhoIsComing as a driver and start earning. Register your car, set your schedule, and connect with passengers.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yourdomain.com/driver_register.php">
    <meta property="og:image" content="https://yourdomain.com/images/driver-registration.jpg">
    <title>Become a Driver - Join WhoIsComing Car Sharing Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/driver_register.css">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "name": "Driver Registration - WhoIsComing",
      "description": "Register as a driver with WhoIsComing - Join our trusted network of drivers. Earn money by sharing your car.",
      "publisher": {
        "@type": "Organization",
        "name": "WhoIsComing",
        "logo": {
          "@type": "ImageObject",
          "url": "https://yourdomain.com/images/logo.png"
        }
      },
      "mainEntity": {
        "@type": "Form",
        "name": "Driver Registration Form",
        "description": "Register to become a driver with WhoIsComing"
      }
    }
    </script>
</head>
<body>
    <header>
        <div class="header-content">
            <a href="index.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Home
            </a>
            <h1>Driver Registration</h1>
            <div></div>
        </div>
    </header>

    <div class="container">
        <div class="register-card">
            <div class="card-header">
                <h2>Become a Driver</h2>
                <p>Join our network of trusted drivers and start earning</p>
            </div>
            
            <div class="card-body">
                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="error-container">
                        <?php foreach ($_SESSION['errors'] as $error): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php unset($_SESSION['errors']); ?>
                <?php endif; ?>
                
                <form action="driver_register.php" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name" class="required-field">Full Name</label>
                            <input type="text" name="name" id="name" required
                                   placeholder="Enter your full name"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="username" class="required-field">Username</label>
                            <input type="text" name="username" id="username" required
                                   pattern="^[a-zA-Z0-9_]{3,20}$"
                                   placeholder="Choose a username (3-20 characters)"
                                   title="3-20 characters (letters, numbers, underscores)"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['username'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email" class="required-field">Email</label>
                            <input type="email" name="email" id="email" required
                                   placeholder="Enter your email address"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['email'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="required-field">Phone Number</label>
                            <input type="tel" name="phone" id="phone" required
                                   placeholder="Enter your phone number"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['phone'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password" class="required-field">Password</label>
                            <input type="password" name="password" id="password" required 
                                   minlength="8" placeholder="Minimum 8 characters">
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="required-field">Confirm Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   required minlength="8" placeholder="Repeat your password">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="license_number" class="required-field">Driver License Number</label>
                            <input type="text" name="license_number" id="license_number" required
                                   placeholder="Enter your license number"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['license_number'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="required-field">Driver Photo</label>
                            <div class="photo-upload">
                                <img src="includes/images/drivers/default.jpg" alt="Preview" class="photo-preview" id="photoPreview">
                                <div>
                                    <label for="photo" class="upload-btn">
                                        <i class="fas fa-camera"></i> Choose Photo
                                    </label>
                                    <input type="file" name="photo" id="photo" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="car_model" class="required-field">Car Model</label>
                            <input type="text" name="car_model" id="car_model" required
                                   placeholder="Enter your car model"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['car_model'] ?? '') ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="car_year" class="required-field">Car Year</label>
                            <input type="number" name="car_year" id="car_year" required 
                                   min="2000" max="<?= date('Y') ?>"
                                   placeholder="Enter your car year"
                                   value="<?= htmlspecialchars($_SESSION['form_data']['car_year'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="car_plate" class="required-field">License Plate Number</label>
                        <input type="text" name="car_plate" id="car_plate" required
                               placeholder="Enter your license plate number"
                               value="<?= htmlspecialchars($_SESSION['form_data']['car_plate'] ?? '') ?>">
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-user-plus"></i> Register as Driver
                    </button>
                    
                    <div class="login-link">
                        Already have an account? <a href="driver_login.php">Login here</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('photoPreview').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Add input validation feedback
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('invalid', function(e) {
                e.target.classList.add('invalid');
            });
            
            input.addEventListener('input', function(e) {
                if (e.target.validity.valid) {
                    e.target.classList.remove('invalid');
                }
            });
        });
    </script>
    
    <?php
    // Clear form data after displaying
    if (isset($_SESSION['form_data'])) {
        unset($_SESSION['form_data']);
    }
    ?>
</body>
</html>