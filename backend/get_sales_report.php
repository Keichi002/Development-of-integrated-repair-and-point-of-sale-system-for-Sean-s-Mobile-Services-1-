<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

$stmt = $conn->prepare("
    SELECT * FROM sales 
    WHERE sale_date BETWEEN ? AND ? 
    ORDER BY sale_date DESC
");
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sales);
?>