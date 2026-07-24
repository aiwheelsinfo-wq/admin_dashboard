<?php
include 'db_connect.php';
header("Content-Type: application/json");

$phone = '9847267465';

// 1. Delete all older duplicate rows for this phone number
$delSql = "DELETE FROM drivers WHERE phone_number = '$phone' AND driver_id < (SELECT max_id FROM (SELECT MAX(driver_id) AS max_id FROM drivers WHERE phone_number = '$phone') t)";
mysqli_query($conn, $delSql);

// 2. Ensure remaining row exists and is active
$check = mysqli_query($conn, "SELECT 1 FROM drivers WHERE phone_number = '$phone'");
if (mysqli_num_rows($check) == 0) {
    $sql = "INSERT INTO drivers (phone_number, full_name, license_no, date_of_birth, license_doe, license_type, driver_address, driver_city, pin_code, status, userType, agency_name, email)
    VALUES ('$phone', 'MUHAMMED ANSIL', 'KL7320220004599', '2003-08-27', '2043-08-26', 'LMV-TR (TRANSPORT - COMMERCIAL TAXIS)', 'PILATHOTTATHIL HOUSE KOTTAKOLLY', 'SULTHANBATHERY', '673592', 'active', 'Vendor', 'AGNI CAR RENTAL', 'ANSIL@GMAIL.COM')";
} else {
    $sql = "UPDATE drivers SET 
        full_name = 'MUHAMMED ANSIL',
        license_no = 'KL7320220004599',
        date_of_birth = '2003-08-27',
        license_doe = '2043-08-26',
        license_type = 'LMV-TR (TRANSPORT - COMMERCIAL TAXIS)',
        driver_address = 'PILATHOTTATHIL HOUSE KOTTAKOLLY',
        driver_city = 'SULTHANBATHERY',
        pin_code = '673592',
        email = 'ANSIL@GMAIL.COM',
        agency_name = 'AGNI CAR RENTAL',
        status = 'active',
        userType = 'Vendor'
        WHERE phone_number = '$phone'";
}

$res = mysqli_query($conn, $sql);

echo json_encode([
    "success" => $res ? true : false,
    "phone" => $phone
]);
?>
