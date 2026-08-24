<?php
session_start();
require_once 'includes/config.php';


// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");


// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login to CarShare - Your trusted car sharing platform. Access your account to book rides, manage your profile, and connect with drivers.">
    <meta name="keywords" content="CarShare login, car sharing, ride sharing, login, account access, car rental">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="https://yourdomain.com/login.php">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Login - CarShare">
    <meta property="og:description" content="Login to CarShare - Your trusted car sharing platform. Access your account to book rides, manage your profile, and connect with drivers.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://yourdomain.com/login.php">
    <meta property="og:image" content="https://yourdomain.com/images/og-image.jpg">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Login - CarShare">
    <meta name="twitter:description" content="Login to CarShare - Your trusted car sharing platform. Access your account to book rides, manage your profile, and connect with drivers.">
    <meta name="twitter:image" content="https://yourdomain.com/images/twitter-image.jpg">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    
    <title>Login - CarShare | Your Trusted Car Sharing Platform</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
    <link rel="preload" href="css/style.css" as="style">

</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <i class="fas fa-car" aria-hidden="true"></i>
                <span>CarShare</span>
            </div>
        </div>
    </header>
    
    <main class="login-container">
        <div class="login-header">
            <h1>Welcome Back</h1>
            <p>Sign in to access your account</p>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['message']) ?>
            </div>
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form action="handle_login.php" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" id="username" required 
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_SESSION['login_data']['username'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" required 
                           placeholder="Enter your password">
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </div>
            
            <div class="login-links">
                <a href="register.php" class="login-link">Create new account</a>
                <a href="forgot-password.php" class="login-link">Forgot password?</a>
                <a href="index.php" class="login-link">Back to homepage</a>
            </div>
        </form>
    </main>
    
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
    
    <?php
    // Clear login data after displaying
    if (isset($_SESSION['login_data'])) {
        unset($_SESSION['login_data']);
    }
    ?>
    
    <!-- Structured Data for Organization -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "CarShare",
        "url": "https://yourdomain.com",
        "logo": "https://yourdomain.com/images/logo.png",
        "description": "Your trusted car sharing platform"
    }
    </script>
    
    <!-- Structured Data for WebPage -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "Login - CarShare",
        "description": "Login to CarShare - Your trusted car sharing platform",
        "url": "https://yourdomain.com/login.php"
    }
    </script>
</body>
</html>