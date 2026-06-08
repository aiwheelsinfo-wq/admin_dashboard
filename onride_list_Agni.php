<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header('Content-Type: application/json'); // Ensures JSON format
ini_set('display_errors', 1); 
error_reporting(E_ALL);

include('db_connect.php');

$sql = "SELECT 
    b.id AS booking_id, b.car_type, b.from_address, b.to_address, b.distance, 
    b.date, b.time, b.return_date, b.return_time, b.starting_km, b.starting_time, 
    b.closing_km, b.closing_time, b.closing_date, b.mobile, b.base_charge, 
    b.driver_ta, b.toll_charge, b.total_amount, b.payment_id, b.payment_type, 
    b.paid_amount, b.driver_id, b.booking_status, b.agent_commission, 
    b.trip_type, b.vehicle_id, b.vender_id, b.payment_status, 
    b.paid_amount_to_driver, 
    d.full_name AS driver_name, d.agency_name AS driver_agency, d.latitude,d.longitude,
    u.id AS user_id, u.phone_number AS user_phone, u.status AS user_status, 
    u.agency_name AS user_agency, u.booking_number, u.name AS user_name, 
    u.email, u.city, u.pincode, u.created_at AS user_created_at, u.accountType 
FROM bookings b 
LEFT JOIN drivers d ON b.driver_id = d.phone_number 
INNER JOIN users u ON b.mobile = u.phone_number 
WHERE 
    (b.booking_status = 'Pending' OR b.booking_status = 'Started' OR 
     CONCAT(b.date, ' ', b.time) >= NOW()) 
ORDER BY b.id DESC;
";

$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(["onridesdata" => $data]);
} else {
    echo json_encode(["onridesdata" => []]);
}

$conn->close();
?>