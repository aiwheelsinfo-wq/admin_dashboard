<?php
/**
 * test-db.php
 * Verify if partners table exists and dump its columns.
 */

require_once __DIR__ . '/db_connect.php';

$result = mysqli_query($conn, "DESCRIBE partners");
if (!$result) {
    die("Error describing partners table: " . mysqli_error($conn));
}

$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row;
}

header('Content-Type: application/json');
echo json_encode($columns, JSON_PRETTY_PRINT);
