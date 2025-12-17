<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$today = date('Y-m-d');
$stats = [];

$active_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status != 'Completed'")->fetch_assoc()['count'];
$today_revenue = $conn->query("SELECT SUM(total) as total FROM sales WHERE sale_date = '$today'")->fetch_assoc()['total'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < min_stock")->fetch_assoc()['count'];
$total_transactions = $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'];

$stats = [
    'active_repairs' => $active_repairs,
    'today_revenue' => $today_revenue,
    'low_stock' => $low_stock,
    'total_transactions' => $total_transactions
];

header('Content-Type: application/json');
echo json_encode($stats);
?>