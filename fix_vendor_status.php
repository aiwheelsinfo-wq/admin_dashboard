<?php
include 'db_connect.php';
mysqli_query($conn, "UPDATE drivers SET userType='vendor', status='active' WHERE phone_number='9847267465'");
echo json_encode(["status" => "success", "message" => "Updated 9847267465 to active vendor"]);
?>
