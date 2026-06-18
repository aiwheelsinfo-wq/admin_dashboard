<?php
header('Content-Type: application/json');
include '../2025/db_connect.php';

$result = $conn->query("SHOW COLUMNS FROM bookings LIKE 'discount_amount'");
$columnExists = ($result && $result->num_rows > 0);

$migResult = $conn->query("SELECT * FROM migrations WHERE migration_name = '006_add_discount_amount_to_bookings.php'");
$migRan = ($migResult && $migResult->num_rows > 0);
$migData = $migRan ? $migResult->fetch_assoc() : null;

echo json_encode([
    'discount_amount_column_exists' => $columnExists,
    'migration_006_recorded' => $migRan,
    'migration_data' => $migData,
]);
