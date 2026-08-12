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

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID.']);
        exit;
    }

    try {
        $stmt_pay = mysqli_prepare($conn, 
            "UPDATE partners 
             SET status = 'active', payment_status = 'paid', payment_id = ?, payment_amount = 10000.00, paid_at = NOW() 
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_pay, 'si', $payment_id, $id);
        if (mysqli_stmt_execute($stmt_pay)) {
            echo json_encode(['success' => true, 'message' => 'Payment of ₹10,000 verified successfully! Your API Production keys are now unlocked.']);
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

// Fetch wallet transactions for this partner
$wallet_txs = [];
$total_deducted = 0.00;
$stmt_tx_list = mysqli_prepare($conn, "SELECT * FROM partner_wallet_transactions WHERE partner_id = ? ORDER BY id DESC LIMIT 100");
if ($stmt_tx_list) {
    mysqli_stmt_bind_param($stmt_tx_list, 'i', $id);
    mysqli_stmt_execute($stmt_tx_list);
    $res_tx = mysqli_stmt_get_result($stmt_tx_list);
    while ($row_tx = mysqli_fetch_assoc($res_tx)) {
        $wallet_txs[] = $row_tx;
        $total_deducted += (float)($row_tx['deduction_amount'] ?? 0);
    }
    mysqli_stmt_close($stmt_tx_list);
}

$wallet_balance = (float)($p['wallet_balance'] ?? 10000.00);
$initial_deposit = (float)($p['initial_deposit'] ?? 10000.00);

$is_paid = (($p['payment_status'] ?? '') === 'paid');
$payment_id_val = $p['payment_id'] ?? 'N/A';
$paid_at_val = !empty($p['paid_at']) ? date('d M Y, h:i A', strtotime($p['paid_at'])) : 'N/A';
$paid_amount_val = !empty($p['payment_amount']) ? number_format($p['payment_amount'], 2) : '10,000.00';
$invoice_no = 'INV-REDOX-100' . $p['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments & Invoices | Rentox B2B Console</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: rgba(17, 24, 39, 0.95);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(20px);
            display: flex; flex-direction: column; position: fixed; height: 100vh; z-index: 1000;
        }
        .sidebar-brand { padding: 28px 24px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--border-color); }
        .brand-logo-icon {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .brand-name { font-size: 1.2rem; font-weight: 800; }

        .sidebar-nav { padding: 20px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            color: var(--text-secondary); text-decoration: none;
            padding: 12px 16px; border-radius: 10px; font-size: 0.92rem; font-weight: 500;
            transition: all 0.2s ease;
        }
        .nav-item:hover { color: #FFF; background: rgba(255, 255, 255, 0.04); }
        .nav-item.active {
            color: #FFF; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(59, 130, 246, 0.2));
            border: 1px solid rgba(99, 102, 241, 0.3); font-weight: 600;
        }

        .main-workspace { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 6px; }
        .page-header p { color: var(--text-secondary); font-size: 0.92rem; }

        /* Metrics Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 20px; position: relative; overflow: hidden;
        }
        .metric-label { color: var(--text-secondary); font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-value { font-size: 1.6rem; font-weight: 800; margin: 10px 0 4px; }
        .metric-desc { font-size: 0.82rem; color: var(--text-secondary); }

        /* Panel Card */
        .panel-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 28px; margin-bottom: 30px;
        }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .status-badge {
            display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;
            border-radius: 20px; font-size: 0.85rem; font-weight: 700;
        }
        .badge-paid { background: rgba(16, 185, 129, 0.15); color: var(--success-color); border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-unpaid { background: rgba(245, 158, 11, 0.15); color: var(--warning-color); border: 1px solid rgba(245, 158, 11, 0.3); }

        /* Invoice Grid */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .info-cell { display: flex; flex-direction: column; gap: 4px; }
        .info-label { font-size: 0.82rem; color: var(--text-secondary); font-weight: 500; }
        .info-value { font-size: 0.95rem; font-weight: 600; color: #FFF; }

        table.custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.custom-table th { padding: 14px 16px; font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        table.custom-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
        table.custom-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        /* Buttons */
        .btn-pay {
            background: linear-gradient(135deg, #10B981, #059669); color: #FFF;
            border: none; border-radius: 12px; padding: 14px 28px; font-size: 1rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 10px;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35); transition: all 0.2s ease;
        }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5); }

        .btn-print {
            background: rgba(99, 102, 241, 0.15); color: var(--primary-accent);
            border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 10px; padding: 10px 18px;
            font-size: 0.9rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-print:hover { background: var(--primary-accent); color: #FFF; }

        /* Toast Popup Notification */
        #toast {
            position: fixed; bottom: 30px; right: 30px; background: var(--bg-card);
            border: 1px solid var(--primary-accent); padding: 16px 20px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none; z-index: 2000; font-size: 0.9rem;
        }

        /* Printable Invoice Styles */
        @media print {
            .sidebar, .page-header, .ambient-glow, .btn-print, .metrics-grid { display: none !important; }
            .main-workspace { margin-left: 0 !important; padding: 0 !important; }
            .panel-card { border: 1px solid #CCC !important; color: #000 !important; background: #FFF !important; box-shadow: none !important; }
            .info-label { color: #555 !important; }
            .info-value { color: #000 !important; }
            .card-title { color: #000 !important; }
        }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="app-container">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-terminal brand-logo-icon"></i>
                <span class="brand-name">Rentox API</span>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php#overview" class="nav-item">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                <a href="dashboard.php#keys" class="nav-item">
                    <i class="fa-solid fa-key"></i> API Credentials
                </a>
                <a href="payments.php" class="nav-item active">
                    <i class="fa-solid fa-credit-card"></i> Payments & Billing
                </a>
                <a href="test-trips.php" class="nav-item">
                    <i class="fa-solid fa-flask-vial"></i> Test Trips Simulator
                </a>
                <a href="dashboard.php#logs" class="nav-item">
                    <i class="fa-solid fa-terminal"></i> Activity Logs
                </a>
                <a href="dashboard.php#docs" class="nav-item">
                    <i class="fa-solid fa-book"></i> API Documentation
                </a>
                <a href="dashboard.php#settings" class="nav-item">
                    <i class="fa-solid fa-sliders"></i> Account Settings
                </a>
            </nav>
        </aside>

        <!-- Main Workspace -->
        <main class="main-workspace">

            <div class="page-header">
                <div>
                    <h1>💳 Payments & Prepaid Wallet Billing</h1>
                    <p>Track your API wallet balance, 10% trip commission deductions, transactions, and tax invoices.</p>
                </div>
                <?php if ($is_paid): ?>
                    <button class="btn-print" onclick="window.print()">
                        <i class="fa-solid fa-print"></i> Print Tax Invoice
                    </button>
                <?php endif; ?>
            </div>

            <!-- Summary Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-label">Current Wallet Balance</span>
                    <div class="metric-value" style="color:var(--success-color);">
                        ₹<?= number_format($wallet_balance, 2) ?>
                    </div>
                    <span class="metric-desc"><i class="fa-solid fa-wallet" style="color:var(--success-color)"></i> Available for 10% fee deductions</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Initial Activation Deposit</span>
                    <div class="metric-value">₹<?= number_format($initial_deposit, 2) ?></div>
                    <span class="metric-desc">Prepaid API activation balance</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Total 10% Fees Deducted</span>
                    <div class="metric-value" style="color:var(--warning-color);">
                        -₹<?= number_format($total_deducted, 2) ?>
                    </div>
                    <span class="metric-desc">10% commission on completed trips</span>
                </div>

                <div class="metric-card">
                    <span class="metric-label">Completed Trips Billed</span>
                    <div class="metric-value" style="color:var(--primary-accent);">
                        <?= count($wallet_txs) ?>
                    </div>
                    <span class="metric-desc">Total completed API trips</span>
                </div>
            </div>

            <!-- Payment Action / Invoice Card -->
            <?php if (!$is_paid): ?>
                <div class="panel-card" style="border-color: rgba(245, 158, 11, 0.4); background: linear-gradient(135deg, rgba(245, 158, 11, 0.08), rgba(17, 24, 39, 0.9));">
                    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:20px;">
                        <div>
                            <span class="status-badge badge-unpaid" style="margin-bottom:12px;"><i class="fa-solid fa-triangle-exclamation"></i> Payment Required</span>
                            <h2 style="font-size:1.4rem; margin-bottom:8px;">Complete ₹10,000 API Setup Payment</h2>
                            <p style="color:var(--text-secondary); max-width:650px; font-size:0.95rem; line-height:1.5;">
                                Your B2B Partner Account profile is approved! Complete the <strong>₹10,000.00</strong> setup payment via Razorpay (UPI, Credit Cards, NetBanking) to instantly unlock your <strong>Live Production API Access Keys</strong>.
                            </p>
                        </div>
                        <div>
                            <button class="btn-pay" onclick="payWithRazorpay()">
                                <i class="fa-solid fa-credit-card"></i> Pay ₹10,000 via Razorpay
                            </button>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Official Tax Invoice Breakdown -->
                <div class="panel-card">
                    <div class="card-header-flex">
                        <h3 class="card-title"><i class="fa-solid fa-file-invoice-dollar" style="color:var(--success-color);"></i> Official Tax Invoice & Payment Receipt</h3>
                        <span class="status-badge badge-paid"><i class="fa-solid fa-circle-check"></i> Paid & Verified</span>
                    </div>

                    <div class="info-grid" style="margin-bottom:30px; padding-bottom:24px; border-bottom:1px solid var(--border-color);">
                        <div class="info-cell">
                            <span class="info-label">Invoice Number</span>
                            <span class="info-value" style="font-family:var(--font-mono); color:var(--primary-accent);"><?= htmlspecialchars($invoice_no) ?></span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Razorpay Payment ID</span>
                            <span class="info-value" style="font-family:var(--font-mono); color:var(--success-color);"><?= htmlspecialchars($p['payment_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Company Legal Name</span>
                            <span class="info-value"><?= htmlspecialchars($p['company_name']) ?></span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">GST / Tax ID</span>
                            <span class="info-value" style="font-family:var(--font-mono);"><?= htmlspecialchars($p['gst_number'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Authorized Email</span>
                            <span class="info-value"><?= htmlspecialchars($p['email']) ?></span>
                        </div>
                        <div class="info-cell">
                            <span class="info-label">Contact Number</span>
                            <span class="info-value"><?= htmlspecialchars($p['mobile_number'] ?? 'N/A') ?></span>
                        </div>
                    </div>

                    <!-- Payment Summary Breakdown Table -->
                    <h4 style="font-size:1rem; margin-bottom:16px; color:#FFF;">Line Item Breakdown</h4>
                    <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:12px; overflow:hidden; margin-bottom:24px;">
                        <table style="width:100%; border-collapse:collapse; text-align:left;">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border-color); background:rgba(255,255,255,0.03);">
                                    <th style="padding:14px 18px; font-size:0.82rem; color:var(--text-secondary);">Description</th>
                                    <th style="padding:14px 18px; font-size:0.82rem; color:var(--text-secondary); text-align:right;">Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:16px 18px; font-weight:600;">Redox B2B Partner API Platform Integration & Onboarding Fee</td>
                                    <td style="padding:16px 18px; text-align:right; font-family:var(--font-mono);">₹8,474.58</td>
                                </tr>
                                <tr style="border-bottom:1px solid var(--border-color);">
                                    <td style="padding:14px 18px; color:var(--text-secondary);">GST @ 18% (CGST 9% + SGST 9%)</td>
                                    <td style="padding:14px 18px; text-align:right; font-family:var(--font-mono); color:var(--text-secondary);">₹1,525.42</td>
                                </tr>
                                <tr style="background:rgba(16,185,129,0.08);">
                                    <td style="padding:16px 18px; font-weight:800; font-size:1.05rem; color:#FFF;">Total Amount Paid</td>
                                    <td style="padding:16px 18px; text-align:right; font-weight:800; font-size:1.15rem; color:var(--success-color); font-family:var(--font-mono);">₹10,000.00</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Issued by: <strong>AGNI CAR RENTAL / REDOX API SERVICES</strong></span>
                        <button class="btn-print" onclick="window.print()">
                            <i class="fa-solid fa-print"></i> Download & Print Invoice
                        </button>
                    </div>
                </div>

                <!-- 10% Trip Commission Ledger & Transactions Panel -->
                <div class="panel-card" style="margin-top:30px;">
                    <div class="card-header-flex">
                        <h3 class="card-title"><i class="fa-solid fa-wallet" style="color:var(--primary-accent);"></i> 10% Trip Commission Wallet Ledger</h3>
                        <span class="status-badge badge-paid"><i class="fa-solid fa-clock-rotate-left"></i> Real-time Deductions</span>
                    </div>
                    
                    <?php if (empty($wallet_txs)): ?>
                        <div style="text-align:center; padding:36px 10px; color:var(--text-secondary);">
                            <i class="fa-solid fa-receipt" style="font-size:2.8rem; opacity:0.3; margin-bottom:12px; color:var(--primary-accent);"></i>
                            <h4 style="font-size:1.05rem; color:#FFF; margin-bottom:6px;">No Trip Fee Deductions Yet</h4>
                            <p style="font-size:0.9rem; max-width:500px; margin:0 auto; line-height:1.5;">When a trip booked via your B2B API is finished, a 10% trip fee deduction will be automatically recorded here in real-time.</p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x:auto;">
                            <table class="custom-table" style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr style="background:rgba(255,255,255,0.03); text-align:left;">
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Date & Time</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Booking Ref</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Total Trip Fare</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Commission</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Amount Deducted</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Remaining Wallet Balance</th>
                                        <th style="padding:14px 16px; font-size:0.8rem; color:var(--text-secondary);">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($wallet_txs as $tx): ?>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">
                                                <?= date('d M Y, h:i A', strtotime($tx['created_at'])) ?>
                                            </td>
                                            <td style="padding:14px 16px; font-family:var(--font-mono); font-weight:700; color:var(--primary-accent);">
                                                #<?= htmlspecialchars($tx['booking_id']) ?>
                                            </td>
                                            <td style="padding:14px 16px; font-weight:600;">
                                                ₹<?= number_format($tx['trip_amount'], 2) ?>
                                            </td>
                                            <td style="padding:14px 16px; color:var(--warning-color); font-weight:700;">
                                                10.00%
                                            </td>
                                            <td style="padding:14px 16px; font-weight:800; color:var(--danger-color); font-family:var(--font-mono);">
                                                -₹<?= number_format($tx['deduction_amount'], 2) ?>
                                            </td>
                                            <td style="padding:14px 16px; font-weight:800; color:var(--success-color); font-family:var(--font-mono);">
                                                ₹<?= number_format($tx['balance_after'], 2) ?>
                                            </td>
                                            <td style="padding:14px 16px;">
                                                <span class="status-badge badge-paid" style="font-size:0.75rem;"><i class="fa-solid fa-check"></i> Deducted</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- Toast Popup Notification -->
    <div id="toast"></div>

    <!-- Razorpay Checkout SDK & Payment Handler -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.borderColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        function payWithRazorpay() {
            const options = {
                "key": "<?= RAZORPAY_ACTIVE_KEY ?>",
                "amount": 1000000,
                "currency": "INR",
                "name": "Redox API Service",
                "description": "B2B Partner API Integration & Activation Fee",
                "handler": function (response) {
                    if (response.razorpay_payment_id) {
                        verifyPartnerPayment(response.razorpay_payment_id);
                    }
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($p['contact_person'] ?? $p['partner_name']) ?>",
                    "email": "<?= htmlspecialchars($p['email']) ?>",
                    "contact": "<?= htmlspecialchars($p['mobile_number']) ?>"
                },
                "theme": {
                    "color": "#6c63ff"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        }

        function verifyPartnerPayment(paymentId) {
            showToast("Verifying ₹10,000 payment...", false);
            const formData = new FormData();
            formData.append('action', 'verify_payment');
            formData.append('razorpay_payment_id', paymentId);

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
