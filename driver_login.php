<?php
session_start();
require_once 'includes/config.php';

// Redirect if already logged in
if (isset($_SESSION['driver_id'])) {
    header("Location: driver_dashboard.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM drivers WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $driver = $stmt->fetch();
        
        if ($driver && password_verify($password, $driver['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['driver_id'] = $driver['id'];
            $_SESSION['driver_name'] = $driver['name'];
            $_SESSION['driver_username'] = $driver['username'];
            $_SESSION['driver_email'] = $driver['email'];
            $_SESSION['driver_photo'] = $driver['photo'];
            $_SESSION['is_driver'] = true;
            $_SESSION['user_type'] = 'driver';
            
            // Update last login time
            $update_stmt = $pdo->prepare("UPDATE drivers SET last_login = NOW() WHERE id = ?");
            $update_stmt->execute([$driver['id']]);
            
            // Redirect to dashboard
            header("Location: driver_dashboard.php");
            exit;
        } else {
            $_SESSION['error'] = "Invalid username or password";
            header("Location: driver_login.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error'] = "Database error. Please try again.";
        header("Location: driver_login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Login - CarShare | Secure Driver Access Portal</title>
    <meta name="description" content="Secure login portal for CarShare drivers. Access your driver dashboard, manage bookings, and handle ride requests safely.">
    <meta name="keywords" content="driver login, carshare login, driver portal, ride sharing login, driver dashboard">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="https://yourdomain.com/driver_login.php">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="Driver Login - CarShare">
    <meta property="og:description" content="Secure login portal for CarShare drivers. Access your driver dashboard, manage bookings, and handle ride requests safely.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yourdomain.com/driver_login.php">
    <meta property="og:image" content="https://yourdomain.com/images/og-image.jpg">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Driver Login - CarShare">
    <meta name="twitter:description" content="Secure login portal for CarShare drivers. Access your driver dashboard, manage bookings, and handle ride requests safely.">
    <meta name="twitter:image" content="https://yourdomain.com/images/twitter-card.jpg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Driver Login - CarShare",
        "description": "Secure login portal for CarShare drivers. Access your driver dashboard, manage bookings, and handle ride requests safely.",
        "publisher": {
            "@type": "Organization",
            "name": "CarShare",
            "logo": {
                "@type": "ImageObject",
                "url": "https://yourdomain.com/images/logo.png"
            }
        }
    }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/driver_login.css">
</head>
<body>
    <div class="login-container">
        <div class="card-header">
            <h2>Driver Login</h2>
            <p>Access your driver account</p>
        </div>
        
        <div class="card-body">
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <form action="driver_login.php" method="post">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-group">
                        <i class="fas fa-user input-icon" aria-hidden="true"></i>
                        <input type="text" name="username" id="username" required aria-label="Username or Email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                        <input type="password" name="password" id="password" required aria-label="Password">
                        <i class="fas fa-eye password-toggle" id="togglePassword" aria-hidden="true" role="button" aria-label="Toggle password visibility"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn">Login</button>
                
                <div class="login-links">
                    <a href="driver_register.php" aria-label="Register as a new driver">Register as Driver</a>
                    <a href="forgot_password.php" aria-label="Reset your password">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>