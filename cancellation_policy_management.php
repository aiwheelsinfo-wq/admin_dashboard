<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// Auto-run database migrations to ensure targets exist
require_once __DIR__ . '/../2025/MigrationRunner.php';
MigrationRunner::run($conn);

// --- Action Handlers ---

// 1. Update Cancellation Policy
if (isset($_POST['action']) && $_POST['action'] === 'update_policy') {
    $cancellation_enabled = isset($_POST['cancellation_enabled']) ? 1 : 0;
    $free_cancellation_hours = intval($_POST['free_cancellation_hours'] ?? 48);
    $refund_above_48h = floatval($_POST['refund_above_48h'] ?? 100.0);
    $refund_24_48h = floatval($_POST['refund_24_48h'] ?? 75.0);
    $refund_12_24h = floatval($_POST['refund_12_24h'] ?? 50.0);
    $refund_6_12h = floatval($_POST['refund_6_12h'] ?? 25.0);
    $refund_below_6h = floatval($_POST['refund_below_6h'] ?? 0.0);
    
    $vendor_comp_above_24h = floatval($_POST['vendor_comp_above_24h'] ?? 0.0);
    $vendor_comp_6_24h = floatval($_POST['vendor_comp_6_24h'] ?? 50.0);
    $vendor_comp_below_6h = floatval($_POST['vendor_comp_below_6h'] ?? 100.0);
    
    $auto_refund = isset($_POST['auto_refund']) ? 1 : 0;
    $manual_approval = isset($_POST['manual_approval']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO cancellation_policy (
        cancellation_enabled, free_cancellation_hours, 
        refund_above_48h, refund_24_48h, refund_12_24h, refund_6_12h, refund_below_6h, 
        vendor_comp_above_24h, vendor_comp_6_24h, vendor_comp_below_6h, 
        auto_refund, manual_approval
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->bind_param("iidddddddiii", 
        $cancellation_enabled, $free_cancellation_hours,
        $refund_above_48h, $refund_24_48h, $refund_12_24h, $refund_6_12h, $refund_below_6h,
        $vendor_comp_above_24h, $vendor_comp_6_24h, $vendor_comp_below_6h,
        $auto_refund, $manual_approval
    );

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Cancellation policy updated successfully!";
    } else {
        $_SESSION['error_msg'] = "Database error: " . $conn->error;
    }
    $stmt->close();
    header("Location: cancellation_policy_management.php");
    exit();
}

// 2. Approve Cancellation Request
if (isset($_GET['action']) && $_GET['action'] === 'approve' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    
    if ($booking_id > 0) {
        // Fetch auto-refund settings
        $policyResult = $conn->query("SELECT auto_refund FROM cancellation_policy ORDER BY id DESC LIMIT 1");
        $policy = $policyResult ? $policyResult->fetch_assoc() : ['auto_refund' => 1];
        
        $new_refund_status = ($policy['auto_refund'] == 1) ? 'Completed' : 'Pending Approval';
        
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = 'Cancelled', refund_status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_refund_status, $booking_id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Booking cancellation request approved!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: cancellation_policy_management.php");
    exit();
}

// 3. Reject Cancellation Request
if (isset($_GET['action']) && $_GET['action'] === 'reject' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    
    if ($booking_id > 0) {
        // Fetch booking to see if it had driver assigned
        $stmt = $conn->prepare("SELECT driver_id FROM bookings WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $b = $res->fetch_assoc();
        $stmt->close();
        
        $status = (!empty($b['driver_id'])) ? 'Accepted' : 'Confirmed'; // Restore correct status
        
        $stmt2 = $conn->prepare("UPDATE bookings SET booking_status = ?, refund_status = NULL, cancellation_reason = NULL WHERE id = ?");
        $stmt2->bind_param("si", $status, $booking_id);
        if ($stmt2->execute()) {
            $_SESSION['success_msg'] = "Booking cancellation request rejected and booking status restored to $status.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt2->close();
    }
    header("Location: cancellation_policy_management.php");
    exit();
}

// 3b. Mark Refund Processing
if (isset($_GET['action']) && $_GET['action'] === 'process_refund' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    if ($booking_id > 0) {
        $stmt = $conn->prepare("UPDATE bookings SET refund_status = 'Processing' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Refund status marked as PROCESSING.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: cancellation_policy_management.php");
    exit();
}

// 4. Mark Refund Completed
if (isset($_GET['action']) && $_GET['action'] === 'complete_refund' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    if ($booking_id > 0) {
        $stmt = $conn->prepare("UPDATE bookings SET refund_status = 'Completed' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Refund status marked as COMPLETED.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: cancellation_policy_management.php");
    exit();
}

// 5. Mark Vendor Compensation Paid
if (isset($_GET['action']) && $_GET['action'] === 'pay_vendor' && isset($_GET['id'])) {
    $booking_id = intval($_GET['id']);
    if ($booking_id > 0) {
        $stmt = $conn->prepare("UPDATE bookings SET vendor_compensation_status = 'Paid' WHERE id = ?");
        $stmt->bind_param("i", $booking_id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Vendor compensation status marked as PAID.";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: cancellation_policy_management.php");
    exit();
}

// --- Fetch Cancellation Policy ---
$policy_res = $conn->query("SELECT * FROM cancellation_policy ORDER BY id DESC LIMIT 1");
$policy = $policy_res ? $policy_res->fetch_assoc() : [
    "cancellation_enabled" => 1,
    "free_cancellation_hours" => 48,
    "refund_above_48h" => 100.0,
    "refund_24_48h" => 75.0,
    "refund_12_24h" => 50.0,
    "refund_6_12h" => 25.0,
    "refund_below_6h" => 0.0,
    "vendor_comp_above_24h" => 0.0,
    "vendor_comp_6_24h" => 50.0,
    "vendor_comp_below_6h" => 100.0,
    "auto_refund" => 1,
    "manual_approval" => 0
];

// --- Fetch Cancellation Requests & Cancelled Bookings ---
$requests = [];
$cancelled_bookings = [];

$res = $conn->query("SELECT b.*, u.name AS user_name, d.full_name AS driver_name 
                     FROM bookings b 
                     LEFT JOIN users u ON b.booker_id = u.phone_number 
                     LEFT JOIN drivers d ON b.driver_id = d.phone_number
                     WHERE b.booking_status IN ('Cancellation Requested', 'Cancelled') 
                     ORDER BY b.id DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['booking_status'] === 'Cancellation Requested') {
            $requests[] = $row;
        } else {
            $cancelled_bookings[] = $row;
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
    <title>Cancellation & Refund Settings — Rentox Admin</title>
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
        .btn-primary-custom {
            background: linear-gradient(135deg, #FFB300, #FFA000);
            color: black;
            border: none;
            padding: 10px 20px;
            font-weight: 700;
            border-radius: 10px;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 179, 0, 0.4);
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
        .status-pill.processing { background-color: rgba(0, 123, 255, 0.15); color: #007bff; }
        .status-pill.completed { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .form-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 20px;
            margin-bottom: 12px;
            border-left: 3px solid #FFB300;
            padding-left: 8px;
        }
    </style>
</head>
<body>

<nav class="top-nav">
    <div class="logo-container">
        <img src="images/logo_rentox.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-ban me-2"></i> Cancellation & Refund Policy
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
            <li><a href="dashboard.php?tab=newuser" id="newuser"><i class="fa-solid fa-users me-2"></i> Customers</a></li>
            <li><a href="https://agnicarrental.com/admin2025/bookacall/admin-bookings.php" id="bookacall"><i class="fa-solid fa-phone me-2"></i>BookACall</a></li>
            <li><a href="dashboard.php?tab=blocked_customer" id="Blocked_Customer"><i class="fas fa-user-slash me-2"></i>Blocked Customer</a></li>
            <li><a href="dashboard.php?tab=extract_data" id="Extract_Data"><i class="fas fa-file-excel me-2"></i> Extract Data</a></li>
            <li><a href="partner/index.php" id="partner_api"><i class="fas fa-handshake me-2"></i> Partner API</a></li>
            <li><a href="partner/monitor.php" id="partner_monitor"><i class="fas fa-desktop me-2"></i> Partner Monitor</a></li>
            <li><a href="car_categories.php" id="car_categories_menu"><i class="fas fa-tags me-2"></i> Car Categories</a></li>
            <li><a href="discount_management.php" id="discount_management_menu"><i class="fas fa-percent me-2"></i> Discount Management</a></li>
            <li><a href="vendor_settlements.php" id="vendor_settlements_menu"><i class="fas fa-wallet me-2"></i> Vendor Settlements</a></li>
            <li><a href="cancellation_policy_management.php" id="cancellation_policy_menu" style="background-color: #465c71;"><i class="fas fa-ban me-2"></i> Cancellation Policy</a></li>
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

        <div class="row g-4">
            <!-- Left Side: Config Form -->
            <div class="col-lg-4">
                <div class="partner-card">
                    <div class="partner-card-header">
                        <h4><i class="fas fa-sliders-h me-1" style="color:#FFB300"></i>Policy Configuration</h4>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_policy">
                        
                        <!-- Toggle Options -->
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="cancellation_enabled" id="cancellation_enabled" <?= $policy['cancellation_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label font-weight-bold" for="cancellation_enabled">Enable Customer Cancellation</label>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Free Cancellation Threshold (Hours before pickup)</label>
                            <input type="number" name="free_cancellation_hours" class="form-control" value="<?= htmlspecialchars($policy['free_cancellation_hours']) ?>" required style="border-radius:10px; padding:8px 12px;">
                        </div>

                        <!-- Refund Brackets -->
                        <div class="form-section-title">Refund Percentages</div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">More than 48 Hours</label>
                                <input type="number" step="0.1" name="refund_above_48h" class="form-control" value="<?= htmlspecialchars($policy['refund_above_48h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">24 to 48 Hours</label>
                                <input type="number" step="0.1" name="refund_24_48h" class="form-control" value="<?= htmlspecialchars($policy['refund_24_48h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-4 mt-2">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">12 to 24 Hours</label>
                                <input type="number" step="0.1" name="refund_12_24h" class="form-control" value="<?= htmlspecialchars($policy['refund_12_24h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-4 mt-2">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">6 to 12 Hours</label>
                                <input type="number" step="0.1" name="refund_6_12h" class="form-control" value="<?= htmlspecialchars($policy['refund_6_12h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-4 mt-2">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">Less than 6 Hours</label>
                                <input type="number" step="0.1" name="refund_below_6h" class="form-control" value="<?= htmlspecialchars($policy['refund_below_6h']) ?>" required style="border-radius:8px;">
                            </div>
                        </div>

                        <!-- Vendor Protection Settings -->
                        <div class="form-section-title">Vendor Compensation (% of Charge)</div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">More than 24 Hours</label>
                                <input type="number" step="0.1" name="vendor_comp_above_24h" class="form-control" value="<?= htmlspecialchars($policy['vendor_comp_above_24h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-6">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">6 to 24 Hours</label>
                                <input type="number" step="0.1" name="vendor_comp_6_24h" class="form-control" value="<?= htmlspecialchars($policy['vendor_comp_6_24h']) ?>" required style="border-radius:8px;">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="form-label" style="font-size:0.8rem; color:#555;">Less than 6 Hours</label>
                                <input type="number" step="0.1" name="vendor_comp_below_6h" class="form-control" value="<?= htmlspecialchars($policy['vendor_comp_below_6h']) ?>" required style="border-radius:8px;">
                            </div>
                        </div>

                        <!-- Refund Automation -->
                        <div class="form-section-title">Automation & Workflow</div>
                        <div class="mb-3 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="auto_refund" id="auto_refund" <?= $policy['auto_refund'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_refund">Enable Automatic Refund Processing</label>
                        </div>
                        <div class="mb-4 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="manual_approval" id="manual_approval" <?= $policy['manual_approval'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="manual_approval">Require Admin Approval to Cancel</label>
                        </div>

                        <button type="submit" class="btn-primary-custom w-100">
                            <i class="fas fa-save me-1"></i>Save Policy Rules
                        </button>
                    </form>
                </div>
            </div>

            <!-- Right Side: Lists -->
            <div class="col-lg-8">
                <!-- Tab 1: Pending Requests -->
                <div class="partner-card">
                    <div class="partner-card-header">
                        <h4><i class="fas fa-clock me-1" style="color:#FFB300;"></i>Pending Cancellation Requests</h4>
                        <span class="badge bg-danger"><?= count($requests) ?> Pending</span>
                    </div>
                    
                    <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                        <table class="monitor-table text-center">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Route</th>
                                    <th>Pickup Time</th>
                                    <th>Customer</th>
                                    <th>Reason</th>
                                    <th>Advance</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($requests)): ?>
                                    <tr>
                                        <td colspan="7" class="text-muted py-4">No pending cancellation requests.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($requests as $idx => $r): ?>
                                        <tr>
                                            <td style="font-weight:700;">#<?= $r['id'] ?></td>
                                            <td style="font-size:0.8rem;"><?= htmlspecialchars($r['from_address']) ?> ➔ <br><?= htmlspecialchars($r['to_address'] ?: 'Local') ?></td>
                                            <td style="font-size:0.8rem; font-weight:600;"><?= date('d M Y', strtotime($r['date'])) ?><br><?= htmlspecialchars($r['time']) ?></td>
                                            <td style="font-size:0.8rem;"><?= htmlspecialchars($r['user_name'] ?: $r['mobile']) ?></td>
                                            <td style="font-size:0.82rem; font-style:italic; color:#dc3545;"><?= htmlspecialchars($r['cancellation_reason']) ?></td>
                                            <td style="font-weight:700;">₹<?= htmlspecialchars($r['paid_amount']) ?></td>
                                            <td>
                                                <div class="d-inline-flex gap-2">
                                                    <a href="cancellation_policy_management.php?action=approve&id=<?= $r['id'] ?>" class="btn-action btn-approve" onclick="return confirm('Approve booking cancellation #<?= $r['id'] ?>? This will trigger refund calculations.')">
                                                        <i class="fas fa-check"></i> Approve
                                                    </a>
                                                    <a href="cancellation_policy_management.php?action=reject&id=<?= $r['id'] ?>" class="btn-action btn-reject" onclick="return confirm('Reject cancellation request #<?= $r['id'] ?> and restore booking?')">
                                                        <i class="fas fa-times"></i> Reject
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: Cancelled History -->
                <div class="partner-card mt-4">
                    <div class="partner-card-header">
                        <h4><i class="fas fa-history me-1" style="color:#FFB300;"></i>Cancelled Bookings & Refunds</h4>
                        <span class="badge bg-secondary"><?= count($cancelled_bookings) ?> Cancelled</span>
                    </div>

                    <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                        <table class="monitor-table text-center">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Details</th>
                                    <th>Cancelled At</th>
                                    <th>Advance</th>
                                    <th>Refund Status</th>
                                    <th>Compensation</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cancelled_bookings)): ?>
                                    <tr>
                                        <td colspan="7" class="text-muted py-4">No cancelled bookings.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cancelled_bookings as $b): ?>
                                        <tr>
                                            <td style="font-weight:700;">#<?= $b['id'] ?></td>
                                            <td class="text-start" style="font-size:0.8rem; line-height: 1.4;">
                                                <strong>Reason:</strong> <?= htmlspecialchars($b['cancellation_reason']) ?><br>
                                                <strong>Charge:</strong> ₹<?= htmlspecialchars($b['cancellation_charge']) ?><br>
                                                <strong>Refund:</strong> ₹<?= htmlspecialchars($b['refund_amount']) ?>
                                            </td>
                                            <td style="font-size:0.8rem;"><?= htmlspecialchars($b['cancelled_at']) ?></td>
                                            <td style="font-weight:600;">₹<?= htmlspecialchars($b['paid_amount']) ?></td>
                                            <td>
                                                <span class="status-pill <?= strtolower($b['refund_status']) ?>">
                                                    <?= htmlspecialchars($b['refund_status'] ?: 'Processing') ?>
                                                </span>
                                            </td>
                                            <td style="font-size:0.8rem; line-height: 1.4;">
                                                <strong>Comp:</strong> ₹<?= htmlspecialchars($b['vendor_compensation']) ?><br>
                                                <strong>Status:</strong> <?= $b['vendor_compensation'] > 0 ? htmlspecialchars($b['vendor_compensation_status'] ?: 'Pending') : 'N/A' ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <?php if ($b['refund_amount'] > 0): ?>
                                                        <?php if (empty($b['refund_status']) || strtolower($b['refund_status']) === 'pending approval'): ?>
                                                            <a href="cancellation_policy_management.php?action=process_refund&id=<?= $b['id'] ?>" class="btn-action btn-approve py-1 px-2" style="background-color:rgba(255,193,7,0.1); color:#d39e00; font-size:0.75rem; border:1px solid rgba(255,193,7,0.3);">
                                                                <i class="fas fa-spinner fa-spin"></i> Process Refund
                                                            </a>
                                                        <?php elseif (strtolower($b['refund_status']) === 'processing'): ?>
                                                            <a href="cancellation_policy_management.php?action=complete_refund&id=<?= $b['id'] ?>" class="btn-action btn-approve py-1 px-2" style="font-size:0.75rem;">
                                                                <i class="fas fa-hand-holding-usd"></i> Refund Completed
                                                            </a>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($b['vendor_compensation'] > 0 && strtolower($b['vendor_compensation_status']) !== 'paid'): ?>
                                                        <a href="cancellation_policy_management.php?action=pay_vendor&id=<?= $b['id'] ?>" class="btn-action btn-approve py-1 px-2" style="background-color:rgba(0,123,255,0.1); color:#007bff; font-size:0.75rem;">
                                                            <i class="fas fa-wallet"></i> Comp Paid
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (($b['refund_amount'] <= 0 || strtolower($b['refund_status']) === 'completed') && ($b['vendor_compensation'] <= 0 || strtolower($b['vendor_compensation_status']) === 'paid')): ?>
                                                        <span class="text-success" style="font-size:0.78rem; font-weight:700;"><i class="fas fa-check-double"></i> Settled</span>
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
            </div>
        </div>

    </main>
</div>

<script>
    $(document).ready(function() {
        // Sidebar hamburger menu
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
