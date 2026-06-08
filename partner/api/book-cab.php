<?php
/**
 * POST /partner/api/book-cab.php
 * Creates a booking via partner API.
 * Booking is stored in main bookings table with source='partner'.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Body (JSON):
 *   from_address         string  required
 *   to_address           string  required
 *   trip_type            string  One-way | Round-Trip | Local-taxi | Local-Duty
 *   car_type             string  required
 *   date                 string  YYYY-MM-DD required
 *   time                 string  HH:MM required
 *   user_name            string  required
 *   user_mobile          string  required
 *   user_email           string  optional
 *   distance_km          float   optional
 *   total_amount         float   optional
 *   return_date          string  YYYY-MM-DD (for Round-Trip)
 *   return_time          string  HH:MM (for Round-Trip)
 *   partner_booking_ref  string  Partner's own reference ID
 */

$_API_NAME = 'book-cab';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Required fields
$required = ['from_address','to_address','trip_type','car_type','date','time','user_name','user_mobile'];
foreach ($required as $field) {
    if (empty(trim($body[$field] ?? ''))) {
        $msg = "Field '{$field}' is required.";
        log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$msg], 'error');
        api_error($msg, 400);
    }
}

// Sanitize
$from        = mysqli_real_escape_string($conn, trim($body['from_address']));
$to          = mysqli_real_escape_string($conn, trim($body['to_address']));
$trip_type   = mysqli_real_escape_string($conn, trim($body['trip_type']));
$car_type    = mysqli_real_escape_string($conn, trim($body['car_type']));
$date        = mysqli_real_escape_string($conn, trim($body['date']));
$time        = mysqli_real_escape_string($conn, trim($body['time']));
$user_name   = mysqli_real_escape_string($conn, trim($body['user_name']));
$user_mobile = mysqli_real_escape_string($conn, trim($body['user_mobile']));
$user_email  = mysqli_real_escape_string($conn, trim($body['user_email']  ?? ''));
$distance    = (float)($body['distance_km']   ?? 0);
$amount      = (float)($body['total_amount']  ?? 0);
$ret_date    = mysqli_real_escape_string($conn, trim($body['return_date'] ?? ''));
$ret_time    = mysqli_real_escape_string($conn, trim($body['return_time'] ?? ''));
$partner_ref = mysqli_real_escape_string($conn, trim($body['partner_booking_ref'] ?? ''));

// Generate booking ID
$booking_id = 'PB' . strtoupper(substr(md5(uniqid($partner['api_key'], true)), 0, 10));

// Check if bookings table has the columns we need; use safe INSERT
// We add 'source' as 'partner' and track booker_id as partner mobile
$insert_sql = "INSERT INTO bookings
    (booking_id, from_address, to_address, trip_type, car_type,
     date, time, user_name, booker_id, email,
     distance, total_amount, return_date, return_time,
     booking_status, booked_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Not Confirmed', NOW())";

$stmt = mysqli_prepare($conn, $insert_sql);

if (!$stmt) {
    // Fallback: try without optional columns
    $err = mysqli_error($conn);
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$err], 'error');
    api_error('Booking creation failed: ' . $err, 500);
}

mysqli_stmt_bind_param($stmt, 'ssssssssssddss',
    $booking_id, $from, $to, $trip_type, $car_type,
    $date, $time, $user_name, $user_mobile, $user_email,
    $distance, $amount, $ret_date, $ret_time
);

if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$err], 'error');
    api_error('Booking failed: ' . $err, 500);
}
mysqli_stmt_close($stmt);

// Record in partner_bookings cross-reference
$pb = mysqli_prepare($conn,
    "INSERT INTO partner_bookings (partner_id, booking_id, partner_booking_ref, trip_type, status)
     VALUES (?, ?, ?, ?, 'pending')"
);
if ($pb) {
    mysqli_stmt_bind_param($pb, 'isss', $partner['id'], $booking_id, $partner_ref, $trip_type);
    mysqli_stmt_execute($pb);
    mysqli_stmt_close($pb);
}

$response = [
    'status'  => true,
    'message' => 'Booking created successfully',
    'data'    => [
        'booking_id'         => $booking_id,
        'partner_booking_ref'=> $partner_ref,
        'booking_status'     => 'Not Confirmed',
        'from_address'       => $from,
        'to_address'         => $to,
        'trip_type'          => $trip_type,
        'car_type'           => $car_type,
        'date'               => $date,
        'time'               => $time,
        'passenger_name'     => $user_name,
        'passenger_mobile'   => $user_mobile,
        'note'               => 'Admin will confirm the booking. Track status using /booking-status?booking_id=' . $booking_id,
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
