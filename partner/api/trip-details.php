<?php
/**
 * GET /partner/api/trip-details.php?booking_id=PB1234
 * Returns FULL trip details including all charges, km readings,
 * commission, per-km rate, toll, parking, permit — everything
 * set by the driver/vendor app.
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

// Full booking details — select all relevant columns explicitly (no driver_name, no driver_agency which may not exist)
$b_stmt = mysqli_prepare($conn,
    "SELECT b.booking_id, b.trip_type, b.car_type, b.from_address, b.to_address,
            b.date, b.time, b.return_date, b.return_time, b.distance,
            b.booking_status, b.booked_at, b.otp,
            b.booker_id, b.mobile,
            b.driver_id, b.vehicle_id,
            b.starting_km, b.starting_time, b.starting_date,
            b.closing_km,  b.closing_time,  b.closing_date,
            b.running_km,
            b.per_km_charge,
            b.base_charge,   b.driver_ta,      b.driver_allowance,
            b.toll_charge,   b.parking_charge, b.permit_charge,
            b.gst,           b.event_duty_gst,
            b.total_amount,  b.vendor_amount,  b.agni_amount,
            b.agent_commission,
            b.payment_type,  b.payment_status,
            b.paid_amount,   b.paid_amount_to_driver,
            b.payment_id,
            u.name AS user_name, u.email AS user_email
     FROM bookings b
     LEFT JOIN users u ON b.booker_id = u.phone_number
     WHERE b.booking_id = ?
     LIMIT 1"
);
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
if (!mysqli_stmt_execute($b_stmt)) {
    $err = mysqli_stmt_error($b_stmt);
    mysqli_stmt_close($b_stmt);
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>$err], 'error');
    api_error('Query failed: ' . $err, 500);
}

$b_result = mysqli_stmt_get_result($b_stmt);
$b        = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

if (!$b) {
    log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], ['status'=>false,'message'=>'Booking not found in system'], 'error');
    api_error('Booking not found in system.', 404);
}

// Calculate running KM if not already stored
$start_km   = $b['starting_km']  !== null ? (float)$b['starting_km']  : null;
$end_km     = $b['closing_km']   !== null ? (float)$b['closing_km']   : null;
$running_km = $b['running_km']   !== null ? (float)$b['running_km']
            : ($start_km !== null && $end_km !== null ? $end_km - $start_km : null);

$response = [
    'status'  => true,
    'message' => 'Trip details retrieved',
    'data'    => [

        // ── Booking Identity ───────────────────────────────────────────
        'booking_id'          => $b['booking_id'],
        'partner_booking_ref' => $pb_row['partner_booking_ref'],
        'booking_status'      => $b['booking_status'],
        'otp'                 => $b['otp'] ?? null,
        'booked_at'           => $b['booked_at'],

        // ── Trip Info ──────────────────────────────────────────────────
        'trip' => [
            'type'        => $b['trip_type'],
            'car_type'    => $b['car_type'] ?? 'N/A',
            'from'        => $b['from_address'],
            'to'          => $b['to_address'],
            'date'        => $b['date'],
            'time'        => $b['time'],
            'return_date' => $b['return_date'] ?? null,
            'return_time' => $b['return_time'] ?? null,
            'distance_km' => $b['distance']   !== null ? (float)$b['distance'] : null,
        ],

        // ── Passenger ─────────────────────────────────────────────────
        'passenger' => [
            'name'   => $b['user_name'],
            'mobile' => $b['booker_id'],
            'email'  => $b['user_email'] ?? null,
        ],

        // ── Driver / Vehicle ───────────────────────────────────────────
        'driver' => [
            'assigned'       => !empty($b['driver_id']),
            'mobile'         => $b['driver_id']  ?? null,
            'vehicle_number' => $b['vehicle_id'] ?? null,
        ],

        // ── Odometer / KM Tracking ────────────────────────────────────
        'km_tracking' => [
            'start_km'       => $start_km,
            'start_date'     => $b['starting_date'] ?? null,
            'start_time'     => $b['starting_time'] ?? null,
            'end_km'         => $end_km,
            'end_date'       => $b['closing_date']  ?? null,
            'end_time'       => $b['closing_time']  ?? null,
            'total_km_run'   => $running_km,
            'per_km_charge'  => $b['per_km_charge'] !== null ? (float)$b['per_km_charge'] : null,
        ],

        // ── Charges Breakdown ─────────────────────────────────────────
        'charges' => [
            'base_charge'        => $b['base_charge']      !== null ? (float)$b['base_charge']      : null,
            'driver_ta'          => $b['driver_ta']         !== null ? (float)$b['driver_ta']         : null,
            'driver_allowance'   => $b['driver_allowance']  !== null ? (float)$b['driver_allowance']  : null,
            'toll_charge'        => $b['toll_charge']       !== null ? (float)$b['toll_charge']       : null,
            'parking_charge'     => $b['parking_charge']    !== null ? (float)$b['parking_charge']    : null,
            'permit_charge'      => $b['permit_charge']     !== null ? (float)$b['permit_charge']     : null,
            'gst'                => $b['gst']               !== null ? (float)$b['gst']               : null,
            'event_duty_gst'     => $b['event_duty_gst']    !== null ? (float)$b['event_duty_gst']    : null,
            'total_amount'       => $b['total_amount']      !== null ? (float)$b['total_amount']      : null,
        ],

        // ── Commission / Revenue Split ────────────────────────────────
        'revenue' => [
            'vendor_amount'         => $b['vendor_amount']        !== null ? (float)$b['vendor_amount']        : null,
            'agni_amount'           => $b['agni_amount']           !== null ? (float)$b['agni_amount']           : null,
            'agent_commission'      => $b['agent_commission']      !== null ? (float)$b['agent_commission']      : null,
            'paid_amount_to_driver' => $b['paid_amount_to_driver'] !== null ? (float)$b['paid_amount_to_driver'] : null,
        ],

        // ── Payment ───────────────────────────────────────────────────
        'payment' => [
            'type'       => $b['payment_type']   ?? null,
            'status'     => $b['payment_status'] ?? null,
            'paid_amount'=> $b['paid_amount']    !== null ? (float)$b['paid_amount'] : null,
            'payment_id' => $b['payment_id']     ?? null,
        ],
    ],
];

log_api_request($partner['id'], $_API_NAME, ['booking_id'=>$booking_id], $response, 'success');
echo json_encode($response);
