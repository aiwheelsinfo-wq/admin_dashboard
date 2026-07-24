<?php
header("Content-Type: application/json");
include 'db_connect.php';

$res = mysqli_query($conn, "SHOW CREATE TABLE `driver_vendor_join_Table`");
$schema = $res ? mysqli_fetch_assoc($res) : [];

echo json_encode(["schema" => $schema]);
?>
