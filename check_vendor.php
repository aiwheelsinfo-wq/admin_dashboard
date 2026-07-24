<?php
header("Content-Type: application/json");
include 'db_connect.php';

$phone = isset($_GET['phone']) ? mysqli_real_escape_string($conn, $_GET['phone']) : '9847267465';

$vRes = mysqli_query($conn, "SELECT * FROM vendors WHERE phone_number LIKE '%$phone%'");
$vData = $vRes ? mysqli_fetch_all($vRes, MYSQLI_ASSOC) : [];

$dRes = mysqli_query($conn, "SELECT * FROM drivers WHERE phone_number LIKE '%$phone%'");
$dData = $dRes ? mysqli_fetch_all($dRes, MYSQLI_ASSOC) : [];

echo json_encode([
    "vendors" => $vData,
    "drivers" => $dData
]);
?>
