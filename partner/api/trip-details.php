<?php
/**
 * GET /partner/api/trip-details.php?booking_id=PB1234
 * Returns full trip details including booking, driver, and payment info.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Query:   booking_id  required
 */

$_API_NAME = 'trip-details';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$booking_id = trim($_GET['booking_id'] ?? '');

if (empty($booking_id)) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'booking_id is required'], 'error');
    api_error('booking_id is required', 400);
}

$booking_id_esc = mysqli_real_escape_string($conn, $booking_id);

// Verify booking belongs to this partner
$pb_stmt = mysqli_prepare($conn, "SELECT partner_booking_ref FROM partner_bookings WHERE partner_id = ? AND booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($pb_stmt, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_stmt);
$pb_result = mysqli_stmt_get_result($pb_stmt);
$pb_row    = mysqli_fetch_assoc($pb_result);
mysqli_stmt_close($pb_stmt);

if (!$pb_row) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found'], 'error');
    api_error('Booking not found or does not belong to your account.', 404);
}

// Full booking details
$b_stmt = mysqli_prepare($conn, "SELECT * FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
mysqli_stmt_execute($b_stmt);
$b_result = mysqli_stmt_get_result($b_stmt);
$b        = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

if (!$b) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found in system'], 'error');
    api_error('Booking not found in system.', 404);
}

$response = [
    'status'  => true,
    'message' => 'Trip details retrieved',
    'data'    => [
        'booking_id'          => $b['booking_id'],
        'partner_booking_ref' => $pb_row['partner_booking_ref'],
        'booking_status'      => $b['booking_status'],
        'trip_type'           => $b['trip_type'],
        'car_type'            => $b['car_type'] ?? 'N/A',
        'from_address'        => $b['from_address'],
        'to_address'          => $b['to_address'],
        'date'                => $b['date'],
        'time'                => $b['time'],
        'return_date'         => $b['return_date'] ?? null,
        'return_time'         => $b['return_time'] ?? null,
        'distance_km'         => $b['distance']    ?? null,
        'passenger' => [
            'name'   => $b['user_name'],
            'mobile' => $b['booker_id'],
            'email'  => $b['email'] ?? null,
        ],
        'driver' => [
            'assigned'       => !empty($b['driver_id']),
            'name'           => $b['driver_name'] ?? null,
            'mobile'         => $b['driver_id']   ?? null,
            'vehicle_number' => $b['vehicle_id']  ?? null,
            'agency'         => $b['driver_agency'] ?? null,
        ],
        'charges' => [
            'base_charge'   => $b['base_charge']   ?? null,
            'driver_ta'     => $b['driver_ta']     ?? null,
            'toll_charge'   => $b['toll_charge']   ?? null,
            'total_amount'  => $b['total_amount']  ?? null,
            'payment_status'=> $b['payment_status'] ?? null,
            'currency'      => 'INR',
        ],
        'booked_at' => $b['booked_at'],
    ],
];

log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], $response, 'success');
echo json_encode($response);
