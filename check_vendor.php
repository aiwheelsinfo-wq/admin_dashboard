<?php
header("Content-Type: application/json");
include 'db_connect.php';

$phone = '9847267465';

// 1. Ensure table structure or insert into vendors
$vCheck = mysqli_query($conn, "SELECT * FROM vendors WHERE phone_number LIKE '%$phone%'");
if ($vCheck && mysqli_num_rows($vCheck) == 0) {
    mysqli_query($conn, "INSERT INTO vendors (owner_name, phone_number, agency_name, status) VALUES ('MUHAMMED ANSIL', '$phone', 'AGNI CAR RENTALS', 'active')");
}

// 2. Update drivers table with agency_name and active status
mysqli_query($conn, "UPDATE drivers SET agency_name='AGNI CAR RENTALS', full_name='MUHAMMED ANSIL', status='active' WHERE phone_number LIKE '%$phone%'");

$vRes = mysqli_query($conn, "SELECT * FROM vendors WHERE phone_number LIKE '%$phone%'");
$vData = $vRes ? mysqli_fetch_all($vRes, MYSQLI_ASSOC) : [];

$dRes = mysqli_query($conn, "SELECT id, phone_number, agency_name, full_name, status FROM drivers WHERE phone_number LIKE '%$phone%'");
$dData = $dRes ? mysqli_fetch_all($dRes, MYSQLI_ASSOC) : [];

echo json_encode([
    "success" => true,
    "vendors" => $vData,
    "drivers" => $dData
]);
?>
