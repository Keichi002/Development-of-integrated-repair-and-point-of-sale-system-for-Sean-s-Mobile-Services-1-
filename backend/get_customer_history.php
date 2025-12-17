<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['customer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Customer ID required']);
    exit();
}

$customer_id = intval($_GET['customer_id']);

$customer_stmt = $conn->prepare("SELECT name FROM customers WHERE id = ?");
$customer_stmt->bind_param("i", $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

if ($customer_result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Customer not found']);
    exit();
}

$customer = $customer_result->fetch_assoc();
$customer_name = $customer['name'];

$repair_history = $conn->query("
    SELECT 
        'repair' as type,
        id,
        device_name,
        service_type,
        amount,
        status,
        date_received as date,
        technician_notes
    FROM repairs 
    WHERE customer_name = '$customer_name' 
    ORDER BY date_received DESC
")->fetch_all(MYSQLI_ASSOC);

$sale_history = $conn->query("
    SELECT 
        'sale' as type,
        id,
        transaction_id,
        items,
        total as amount,
        'Completed' as status,
        sale_date as date,
        payment_method
    FROM sales 
    WHERE customer_name = '$customer_name' 
    ORDER BY sale_date DESC
")->fetch_all(MYSQLI_ASSOC);

$history = array_merge($repair_history, $sale_history);

usort($history, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

$total_spent = $conn->query("
    SELECT 
        (SELECT COALESCE(SUM(amount), 0) FROM repairs WHERE customer_name = '$customer_name') +
        (SELECT COALESCE(SUM(total), 0) FROM sales WHERE customer_name = '$customer_name') as total
")->fetch_assoc()['total'];

$response = [
    'customer_name' => $customer_name,
    'total_spent' => $total_spent,
    'history' => $history
];

header('Content-Type: application/json');
echo json_encode($response);
?>