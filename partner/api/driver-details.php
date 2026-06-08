<?php
/**
 * GET /partner/api/driver-details.php?booking_id=PB1234
 * Returns assigned driver details for a booking.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Query:   booking_id  required
 */

$_API_NAME = 'driver-details';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$booking_id = trim($_GET['booking_id'] ?? '');

if (empty($booking_id)) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'booking_id is required'], 'error');
    api_error('booking_id is required', 400);
}

$booking_id_esc = mysqli_real_escape_string($conn, $booking_id);

// Verify this booking belongs to this partner
$pb_stmt = mysqli_prepare($conn, "SELECT booking_id FROM partner_bookings WHERE partner_id = ? AND booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($pb_stmt, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_stmt);
$pb_result = mysqli_stmt_get_result($pb_stmt);
if (!mysqli_fetch_assoc($pb_result)) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found'], 'error');
    api_error('Booking not found or does not belong to your account.', 404);
}
mysqli_stmt_close($pb_stmt);

// Get booking with driver info
$b_stmt = mysqli_prepare($conn, "SELECT b.booking_id, b.booking_status, b.driver_id, b.driver_name, b.vehicle_id, d.driver_city, d.license_doe FROM bookings b LEFT JOIN drivers d ON d.phone_number = b.driver_id WHERE b.booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
mysqli_stmt_execute($b_stmt);
$b_result = mysqli_stmt_get_result($b_stmt);
$booking  = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

if (!$booking) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found in system'], 'error');
    api_error('Booking not found in system.', 404);
}

$driver_assigned = !empty($booking['driver_id']);
$response = [
    'status'  => true,
    'message' => $driver_assigned ? 'Driver details retrieved' : 'No driver assigned yet',
    'data'    => [
        'booking_id'      => $booking['booking_id'],
        'booking_status'  => $booking['booking_status'],
        'driver_assigned' => $driver_assigned,
        'driver_name'     => $booking['driver_name']   ?? null,
        'driver_mobile'   => $booking['driver_id']     ?? null,
        'vehicle_number'  => $booking['vehicle_id']    ?? null,
        'driver_city'     => $booking['driver_city']   ?? null,
    ],
];

log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], $response, 'success');
echo json_encode($response);
