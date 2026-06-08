<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header('Content-Type: application/json'); // Ensures JSON format
ini_set('display_errors', 1); 
error_reporting(E_ALL);

include('db_connect.php');

$sql = "
SELECT  drivers.driver_id, drivers.phone_number, drivers.full_name, drivers.email, drivers.date_of_birth, drivers.vehicle_id, drivers.vehicle_type, drivers.vehicle_name AS driver_vehicle_name, drivers.fuel_type, drivers.status, drivers.created_at, drivers.updated_at, drivers.fcm_token, drivers.latitude, drivers.longitude, drivers.timestamp, drivers.driver_address, drivers.pin_code, drivers.driver_city, drivers.license_no, drivers.license_doe, drivers.license_type, drivers.adhaar_card_no, drivers.pan_card_no, drivers.photo,cars.vehicle_number, cars.owner_id, cars.vehicle_name AS car_name FROM drivers LEFT JOIN cars ON drivers.phone_number = cars.owner_id ORDER BY drivers.driver_id DESC;
";

$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(["driversdata" => $data]);
} else {
    echo json_encode(["driversdata" => []]);
}

$conn->close();
?>