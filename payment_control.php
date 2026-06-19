<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}

require_once __DIR__ . '/db_connect.php';

// Fetch alert messages
$success_msg = $_SESSION['success_msg'] ?? null;
$error_msg = $_SESSION['error_msg'] ?? null;
unset($_SESSION['success_msg'], $_SESSION['error_msg']);

// 1. Calculate overview stats
$total_bookings_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bookings WHERE booking_status NOT IN ('Deleted', 'Failed')");
$total_bookings = mysqli_fetch_assoc($total_bookings_q)['cnt'];

$total_advance_q = mysqli_query($conn, "SELECT SUM(paid_amount) as amt FROM bookings WHERE booking_status NOT IN ('Deleted', 'Failed')");
$total_advance = (double)mysqli_fetch_assoc($total_advance_q)['amt'];

$total_balance_q = mysqli_query($conn, "SELECT SUM(remaining_balance) as amt FROM bookings WHERE booking_status NOT IN ('Deleted', 'Failed')");
$total_balance = (double)mysqli_fetch_assoc($total_balance_q)['amt'];

$total_settlements_q = mysqli_query($conn, "SELECT SUM(settled_amount) as amt FROM vendor_settlements WHERE status = 'Paid'");
$total_settlements = (double)mysqli_fetch_assoc($total_settlements_q)['amt'];

$pending_settlements_q = mysqli_query($conn, "
    SELECT COUNT(*) as cnt 
    FROM bookings b 
    LEFT JOIN vendor_settlements s ON b.id = s.booking_id 
    WHERE b.booking_status NOT IN ('Deleted', 'Failed') 
      AND b.paid_amount > 0 
      AND b.trip_type != 'Round-Trip' 
      AND (s.status IS NULL OR s.status != 'Paid')
");
$pending_settlements = mysqli_fetch_assoc($pending_settlements_q)['cnt'];

$completed_settlements_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM vendor_settlements WHERE status = 'Paid'");
$completed_settlements = mysqli_fetch_assoc($completed_settlements_q)['cnt'];

// 2. Tab filtering for Remaining Balance
$tab = $_GET['tab'] ?? 'all';
$where_clause = "WHERE b.booking_status NOT IN ('Deleted', 'Failed')";

if ($tab === 'pending') {
    $where_clause .= " AND b.remaining_balance > 0 AND b.collection_status = 'Pending Collection'";
} elseif ($tab === 'advance_paid') {
    $where_clause .= " AND b.paid_amount > 0 AND b.payment_status IN ('Advance Paid', 'success')";
} elseif ($tab === 'partially_paid') {
    $where_clause .= " AND b.remaining_balance > 0 AND b.collection_status = 'Collected'";
} elseif ($tab === 'fully_paid') {
    $where_clause .= " AND b.remaining_balance = 0";
} elseif ($tab === 'completed') {
    $where_clause .= " AND (b.booking_status = 'Completed' OR b.collection_status = 'Completed')";
}

// 3. Fetch Remaining Balance List
$remaining_balances = [];
$rb_query = "
    SELECT 
        b.id AS booking_id,
        b.booker_id,
        COALESCE(u.name, 'Unknown') AS customer_name,
        b.vender_id,
        b.total_amount,
        b.paid_amount,
        b.remaining_balance,
        b.collection_status,
        b.collection_date
    FROM bookings b
    LEFT JOIN users u ON b.booker_id = u.phone_number
    $where_clause
    ORDER BY b.id DESC
";
$rb_result = mysqli_query($conn, $rb_query);
while ($row = mysqli_fetch_assoc($rb_result)) {
    $remaining_balances[] = $row;
}

// 4. Fetch Vendor Settlement Approval list
$settlements = [];
$set_query = "
    SELECT 
        b.id AS booking_id,
        b.vender_id,
        b.total_amount,
        b.paid_amount,
        b.trip_type,
        COALESCE(s.status, 'Pending') AS settlement_status,
        s.remarks,
        s.settled_amount,
        s.settled_date,
        s.bank_reference
    FROM bookings b
    LEFT JOIN vendor_settlements s ON b.id = s.booking_id
    WHERE b.booking_status NOT IN ('Deleted', 'Failed') 
      AND b.paid_amount > 0 
      AND b.trip_type != 'Round-Trip'
    ORDER BY b.id DESC
";
$set_result = mysqli_query($conn, $set_query);
while ($row = mysqli_fetch_assoc($set_result)) {
    $settlements[] = $row;
}

// 5. Fetch Settlement History
$history = [];
$hist_query = "
    SELECT 
        sh.id,
        sh.booking_id,
        sh.amount,
        sh.settled_date,
        sh.bank_reference,
        sh.admin_notes,
        vs.vendor_id
    FROM settlement_history sh
    LEFT JOIN vendor_settlements vs ON sh.settlement_id = vs.id
    ORDER BY sh.id DESC
";
$hist_result = mysqli_query($conn, $hist_query);
while ($row = mysqli_fetch_assoc($hist_result)) {
    $history[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Control — Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <link rel="stylesheet" href="css/Dashboard_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        body {
            font-family: 'Outfit', 'Segoe UI', system-ui, sans-serif !important;
            background-color: #f6f8fa;
        }
        .content {
            padding: 24px !important;
        }
        .kpi-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.2s ease;
        }
        .kpi-card:hover {
            transform: translateY(-2px);
        }
        .kpi-icon {
            font-size: 2rem;
            color: #6c63ff;
            background: rgba(108, 99, 255, 0.1);
            padding: 12px;
            border-radius: 12px;
        }
        .partner-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .partner-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #eaedf1;
        }
        .partner-card-header h4 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        .monitor-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .monitor-table th {
            background-color: #f8f9fa;
            color: #555;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 2px solid #eaedf1;
        }
        .monitor-table td {
            padding: 14px 16px;
            font-size: 0.88rem;
            color: #333;
            border-bottom: 1px solid #eaedf1;
            vertical-align: middle;
        }
        .monitor-table tbody tr:hover {
            background-color: rgba(108, 99, 255, 0.02);
        }
        /* Buttons styling */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-action.btn-approve {
            background-color: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        .btn-action.btn-approve:hover {
            background-color: #28a745;
            color: #fff;
        }
        .btn-action.btn-reject {
            background-color: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }
        .btn-action.btn-reject:hover {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-action.btn-pay {
            background-color: rgba(0, 123, 255, 0.12);
            color: #007bff;
        }
        .btn-action.btn-pay:hover {
            background-color: #007bff;
            color: #fff;
        }
        .btn-action.btn-update {
            background-color: rgba(255, 193, 7, 0.12);
            color: #d39e00;
        }
        .btn-action.btn-update:hover {
            background-color: #ffc107;
            color: #fff;
        }
        /* Status Pills */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pill.pending { background-color: rgba(255, 193, 7, 0.15); color: #b58000; }
        .status-pill.approved { background-color: rgba(0, 123, 255, 0.15); color: #007bff; }
        .status-pill.rejected { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .status-pill.paid { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.completed { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.collected { background-color: rgba(108, 99, 255, 0.15); color: #6c63ff; }
        .status-pill.pending-collection { background-color: rgba(108, 99, 255, 0.1); color: #6c63ff; }
    </style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container">
        <img src="images/logo.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-wallet me-2"></i> Payment Control
    </h1>
    <div class="center-nav">
        <a href="dashboard.php" class="home-btn"><i class="fas fa-home me-2"></i> Home</a>
    </div>
    <div class="right-nav">
        <form action="logout.php" method="POST" class="logout-form">
            <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
</nav>

<div class="container-fluid separator"></div>

<div class="dashboard-container">
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <ul>
            <li><a href="dashboard.php?tab=driver" id="driver"><i class="fas fa-user me-2"></i> Driver</a></li>
            <li><a href="dashboard.php?tab=cab" id="cab"><i class="fas fa-taxi me-2"></i> Cab</a></li>
            <li><a href="dashboard.php?tab=booking" id="booking"><i class="fas fa-calendar me-2"></i> Booking</a></li>
            <li><a href="dashboard.php?tab=completed" id="Complete"><i class="fas fa-calendar-check me-2"></i> Completed</a></li>
            <li>
                <a href="dashboard.php?tab=newuser" id="newuser">
                    <i class="fa-solid fa-users me-2"></i> Customers
                </a>
            </li>
            <li><a href="https://agnicarrental.com/admin2025/bookacall/admin-bookings.php" id="bookacall"><i class="fa-solid fa-phone me-2"></i>BookACall</a></li>
            <li><a href="dashboard.php?tab=blocked_customer" id="Blocked_Customer"><i class="fas fa-user-slash me-2"></i>Blocked Customer</a></li>
            <li><a href="dashboard.php?tab=extract_data" id="Extract_Data"><i class="fas fa-file-excel me-2"></i> Extract Data</a></li>
            <li><a href="partner/index.php" id="partner_api"><i class="fas fa-handshake me-2"></i> Partner API</a></li>
            <li><a href="partner/monitor.php" id="partner_monitor"><i class="fas fa-desktop me-2"></i> Partner Monitor</a></li>
            <li><a href="car_categories.php" id="car_categories_menu"><i class="fas fa-tags me-2"></i> Car Categories</a></li>
            <li><a href="discount_management.php" id="discount_management_menu"><i class="fas fa-percent me-2"></i> Discount Management</a></li>
            <li><a href="payment_control.php" id="payment_control_menu" style="background-color: #465c71;"><i class="fas fa-wallet me-2"></i> Payment Control</a></li>
        </ul>
    </nav>

    <!-- Main Content Panel -->
    <main class="content">
        
        <!-- Messages -->
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius:12px;">
                <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($success_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="border-radius:12px;">
                <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($error_msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Grid -->
        <div class="row g-4 mb-4">
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Total Bookings</div>
                        <h4 class="mb-0 fw-bold mt-1"><?= $total_bookings ?></h4>
                    </div>
                    <i class="fas fa-list kpi-icon"></i>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Advance Paid</div>
                        <h4 class="mb-0 fw-bold mt-1 text-success">₹<?= number_format($total_advance, 0) ?></h4>
                    </div>
                    <i class="fas fa-arrow-down kpi-icon text-success" style="background:rgba(40,167,69,0.1)"></i>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Remaining Balance</div>
                        <h4 class="mb-0 fw-bold mt-1 text-warning">₹<?= number_format($total_balance, 0) ?></h4>
                    </div>
                    <i class="fas fa-clock kpi-icon text-warning" style="background:rgba(255,193,7,0.1)"></i>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Vendor Settlements</div>
                        <h4 class="mb-0 fw-bold mt-1 text-primary">₹<?= number_format($total_settlements, 0) ?></h4>
                    </div>
                    <i class="fas fa-hand-holding-usd kpi-icon text-primary" style="background:rgba(0,123,255,0.1)"></i>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Pending Payouts</div>
                        <h4 class="mb-0 fw-bold mt-1 text-danger"><?= $pending_settlements ?></h4>
                    </div>
                    <i class="fas fa-hourglass-half kpi-icon text-danger" style="background:rgba(220,53,69,0.1)"></i>
                </div>
            </div>
            <div class="col-md-4 col-lg-2">
                <div class="kpi-card">
                    <div>
                        <div class="text-muted small">Completed Payouts</div>
                        <h4 class="mb-0 fw-bold mt-1 text-success"><?= $completed_settlements ?></h4>
                    </div>
                    <i class="fas fa-check-double kpi-icon text-success" style="background:rgba(40,167,69,0.1)"></i>
                </div>
            </div>
        </div>

        <!-- Section 1: Vendor Settlements -->
        <div class="partner-card">
            <div class="partner-card-header">
                <h4><i class="fas fa-handshake me-2" style="color: #6c63ff;"></i>Vendor Advance Payout Settlement Control</h4>
            </div>
            <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Vendor ID</th>
                            <th>Trip Type</th>
                            <th>Total Amount</th>
                            <th>Advance Paid</th>
                            <th>Settlement Earnings (90%)</th>
                            <th>Status</th>
                            <th>Reference / Details</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($settlements)): ?>
                            <tr>
                                <td colspan="9" class="text-muted py-4">No bookings found for settlements.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($settlements as $set): ?>
                                <?php 
                                    $earnings = ($set['trip_type'] === 'Round-Trip') ? 0.00 : ($set['paid_amount'] * 0.90);
                                    $status = strtolower($set['settlement_status']);
                                ?>
                                <tr>
                                    <td>#<?= $set['booking_id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($set['vender_id']) ?></span></td>
                                    <td><?= htmlspecialchars($set['trip_type']) ?></td>
                                    <td>₹<?= number_format($set['total_amount'], 2) ?></td>
                                    <td>₹<?= number_format($set['paid_amount'], 2) ?></td>
                                    <td class="fw-bold">₹<?= number_format($earnings, 2) ?></td>
                                    <td>
                                        <span class="status-pill <?= $status ?>">
                                            <?= htmlspecialchars($set['settlement_status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.82rem; color: #555;">
                                        <?php if ($status === 'paid'): ?>
                                            Ref: <?= htmlspecialchars($set['bank_reference']) ?><br>
                                            Date: <?= htmlspecialchars($set['settled_date']) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($set['remarks'] ?: '-') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <?php if ($status === 'pending'): ?>
                                                <form action="payment_action.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="approve_settlement">
                                                    <input type="hidden" name="booking_id" value="<?= $set['booking_id'] ?>">
                                                    <button type="submit" class="btn-action btn-approve"><i class="fas fa-check"></i> Approve</button>
                                                </form>
                                                <button class="btn-action btn-reject" onclick="triggerReject(<?= $set['booking_id'] ?>)"><i class="fas fa-times"></i> Reject</button>
                                            <?php elseif ($status === 'approved'): ?>
                                                <button class="btn-action btn-pay" onclick="triggerPay(<?= $set['booking_id'] ?>, <?= $earnings ?>)"><i class="fas fa-money-bill-wave"></i> Mark Paid</button>
                                                <button class="btn-action btn-reject" onclick="triggerReject(<?= $set['booking_id'] ?>)"><i class="fas fa-times"></i> Reject</button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Remaining Balances -->
        <div class="partner-card">
            <div class="partner-card-header flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                <h4><i class="fas fa-dollar-sign me-2" style="color: #28a745;"></i>Remaining Balance Collection Tracking</h4>
                
                <!-- Status Tabs -->
                <ul class="nav nav-pills" style="font-size:0.85rem;">
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'all' ? 'active bg-primary' : 'text-secondary' ?>" href="payment_control.php?tab=all">All</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'pending' ? 'active bg-warning text-dark' : 'text-secondary' ?>" href="payment_control.php?tab=pending">Pending Collection</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'advance_paid' ? 'active bg-info text-dark' : 'text-secondary' ?>" href="payment_control.php?tab=advance_paid">Advance Paid</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'partially_paid' ? 'active bg-secondary text-white' : 'text-secondary' ?>" href="payment_control.php?tab=partially_paid">Collected (Pending Approval)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'fully_paid' ? 'active bg-success text-white' : 'text-secondary' ?>" href="payment_control.php?tab=fully_paid">Fully Paid</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $tab === 'completed' ? 'active bg-success text-white' : 'text-secondary' ?>" href="payment_control.php?tab=completed">Completed</a>
                    </li>
                </ul>
            </div>
            
            <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Vendor ID</th>
                            <th>Total Amount</th>
                            <th>Advance Paid</th>
                            <th>Remaining Balance</th>
                            <th>Collection Status</th>
                            <th>Collection Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($remaining_balances)): ?>
                            <tr>
                                <td colspan="9" class="text-muted py-4">No remaining balance records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($remaining_balances as $rb): ?>
                                <?php 
                                    $col_status = strtolower(str_replace(' ', '-', $rb['collection_status']));
                                ?>
                                <tr>
                                    <td>#<?= $rb['booking_id'] ?></td>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($rb['customer_name']) ?></div>
                                        <span class="text-muted small"><?= htmlspecialchars($rb['booker_id']) ?></span>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($rb['vender_id'] ?: 'N/A') ?></span></td>
                                    <td>₹<?= number_format($rb['total_amount'], 2) ?></td>
                                    <td>₹<?= number_format($rb['paid_amount'], 2) ?></td>
                                    <td class="fw-bold text-danger">₹<?= number_format($rb['remaining_balance'], 2) ?></td>
                                    <td>
                                        <span class="status-pill <?= $col_status ?>">
                                            <?= htmlspecialchars($rb['collection_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= $rb['collection_date'] ?: '-' ?></td>
                                    <td>
                                        <div class="d-inline-flex gap-2">
                                            <button class="btn-action btn-update" onclick="triggerUpdateBalance(<?= $rb['booking_id'] ?>, <?= $rb['remaining_balance'] ?>)"><i class="fas fa-edit"></i> Update</button>
                                            
                                            <?php if ($rb['collection_status'] === 'Pending Collection'): ?>
                                                <form action="payment_action.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="mark_collected">
                                                    <input type="hidden" name="booking_id" value="<?= $rb['booking_id'] ?>">
                                                    <button type="submit" class="btn-action btn-approve"><i class="fas fa-check"></i> Mark Collected</button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($rb['collection_status'] !== 'Completed'): ?>
                                                <form action="payment_action.php" method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="mark_fully_paid">
                                                    <input type="hidden" name="booking_id" value="<?= $rb['booking_id'] ?>">
                                                    <button type="submit" class="btn-action btn-pay"><i class="fas fa-check-double"></i> Mark Completed</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 3: Settlement History Log -->
        <div class="partner-card">
            <div class="partner-card-header">
                <h4><i class="fas fa-history me-2" style="color: #17a2b8;"></i>Settlement Payout History</h4>
            </div>
            <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Booking ID</th>
                            <th>Vendor ID</th>
                            <th>Settled Amount</th>
                            <th>Payout Date</th>
                            <th>Bank Reference</th>
                            <th>Admin Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr>
                                <td colspan="7" class="text-muted py-4">No settlement payout records logged.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td>#<?= $h['id'] ?></td>
                                    <td>#<?= $h['booking_id'] ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($h['vendor_id']) ?></span></td>
                                    <td class="fw-bold text-success">₹<?= number_format($h['amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($h['settled_date']) ?></td>
                                    <td><span class="font-monospace text-dark"><?= htmlspecialchars($h['bank_reference']) ?></span></td>
                                    <td><?= htmlspecialchars($h['admin_notes'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<!-- Modal: Pay Settlement -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="payModalLabel"><i class="fas fa-money-bill-wave me-2 text-success"></i>Mark Payout as Settled</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payment_action.php" method="POST">
                <input type="hidden" name="action" value="mark_settled">
                <input type="hidden" name="booking_id" id="pay-booking-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Booking ID</label>
                        <input type="text" id="pay-booking-display" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Settled Payout Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="settled_amount" id="pay-amount" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Settled Date <span class="text-danger">*</span></label>
                        <input type="date" name="settled_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bank Reference / Txn ID <span class="text-danger">*</span></label>
                        <input type="text" name="bank_reference" class="form-control" placeholder="e.g. UTR123456789" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Admin Notes</label>
                        <textarea name="remarks" class="form-control" placeholder="Enter optional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#6c63ff; border:none;">Log Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reject Settlement -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rejectModalLabel"><i class="fas fa-times me-2 text-danger"></i>Reject Settlement Payout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payment_action.php" method="POST">
                <input type="hidden" name="action" value="reject_settlement">
                <input type="hidden" name="booking_id" id="reject-booking-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Booking ID</label>
                        <input type="text" id="reject-booking-display" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                        <textarea name="remarks" class="form-control" placeholder="Describe why this payout is rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Update Remaining Balance -->
<div class="modal fade" id="balanceModal" tabindex="-1" aria-labelledby="balanceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="balanceModalLabel"><i class="fas fa-edit me-2 text-warning"></i>Update Remaining Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="payment_action.php" method="POST">
                <input type="hidden" name="action" value="update_balance">
                <input type="hidden" name="booking_id" id="balance-booking-id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Booking ID</label>
                        <input type="text" id="balance-booking-display" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">New Remaining Balance (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="remaining_balance" id="balance-amount" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#6c63ff; border:none;">Update Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function triggerPay(bookingId, amount) {
        document.getElementById('pay-booking-id').value = bookingId;
        document.getElementById('pay-booking-display').value = '#' + bookingId;
        document.getElementById('pay-amount').value = amount;
        
        var payModal = new bootstrap.Modal(document.getElementById('payModal'));
        payModal.show();
    }

    function triggerReject(bookingId) {
        document.getElementById('reject-booking-id').value = bookingId;
        document.getElementById('reject-booking-display').value = '#' + bookingId;
        
        var rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
        rejectModal.show();
    }

    function triggerUpdateBalance(bookingId, currentBalance) {
        document.getElementById('balance-booking-id').value = bookingId;
        document.getElementById('balance-booking-display').value = '#' + bookingId;
        document.getElementById('balance-amount').value = currentBalance;
        
        var balanceModal = new bootstrap.Modal(document.getElementById('balanceModal'));
        balanceModal.show();
    }

    $(document).ready(function() {
        const hamburger = $('#hamburger');
        const sidebar = $('#sidebar');
        hamburger.on('click', () => {
            sidebar.toggleClass('active');
        });
        $(document).on('click', (e) => {
            if (!sidebar.is(e.target) && !sidebar.has(e.target).length && !hamburger.is(e.target) && !hamburger.has(e.target).length) {
                sidebar.removeClass('active');
            }
        });
    });
</script>

</body>
</html>
