<?php
// Include config file
require_once 'includes/config.php';

// Define variables and initialize with empty values
$username = $password = "";
$username_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Check if username is empty
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter username.";
    } else {
        $username = trim($_POST["username"]);
    }

    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate credentials if no input errors
    if (empty($username_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, name, username, password_hash FROM registered_users WHERE username = :username";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":username", $param_username, PDO::PARAM_STR);

            // Set parameters
            $param_username = $username;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Check if username exists, if yes then verify password
                if ($stmt->rowCount() == 1) {
                    if ($row = $stmt->fetch()) {
                        $id = $row["id"];
                        $name = $row["name"];
                        $hashed_password = $row["password_hash"];
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session

                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["username"] = $username;
                            $_SESSION["user_name"] = $name; // Store user's full name too
                            $_SESSION["user_type"] = 'passenger';

                            // Redirect user to dashboard page
                            header("location: dashboard.php");
                            exit;
                        } else {
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else {
                    // Username doesn't exist, display a generic error message
                    $login_err = "Invalid username or password.";
                }
            } else {
                $_SESSION['error'] = "Oops! Something went wrong executing query. Please try again later.";
                header("location: login.php");
                exit;
            }

            // Close statement
            unset($stmt);
        } else {
             $_SESSION['error'] = "Database error preparing login statement.";
             header("location: login.php");
             exit;
        }
    }

    // Close connection
    unset($pdo);

    // If there were login errors, redirect back to login form
    if (!empty($login_err) || !empty($username_err) || !empty($password_err)) {
        $_SESSION['error'] = $login_err ?: ($username_err ?: $password_err);
        header("location: login.php");
        exit;
    }

} else {
     // If accessed directly without POST, redirect to login page
    header("location: login.php");
    exit;
}
?>
