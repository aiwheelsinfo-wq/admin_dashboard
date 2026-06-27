<?php
/**
 * POST /partner/api/cancel-booking.php
 * Cancel a booking with real refund calculation based on cancellation policy.
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

// Fetch full booking details
$b_stmt = mysqli_prepare($conn, "SELECT booking_id, booking_status, trip_type, date, time, total_amount, paid_amount, vender_id, driver_id FROM bookings WHERE booking_id = ? LIMIT 1");
mysqli_stmt_bind_param($b_stmt, 's', $booking_id_esc);
mysqli_stmt_execute($b_stmt);
$b_result = mysqli_stmt_get_result($b_stmt);
$booking  = mysqli_fetch_assoc($b_result);
mysqli_stmt_close($b_stmt);

// Cannot cancel if already started/completed
$non_cancellable = ['Started', 'Ongoing', 'Completed', 'Cancelled', 'Customer Cancelled', 'Deleted', 'Failed'];
if (in_array($booking['booking_status'] ?? '', $non_cancellable)) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Cannot cancel booking with status: ' . $booking['booking_status']], 'error');
    api_error('Cannot cancel a booking with status: ' . $booking['booking_status'], 409);
}

// Detect Local Taxi
$isLocalTaxi = false;
$tripTypeLower = strtolower($booking['trip_type'] ?? '');
if (strpos($tripTypeLower, 'local') !== false && strpos($tripTypeLower, 'taxi') !== false) {
    $isLocalTaxi = true;
}

// Time-based calculation
$diff_hours = 999;
if (!$isLocalTaxi) {
    $pickup_str = ($booking['date'] ?? '') . ' ' . ($booking['time'] ?? '');
    $pickup_ts  = strtotime($pickup_str);
    if ($pickup_ts !== false) {
        $diff_hours = ($pickup_ts - time()) / 3600.0;
    }
    if ($diff_hours <= 0) {
        api_error('Trip has already started. Cancellation is not allowed.', 409);
    }
}

// Fetch cancellation policy
$policyResult = $conn->query("SELECT * FROM cancellation_policy ORDER BY id DESC LIMIT 1");
$policy = $policyResult ? $policyResult->fetch_assoc() : null;

$advance_paid = (float)($booking['paid_amount'] ?? 0);
$refund_amount = 0.0;
$cancellation_charge = 0.0;
$refund_percentage = 0.0;
$vendor_compensation = 0.0;

if ($isLocalTaxi) {
    $refund_percentage = 100.0;
    $refund_amount = $advance_paid;
    $cancellation_charge = 0.0;
} elseif ($policy) {
    if ($diff_hours >= 48) {
        $refund_percentage = (float)$policy['refund_above_48h'];
    } elseif ($diff_hours >= 24) {
        $refund_percentage = (float)$policy['refund_24_48h'];
    } elseif ($diff_hours >= 12) {
        $refund_percentage = (float)$policy['refund_12_24h'];
    } elseif ($diff_hours >= 6) {
        $refund_percentage = (float)$policy['refund_6_12h'];
    } else {
        $refund_percentage = (float)$policy['refund_below_6h'];
    }
    $refund_amount = $advance_paid * ($refund_percentage / 100.0);
    $cancellation_charge = $advance_paid - $refund_amount;

    if (!empty($booking['vender_id']) || !empty($booking['driver_id'])) {
        $vendor_comp_percent = 0.0;
        if ($diff_hours >= 24) $vendor_comp_percent = (float)($policy['vendor_comp_above_24h'] ?? 0);
        elseif ($diff_hours >= 6) $vendor_comp_percent = (float)($policy['vendor_comp_6_24h'] ?? 0);
        else $vendor_comp_percent = (float)($policy['vendor_comp_below_6h'] ?? 0);
        $vendor_compensation = $cancellation_charge * ($vendor_comp_percent / 100.0);
    }
} else {
    $refund_percentage = ($diff_hours > 24) ? 90.0 : 0.0;
    $refund_amount = $advance_paid * ($refund_percentage / 100.0);
    $cancellation_charge = $advance_paid - $refund_amount;
}

$new_booking_status = 'Cancelled';
$new_refund_status = ($refund_amount > 0) ? 'Processing' : 'Not Applicable';
$new_vendor_comp_status = ($vendor_compensation > 0) ? 'Pending' : null;

$upd = mysqli_prepare($conn,
    "UPDATE bookings SET
        booking_status = ?,
        cancellation_reason = ?,
        cancelled_at = NOW(),
        cancellation_charge = ?,
        refund_amount = ?,
        refund_status = ?,
        vendor_compensation = ?,
        vendor_compensation_status = ?
    WHERE booking_id = ?");
mysqli_stmt_bind_param($upd, 'ssddsdss',
    $new_booking_status, $reason,
    $cancellation_charge, $refund_amount,
    $new_refund_status,
    $vendor_compensation, $new_vendor_comp_status,
    $booking_id_esc
);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

$pb_upd = mysqli_prepare($conn, "UPDATE partner_bookings SET status = 'cancelled' WHERE partner_id = ? AND booking_id = ?");
mysqli_stmt_bind_param($pb_upd, 'is', $partner['id'], $booking_id_esc);
mysqli_stmt_execute($pb_upd);
mysqli_stmt_close($pb_upd);

$response = [
    'status'  => true,
    'message' => 'Booking cancelled successfully',
    'data'    => [
        'booking_id'          => $booking_id,
        'trip_type'           => $booking['trip_type'],
        'booking_status'      => $new_booking_status,
        'cancellation_reason' => $reason,
        'refund' => [
            'advance_paid'         => $advance_paid,
            'refund_percentage'    => $refund_percentage,
            'refund_amount'        => round($refund_amount, 2),
            'cancellation_charge'  => round($cancellation_charge, 2),
            'vendor_compensation'  => round($vendor_compensation, 2),
            'refund_status'        => $new_refund_status,
            'is_local_taxi'        => $isLocalTaxi,
            'hours_before_pickup'  => round($diff_hours, 1),
        ],
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
