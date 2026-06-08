<?php
/**
 * force-migrate.php
 * Force add the missing booking_id column to the bookings table.
 */

require_once __DIR__ . '/db_connect.php';

// Disable error display restrictions for this debug run
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Running Force Migration...</h3>";

// 1. Add booking_id column
$sql1 = "ALTER TABLE bookings ADD COLUMN booking_id VARCHAR(100) DEFAULT NULL AFTER id";
if (mysqli_query($conn, $sql1)) {
    echo "<p style='color:green;'>✅ Successfully added column 'booking_id' to bookings table!</p>";
} else {
    echo "<p style='color:red;'>❌ Failed to add column: " . mysqli_error($conn) . "</p>";
}

// 2. Add index for booking_id
$sql2 = "ALTER TABLE bookings ADD INDEX idx_bookings_booking_id (booking_id)";
if (mysqli_query($conn, $sql2)) {
    echo "<p style='color:green;'>✅ Successfully added index idx_bookings_booking_id!</p>";
} else {
    echo "<p style='color:red;'>❌ Failed to add index: " . mysqli_error($conn) . "</p>";
}

mysqli_close($conn);
?>
