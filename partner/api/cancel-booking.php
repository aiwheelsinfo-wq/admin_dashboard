<?php
/**
 * POST /partner/api/cancel-booking.php
 * Cancel a booking made via partner API.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Body (JSON):
 *   booking_id  string  required
 *   reason      string  optional
 */

$_API_NAME = 'cancel-booking';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$booking_id = trim($body['booking_id'] ?? '');
$reason     = trim($body['reason']     ?? 'Cancelled by partner');

if (empty($booking_id)) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'booking_id is required'], 'error');
    api_error('booking_id is required', 400);
}

$booking_id_esc = mysqli_real_escape_string($conn, $booking_id);

// Verify booking belongs to this partner
$pb_stmt = mysqli_prepare($conn, "SELECT * FROM partner_bookings WHERE partner_id = ? AND booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($pb_stmt, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_stmt);
$pb_result = mysqli_stmt_get_result($pb_stmt);
$pb_row    = mysqli_fetch_assoc($pb_result);
mysqli_stmt_close($pb_stmt);

if (!$pb_row) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Booking not found'], 'error');
    api_error('Booking not found or does not belong to your account.', 404);
}

// Get current status
$b_stmt = mysqli_prepare($conn, "SELECT booking_status FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
mysqli_stmt_execute($b_stmt);
$b_result = mysqli_stmt_get_result($b_stmt);
$booking  = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

// Cannot cancel if already started/completed
$non_cancellable = ['Started', 'Completed', 'Cancelled', 'Deleted'];
if (in_array($booking['booking_status'] ?? '', $non_cancellable)) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Cannot cancel booking with status: ' . $booking['booking_status']], 'error');
    api_error('Cannot cancel a booking with status: ' . $booking['booking_status'], 409);
}

// Update booking status to Cancelled
$upd = mysqli_prepare($conn, "UPDATE bookings SET booking_status = 'Cancelled' WHERE booking_id = ?");
mysqli_stmt_bind_param($upd, 's', $booking_id_esc);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

// Update partner_bookings
$pb_upd = mysqli_prepare($conn, "UPDATE partner_bookings SET status = 'cancelled' WHERE partner_id = ? AND booking_id = ?");
mysqli_stmt_bind_param($pb_upd, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_upd);
mysqli_stmt_close($pb_upd);

$response = [
    'status'  => true,
    'message' => 'Booking cancelled successfully',
    'data'    => [
        'booking_id'    => $booking_id,
        'booking_status'=> 'Cancelled',
        'reason'        => $reason,
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
