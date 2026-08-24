<?php
// Include config file to establish database connection and start session
require_once 'includes/config.php';

// Security headers
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");

// Function to get user's IP address (basic)
function getUserIpAddr(){
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        //ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
        //ip pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }else{
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'UNKNOWN'; // Validate IP
}

// Function to validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }
    
    return $errors;
}

// Initialize variables
$name = $username = $password = $confirm_password = "";
$name_err = $username_err = $password_err = $confirm_password_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error'] = "Invalid request. Please try again.";
        header("location: register.php");
        exit;
    }

    // Rate limiting
    $ip = getUserIpAddr();
    $rate_limit_key = "rate_limit_" . $ip;
    $rate_limit_count = isset($_SESSION[$rate_limit_key]) ? $_SESSION[$rate_limit_key] : 0;
    $rate_limit_time = isset($_SESSION[$rate_limit_key . '_time']) ? $_SESSION[$rate_limit_key . '_time'] : 0;

    if ($rate_limit_count >= 5 && (time() - $rate_limit_time) < 300) {
        $_SESSION['error'] = "Too many registration attempts. Please try again in 5 minutes.";
        header("location: register.php");
        exit;
    }

    // Validate name
    if (empty(trim($_POST["name"]))) {
        $name_err = "Please enter your full name.";
    } else {
        $name = filter_var(trim($_POST["name"]), FILTER_SANITIZE_STRING);
        if (strlen($name) < 2 || strlen($name) > 50) {
            $name_err = "Name must be between 2 and 50 characters.";
        }
    }

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', trim($_POST["username"]))) {
        $username_err = "Username must be 3-20 characters and contain only letters, numbers, and underscores.";
    } else {
        $username = filter_var(trim($_POST["username"]), FILTER_SANITIZE_STRING);
        
        // Check if username exists
        $sql = "SELECT id FROM registered_users WHERE username = :username";
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $username_err = "This username is already taken.";
                }
            }
            unset($stmt);
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } else {
        $password = trim($_POST["password"]);
        $password_errors = validatePasswordStrength($password);
        if (!empty($password_errors)) {
            $password_err = implode("<br>", $password_errors);
        }
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if ($password !== $confirm_password) {
            $confirm_password_err = "Passwords did not match.";
        }
    }

    // Check input errors before inserting in database
    if (empty($name_err) && empty($username_err) && empty($password_err) && empty($confirm_password_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO registered_users (name, username, password_hash, registration_datetime, registration_location, cookies_data) 
                VALUES (:name, :username, :password_hash, :reg_datetime, :reg_location, :cookies)";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":name", $name, PDO::PARAM_STR);
            $stmt->bindParam(":username", $username, PDO::PARAM_STR);
            $stmt->bindParam(":password_hash", password_hash($password, PASSWORD_DEFAULT), PDO::PARAM_STR);
            $stmt->bindParam(":reg_datetime", date('Y-m-d H:i:s'), PDO::PARAM_STR);
            $stmt->bindParam(":reg_location", getUserIpAddr(), PDO::PARAM_STR);
            $stmt->bindParam(":cookies", json_encode($_COOKIE), PDO::PARAM_STR);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Update rate limiting
                $_SESSION[$rate_limit_key] = $rate_limit_count + 1;
                $_SESSION[$rate_limit_key . '_time'] = time();
                
                $_SESSION['message'] = "Registration successful! You can now login.";
                header("location: login.php");
                exit;
            } else {
                $_SESSION['error'] = "Oops! Something went wrong. Please try again later.";
                header("location: register.php");
                exit;
            }
            unset($stmt);
        }
    } else {
        // Store errors in session
        $_SESSION['error'] = implode("<br>", array_filter([$name_err, $username_err, $password_err, $confirm_password_err]));
        header("location: register.php");
        exit;
    }
    unset($pdo);
} else {
    // If accessed directly without POST, redirect to registration page
    header("location: register.php");
    exit;
}
?>
