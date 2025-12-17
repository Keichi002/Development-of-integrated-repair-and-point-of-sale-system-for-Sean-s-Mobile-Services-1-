<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$stats = [];

$total_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs")->fetch_assoc()['count'];
$completed_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'Completed'")->fetch_assoc()['count'];
$pending_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status != 'Completed'")->fetch_assoc()['count'];
$completion_rate = $total_repairs > 0 ? round(($completed_repairs / $total_repairs) * 100) : 0;

$stats = [
    'total_repairs' => $total_repairs,
    'completed_repairs' => $completed_repairs,
    'pending_repairs' => $pending_repairs,
    'completion_rate' => $completion_rate
];

$status_stats = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM repairs 
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

$stats['by_status'] = $status_stats;

header('Content-Type: application/json');
echo json_encode($stats);
?>