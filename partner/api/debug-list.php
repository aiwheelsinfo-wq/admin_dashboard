<?php
// TEMPORARY DEBUG FILE - DELETE AFTER FIXING
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . '/../../db_connect.php';

$debug = [];

// 1. Check DB connection
$debug['db_connected'] = ($conn !== false && $conn !== null);
if (!$debug['db_connected']) {
    echo json_encode(['debug' => $debug, 'error' => 'DB connection failed']);
    exit;
}

// 2. Check if bookings table has 'id' column
$check_id = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'id'");
$debug['bookings_has_id_column'] = (mysqli_num_rows($check_id) > 0);

// 3. Check if bookings table has 'booked_at' column
$check_booked_at = mysqli_query($conn, "SHOW COLUMNS FROM bookings LIKE 'booked_at'");
$debug['bookings_has_booked_at_column'] = (mysqli_num_rows($check_booked_at) > 0);

// 4. Check if partner_bookings table exists
$check_pb = mysqli_query($conn, "SHOW TABLES LIKE 'partner_bookings'");
$debug['partner_bookings_table_exists'] = (mysqli_num_rows($check_pb) > 0);

// 5. Show all columns in bookings table
$cols = mysqli_query($conn, "SHOW COLUMNS FROM bookings");
$debug['bookings_columns'] = [];
while ($col = mysqli_fetch_assoc($cols)) {
    $debug['bookings_columns'][] = $col['Field'];
}

// 6. Test the fixed query with partner_id = 1
$sql = "SELECT b.booking_id, pb.partner_booking_ref, b.booking_status, b.booked_at
        FROM partner_bookings pb
        INNER JOIN bookings b ON pb.booking_id = b.booking_id
        WHERE pb.partner_id = 1
        ORDER BY b.booked_at DESC
        LIMIT 3";

$result = mysqli_query($conn, $sql);
if ($result === false) {
    $debug['query_error'] = mysqli_error($conn);
    $debug['query_success'] = false;
} else {
    $debug['query_success'] = true;
    $debug['row_count'] = mysqli_num_rows($result);
}

echo json_encode(['debug' => $debug], JSON_PRETTY_PRINT);
