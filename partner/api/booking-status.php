<?php
/**
 * GET /partner/api/booking-status.php?booking_id=PB1234
 * Returns current status of a booking.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Query:   booking_id  required
 */

$_API_NAME = 'booking-status';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$booking_id = trim($_GET['booking_id'] ?? '');

if (empty($booking_id)) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'booking_id is required'], 'error');
    api_error('Query param booking_id is required', 400);
}

$booking_id_esc = mysqli_real_escape_string($conn, $booking_id);

// Verify this booking belongs to this partner
$pb_stmt = mysqli_prepare($conn, "SELECT * FROM partner_bookings WHERE partner_id = ? AND booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($pb_stmt, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_stmt);
$pb_result = mysqli_stmt_get_result($pb_stmt);
$pb_row    = mysqli_fetch_assoc($pb_result);
mysqli_stmt_close($pb_stmt);

if (!$pb_row) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found for this partner'], 'error');
    api_error('Booking not found or does not belong to your account.', 404);
}

// Fetch booking details
$b_stmt = mysqli_prepare($conn, "SELECT booking_id, from_address, to_address, trip_type, car_type, date, time, user_name, booker_id, booking_status, driver_id, driver_name, vehicle_id, total_amount, booked_at FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
mysqli_stmt_execute($b_stmt);
$b_result = mysqli_stmt_get_result($b_stmt);
$booking  = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

if (!$booking) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found in system'], 'error');
    api_error('Booking not found in system.', 404);
}

$response = [
    'status'  => true,
    'message' => 'Booking status retrieved',
    'data'    => [
        'booking_id'          => $booking['booking_id'],
        'partner_booking_ref' => $pb_row['partner_booking_ref'],
        'booking_status'      => $booking['booking_status'],
        'trip_type'           => $booking['trip_type'],
        'car_type'            => $booking['car_type'] ?? 'N/A',
        'date'                => $booking['date'],
        'time'                => $booking['time'],
        'from_address'        => $booking['from_address'],
        'to_address'          => $booking['to_address'],
        'passenger_name'      => $booking['user_name'],
        'passenger_mobile'    => $booking['booker_id'],
        'driver_assigned'     => !empty($booking['driver_id']),
        'driver_name'         => $booking['driver_name'] ?? null,
        'driver_mobile'       => $booking['driver_id']   ?? null,
        'vehicle_number'      => $booking['vehicle_id']  ?? null,
        'total_amount'        => $booking['total_amount'] ?? null,
        'booked_at'           => $booking['booked_at'],
    ],
];

log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], $response, 'success');
echo json_encode($response);
