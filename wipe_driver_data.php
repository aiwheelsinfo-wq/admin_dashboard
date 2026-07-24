<?php
include 'db_connect.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$phone = trim($_POST['phone_number'] ?? $_GET['phone_number'] ?? '');
$dl = trim($_POST['license_no'] ?? $_GET['license_no'] ?? '');

if (empty($phone) && empty($dl)) {
    echo json_encode(["success" => false, "message" => "Please provide phone_number or license_no to wipe"]);
    exit;
}

$phone_esc = mysqli_real_escape_string($conn, $phone);
$dl_esc = mysqli_real_escape_string($conn, strtoupper(preg_replace('/[\s\-]/', '', $dl)));

$wiped = [];

if (!empty($phone_esc)) {
    mysqli_query($conn, "DELETE FROM driver_dl_verifications WHERE dl_number IN (SELECT license_no FROM drivers WHERE phone_number='$phone_esc')");
    mysqli_query($conn, "DELETE FROM driver_vendor_join_Table WHERE driver_id='$phone_esc' OR vendor_id='$phone_esc'");
    mysqli_query($conn, "DELETE FROM drivers WHERE phone_number='$phone_esc'");
    $wiped[] = "Phone $phone_esc wiped completely";
}

if (!empty($dl_esc)) {
    mysqli_query($conn, "DELETE FROM driver_dl_verifications WHERE dl_number='$dl_esc'");
    mysqli_query($conn, "DELETE FROM drivers WHERE UPPER(REPLACE(REPLACE(license_no, ' ', ''), '-', '')) = '$dl_esc'");
    $wiped[] = "DL $dl_esc wiped completely";
}

echo json_encode([
    "success" => true,
    "message" => "All requested driver & license data wiped completely from system!",
    "details" => $wiped
]);
?>
