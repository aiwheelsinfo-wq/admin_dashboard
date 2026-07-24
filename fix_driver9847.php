<?php
include 'db_connect.php';
header("Content-Type: application/json");

$phone = '9847267465';

$sql = "UPDATE drivers SET 
    full_name = 'MUHAMMED ANSIL',
    license_no = 'KL7320220004599',
    date_of_birth = '2003-08-27',
    license_doe = '2043-08-26',
    license_type = 'LMV-TR (TRANSPORT - COMMERCIAL TAXIS)',
    driver_address = 'PILATHOTTATHIL HOUSE KOTTAKOLLY',
    driver_city = 'SULTHANBATHERY',
    pin_code = '673592',
    status = 'active',
    userType = 'Vendor'
    WHERE phone_number = '$phone'";

$res = mysqli_query($conn, $sql);

echo json_encode([
    "success" => $res ? true : false,
    "affected" => mysqli_affected_rows($conn)
]);
?>
