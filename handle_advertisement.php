<?php
// Include config file and start session
require_once 'includes/config.php';

// Check if the user is logged in, otherwise redirect to login page
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Define variables and initialize with empty values
$title = $description = "";
$title_err = $description_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Get user ID from session
    $user_id = $_SESSION["user_id"];

    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title.";
    } else {
        $title = trim($_POST["title"]);
        // Optional: Add more validation like length check if needed
        if (strlen($title) > 255) {
             $title_err = "Title cannot be longer than 255 characters.";
        }
    }

    // Validate description
    if (empty(trim($_POST["description"]))) {
        $description_err = "Please enter a description.";
    } else {
        $description = trim($_POST["description"]);
        // Optional: Add more validation if needed
    }

    // Check input errors before inserting in database
    if (empty($title_err) && empty($description_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO advertisements (user_id, title, description) VALUES (:user_id, :title, :description)";

        if ($stmt = $pdo->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bindParam(":user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam(":title", $title, PDO::PARAM_STR);
            $stmt->bindParam(":description", $description, PDO::PARAM_STR);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Redirect back to dashboard with success message
                $_SESSION['message'] = "Advertisement posted successfully.";
                header("location: dashboard.php");
                exit;
            } else {
                $_SESSION['error'] = "Oops! Something went wrong posting the advertisement. Please try again later.";
                header("location: dashboard.php");
                exit;
            }

            // Close statement
            unset($stmt);
        } else {
             $_SESSION['error'] = "Database error preparing advertisement insert.";
             header("location: dashboard.php");
             exit;
        }
    } else {
        // If there were errors, store them in session and redirect back to dashboard
        $_SESSION['error'] = implode("<br>", array_filter([$title_err, $description_err]));
        header("location: dashboard.php");
        exit;
    }

    // Close connection
    unset($pdo);

} else {
    // If accessed directly without POST, redirect to dashboard
    header("location: dashboard.php");
    exit;
}
?>
