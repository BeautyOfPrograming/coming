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
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$rate_limit_key = "rate_limit_" . $ip;
$rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key] : 0;
$rate_limit_time = isset($_SESSION[$rate_limit_key . '_time']) ? $_SESSION[$rate_limit_key . '_time'] : 0;

if ($rate_limit_count >= 5 && (time() - $rate_limit_time) < 300) {
    $_SESSION['error'] = "Too many registration attempts. Please try again in 5 minutes.";
    header("Location: register.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WhoIsComing | Car Sharing Platform</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Join WhoIsComing - the trusted car sharing platform. Create your account to start sharing rides or offering your car for rent. Safe, secure, and easy to use.">
    <meta name="keywords" content="car sharing, ride sharing, car rental, register, sign up, whoiscoming">
    <meta name="author" content="WhoIsComing">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://whoiscoming.com/register.php">
    <meta property="og:title" content="Register - WhoIsComing | Car Sharing Platform">
    <meta property="og:description" content="Join WhoIsComing - the trusted car sharing platform. Create your account to start sharing rides or offering your car for rent.">
    <meta property="og:image" content="https://whoiscoming.com/images/og-image.jpg">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://whoiscoming.com/register.php">
    <meta property="twitter:title" content="Register - WhoIsComing | Car Sharing Platform">
    <meta property="twitter:description" content="Join WhoIsComing - the trusted car sharing platform. Create your account to start sharing rides or offering your car for rent.">
    <meta property="twitter:image" content="https://whoiscoming.com/images/twitter-image.jpg">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://whoiscoming.com/register.php">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="favicon.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">


        <link rel="stylesheet" href="css/register.css">
    <link rel="preload" href="css/style.css" as="style">

</head>
<body>
 
    
    <header role="banner">
        <div class="container">
            <div class="logo" role="img" aria-label="WhoIsComing Logo">
                <i class="fas fa-car" aria-hidden="true"></i>
                <span>CarShare</span>
            </div>
        </div>
    </header>
    
    <main id="main-content" role="main">
        <div class="register-container">
            <div class="register-card">
                <div class="card-header">
                    <h1>Create Your Account</h1>
                    <p>Join our community of car owners and renters</p>
                </div>
                
                <div class="card-body">
                    <?php if (isset($_SESSION['message'])): ?>
                        <div class="message" role="alert">
                            <i class="fas fa-check-circle" aria-hidden="true"></i>
                            <?= htmlspecialchars($_SESSION['message']) ?>
                        </div>
                        <?php unset($_SESSION['message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="error-message" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?= htmlspecialchars($_SESSION['error']) ?>
                        </div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>

                    <form action="handle_register.php" method="post" aria-label="Registration form">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <div class="input-group">
                                <i class="fas fa-user input-icon" aria-hidden="true"></i>
                                <input type="text" name="name" id="name" required 
                                       placeholder="John Doe"
                                       aria-required="true"
                                       value="<?= htmlspecialchars($_SESSION['form_data']['name'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-group">
                                <i class="fas fa-at input-icon" aria-hidden="true"></i>
                                <input type="text" name="username" id="username" required 
                                       pattern="^[a-zA-Z0-9_]{3,20}$" 
                                       title="3-20 characters (letters, numbers, underscores)"
                                       placeholder="johndoe123"
                                       aria-required="true"
                                       value="<?= htmlspecialchars($_SESSION['form_data']['username'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                                <input type="password" name="password" id="password" required 
                                       minlength="8" 
                                       title="At least 8 characters"
                                       placeholder="••••••••"
                                       aria-required="true">
                                <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-group">
                                <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                                <input type="password" name="confirm_password" id="confirm_password" required 
                                       minlength="8" 
                                       placeholder="••••••••"
                                       aria-required="true">
                                <button type="button" class="password-toggle" id="toggleConfirmPassword" aria-label="Toggle confirm password visibility">
                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">
                            <i class="fas fa-user-plus" aria-hidden="true"></i> Register Now
                        </button>
                        
                        <div class="login-link">
                            Already have an account? <a href="login.php">Sign in here</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <footer role="contentinfo">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> WhoIsComing. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Toggle password visibility
        const togglePassword = document.querySelector('#togglePassword');
        const password = document.querySelector('#password');
        
        const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
        const confirmPassword = document.querySelector('#confirm_password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
        });
        
        toggleConfirmPassword.addEventListener('click', function() {
            const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye-slash');
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