<?php
header("Content-Type: application/json");
include 'db_connect.php';

$res = mysqli_query($conn, "SHOW TABLES");
$tables = $res ? mysqli_fetch_all($res) : [];

echo json_encode(["tables" => $tables]);
?>
