<?php
header("Content-Type: application/json");
include 'db_connect.php';

$phone = '9847267465';

$vCols = [];
$res = mysqli_query($conn, "DESCRIBE vendors");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $vCols[] = $row['Field'];
    }
}

// Update drivers table directly
mysqli_query($conn, "UPDATE drivers SET agency_name='AGNI CAR RENTALS', full_name='MUHAMMED ANSIL', status='active' WHERE phone_number LIKE '%$phone%'");

// Check if vendor exists or insert with correct columns
$vCheck = mysqli_query($conn, "SELECT * FROM vendors WHERE phone_number LIKE '%$phone%'");
if ($vCheck && mysqli_num_rows($vCheck) == 0) {
    if (in_array('vendor_name', $vCols)) {
        mysqli_query($conn, "INSERT INTO vendors (vendor_name, phone_number, agency_name, status) VALUES ('MUHAMMED ANSIL', '$phone', 'AGNI CAR RENTALS', 'active')");
    } elseif (in_array('name', $vCols)) {
        mysqli_query($conn, "INSERT INTO vendors (name, phone_number, agency_name, status) VALUES ('MUHAMMED ANSIL', '$phone', 'AGNI CAR RENTALS', 'active')");
    } else {
        mysqli_query($conn, "INSERT INTO vendors (phone_number, agency_name, status) VALUES ('$phone', 'AGNI CAR RENTALS', 'active')");
    }
}

$vRes = mysqli_query($conn, "SELECT * FROM vendors WHERE phone_number LIKE '%$phone%'");
$vData = $vRes ? mysqli_fetch_all($vRes, MYSQLI_ASSOC) : [];

$dRes = mysqli_query($conn, "SELECT id, phone_number, agency_name, full_name, status FROM drivers WHERE phone_number LIKE '%$phone%'");
$dData = $dRes ? mysqli_fetch_all($dRes, MYSQLI_ASSOC) : [];

echo json_encode([
    "success" => true,
    "vendors_columns" => $vCols,
    "vendors" => $vData,
    "drivers" => $dData
]);
?>
