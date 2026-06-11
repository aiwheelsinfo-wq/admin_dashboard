<?php
require_once __DIR__ . '/../db_connect.php';

$queries = [
    "ALTER TABLE partners ADD COLUMN address TEXT NULL AFTER gst_number",
    "ALTER TABLE partners ADD COLUMN bank_details TEXT NULL AFTER address",
    "ALTER TABLE partners ADD COLUMN documents VARCHAR(255) NULL AFTER bank_details"
];

echo "<h2>Database Schema Update</h2>";
foreach ($queries as $q) {
    if (mysqli_query($conn, $q)) {
        echo "<p style='color:green;'>Success: " . htmlspecialchars($q) . "</p>";
    } else {
        echo "<p style='color:red;'>Error: " . htmlspecialchars($q) . " -> " . mysqli_error($conn) . "</p>";
    }
}
?>
