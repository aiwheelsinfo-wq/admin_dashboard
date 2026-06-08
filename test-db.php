<?php
/**
 * test-db.php
 * Dump all migrations from schema_migrations table.
 */

require_once __DIR__ . '/db_connect.php';

$result = mysqli_query($conn, "SELECT * FROM schema_migrations ORDER BY id ASC");
if (!$result) {
    die("Error querying migrations: " . mysqli_error($conn));
}

$migrations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $migrations[] = $row;
}

header('Content-Type: application/json');
echo json_encode($migrations, JSON_PRETTY_PRINT);
