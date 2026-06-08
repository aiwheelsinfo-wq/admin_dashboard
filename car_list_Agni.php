<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header('Content-Type: application/json'); // Ensures JSON format
ini_set('display_errors', 1); 
error_reporting(E_ALL);

include('db_connect.php');

$sql = "
SELECT cars.id, cars.owner_id, cars.vehicle_number, cars.vehicle_type,cars.vehicle_name, cars.fuel_type, cars.status, cars.rc_no, cars.rc_name, cars.rc_manufecture_date, cars.insurance_number, cars.insurance_doe, cars.puc_doi, cars.puc_doe, cars.texi_permit_no, cars.texi_permit_doi, cars.texi_permit_doe, cars.fitness_certificate_no, cars.fitness_certificate_doi, cars.fitness_certificate_doe, drivers.driver_city FROM cars LEFT JOIN drivers ON cars.owner_id = drivers.phone_number ORDER BY `id` DESC;
";

$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(["carsdata" => $data]);
} else {
    echo json_encode(["carsdata" => []]);
}

$conn->close();
?>