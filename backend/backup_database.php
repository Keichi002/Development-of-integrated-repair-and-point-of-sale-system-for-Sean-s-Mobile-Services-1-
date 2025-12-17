<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$host = "localhost";
$user = "root";
$pass = "";
$db   = "repair_pos";

$backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
$command = "mysqldump --host={$host} --user={$user} --password={$pass} {$db} > {$backup_file}";

system($command);

if (file_exists($backup_file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($backup_file));
    readfile($backup_file);
    unlink($backup_file);
    exit;
} else {
    echo "Backup failed! Please check MySQL credentials.";
}
?>