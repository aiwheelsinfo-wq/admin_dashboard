<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// Auto-run database migrations to ensure target columns exist
require_once __DIR__ . '/../2025/MigrationRunner.php';
MigrationRunner::run($conn);

// --- Action Handlers ---

// 1. Status Update via GET (Approve, Hold, Reject, Processing)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    $allowed_statuses = [
        'approve' => 'Approved',
        'hold' => 'Hold',
        'reject' => 'Rejected',
        'processing' => 'Processing',
        'pending' => 'Pending'
    ];

    if (array_key_exists($action, $allowed_statuses)) {
        $status = $allowed_statuses[$action];
        $stmt = $conn->prepare("UPDATE bookings SET settlement_status = ? WHERE id = ? AND trip_type = 'One-way'");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Settlement status updated to " . htmlspecialchars($status) . " successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: vendor_settlements.php");
    exit();
}

// 2. Mark as Paid via POST
if (isset($_POST['action']) && $_POST['action'] === 'pay') {
    $id = (int)($_POST['id'] ?? 0);
    $txn_ref = trim($_POST['transaction_reference'] ?? '');

    if ($id <= 0) {
        $_SESSION['error_msg'] = "Invalid booking ID.";
    } elseif (empty($txn_ref)) {
        $_SESSION['error_msg'] = "Transaction reference cannot be empty.";
    } else {
        $now = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("UPDATE bookings SET settlement_status = 'Paid', settlement_date = ?, transaction_reference = ? WHERE id = ? AND trip_type = 'One-way'");
        $stmt->bind_param("ssi", $now, $txn_ref, $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Settlement marked as Paid successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: vendor_settlements.php");
    exit();
}

// --- Fetch Settlements ---
$search = trim($_GET['search'] ?? '');
$settlements = [];

$query = "
    SELECT 
        b.id AS booking_id,
        b.paid_amount,
        b.booking_status,
        b.settlement_status,
        b.settlement_date,
        b.transaction_reference,
        u.name AS customer_name,
        COALESCE(v.agency_name, v.name, b.vender_id) AS vendor_name
    FROM bookings b
    INNER JOIN users u ON b.booker_id = u.phone_number
    LEFT JOIN users v ON b.vender_id = v.phone_number
    WHERE b.trip_type = 'One-way' 
      AND b.payment_type = 'Advance' 
      AND (b.payment_status = 'success' OR b.payment_status = 'Advance Paid')
";

if ($search !== '') {
    $query .= " AND (b.id LIKE ? OR u.name LIKE ? OR v.name LIKE ? OR v.agency_name LIKE ?)";
    $search_wildcard = "%" . $search . "%";
    $q_stmt = $conn->prepare($query . " ORDER BY b.id DESC");
    $q_stmt->bind_param("ssss", $search_wildcard, $search_wildcard, $search_wildcard, $search_wildcard);
    $q_stmt->execute();
    $result = $q_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $settlements[] = $row;
    }
    $q_stmt->close();
} else {
    $result = mysqli_query($conn, $query . " ORDER BY b.id DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settlements[] = $row;
        }
    }
}

$success_msg = $_SESSION['success_msg'] ?? null;
$error_msg = $_SESSION['error_msg'] ?? null;
unset($_SESSION['success_msg'], $_SESSION['error_msg']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Settlement Management — Agni Car Rental</title>
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
        .partner-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .partner-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #dee2e6;
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
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 6px 12px;
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
        .btn-action.btn-hold {
            background-color: rgba(255, 193, 7, 0.12);
            color: #d39e00;
        }
        .btn-action.btn-hold:hover {
            background-color: #ffc107;
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
            background-color: rgba(108, 99, 255, 0.12);
            color: #6C63FF;
        }
        .btn-action.btn-pay:hover {
            background-color: #6C63FF;
            color: #fff;
        }
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pill.pending { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }
        .status-pill.approved { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.hold { background-color: rgba(255, 87, 34, 0.15); color: #ff5722; }
        .status-pill.processing { background-color: rgba(0, 123, 255, 0.15); color: #007bff; }
        .status-pill.paid { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.rejected { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; }
        .trip-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 600;
        }
        .trip-pill.completed { background-color: #d1e7dd; color: #0f5132; }
        .trip-pill.pending { background-color: #fff3cd; color: #664d03; }
        .trip-pill.accepted { background-color: #cff4fc; color: #055160; }
        .trip-pill.started { background-color: #e2e3e5; color: #41464b; }
    </style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container">
        <img src="images/logo.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-wallet me-2"></i> Vendor Settlements
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
            <li><a href="vendor_settlements.php" id="vendor_settlements_menu" style="background-color: #465c71;"><i class="fas fa-wallet me-2"></i> Vendor Settlements</a></li>
            <li><a href="cancellation_policy_management.php" id="cancellation_policy_menu"><i class="fas fa-ban me-2"></i> Cancellation Policy</a></li>
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

        <div class="partner-card">
            <div class="partner-card-header flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                <h4><i class="fas fa-list me-1" style="color:#6C63FF;"></i>One-Way Advance Settlements</h4>
                
                <!-- Search Form -->
                <form method="GET" style="max-width:260px; width:100%;">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>" style="border-radius:10px 0 0 10px; font-size:0.88rem;">
                        <button class="btn btn-secondary" type="submit" style="border-radius:0 10px 10px 0; background:#6C63FF; border:none;">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Vendor</th>
                            <th>Advance Paid</th>
                            <th>Vendor Share (60%)</th>
                            <th>Trip Status</th>
                            <th>Settlement Status</th>
                            <th>Settlement Date</th>
                            <th>Txn Reference</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($settlements)): ?>
                            <tr>
                                <td colspan="10" class="text-muted py-4">No settlements found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($settlements as $s): ?>
                                <?php 
                                    $advance = floatval($s['paid_amount']);
                                    $eligible = $advance * 0.60;
                                    $trip_status = $s['booking_status'];
                                    $settlement_status = $s['settlement_status'] ?: 'Pending';
                                ?>
                                <tr>
                                    <td style="font-weight:700;">#<?= $s['booking_id'] ?></td>
                                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($s['vendor_name'] ?: 'N/A') ?></td>
                                    <td>₹<?= number_format($advance, 2) ?></td>
                                    <td style="font-weight:700; color:#28a745;">₹<?= number_format($eligible, 2) ?></td>
                                    <td>
                                        <span class="trip-pill <?= strtolower($trip_status) ?>">
                                            <?= htmlspecialchars($trip_status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill <?= strtolower($settlement_status) ?>">
                                            <?= htmlspecialchars($settlement_status) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.82rem; color:#666;">
                                        <?= $s['settlement_date'] ? date('d M Y H:i', strtotime($s['settlement_date'])) : 'N/A' ?>
                                    </td>
                                    <td style="font-family:monospace; font-size:0.82rem;">
                                        <?= htmlspecialchars($s['transaction_reference'] ?: 'N/A') ?>
                                    </td>
                                    <td>
                                        <div class="d-inline-flex gap-1">
                                            <?php if ($settlement_status !== 'Paid'): ?>
                                                <?php if ($settlement_status !== 'Approved'): ?>
                                                    <a href="vendor_settlements.php?action=approve&id=<?= $s['booking_id'] ?>" class="btn-action btn-approve" title="Approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($settlement_status !== 'Hold'): ?>
                                                    <a href="vendor_settlements.php?action=hold&id=<?= $s['booking_id'] ?>" class="btn-action btn-hold" title="Hold">
                                                        <i class="fas fa-pause"></i> Hold
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($settlement_status !== 'Rejected'): ?>
                                                    <a href="vendor_settlements.php?action=reject&id=<?= $s['booking_id'] ?>" class="btn-action btn-reject" title="Reject" onclick="return confirm('Reject settlement for booking #<?= $s['booking_id'] ?>?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <button class="btn-action btn-pay" onclick="openPaymentModal(<?= $s['booking_id'] ?>, <?= $eligible ?>)">
                                                    <i class="fas fa-rupee-sign"></i> Pay
                                                </button>
                                            <?php else: ?>
                                                <span class="text-success"><i class="fas fa-check-double"></i> Done</span>
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

    </main>
</div>

<!-- Pay Modal -->
<div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" style="border-bottom:1px solid #eaedf1;">
                <h5 class="modal-title" id="payModalLabel" style="font-weight:700;"><i class="fas fa-rupee-sign me-2" style="color:#6C63FF"></i>Mark Settlement as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding:24px;">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="id" id="pay-booking-id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Eligible Amount</label>
                        <input type="text" id="pay-amount-display" class="form-control" readonly style="border-radius:10px; background-color:#f8f9fa;">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Transaction Reference <span class="text-danger">*</span></label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. UPI Ref / Bank UTN ID" required style="border-radius:10px; padding:10px 14px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #eaedf1; padding:16px 24px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#6C63FF; border:none; border-radius:10px; padding:10px 20px;">Mark as Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openPaymentModal(bookingId, amount) {
        document.getElementById('pay-booking-id').value = bookingId;
        document.getElementById('pay-amount-display').value = '₹' + amount.toFixed(2);
        
        var payModal = new bootstrap.Modal(document.getElementById('payModal'));
        payModal.show();
    }

    $(document).ready(function() {
        const hamburger = $('#hamburger');
        const sidebar = $('#sidebar');
        hamburger.on('click', () => {
            sidebar.toggleClass('active');
        });
    });
</script>

</body>
</html>
