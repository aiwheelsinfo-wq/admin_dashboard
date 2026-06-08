<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connect.php';

// SQL to fetch all blocked users
$sql = "SELECT * FROM users WHERE status = 'Blocked' ORDER BY id DESC;";
$result = $conn->query($sql);

$response = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $response[] = $row;
    }
    echo json_encode(["success" => true, "Blocked_Customerdata" => $response]);
} else {
    echo json_encode(["success" => false, "message" => "No blocked users found."]);
}

$conn->close();
?>
