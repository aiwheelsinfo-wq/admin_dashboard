<?php
header("Content-Type: application/json");
include 'db_connect.php';

$phone = isset($_GET['phone']) ? mysqli_real_escape_string($conn, $_GET['phone']) : '9847267465';
$status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'filled';

$update = mysqli_query($conn, "UPDATE drivers SET status='$status' WHERE phone_number='$phone'");

echo json_encode([
    "success" => $update ? true : false,
    "phone" => $phone,
    "new_status" => $status
]);
?>
