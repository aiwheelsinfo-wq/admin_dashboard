<?php
/**
 * Deducts 10% trip commission fee from B2B partner's prepaid activation wallet
 * when a booking is completed.
 */
function process_partner_trip_commission($conn, $booking_id) {
    if (empty($booking_id)) return false;

    // 1. Check if booking is linked to a partner in partner_bookings
    $stmt_pb = mysqli_prepare($conn, "SELECT partner_id FROM partner_bookings WHERE booking_id = ? LIMIT 1");
    if (!$stmt_pb) return false;
    mysqli_stmt_bind_param($stmt_pb, 's', $booking_id);
    mysqli_stmt_execute($stmt_pb);
    $res_pb = mysqli_stmt_get_result($stmt_pb);
    $pb_row = mysqli_fetch_assoc($res_pb);
    mysqli_stmt_close($stmt_pb);

    if (!$pb_row || empty($pb_row['partner_id'])) {
        return false; // Not a partner booking
    }

    $partner_id = (int)$pb_row['partner_id'];

    // 2. Prevent duplicate deduction for the same booking
    $stmt_chk = mysqli_prepare($conn, "SELECT id FROM partner_wallet_transactions WHERE partner_id = ? AND booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_chk, 'is', $partner_id, $booking_id);
    mysqli_stmt_execute($stmt_chk);
    mysqli_stmt_store_result($stmt_chk);
    $exists = (mysqli_stmt_num_rows($stmt_chk) > 0);
    mysqli_stmt_close($stmt_chk);

    if ($exists) {
        return true; // Already processed
    }

    // 3. Fetch booking amount & sandbox flags from bookings table
    $stmt_b = mysqli_prepare($conn, "SELECT total_amount, is_sandbox, is_test FROM bookings WHERE booking_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_b, 's', $booking_id);
    mysqli_stmt_execute($stmt_b);
    $res_b = mysqli_stmt_get_result($stmt_b);
    $b_row = mysqli_fetch_assoc($res_b);
    mysqli_stmt_close($stmt_b);

    if (!$b_row) return false;

    // Do NOT deduct real wallet funds for Sandbox / Test Mode trips!
    if (!empty($b_row['is_sandbox']) || !empty($b_row['is_test']) || strpos($booking_id, 'TEST-') === 0) {
        return false;
    }

    $trip_amount = (float)($b_row['total_amount'] ?? 0.00);
    if ($trip_amount <= 0) return false;

    // 10% commission deduction calculation
    $commission_rate = 10.00;
    $deduction_amount = round($trip_amount * ($commission_rate / 100.0), 2);

    // 4. Fetch current partner wallet balance
    $stmt_p = mysqli_prepare($conn, "SELECT wallet_balance FROM partners WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_p, 'i', $partner_id);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);
    $p_row = mysqli_fetch_assoc($res_p);
    mysqli_stmt_close($stmt_p);

    if (!$p_row) return false;

    $balance_before = (float)($p_row['wallet_balance'] ?? 10000.00);
    $balance_after = max(0.00, round($balance_before - $deduction_amount, 2));

    // 5. Update partner wallet balance
    $stmt_up = mysqli_prepare($conn, "UPDATE partners SET wallet_balance = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_up, 'di', $balance_after, $partner_id);
    mysqli_stmt_execute($stmt_up);
    mysqli_stmt_close($stmt_up);

    // 6. Record transaction ledger entry
    $desc = "10% API Commission Fee for Trip #{$booking_id}";
    $stmt_tx = mysqli_prepare($conn, 
        "INSERT INTO partner_wallet_transactions 
         (partner_id, booking_id, trip_amount, commission_rate, deduction_amount, balance_before, balance_after, description)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt_tx, 'isddddds', 
        $partner_id, $booking_id, $trip_amount, $commission_rate, $deduction_amount, $balance_before, $balance_after, $desc
    );
    mysqli_stmt_execute($stmt_tx);
    mysqli_stmt_close($stmt_tx);

    return true;
}
