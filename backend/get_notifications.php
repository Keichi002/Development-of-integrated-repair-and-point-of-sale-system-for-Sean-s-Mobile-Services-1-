<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([]);
    exit();
}

$notifications = [];

$pending_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'Pending'")->fetch_assoc()['count'];
if ($pending_repairs > 0) {
    $notifications[] = "$pending_repairs pending repair" . ($pending_repairs > 1 ? 's' : '');
}

$low_stock_items = $conn->query("SELECT name, quantity, min_stock FROM inventory WHERE quantity < min_stock LIMIT 3")->fetch_all(MYSQLI_ASSOC);
foreach ($low_stock_items as $item) {
    $notifications[] = "Low stock: {$item['name']} ({$item['quantity']}/{$item['min_stock']})";
}

$ready_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'For Pickup'")->fetch_assoc()['count'];
if ($ready_repairs > 0) {
    $notifications[] = "$ready_repairs repair" . ($ready_repairs > 1 ? 's' : '') . " ready for pickup";
}

$today = date('Y-m-d');
$sales_today = $conn->query("SELECT COUNT(*) as count FROM sales WHERE sale_date = '$today'")->fetch_assoc()['count'];
if ($sales_today > 0) {
    $notifications[] = "$sales_today sale" . ($sales_today > 1 ? 's' : '') . " today";
}

header('Content-Type: application/json');
echo json_encode($notifications);
?>