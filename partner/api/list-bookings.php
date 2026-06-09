<?php
/**
 * GET /partner/api/list-bookings.php
 * Returns a list of all bookings created by this partner.
 *
 * Headers: X-API-Key, X-Secret-Key
 */

$_API_NAME = 'list-bookings';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

// Fetch all bookings for this partner
$sql = "SELECT b.booking_id, pb.partner_booking_ref, b.from_address, b.to_address, b.trip_type, b.car_type, b.date, b.time, u.name AS user_name, b.booker_id AS user_mobile, b.booking_status, b.driver_id, b.driver_name, b.vehicle_id, b.total_amount, b.booked_at 
        FROM partner_bookings pb
        INNER JOIN bookings b ON pb.booking_id = b.booking_id
        LEFT JOIN users u ON b.booker_id = u.phone_number
        WHERE pb.partner_id = ?
        ORDER BY b.id DESC";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    $err = mysqli_error($conn);
    log_api_request($partner['id'], $_API_NAME, [], ['status'=>false,'message'=>$err], 'error');
    api_error('Failed to prepare query: ' . $err, 500);
}

mysqli_stmt_bind_param($stmt, 'i', $partner['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$bookings = [];
while ($row = mysqli_fetch_assoc($result)) {
    $bookings[] = [
        'booking_id'          => $row['booking_id'],
        'partner_booking_ref' => $row['partner_booking_ref'],
        'booking_status'      => $row['booking_status'],
        'trip_type'           => $row['trip_type'],
        'car_type'            => $row['car_type'] ?? 'N/A',
        'date'                => $row['date'],
        'time'                => $row['time'],
        'from_address'        => $row['from_address'],
        'to_address'          => $row['to_address'],
        'passenger_name'      => $row['user_name'],
        'passenger_mobile'    => $row['user_mobile'],
        'driver_assigned'     => !empty($row['driver_id']),
        'driver_name'         => $row['driver_name'] ?? null,
        'driver_mobile'       => $row['driver_id']   ?? null,
        'vehicle_number'      => $row['vehicle_id']  ?? null,
        'total_amount'        => $row['total_amount'] ?? null,
        'booked_at'           => $row['booked_at'],
    ];
}
mysqli_stmt_close($stmt);

$response = [
    'status'  => true,
    'message' => 'Bookings list retrieved',
    'data'    => $bookings
];

log_api_request($partner['id'], $_API_NAME, [], $response, 'success');
echo json_encode($response);
