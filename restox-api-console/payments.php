<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

if (file_exists(__DIR__ . '/../../2025/razorpay_config.php')) {
    require_once __DIR__ . '/../../2025/razorpay_config.php';
}
if (!defined('RAZORPAY_ACTIVE_KEY')) {
    define('RAZORPAY_ACTIVE_KEY', 'rzp_test_GIqSfPJk12gAgz');
}

// Auth Check (must be logged in as partner)
if (!isset($_SESSION['partner_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['partner_id'];
$error = '';
$success = '';

// Handle Razorpay Payment Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_payment') {
    header('Content-Type: application/json');
    $payment_id = trim($_POST['razorpay_payment_id'] ?? '');
    $topup_amount = (float)($_POST['amount'] ?? 0);

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID.']);
        exit;
    }

    try {
        // Fetch current partner wallet balance & activation deposit required
        $stmt_get = mysqli_prepare($conn, "SELECT wallet_balance, activation_deposit_required, status FROM partners WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_get, 'i', $id);
        mysqli_stmt_execute($stmt_get);
        $res_get = mysqli_stmt_get_result($stmt_get);
        $row_get = mysqli_fetch_assoc($res_get);
        mysqli_stmt_close($stmt_get);

        $curr_balance = (float)($row_get['wallet_balance'] ?? 0.00);
        $act_req = (float)($row_get['activation_deposit_required'] ?? 10000.00);

        if ($topup_amount <= 0) {
            $topup_amount = $act_req;
        }

        $new_balance = $curr_balance + $topup_amount;

        $stmt_pay = mysqli_prepare($conn, 
            "UPDATE partners 
             SET status = 'active', payment_status = 'paid', payment_id = ?, payment_amount = payment_amount + ?, wallet_balance = ?, paid_at = NOW() 
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_pay, 'sddi', $payment_id, $topup_amount, $new_balance, $id);
        if (mysqli_stmt_execute($stmt_pay)) {
            // Record top-up credit transaction log
            $desc = "Prepaid Wallet Top-Up via Razorpay (Txn ID: {$payment_id})";
            $stmt_tx = mysqli_prepare($conn, 
                "INSERT INTO partner_wallet_transactions 
                 (partner_id, booking_id, trip_amount, commission_rate, deduction_amount, balance_before, balance_after, description)
                 VALUES (?, 'TOPUP', ?, 0.00, 0.00, ?, ?, ?)"
            );
            if ($stmt_tx) {
                mysqli_stmt_bind_param($stmt_tx, 'iddds', $id, $topup_amount, $curr_balance, $new_balance, $desc);
                mysqli_stmt_execute($stmt_tx);
                mysqli_stmt_close($stmt_tx);
            }

            echo json_encode([
                'success' => true, 
                'message' => 'Payment of ₹' . number_format($topup_amount, 2) . ' verified successfully! ₹' . number_format($topup_amount, 2) . ' credited to your wallet. New Balance: ₹' . number_format($new_balance, 2)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record payment in database.']);
        }
        mysqli_stmt_close($stmt_pay);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch latest partner details
$stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$p = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$p) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Auto-sync any completed trips that haven't been deducted yet
require_once __DIR__ . '/wallet_helper.php';
sync_unprocessed_partner_commissions($conn, $id);

// Re-fetch latest partner details (to get updated wallet_balance)
$stmt_ref = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_ref, 'i', $id);
mysqli_stmt_execute($stmt_ref);
$res_ref = mysqli_stmt_get_result($stmt_ref);
$p = mysqli_fetch_assoc($res_ref);
mysqli_stmt_close($stmt_ref);

// Fetch wallet transactions for this partner with full trip details
$wallet_txs = [];
$total_deducted = 0.00;
$total_trip_fare = 0.00;

$stmt_tx_list = mysqli_prepare($conn, 
    "SELECT pwt.*, 
            b.from_address, b.to_address, b.car_type, b.trip_type, b.date AS trip_date, b.time AS trip_time,
            b.starting_km, b.closing_km, b.mobile AS passenger_phone, b.customer_number, b.driver_id, b.vehicle_id AS booking_vehicle_id,
            pb.partner_booking_ref,
            d.full_name AS driver_name, d.phone_number AS driver_phone
     FROM partner_wallet_transactions pwt
     LEFT JOIN bookings b ON pwt.booking_id = b.booking_id
     LEFT JOIN partner_bookings pb ON (pwt.booking_id = pb.booking_id AND pwt.partner_id = pb.partner_id)
     LEFT JOIN drivers d ON (b.driver_id = d.phone_number OR b.driver_id = d.driver_id)
     WHERE pwt.partner_id = ? 
     ORDER BY pwt.id DESC LIMIT 100"
);
if ($stmt_tx_list) {
    mysqli_stmt_bind_param($stmt_tx_list, 'i', $id);
    mysqli_stmt_execute($stmt_tx_list);
    $res_tx = mysqli_stmt_get_result($stmt_tx_list);
    while ($row_tx = mysqli_fetch_assoc($res_tx)) {
        $wallet_txs[] = $row_tx;
        $total_deducted += (float)($row_tx['deduction_amount'] ?? 0);
        $total_trip_fare += (float)($row_tx['total_fare'] ?? 0);
    }
    mysqli_stmt_close($stmt_tx_list);
}

$is_paid = (($p['payment_status'] ?? '') === 'paid' || ($p['status'] ?? '') === 'active');
$activation_deposit_required = (float)($p['activation_deposit_required'] ?? 10000.00);
$commission_rate = (float)($p['commission_rate'] ?? 10.00);
$initial_deposit = (float)($p['initial_deposit'] ?? 10000.00);

$wallet_balance = $is_paid ? (float)($p['wallet_balance'] ?? $activation_deposit_required) : 0.00;
$payment_id_val = $p['payment_id'] ?? 'N/A';
$paid_at_val = !empty($p['paid_at']) ? date('d M Y, h:i A', strtotime($p['paid_at'])) : 'N/A';
$paid_amount_val = !empty($p['payment_amount']) ? number_format($p['payment_amount'], 2) : number_format($activation_deposit_required, 2);
$invoice_no = 'INV-RENTOX-100' . $p['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments & Wallet | Rentox B2B Console</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --bg-base: #0B0F17;
            --bg-card: #111827;
            --border-color: rgba(255, 255, 255, 0.08);
            --primary-accent: #6366F1;
            --secondary-accent: #3B82F6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --text-main: #F9FAFB;
            --text-secondary: #9CA3AF;
            --sidebar-width: 260px;
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .ambient-glow {
            position: fixed; border-radius: 50%; filter: blur(140px); z-index: -1; opacity: 0.25; pointer-events: none;
        }
        .glow-1 { top: -10%; left: 20%; width: 450px; height: 450px; background: var(--primary-accent); }
        .glow-2 { bottom: 10%; right: 5%; width: 400px; height: 400px; background: var(--success-color); }

        .app-container { display: flex; min-height: 100vh; }

        /* === SIDEBAR === */
        .sidebar {
            width: var(--sidebar-width);
            background-color: rgba(15, 23, 42, 0.97);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 1000;
            overflow-x: hidden;
            transition: width 0.25s cubic-bezier(0.4,0,0.2,1);
        }

        .sidebar-brand {
            height: 80px; padding: 0 18px;
            display: flex; align-items: center; justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.06); flex-shrink: 0;
        }

        .brand-block { display: flex; align-items: center; gap: 12px; overflow: hidden; white-space: nowrap; }

        .brand-logo-icon {
            font-size: 1.2rem; color: var(--primary-accent);
            background: rgba(99,102,241,0.12); padding: 8px; border-radius: 10px;
            border: 1px solid rgba(99,102,241,0.25); flex-shrink: 0;
        }

        .brand-title-group { display: flex; flex-direction: column; line-height: 1.15; }
        .brand-title { font-size: 1.02rem; font-weight: 800; color: #FFF; letter-spacing: -0.3px; }
        .brand-sub-badge { font-size: 0.6rem; font-weight: 800; color: #A5B4FC; letter-spacing: 1.5px; text-transform: uppercase; }

        .sidebar-nav {
            padding: 14px 12px;
            display: flex; flex-direction: column; gap: 3px;
            flex: 1; overflow-y: auto; overflow-x: hidden;
        }
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 4px; }

        .nav-section-label {
            font-size: 0.64rem; font-weight: 800; letter-spacing: 1.5px;
            color: #64748B; padding: 12px 10px 4px;
            text-transform: uppercase; white-space: nowrap;
        }

        .nav-item {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; color: var(--text-secondary); text-decoration: none;
            padding: 10px 12px; border-radius: 10px;
            font-size: 0.87rem; font-weight: 600;
            transition: all 0.18s ease; position: relative;
            white-space: nowrap; height: 44px;
        }
        .nav-item-content { display: flex; align-items: center; gap: 12px; overflow: hidden; }
        .nav-item i { font-size: 1rem; width: 20px; text-align: center; flex-shrink: 0; color: #94A3B8; transition: color 0.18s; }
        .nav-item:hover { color: #FFF; background: rgba(255,255,255,0.04); }
        .nav-item:hover i { color: #FFF; }
        .nav-item.active { color: #FFF; background: rgba(99,102,241,0.12); border: 1px solid rgba(99,102,241,0.25); }
        .nav-item.active i { color: var(--primary-accent); }
        .nav-item.active::before {
            content: ''; position: absolute; left: 0; top: 14%; bottom: 14%;
            width: 3.5px; background: var(--primary-accent); border-radius: 0 4px 4px 0;
            box-shadow: 0 0 8px var(--primary-accent);
        }

        .nav-badge {
            font-size: 0.7rem; font-weight: 700; padding: 3px 7px;
            border-radius: 12px; white-space: nowrap; line-height: 1;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .badge-green  { background: rgba(16,185,129,0.15); color: #34D399; border: 1px solid rgba(16,185,129,0.3); }
        .badge-orange { background: rgba(245,158,11,0.15); color: #FBBF24; border: 1px solid rgba(245,158,11,0.3); }
        .badge-neutral{ background: rgba(255,255,255,0.06); color: var(--text-secondary); border: 1px solid rgba(255,255,255,0.1); }

        .sidebar-system-status {
            background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 12px; padding: 10px 12px;
            margin: 0 12px 10px;
            display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .status-dot-pulse {
            width: 8px; height: 8px; border-radius: 50%;
            background: var(--success-color); box-shadow: 0 0 8px var(--success-color); flex-shrink: 0;
        }
        .system-status-title { font-size: 0.78rem; font-weight: 700; color: #FFF; }
        .system-status-sub  { font-size: 0.7rem; color: var(--text-secondary); }

        .sidebar-footer {
            padding: 14px 12px; border-top: 1px solid rgba(255,255,255,0.06);
            display: flex; flex-direction: column; gap: 10px;
            flex-shrink: 0; background: rgba(7,11,20,0.4);
        }
        .sidebar-user-card { display: flex; align-items: center; gap: 12px; overflow: hidden; }
        .user-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.95rem; color: #FFF;
            border: 1px solid rgba(255,255,255,0.15); flex-shrink: 0;
        }
        .user-info { display: flex; flex-direction: column; overflow: hidden; line-height: 1.3; }
        .user-company { font-size: 0.86rem; font-weight: 700; color: #FFF; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .user-role { font-size: 0.72rem; color: var(--text-secondary); }
        .user-status-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 0.68rem; font-weight: 700; margin-top: 2px; }
        .user-status-pill.status-green  { color: #34D399; }
        .user-status-pill.status-orange { color: #FBBF24; }

        .btn-logout-sidebar {
            display: flex; align-items: center; justify-content: center; gap: 8px;
            background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.15);
            color: #FCA5A5; padding: 9px; border-radius: 10px; text-decoration: none;
            font-size: 0.85rem; font-weight: 600; transition: all 0.25s ease;
            cursor: pointer; width: 100%;
        }
        .btn-logout-sidebar:hover { background: var(--danger-color); color: #FFF; border-color: var(--danger-color); }

        .main-workspace { margin-left: var(--sidebar-width); flex: 1; padding: 36px 40px; }

        /* Header & Breadcrumb */
        .breadcrumb-nav {
            font-size: 0.82rem; color: var(--text-secondary); font-weight: 500;
            margin-bottom: 8px; display: flex; align-items: center; gap: 8px;
        }
        .breadcrumb-nav .active { color: var(--primary-accent); font-weight: 600; }

        .page-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
            gap: 20px; flex-wrap: wrap;
        }
        .page-header h1 { font-size: 1.8rem; font-weight: 800; color: #FFF; margin-bottom: 4px; }
        .page-header p { color: var(--text-secondary); font-size: 0.92rem; }

        /* Large Wallet Overview 2-Column Grid */
        .wallet-hero-grid {
            display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 24px; margin-bottom: 28px;
        }
        @media (max-width: 992px) { .wallet-hero-grid { grid-template-columns: 1fr; } }

        .wallet-card-primary {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.95), rgba(15, 23, 42, 0.85));
            border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 20px;
            padding: 32px; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        .wallet-card-primary::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #10B981, #6366F1);
        }

        .wallet-card-secondary {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.95), rgba(15, 23, 42, 0.85));
            border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 20px;
            padding: 32px; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        .wallet-card-secondary::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #F59E0B, #10B981);
        }

        .balance-amount-display {
            font-size: 2.8rem; font-weight: 800; font-family: var(--font-mono); color: #FFF;
            margin: 10px 0; letter-spacing: -0.5px;
        }

        /* 4-Step Wallet Flow Component */
        .wallet-flow-card {
            background: rgba(17, 24, 39, 0.6); border: 1px solid var(--border-color);
            border-radius: 18px; padding: 24px 28px; margin-bottom: 28px;
        }
        .flow-steps-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-top: 18px;
        }
        @media (max-width: 992px) { .flow-steps-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .flow-steps-grid { grid-template-columns: 1fr; } }

        .flow-step-item {
            background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 14px; padding: 18px; position: relative;
        }
        .flow-step-num {
            font-size: 0.75rem; font-weight: 800; color: var(--primary-accent); font-family: var(--font-mono);
            margin-bottom: 6px; letter-spacing: 1px;
        }
        .flow-step-title { font-size: 0.95rem; font-weight: 700; color: #FFF; margin-bottom: 4px; }
        .flow-step-desc { font-size: 0.8rem; color: var(--text-secondary); line-height: 1.45; }

        /* Metrics Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .metric-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 20px; position: relative; overflow: hidden;
        }
        .metric-label { color: var(--text-secondary); font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-value { font-size: 1.6rem; font-weight: 800; margin: 8px 0 4px; }
        .metric-desc { font-size: 0.82rem; color: var(--text-secondary); }

        /* Panel Card */
        .panel-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 18px; padding: 28px; margin-bottom: 28px;
        }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; color: #FFF; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px;
            border-radius: 20px; font-size: 0.82rem; font-weight: 700;
        }
        .badge-paid { background: rgba(16, 185, 129, 0.15); color: var(--success-color); border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-unpaid { background: rgba(245, 158, 11, 0.15); color: var(--warning-color); border: 1px solid rgba(245, 158, 11, 0.3); }

        /* Timeline Stepper */
        .progress-stepper {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            background: rgba(255,255,255,0.02); border: 1px solid var(--border-color);
            border-radius: 14px; padding: 16px 20px; margin-top: 16px; flex-wrap: wrap;
        }
        .p-step { display: flex; align-items: center; gap: 8px; font-size: 0.86rem; font-weight: 600; color: var(--text-secondary); }
        .p-step.done { color: #34D399; }
        .p-step.active { color: #FFF; }
        .p-divider { height: 1px; flex: 1; background: rgba(255,255,255,0.08); min-width: 20px; }
        @media (max-width: 768px) { .p-divider { display: none; } }

        /* Tables */
        table.custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.custom-table th { padding: 14px 16px; font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        table.custom-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
        table.custom-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        /* Documents Grid */
        .docs-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px;
        }
        .doc-card {
            background: rgba(255,255,255,0.02); border: 1px solid var(--border-color);
            border-radius: 14px; padding: 18px; display: flex; align-items: center; justify-content: space-between;
        }

        /* Buttons */
        .btn-pay-hero {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #FFF; border: none; border-radius: 12px; padding: 14px 28px;
            font-size: 1rem; font-weight: 800; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35); transition: all 0.25s ease;
        }
        .btn-pay-hero:hover {
            transform: translateY(-2px); box-shadow: 0 12px 30px rgba(16, 185, 129, 0.5); filter: brightness(1.08);
        }

        .btn-print {
            background: rgba(99, 102, 241, 0.15); color: var(--primary-accent);
            border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 10px; padding: 10px 18px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-print:hover { background: var(--primary-accent); color: #FFF; }

        .btn-view-details {
            background: rgba(99, 102, 241, 0.15); color: #818CF8;
            border: 1px solid rgba(99, 102, 241, 0.35); border-radius: 8px;
            padding: 7px 14px; font-size: 0.82rem; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease;
        }
        .btn-view-details:hover {
            background: var(--primary-accent); color: #FFF; border-color: var(--primary-accent);
            transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
        }

        /* Preset Amount Chips & Custom Input */
        .chip-container {
            display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; margin-bottom: 20px;
        }
        .preset-chip {
            background: rgba(255,255,255,0.05); border: 1px solid var(--border-color);
            color: #FFF; padding: 9px 16px; border-radius: 10px; font-weight: 700;
            font-size: 0.88rem; cursor: pointer; transition: all 0.2s ease;
        }
        .preset-chip:hover {
            background: rgba(16, 185, 129, 0.15); border-color: #10B981; color: #34D399;
        }
        .preset-chip.active {
            background: linear-gradient(135deg, #10B981, #059669);
            border-color: #10B981; color: #FFF; box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }

        .custom-amount-input {
            width: 100%; background: rgba(7, 11, 20, 0.6); border: 1px solid var(--border-color);
            border-radius: 12px; padding: 14px 18px; color: #FFF; font-size: 1.3rem; font-weight: 800;
            font-family: var(--font-mono); outline: none; transition: border 0.2s ease; box-sizing: border-box;
        }
        .custom-amount-input:focus {
            border-color: #10B981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
        }

        /* Modal Window Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(11, 15, 23, 0.85); backdrop-filter: blur(12px);
            display: none; justify-content: center; align-items: center; z-index: 3000; padding: 20px;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.22s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: scale(1); } }

        .modal-content {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 20px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0,0,0,0.7); position: relative;
        }
        .modal-header {
            padding: 22px 28px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.02);
        }
        .modal-title { font-size: 1.2rem; font-weight: 800; display: flex; align-items: center; gap: 10px; color: #FFF; }
        .modal-close {
            background: rgba(255,255,255,0.06); border: none; color: var(--text-secondary);
            width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: 1rem; transition: all 0.2s ease;
        }
        .modal-close:hover { background: rgba(239, 68, 68, 0.2); color: #FFF; }

        .modal-body { padding: 28px; display: flex; flex-direction: column; gap: 24px; }
        .m-section-title { font-size: 0.8rem; font-weight: 800; letter-spacing: 1px; color: var(--primary-accent); text-transform: uppercase; margin-bottom: 12px; }
        .m-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .m-box { background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 12px; padding: 14px 16px; }
        .m-lbl { font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 4px; display: block; }
        .m-val { font-size: 0.95rem; font-weight: 700; color: #FFF; }

        /* Toast notification */
        .toast-notification {
            position: fixed; bottom: 30px; right: 30px; background: #1E293B; color: #FFF;
            border: 1px solid var(--primary-accent); padding: 16px 24px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none; z-index: 4000; font-weight: 600;
        }

        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-workspace { margin-left: 0; padding: 20px 16px; }
        }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="app-container">

        <!-- Left Navigation Sidebar -->
        <aside class="sidebar" id="appSidebar">

            <div class="sidebar-brand">
                <div class="brand-block">
                    <i class="fa-solid fa-terminal brand-logo-icon"></i>
                    <div class="brand-title-group">
                        <span class="brand-title">Rentox API</span>
                        <span class="brand-sub-badge">Developer Console</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">

                <span class="nav-section-label">Main</span>
                <a href="dashboard.php#overview" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-chart-pie"></i><span>Dashboard</span></div>
                </a>

                <span class="nav-section-label">API &amp; Development</span>
                <a href="dashboard.php#keys" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-key"></i><span>API Credentials</span></div>
                    <?php if ($is_paid): ?>
                        <span class="nav-badge badge-green"><i class="fa-solid fa-circle" style="font-size:.5rem;"></i> Live</span>
                    <?php else: ?>
                        <span class="nav-badge badge-neutral">Sandbox</span>
                    <?php endif; ?>
                </a>
                <a href="test-trips.php" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-flask-vial"></i><span>Test Trips Simulator</span></div>
                </a>
                <a href="dashboard.php#docs" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-book"></i><span>API Documentation</span></div>
                </a>

                <span class="nav-section-label">Billing &amp; Activity</span>
                <a href="payments.php" class="nav-item active">
                    <div class="nav-item-content"><i class="fa-solid fa-wallet"></i><span>Payments &amp; Wallet</span></div>
                    <?php if (!$is_paid): ?>
                        <span class="nav-badge badge-orange"><i class="fa-solid fa-circle" style="font-size:.5rem;"></i> Required</span>
                    <?php endif; ?>
                </a>
                <a href="dashboard.php#logs" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-list-check"></i><span>Activity Logs</span></div>
                </a>

                <span class="nav-section-label">Account</span>
                <a href="dashboard.php#settings" class="nav-item">
                    <div class="nav-item-content"><i class="fa-solid fa-sliders"></i><span>Account Settings</span></div>
                </a>

            </nav>

            <div class="sidebar-system-status">
                <div class="status-dot-pulse"></div>
                <div>
                    <div class="system-status-title">All Systems Operational</div>
                    <div class="system-status-sub">Production API · Sandbox · Billing</div>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="sidebar-user-card">
                    <div class="user-avatar"><?= strtoupper(substr($p['company_name'], 0, 2)) ?></div>
                    <div class="user-info">
                        <span class="user-company"><?= htmlspecialchars($p['company_name']) ?></span>
                        <span class="user-role">B2B Integration</span>
                        <?php if ($is_paid): ?>
                            <span class="user-status-pill status-green"><i class="fa-solid fa-circle" style="font-size:.45rem;"></i> API Active</span>
                        <?php else: ?>
                            <span class="user-status-pill status-orange"><i class="fa-solid fa-circle" style="font-size:.45rem;"></i> Activation Required</span>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="dashboard.php?action=logout" class="btn-logout-sidebar" onclick="confirmPartnerLogout(event)">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Log Out</span>
                </a>
            </div>

        </aside>

        <!-- Main Workspace -->
        <main class="main-workspace">

            <!-- Breadcrumb & Page Header -->
            <div class="breadcrumb-nav">
                <span>Dashboard</span> <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i> <span class="active">Payments & Wallet</span>
            </div>

            <div class="page-header">
                <div>
                    <h1>Payments & Wallet</h1>
                    <p>Manage your prepaid API balance, trip commissions, payments, and billing history.</p>
                </div>
                <div style="display:flex; align-items:center; gap:12px;">
                    <?php if ($is_paid): ?>
                        <span class="status-badge badge-paid"><i class="fa-solid fa-circle-check"></i> Wallet Active</span>
                    <?php else: ?>
                        <span class="status-badge badge-unpaid"><i class="fa-solid fa-clock"></i> Payment Pending</span>
                    <?php endif; ?>

                    <button class="btn-print" onclick="window.print()">
                        <i class="fa-solid fa-download"></i> Download Invoice
                    </button>
                </div>
            </div>

            <!-- 1. Wallet Overview — 2-Column Hero Grid -->
            <div class="wallet-hero-grid">
                
                <!-- Left Card: Available Wallet Balance -->
                <div class="wallet-card-primary">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span style="font-size:0.82rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px;">
                                <i class="fa-solid fa-wallet" style="color:var(--success-color);"></i> Available Wallet Balance
                            </span>
                            <?php if ($wallet_balance > 0): ?>
                                <span class="status-badge badge-paid"><i class="fa-solid fa-check"></i> Wallet Active</span>
                            <?php else: ?>
                                <span class="status-badge badge-unpaid" style="background:rgba(239,68,68,0.15); color:#FCA5A5; border-color:rgba(239,68,68,0.3);"><i class="fa-solid fa-circle-exclamation"></i> Wallet Empty</span>
                            <?php endif; ?>
                        </div>

                        <div class="balance-amount-display">
                            ₹<?= number_format($wallet_balance, 2) ?>
                        </div>

                        <p style="color:var(--text-secondary); font-size:0.88rem; margin-top:4px;">
                            Add funds to your prepaid wallet to process live API trips and automated deductions.
                        </p>
                    </div>

                    <div style="display:flex; gap:12px; margin-top:24px; flex-wrap:wrap;">
                        <button class="btn-pay-hero" onclick="openTopUpModal()" style="flex:1; padding:12px 20px; font-size:0.92rem;">
                            <i class="fa-solid fa-plus-circle"></i> Add Funds to Wallet
                        </button>
                        <button class="btn-print" onclick="document.getElementById('txHistorySec').scrollIntoView({behavior:'smooth'})" style="padding:12px 20px;">
                            <i class="fa-solid fa-list"></i> View Transactions
                        </button>
                    </div>
                </div>

                <!-- Right Card: Initial Activation Deposit -->
                <div class="wallet-card-secondary">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <span style="font-size:0.82rem; font-weight:700; color:var(--text-secondary); text-transform:uppercase; letter-spacing:1px;">
                                <i class="fa-solid fa-shield-halved" style="color:var(--warning-color);"></i> Initial Activation Deposit
                            </span>
                            <span class="status-badge" style="background:rgba(99,102,241,0.15); color:#A5B4FC; border:1px solid rgba(99,102,241,0.3);">
                                One-Time Initial Deposit
                            </span>
                        </div>

                        <div class="balance-amount-display" style="color:#FBBF24;">
                            ₹<?= number_format($activation_deposit_required, 2) ?>
                        </div>

                        <p style="color:var(--text-secondary); font-size:0.88rem; margin-top:4px;">
                            Required to activate production API access and fund your initial prepaid wallet.
                        </p>
                    </div>

                    <div style="margin-top:20px;">
                        <?php if (!$is_paid): ?>
                            <button class="btn-pay-hero" onclick="payWithRazorpay()" id="btnPayMain" style="width:100%;">
                                <i class="fa-solid fa-credit-card"></i> Pay ₹<?= number_format($activation_deposit_required) ?> via Razorpay
                            </button>
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; font-size:0.78rem; color:var(--text-secondary);">
                                <span>UPI • Credit Card • Debit Card • NetBanking</span>
                                <span><i class="fa-solid fa-lock"></i> Razorpay Secure</span>
                            </div>
                        <?php else: ?>
                            <div style="background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); border-radius:12px; padding:14px; text-align:center;">
                                <span style="color:var(--success-color); font-weight:700; font-size:0.95rem;">
                                    <i class="fa-solid fa-circle-check"></i> Initial Deposit Completed & Credited
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- 2. How Your API Wallet Works Section -->
            <div class="wallet-flow-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-size:1.15rem; font-weight:800; color:#FFF; display:flex; align-items:center; gap:10px;">
                        <i class="fa-solid fa-diagram-project" style="color:var(--primary-accent);"></i> How Your API Wallet Works
                    </h3>
                    <span style="font-size:0.82rem; color:var(--text-secondary);">Prepaid API Billing Model</span>
                </div>

                <div class="flow-steps-grid">
                    <div class="flow-step-item">
                        <div class="flow-step-num">STEP 01</div>
                        <div class="flow-step-title">Add Funds</div>
                        <div class="flow-step-desc">Pay the initial ₹<?= number_format($activation_deposit_required) ?> deposit to fund your prepaid API wallet.</div>
                    </div>

                    <div class="flow-step-item">
                        <div class="flow-step-num">STEP 02</div>
                        <div class="flow-step-title">API Trip</div>
                        <div class="flow-step-desc">A customer completed trip is processed and booked via your API key.</div>
                    </div>

                    <div class="flow-step-item">
                        <div class="flow-step-num">STEP 03</div>
                        <div class="flow-step-title">10% Commission</div>
                        <div class="flow-step-desc">A <?= $commission_rate ?>% API commission is calculated based on the completed trip fare.</div>
                    </div>

                    <div class="flow-step-item">
                        <div class="flow-step-num">STEP 04</div>
                        <div class="flow-step-title">Wallet Deduction</div>
                        <div class="flow-step-desc">The 10% commission is automatically deducted from your prepaid balance.</div>
                    </div>
                </div>
            </div>

            <!-- 3. Wallet Statistics Row -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-label">Wallet Balance</span>
                    <div class="metric-value" style="color:var(--success-color);">₹<?= number_format($wallet_balance, 2) ?></div>
                    <span class="metric-desc"><i class="fa-solid fa-wallet"></i> Available for commission deductions</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Total Deposited</span>
                    <div class="metric-value" style="color:#FFF;">₹<?= number_format($is_paid ? $activation_deposit_required : 0, 2) ?></div>
                    <span class="metric-desc"><i class="fa-solid fa-arrow-down-to-line"></i> Lifetime wallet deposits</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Total Commission Deducted</span>
                    <div class="metric-value" style="color:var(--warning-color);">₹<?= number_format($total_deducted, 2) ?></div>
                    <span class="metric-desc"><i class="fa-solid fa-percent"></i> <?= $commission_rate ?>% per-trip fee</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Completed Trips</span>
                    <div class="metric-value" style="color:#818CF8;"><?= count($wallet_txs) ?></div>
                    <span class="metric-desc"><i class="fa-solid fa-taxi"></i> Total billed API trips</span>
                </div>
            </div>

            <!-- 4. Current Payment Required Alert / Activation Progress Timeline -->
            <?php if (!$is_paid): ?>
                <div class="panel-card" style="border-color:rgba(245,158,11,0.3); background:rgba(245,158,11,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:20px;">
                        <div>
                            <span class="status-badge badge-unpaid" style="margin-bottom:12px;">
                                <i class="fa-solid fa-triangle-exclamation"></i> Activation Payment Required
                            </span>
                            <h3 style="font-size:1.3rem; font-weight:800; color:#FFF; margin-bottom:8px;">
                                Add ₹<?= number_format($activation_deposit_required) ?> to Activate Your API Wallet
                            </h3>
                            <p style="color:var(--text-secondary); font-size:0.92rem; max-width:640px; line-height:1.5;">
                                Your B2B Partner Account has been approved by Admin! Complete the initial ₹<?= number_format($activation_deposit_required) ?> prepaid wallet deposit to activate your Live Production API Access.
                            </p>

                            <div style="display:flex; gap:16px; flex-wrap:wrap; margin-top:16px; font-size:0.88rem; color:#D1D5DB;">
                                <span><i class="fa-solid fa-check" style="color:var(--success-color);"></i> Production API access</span>
                                <span><i class="fa-solid fa-check" style="color:var(--success-color);"></i> Live API credentials</span>
                                <span><i class="fa-solid fa-check" style="color:var(--success-color);"></i> Prepaid wallet activated</span>
                                <span><i class="fa-solid fa-check" style="color:var(--success-color);"></i> API trip billing enabled</span>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <div style="font-size:1.8rem; font-weight:800; color:#FFF; font-family:var(--font-mono);">
                                ₹<?= number_format($activation_deposit_required) ?>
                            </div>
                            <span style="font-size:0.8rem; color:var(--text-secondary); display:block; margin-bottom:12px;">One-time initial deposit</span>
                            <button class="btn-pay-hero" onclick="payWithRazorpay()">
                                <i class="fa-solid fa-credit-card"></i> Pay ₹<?= number_format($activation_deposit_required) ?> via Razorpay
                            </button>
                        </div>
                    </div>

                    <!-- Timeline Stepper -->
                    <div class="progress-stepper">
                        <div class="p-step done"><i class="fa-solid fa-circle-check"></i> Account Approved</div>
                        <div class="p-divider"></div>
                        <div class="p-step active" style="color:#FBBF24;"><i class="fa-solid fa-circle-dot"></i> Initial Deposit (Pending)</div>
                        <div class="p-divider"></div>
                        <div class="p-step"><i class="fa-solid fa-circle"></i> Wallet Activated</div>
                        <div class="p-divider"></div>
                        <div class="p-step"><i class="fa-solid fa-circle"></i> Production API</div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Successful Payment Active State Panel -->
                <div class="panel-card" style="border-color:rgba(16,185,129,0.3); background:rgba(16,185,129,0.04);">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:20px;">
                        <div>
                            <span class="status-badge badge-paid" style="margin-bottom:10px;">
                                <i class="fa-solid fa-circle-check"></i> Wallet Activated Successfully
                            </span>
                            <h3 style="font-size:1.3rem; font-weight:800; color:#FFF; margin-bottom:6px;">
                                Production API Access & Wallet Active
                            </h3>
                            <p style="color:var(--text-secondary); font-size:0.92rem;">
                                Your ₹<?= number_format($activation_deposit_required) ?> payment has been received and added to your prepaid API wallet balance.
                            </p>
                        </div>

                        <div style="display:flex; gap:12px; flex-wrap:wrap;">
                            <a href="dashboard.php#keys" class="btn-pay-hero" style="background:linear-gradient(135deg, var(--primary-accent), #4F46E5); font-size:0.88rem; padding:10px 18px; text-decoration:none;">
                                <i class="fa-solid fa-key"></i> View API Credentials
                            </a>
                            <a href="dashboard.php#docs" class="btn-print" style="text-decoration:none;">
                                <i class="fa-solid fa-book"></i> View Documentation
                            </a>
                        </div>
                    </div>

                    <!-- Timeline Stepper -->
                    <div class="progress-stepper">
                        <div class="p-step done"><i class="fa-solid fa-circle-check"></i> Account Approved</div>
                        <div class="p-divider"></div>
                        <div class="p-step done"><i class="fa-solid fa-circle-check"></i> Initial Deposit Completed</div>
                        <div class="p-divider"></div>
                        <div class="p-step done"><i class="fa-solid fa-circle-check"></i> Wallet Activated</div>
                        <div class="p-divider"></div>
                        <div class="p-step done"><i class="fa-solid fa-circle-check"></i> Production API Active</div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 5. Recent Transactions Table Section -->
            <div class="panel-card" id="txHistorySec">
                <div class="card-header-flex">
                    <h3 class="card-title">
                        <i class="fa-solid fa-clock-rotate-left" style="color:var(--primary-accent);"></i> Recent Wallet Transactions
                    </h3>
                    <span style="font-size:0.85rem; color:var(--text-secondary);">Last 100 Transactions</span>
                </div>

                <div style="overflow-x:auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Transaction / Booking Ref</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Wallet Balance</th>
                                <th>Status</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($is_paid): ?>
                                <!-- Credit Initial Deposit Row -->
                                <tr>
                                    <td><?= $paid_at_val ?></td>
                                    <td>
                                        <div style="font-weight:700; color:#FFF;">Initial Wallet Deposit</div>
                                        <div style="font-size:0.78rem; color:var(--text-secondary); font-family:var(--font-mono);">Payment ID: <?= htmlspecialchars($payment_id_val) ?></div>
                                    </td>
                                    <td><span style="color:#34D399; font-weight:700; font-size:0.82rem; background:rgba(16,185,129,0.12); padding:3px 8px; border-radius:6px;">Credit</span></td>
                                    <td style="font-family:var(--font-mono); font-weight:700; color:#34D399;">+₹<?= number_format($activation_deposit_required, 2) ?></td>
                                    <td style="font-family:var(--font-mono); font-weight:600;">₹<?= number_format($activation_deposit_required, 2) ?></td>
                                    <td><span class="status-badge badge-paid"><i class="fa-solid fa-check"></i> Completed</span></td>
                                    <td><button class="btn-view-details" onclick="window.print()"><i class="fa-solid fa-receipt"></i> Invoice</button></td>
                                </tr>
                            <?php endif; ?>

                            <?php if (!empty($wallet_txs)): ?>
                                <?php foreach ($wallet_txs as $tx): ?>
                                    <?php if ($tx['booking_id'] === 'TOPUP' || (float)($tx['deduction_amount'] ?? 0) <= 0): ?>
                                        <tr>
                                            <td><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                            <td>
                                                <div style="font-weight:700; color:#FFF;">Prepaid Wallet Top-Up</div>
                                                <div style="font-size:0.78rem; color:var(--text-secondary); font-family:var(--font-mono);"><?= htmlspecialchars($tx['description'] ?? 'Wallet Credit') ?></div>
                                            </td>
                                            <td><span style="color:#34D399; font-weight:700; font-size:0.82rem; background:rgba(16,185,129,0.12); padding:3px 8px; border-radius:6px;">Credit</span></td>
                                            <td style="font-family:var(--font-mono); font-weight:700; color:#34D399;">+₹<?= number_format($tx['trip_amount'], 2) ?></td>
                                            <td style="font-family:var(--font-mono); font-weight:600;">₹<?= number_format($tx['balance_after'], 2) ?></td>
                                            <td><span class="status-badge badge-paid"><i class="fa-solid fa-check"></i> Completed</span></td>
                                            <td><button class="btn-view-details" onclick="window.print()"><i class="fa-solid fa-receipt"></i> Receipt</button></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td><?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?></td>
                                            <td>
                                                <div style="font-weight:700; color:#FFF; font-family:var(--font-mono);"><?= htmlspecialchars($tx['partner_booking_ref'] ?? ('BOOK-' . $tx['booking_id'])) ?></div>
                                                <div style="font-size:0.78rem; color:var(--text-secondary);"><?= htmlspecialchars($tx['car_type'] ?? 'Cab Booking') ?> • Trip #<?= $tx['booking_id'] ?></div>
                                            </td>
                                            <td><span style="color:#FBBF24; font-weight:700; font-size:0.82rem; background:rgba(245,158,11,0.12); padding:3px 8px; border-radius:6px;">Debit</span></td>
                                            <td style="font-family:var(--font-mono); font-weight:700; color:#FBBF24;">-₹<?= number_format($tx['deduction_amount'], 2) ?></td>
                                            <td style="font-family:var(--font-mono); font-weight:600;">₹<?= number_format($tx['balance_after'], 2) ?></td>
                                            <td><span class="status-badge badge-paid"><i class="fa-solid fa-check"></i> Deducted</span></td>
                                            <td>
                                                <button class="btn-view-details" onclick='openTripModal(<?= json_encode($tx, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>)'>
                                                    <i class="fa-solid fa-eye"></i> View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:32px 10px; color:var(--text-secondary);">
                                        <i class="fa-solid fa-receipt" style="font-size:2rem; opacity:0.3; margin-bottom:8px; display:block;"></i>
                                        No trip commission deductions recorded yet.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 6. API Commission Summary & Billing Documents Grid -->
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:28px;">
                
                <!-- API Commission Summary Card -->
                <div class="panel-card" style="margin-bottom:0;">
                    <h3 class="card-title" style="margin-bottom:18px;">
                        <i class="fa-solid fa-calculator" style="color:var(--primary-accent);"></i> API Commission Summary
                    </h3>
                    <div style="display:flex; flex-direction:column; gap:12px; font-size:0.9rem;">
                        <div style="display:flex; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.04);">
                            <span style="color:var(--text-secondary);">Total Completed Trips</span>
                            <span style="font-weight:700; color:#FFF;"><?= count($wallet_txs) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.04);">
                            <span style="color:var(--text-secondary);">Total Trip Value</span>
                            <span style="font-weight:700; color:#FFF; font-family:var(--font-mono);">₹<?= number_format($total_trip_fare, 2) ?></span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding-bottom:8px; border-bottom:1px solid rgba(255,255,255,0.04);">
                            <span style="color:var(--text-secondary);">Commission Rate</span>
                            <span style="font-weight:700; color:#34D399;"><?= $commission_rate ?>% per trip</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; padding-top:4px; font-weight:800; font-size:1rem;">
                            <span style="color:var(--text-secondary);">Total Commission Deducted</span>
                            <span style="color:var(--warning-color); font-family:var(--font-mono);">₹<?= number_format($total_deducted, 2) ?></span>
                        </div>
                    </div>
                    <p style="font-size:0.8rem; color:var(--text-secondary); margin-top:16px; line-height:1.4;">
                        <i class="fa-solid fa-circle-info" style="color:var(--primary-accent);"></i> <?= $commission_rate ?>% commission is calculated from completed API trips and automatically deducted from your prepaid wallet balance.
                    </p>
                </div>

                <!-- Billing Documents Card -->
                <div class="panel-card" style="margin-bottom:0;">
                    <h3 class="card-title" style="margin-bottom:18px;">
                        <i class="fa-solid fa-folder-open" style="color:var(--success-color);"></i> Billing Documents
                    </h3>

                    <div class="docs-grid">
                        <div class="doc-card">
                            <div>
                                <strong style="display:block; font-size:0.9rem; color:#FFF;">Initial Deposit Invoice</strong>
                                <span style="font-size:0.78rem; color:var(--text-secondary);"><?= $invoice_no ?></span>
                            </div>
                            <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> View</button>
                        </div>

                        <div class="doc-card">
                            <div>
                                <strong style="display:block; font-size:0.9rem; color:#FFF;">Payment Receipt</strong>
                                <span style="font-size:0.78rem; color:var(--text-secondary);"><?= $payment_id_val ?></span>
                            </div>
                            <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-receipt"></i> View</button>
                        </div>

                        <div class="doc-card">
                            <div>
                                <strong style="display:block; font-size:0.9rem; color:#FFF;">Monthly Statement</strong>
                                <span style="font-size:0.78rem; color:var(--text-secondary);"><?= date('F Y') ?></span>
                            </div>
                            <button class="btn-print" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> View</button>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- Interactive Trip Details Modal Popup -->
    <div class="modal-overlay" id="tripModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-receipt" style="color:var(--primary-accent);"></i>
                    <span id="m_ref_title">Trip Commission Details</span>
                </div>
                <button class="modal-close" onclick="closeTripModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div>
                    <div class="m-section-title"><i class="fa-solid fa-route"></i> Route & Trip Schedule</div>
                    <div class="m-box" style="margin-bottom:12px;">
                        <span class="m-lbl">Pickup & Dropoff Route</span>
                        <div style="font-weight:700; font-size:1rem; color:#FFF;" id="m_route">--</div>
                    </div>
                    
                    <div class="m-grid">
                        <div class="m-box">
                            <span class="m-lbl">Date & Pickup Time</span>
                            <div class="m-val" id="m_date_time">--</div>
                        </div>
                        <div class="m-box">
                            <span class="m-lbl">Vehicle & Trip Type</span>
                            <div class="m-val" id="m_car_trip_type">--</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="m-section-title"><i class="fa-solid fa-gauge-high"></i> Odometer Readings</div>
                    <div class="m-grid">
                        <div class="m-box">
                            <span class="m-lbl">Starting Odometer KM</span>
                            <div class="m-val" id="m_starting_km">--</div>
                        </div>
                        <div class="m-box">
                            <span class="m-lbl">Closing Odometer KM</span>
                            <div class="m-val" id="m_closing_km">--</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="m-section-title"><i class="fa-solid fa-user-gear"></i> Passenger & Driver Info</div>
                    <div class="m-grid">
                        <div class="m-box">
                            <span class="m-lbl">Passenger Contact</span>
                            <div class="m-val" id="m_passenger_phone">--</div>
                        </div>
                        <div class="m-box">
                            <span class="m-lbl">Assigned Driver & Vehicle RC</span>
                            <div class="m-val" id="m_driver_info">--</div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="m-section-title"><i class="fa-solid fa-file-invoice-dollar"></i> Financial & Wallet Breakdown</div>
                    <div class="m-grid">
                        <div class="m-box">
                            <span class="m-lbl">Total Trip Fare</span>
                            <div class="m-val" style="color:#FFF;" id="m_total_fare">₹0.00</div>
                        </div>
                        <div class="m-box" style="background:rgba(245,158,11,0.08); border-color:rgba(245,158,11,0.25);">
                            <span class="m-lbl" style="color:#FBBF24;">10% Commission Deducted</span>
                            <div class="m-val" style="color:#FBBF24;" id="m_deduction">₹0.00</div>
                        </div>
                    </div>
                    <div class="m-box" style="margin-top:12px; background:rgba(16,185,129,0.08); border-color:rgba(16,185,129,0.25);">
                        <span class="m-lbl" style="color:#34D399;">Wallet Balance After Trip</span>
                        <div class="m-val" style="color:#34D399; font-size:1.1rem;" id="m_balance_after">₹0.00</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Funds / Top Up Wallet Modal -->
    <div class="modal-overlay" id="topUpModal">
        <div class="modal-content" style="max-width: 540px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fa-solid fa-wallet" style="color:var(--success-color);"></i> Top Up Prepaid API Wallet
                </h3>
                <button class="modal-close" onclick="closeTopUpModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <p style="color:var(--text-secondary); font-size:0.9rem; margin-bottom:18px; line-height:1.5;">
                    Select or enter an amount to top up your prepaid API wallet balance via Razorpay online payment.
                </p>

                <!-- Quick Amount Chips -->
                <label style="display:block; font-size:0.78rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.8px;">
                    Quick Top-Up Amounts
                </label>
                <div class="chip-container">
                    <button type="button" class="preset-chip" onclick="selectChip(2000, this)">+ ₹2,000</button>
                    <button type="button" class="preset-chip active" onclick="selectChip(5000, this)">+ ₹5,000</button>
                    <button type="button" class="preset-chip" onclick="selectChip(10000, this)">+ ₹10,000</button>
                    <button type="button" class="preset-chip" onclick="selectChip(25000, this)">+ ₹25,000</button>
                    <button type="button" class="preset-chip" onclick="selectChip(50000, this)">+ ₹50,000</button>
                </div>

                <!-- Custom Amount Input -->
                <label style="display:block; font-size:0.78rem; font-weight:800; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.8px; margin-bottom:8px;">
                    Or Enter Custom Amount (₹)
                </label>
                <div style="position:relative; margin-bottom:22px;">
                    <span style="position:absolute; left:18px; top:50%; transform:translateY(-50%); font-size:1.3rem; font-weight:800; color:#34D399;">₹</span>
                    <input type="number" id="customTopupInput" class="custom-amount-input" style="padding-left:38px;" value="5000" min="500" step="500" oninput="updateTopupCalculation()">
                </div>

                <!-- Balance Calculation Summary Box -->
                <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:24px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.88rem;">
                        <span style="color:var(--text-secondary);">Current Wallet Balance</span>
                        <span style="color:#FFF; font-weight:700; font-family:var(--font-mono);">₹<?= number_format($wallet_balance, 2) ?></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.88rem;">
                        <span style="color:var(--text-secondary);">Top-Up Credit Amount</span>
                        <span style="color:#34D399; font-weight:700; font-family:var(--font-mono);" id="modalAddAmount">+₹5,000.00</span>
                    </div>
                    <div style="display:flex; justify-content:space-between; padding-top:10px; border-top:1px dashed rgba(255,255,255,0.1); font-size:1rem; font-weight:800;">
                        <span style="color:#FFF;">New Wallet Balance</span>
                        <span style="color:#34D399; font-family:var(--font-mono);" id="modalNewBalance">₹<?= number_format($wallet_balance + 5000, 2) ?></span>
                    </div>
                </div>

                <!-- Pay Button -->
                <button type="button" class="btn-pay-hero" id="btnCustomPay" onclick="payCustomAmountWithRazorpay()" style="width:100%;">
                    <i class="fa-solid fa-bolt"></i> Pay ₹5,000 via Razorpay
                </button>

                <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:14px; font-size:0.78rem; color:var(--text-secondary);">
                    <i class="fa-solid fa-shield-halved" style="color:#34D399;"></i>
                    <span>Instant Auto-Credit Upon Payment Completion</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toast"></div>

    <!-- Razorpay Checkout SDK & Payment Handler -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function openTripModal(data) {
            document.getElementById('m_ref_title').innerText = 'Trip Details: ' + (data.partner_booking_ref || ('BOOK-' + data.booking_id));
            document.getElementById('m_route').innerText = (data.from_address || 'N/A') + '  ➔  ' + (data.to_address || 'N/A');
            document.getElementById('m_date_time').innerText = (data.trip_date || 'N/A') + ' at ' + (data.trip_time || 'N/A');
            document.getElementById('m_car_trip_type').innerText = (data.car_type || 'Cab') + ' • ' + (data.trip_type || 'One-way');
            document.getElementById('m_starting_km').innerText = (data.starting_km ? data.starting_km + ' KM' : 'N/A');
            document.getElementById('m_closing_km').innerText = (data.closing_km ? data.closing_km + ' KM' : 'N/A');
            
            document.getElementById('m_passenger_phone').innerText = data.passenger_phone || data.customer_number || 'N/A';
            
            let driverText = 'Not Assigned';
            if (data.driver_name) {
                driverText = data.driver_name + ' (' + (data.driver_phone || '') + ')';
            } else if (data.driver_id) {
                driverText = 'Driver ID: ' + data.driver_id;
            }
            if (data.booking_vehicle_id) {
                driverText += ' | RC: ' + data.booking_vehicle_id;
            }
            document.getElementById('m_driver_info').innerText = driverText;

            document.getElementById('m_total_fare').innerText = '₹' + parseFloat(data.total_fare || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('m_deduction').innerText = '-₹' + parseFloat(data.deduction_amount || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('m_balance_after').innerText = '₹' + parseFloat(data.balance_after || 0).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            document.getElementById('tripModal').classList.add('active');
        }

        function closeTripModal() {
            document.getElementById('tripModal').classList.remove('active');
        }

        window.onclick = function(event) {
            const modal = document.getElementById('tripModal');
            if (event.target === modal) {
                closeTripModal();
            }
        };

        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.borderColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        let currentWalletBal = <?= (float)$wallet_balance ?>;

        function openTopUpModal() {
            document.getElementById('topUpModal').classList.add('active');
            updateTopupCalculation();
        }

        function closeTopUpModal() {
            document.getElementById('topUpModal').classList.remove('active');
        }

        function selectChip(amount, btn) {
            document.querySelectorAll('.preset-chip').forEach(c => c.classList.remove('active'));
            if (btn) btn.classList.add('active');
            document.getElementById('customTopupInput').value = amount;
            updateTopupCalculation();
        }

        function updateTopupCalculation() {
            const input = document.getElementById('customTopupInput');
            let amt = parseFloat(input.value) || 0;
            if (amt < 0) amt = 0;

            document.getElementById('modalAddAmount').innerText = '+₹' + amt.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            
            let newBal = currentWalletBal + amt;
            document.getElementById('modalNewBalance').innerText = '₹' + newBal.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            const btn = document.getElementById('btnCustomPay');
            if (amt < 500) {
                btn.disabled = true;
                btn.style.opacity = '0.6';
                btn.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Minimum Top-Up is ₹500';
            } else {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Pay ₹' + amt.toLocaleString('en-IN') + ' via Razorpay';
            }
        }

        function payCustomAmountWithRazorpay() {
            const input = document.getElementById('customTopupInput');
            const amt = parseFloat(input.value) || 0;
            if (amt < 500) {
                showToast("Minimum top-up amount is ₹500.", true);
                return;
            }

            const btn = document.getElementById('btnCustomPay');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening Secure Checkout...';
            }

            const options = {
                "key": "<?= RAZORPAY_ACTIVE_KEY ?>",
                "amount": Math.round(amt * 100),
                "currency": "INR",
                "name": "Rentox API Service",
                "description": "Prepaid Wallet Top-Up (₹" + amt.toLocaleString('en-IN') + ")",
                "handler": function (response) {
                    if (response.razorpay_payment_id) {
                        verifyPartnerPayment(response.razorpay_payment_id, amt);
                    }
                },
                "modal": {
                    "ondismiss": function() {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Pay ₹' + amt.toLocaleString('en-IN') + ' via Razorpay';
                        }
                    }
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($p['contact_person'] ?? $p['partner_name']) ?>",
                    "email": "<?= htmlspecialchars($p['email']) ?>",
                    "contact": "<?= htmlspecialchars($p['mobile_number']) ?>"
                },
                "theme": {
                    "color": "#10B981"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        }

        function payWithRazorpay() {
            const btn = document.getElementById('btnPayMain');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Opening Secure Checkout...';
            }

            const options = {
                "key": "<?= RAZORPAY_ACTIVE_KEY ?>",
                "amount": <?= (int)($activation_deposit_required * 100) ?>,
                "currency": "INR",
                "name": "Rentox API Service",
                "description": "B2B Partner API Integration & Activation Fee",
                "handler": function (response) {
                    if (response.razorpay_payment_id) {
                        verifyPartnerPayment(response.razorpay_payment_id, <?= (float)$activation_deposit_required ?>);
                    }
                },
                "modal": {
                    "ondismiss": function() {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Pay ₹<?= number_format($activation_deposit_required) ?> via Razorpay';
                        }
                    }
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($p['contact_person'] ?? $p['partner_name']) ?>",
                    "email": "<?= htmlspecialchars($p['email']) ?>",
                    "contact": "<?= htmlspecialchars($p['mobile_number']) ?>"
                },
                "theme": {
                    "color": "#10B981"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        }

        function confirmPartnerLogout(e) {
            if (e) e.preventDefault();
            Swal.fire({
                title: 'Sign Out?',
                text: 'Are you sure you want to log out of your Rentox Partner Console?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#475569',
                confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Yes, Log Out',
                cancelButtonText: 'Cancel',
                background: '#0F172A',
                color: '#F8FAFC',
                customClass: {
                    popup: 'swal2-dark-popup'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'dashboard.php?action=logout';
                }
            });
        }

        function verifyPartnerPayment(paymentId, topupAmt) {
            showToast("Verifying payment & crediting wallet...", false);
            const formData = new FormData();
            formData.append('action', 'verify_payment');
            formData.append('razorpay_payment_id', paymentId);
            if (topupAmt) {
                formData.append('amount', topupAmt);
            }

            fetch('payments.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, false);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Payment verification failed', true);
                    }
                })
                .catch(err => showToast('Error: ' + err.message, true));
        }
    </script>
</body>
</html>
