<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header('Content-Type: application/json'); // Ensures JSON format
ini_set('display_errors', 1); 
error_reporting(E_ALL);

include('db_connect.php');

$sql = "
    SELECT `id`, `phone_number`, `status`, `agency_name`, `booking_number`, `name`, `email`,
    `city`, `pincode`, `created_at`, `accountType`, `fcm_token`, `agent_id` FROM `users` ORDER BY id DESC;
";

$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(["newusersdata" => $data]);
} else {
    echo json_encode(["newusersdata" => []]);
}

$conn->close();
?>