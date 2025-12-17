<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit();
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM repairs WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Repair not found']);
    exit();
}

$repair = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($repair);
?>