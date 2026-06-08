<?php
/**
 * test-db.php
 * Dump column names and types of the bookings table to verify schema.
 */

require_once __DIR__ . '/db_connect.php';

$result = mysqli_query($conn, "DESCRIBE bookings");
if (!$result) {
    die("Error describing table: " . mysqli_error($conn));
}

$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row;
}

header('Content-Type: application/json');
echo json_encode($columns, JSON_PRETTY_PRINT);
