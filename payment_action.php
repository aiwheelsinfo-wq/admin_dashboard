<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if ($booking_id <= 0) {
        $_SESSION['error_msg'] = "Invalid booking ID.";
        header("Location: payment_control.php");
        exit();
    }

    // Fetch booking details for context
    $booking_q = mysqli_query($conn, "SELECT vender_id, total_amount, paid_amount, trip_type FROM bookings WHERE id = $booking_id");
    $booking = mysqli_fetch_assoc($booking_q);

    if (!$booking) {
        $_SESSION['error_msg'] = "Booking not found.";
        header("Location: payment_control.php");
        exit();
    }

    $vendor_id = $booking['vender_id'];
    $paid_amount = (double)$booking['paid_amount'];
    $trip_type = $booking['trip_type'];

    // 1. Approve Settlement
    if ($action === 'approve_settlement') {
        // Calculate earnings (90% of paid_amount, or 0 if Round-Trip)
        $earnings = ($trip_type === 'Round-Trip') ? 0.00 : ($paid_amount * 0.90);

        // Check if settlement exists
        $settlement_q = mysqli_query($conn, "SELECT id FROM vendor_settlements WHERE booking_id = $booking_id");
        if (mysqli_num_rows($settlement_q) > 0) {
            $sql = "UPDATE vendor_settlements SET status = 'Approved', earnings = $earnings WHERE booking_id = $booking_id";
        } else {
            $sql = "INSERT INTO vendor_settlements (booking_id, vendor_id, earnings, status) VALUES ($booking_id, '$vendor_id', $earnings, 'Approved')";
        }

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success_msg'] = "Settlement for Booking #$booking_id approved successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }

    // 2. Reject Settlement
    elseif ($action === 'reject_settlement') {
        $remarks = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
        $earnings = ($trip_type === 'Round-Trip') ? 0.00 : ($paid_amount * 0.90);

        $settlement_q = mysqli_query($conn, "SELECT id FROM vendor_settlements WHERE booking_id = $booking_id");
        if (mysqli_num_rows($settlement_q) > 0) {
            $sql = "UPDATE vendor_settlements SET status = 'Rejected', remarks = '$remarks' WHERE booking_id = $booking_id";
        } else {
            $sql = "INSERT INTO vendor_settlements (booking_id, vendor_id, earnings, status, remarks) VALUES ($booking_id, '$vendor_id', $earnings, 'Rejected', '$remarks')";
        }

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success_msg'] = "Settlement for Booking #$booking_id rejected.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }

    // 3. Mark Settlement Paid
    elseif ($action === 'mark_settled') {
        $settled_amount = (double)($_POST['settled_amount'] ?? 0);
        $settled_date = mysqli_real_escape_string($conn, $_POST['settled_date'] ?? date('Y-m-d'));
        $bank_reference = mysqli_real_escape_string($conn, $_POST['bank_reference'] ?? '');
        $admin_notes = mysqli_real_escape_string($conn, $_POST['remarks'] ?? '');
        $earnings = ($trip_type === 'Round-Trip') ? 0.00 : ($paid_amount * 0.90);

        // Ensure settlement row exists
        $settlement_q = mysqli_query($conn, "SELECT id FROM vendor_settlements WHERE booking_id = $booking_id");
        if (mysqli_num_rows($settlement_q) > 0) {
            $sett_row = mysqli_fetch_assoc($settlement_q);
            $settlement_id = $sett_row['id'];
            $sql = "UPDATE vendor_settlements SET 
                        status = 'Paid', 
                        settled_amount = $settled_amount, 
                        settled_date = '$settled_date', 
                        bank_reference = '$bank_reference',
                        remarks = '$admin_notes' 
                    WHERE id = $settlement_id";
            $res = mysqli_query($conn, $sql);
        } else {
            $sql = "INSERT INTO vendor_settlements (booking_id, vendor_id, earnings, status, settled_amount, settled_date, bank_reference, remarks) 
                    VALUES ($booking_id, '$vendor_id', $earnings, 'Paid', $settled_amount, '$settled_date', '$bank_reference', '$admin_notes')";
            $res = mysqli_query($conn, $sql);
            $settlement_id = mysqli_insert_id($conn);
        }

        if ($res) {
            // Log to settlement_history
            $history_sql = "INSERT INTO settlement_history (settlement_id, booking_id, amount, settled_date, bank_reference, admin_notes) 
                            VALUES ($settlement_id, $booking_id, $settled_amount, '$settled_date', '$bank_reference', '$admin_notes')";
            mysqli_query($conn, $history_sql);

            $_SESSION['success_msg'] = "Settlement for Booking #$booking_id marked as Paid and logged to history.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }

    // 4. Update Remaining Balance
    elseif ($action === 'update_balance') {
        $remaining_balance = (double)($_POST['remaining_balance'] ?? 0);
        $sql = "UPDATE bookings SET remaining_balance = $remaining_balance WHERE id = $booking_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success_msg'] = "Remaining balance for Booking #$booking_id updated to ₹$remaining_balance.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }

    // 5. Mark Balance Collected
    elseif ($action === 'mark_collected') {
        $sql = "UPDATE bookings SET collection_status = 'Collected', collection_date = '" . date('Y-m-d') . "' WHERE id = $booking_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success_msg'] = "Remaining balance for Booking #$booking_id marked as Collected by Vendor.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }

    // 6. Mark Fully Paid (Completed Collection)
    elseif ($action === 'mark_fully_paid') {
        $sql = "UPDATE bookings SET collection_status = 'Completed', collection_approved_at = NOW() WHERE id = $booking_id";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success_msg'] = "Remaining balance for Booking #$booking_id marked as Completed / Fully Paid.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . mysqli_error($conn);
        }
    }
}

header("Location: payment_control.php");
exit();
?>
