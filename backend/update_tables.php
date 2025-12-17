<?php
include "db_connect.php";

$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS service_type VARCHAR(150) AFTER customer_name");
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS technician_notes TEXT AFTER status");
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS date_received DATE AFTER technician_notes");
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS date_completed DATE AFTER date_received");
$conn->query("ALTER TABLE repairs ADD COLUMN IF NOT EXISTS completed_at TIMESTAMP NULL AFTER date_completed");

$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS min_stock INT DEFAULT 5 AFTER quantity");
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS date_added DATE AFTER price");

$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS amount_tendered DECIMAL(10,2) AFTER payment_method");
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS change_amount DECIMAL(10,2) AFTER amount_tendered");
$conn->query("ALTER TABLE sales ADD COLUMN IF NOT EXISTS sale_date DATE AFTER change_amount");

echo "Database tables updated successfully!";
?>