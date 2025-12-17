<?php
session_start();
include "db_connect.php";

if (isset($_SESSION['user'])) {
    header("Location: ../dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            $activity_type = "login";
            $description = "User logged in";
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, ?, ?)");
            $log_stmt->bind_param("iss", $_SESSION['user_id'], $activity_type, $description);
            $log_stmt->execute();
            
            header("Location: ../dashboard.php");
            exit();
        } else {
            $error = "Invalid credentials";
        }
    } else {
        $error = "Invalid credentials";
    }
    $stmt->close();
}

header("Location: ../index.php?error=" . urlencode($error));
exit();
?>