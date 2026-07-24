<?php
header("Content-Type: application/json");
include 'db_connect.php';

$vendor = $_GET['vendor'] ?? '9847267465';

$jRes = mysqli_query($conn, "SELECT * FROM `driver_vendor_join_Table`");
$jData = $jRes ? mysqli_fetch_all($jRes, MYSQLI_ASSOC) : [];

$dRes = mysqli_query($conn, "SELECT driver_id, phone_number, full_name, status, created_at FROM drivers ORDER BY driver_id DESC LIMIT 10");
$dData = $dRes ? mysqli_fetch_all($dRes, MYSQLI_ASSOC) : [];

echo json_encode([
    "joins" => $jData,
    "latest_drivers" => $dData
]);
?>
