<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "repair_pos";

echo "<h2>Setting up Mobile Repair POS Database...</h2>";

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


$conn->query("CREATE DATABASE IF NOT EXISTS $db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->select_db($db);

echo "Database '$db' ready.<br>";

$conn->query("DROP TABLE IF EXISTS users");
$conn->query("
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
");

$password = password_hash('admin123', PASSWORD_DEFAULT);
$conn->query("INSERT INTO users (username, password, role) VALUES ('admin', '$password', 'admin')");

echo "<strong style='color:green;'>SUCCESS!</strong><br>";
echo "Admin user created: <strong>admin / admin123</strong><br><br>";

echo "You can now delete this file (setup.php) and login at index.php";
?>


<!--http://localhost/repair_pos/setup.php 
    http://localhost/repair_pos/backend/create_tables.php
    Kani ang pang run incase makalimot
-->