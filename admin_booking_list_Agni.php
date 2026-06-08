<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

header('Content-Type: application/json'); // Ensures JSON format
ini_set('display_errors', 1); 
error_reporting(E_ALL);

include('db_connect.php');

$sql ="SELECT b.id AS booking_id, b.car_type, b.from_address, b.to_address, b.distance, b.date, b.time,b.booked_at, b.return_date, b.return_time, b.starting_km, b.starting_time, b.closing_km, b.closing_time, b.closing_date, b.mobile, b.base_charge, b.driver_ta, b.toll_charge,b.parking_charge,b.permit_charge, b.total_amount, b.payment_id, b.payment_type, b.paid_amount,b.booker_id, b.driver_id, b.booking_status, b.agent_commission, b.trip_type, b.vehicle_id, b.vender_id, b.payment_status, b.paid_amount_to_driver,b.agni_amount,b.vendor_amount, d.full_name AS driver_name, d.agency_name AS driver_agency, u.id AS user_id, u.phone_number AS user_phone, u.status AS user_status, u.agency_name AS user_agency, u.booking_number, u.name AS user_name, u.email, u.city, u.pincode, u.created_at AS user_created_at, u.accountType FROM bookings b LEFT JOIN drivers d ON b.driver_id = d.phone_number INNER JOIN users u ON b.booker_id = u.phone_number ORDER BY b.id DESC;";
$result = $conn->query($sql);

$data = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode(["bookingsdata" => $data]);
} else {
    echo json_encode(["bookingsdata" => []]);
}

$conn->close();
?>