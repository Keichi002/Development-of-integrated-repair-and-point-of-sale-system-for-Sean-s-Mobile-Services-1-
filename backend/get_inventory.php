<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$result = $conn->query("SELECT id, name, category, quantity, price FROM inventory WHERE quantity > 0 ORDER BY name");
$inventory = [];

while ($row = $result->fetch_assoc()) {
    $inventory[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => (float)$row['price'],
        'quantity' => (int)$row['quantity'],
        'category' => $row['category']
    ];
}

header('Content-Type: application/json');
echo json_encode($inventory);
?>