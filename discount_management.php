<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// Auto-run database migrations to ensure target tables (e.g. 'discounts') exist
require_once __DIR__ . '/../2025/MigrationRunner.php';
MigrationRunner::run($conn);

// --- Action Handlers ---

// 1. Add Discount
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $name = trim($_POST['name'] ?? '');
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $apply_scope = $_POST['apply_scope'] ?? 'One-way';

    if (empty($name) || empty($start_date) || empty($end_date) || $discount_value <= 0) {
        $_SESSION['error_msg'] = "Please fill in all required fields and ensure discount value is greater than 0.";
    } else {
        $stmt = $conn->prepare("INSERT INTO discounts (name, discount_type, discount_value, description, status, start_date, end_date, apply_scope) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsssss", $name, $discount_type, $discount_value, $description, $status, $start_date, $end_date, $apply_scope);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Discount created successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: discount_management.php");
    exit();
}

// 2. Edit Discount
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $discount_type = $_POST['discount_type'] ?? 'percentage';
    $discount_value = floatval($_POST['discount_value'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $status = $_POST['status'] ?? 'active';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $apply_scope = $_POST['apply_scope'] ?? 'One-way';

    if ($id <= 0 || empty($name) || empty($start_date) || empty($end_date) || $discount_value <= 0) {
        $_SESSION['error_msg'] = "Please fill in all required fields and ensure discount value is greater than 0.";
    } else {
        $stmt = $conn->prepare("UPDATE discounts SET name = ?, discount_type = ?, discount_value = ?, description = ?, status = ?, start_date = ?, end_date = ?, apply_scope = ? WHERE id = ?");
        $stmt->bind_param("ssdsssssi", $name, $discount_type, $discount_value, $description, $status, $start_date, $end_date, $apply_scope, $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Discount updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: discount_management.php");
    exit();
}

// 3. Toggle Status (Active / Inactive)
if (isset($_GET['action']) && in_array($_GET['action'], ['activate', 'deactivate']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['action'] === 'activate' ? 'active' : 'inactive';

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE discounts SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Discount status updated to " . ucfirst($status) . " successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: discount_management.php");
    exit();
}

// 4. Delete Discount
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM discounts WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Discount deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: discount_management.php");
    exit();
}

// --- Fetch Discounts ---
$search = trim($_GET['search'] ?? '');
$discounts = [];

if ($search !== '') {
    $search_wildcard = "%" . $search . "%";
    $q_stmt = $conn->prepare("SELECT * FROM discounts WHERE name LIKE ? OR description LIKE ? ORDER BY id DESC");
    $q_stmt->bind_param("ss", $search_wildcard, $search_wildcard);
    $q_stmt->execute();
    $result = $q_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $discounts[] = $row;
    }
    $q_stmt->close();
} else {
    $result = mysqli_query($conn, "SELECT * FROM discounts ORDER BY id DESC");
    while ($row = mysqli_fetch_assoc($result)) {
        $discounts[] = $row;
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
    <title>Discount Management — Agni Car Rental</title>
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
            font-size: 0.8rem;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-action.btn-edit {
            background-color: rgba(255, 193, 7, 0.12);
            color: #d39e00;
        }
        .btn-action.btn-edit:hover {
            background-color: #ffc107;
            color: #fff;
        }
        .btn-action.btn-block {
            background-color: rgba(220, 53, 69, 0.12);
            color: #dc3545;
        }
        .btn-action.btn-block:hover {
            background-color: #dc3545;
            color: #fff;
        }
        .btn-action.btn-unblock {
            background-color: rgba(40, 167, 69, 0.12);
            color: #28a745;
        }
        .btn-action.btn-unblock:hover {
            background-color: #28a745;
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
        .status-pill.active { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.inactive { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; }
    </style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container">
        <img src="images/logo_rentox.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-percent me-2"></i> Discount Management
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
            <li><a href="discount_management.php" id="discount_management_menu" style="background-color: #465c71;"><i class="fas fa-percent me-2"></i> Discount Management</a></li>
            <li><a href="vendor_settlements.php" id="vendor_settlements_menu"><i class="fas fa-wallet me-2"></i> Vendor Settlements</a></li>
            <li><a href="cancellation_policy_management.php" id="cancellation_policy_menu"><i class="fas fa-ban me-2"></i> Cancellation Policy</a></li>
            <li><a href="dashboard.php?tab=shared_onboardings" id="shared_onboardings_menu"><i class="fa-solid fa-share-nodes me-2"></i> Shared Fleet</a></li>
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
            <!-- Create Discount Form Panel -->
            <div class="col-lg-4">
                <div class="partner-card">
                    <div class="partner-card-header">
                        <h4><i class="fas fa-plus me-1" style="color:#FFB300"></i>Create Discount</h4>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Discount Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Monsoon Special" required style="border-radius:10px; padding:10px 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Discount Type <span class="text-danger">*</span></label>
                            <select name="discount_type" class="form-select" style="border-radius:10px; padding:10px 14px;">
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (₹)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Discount Value <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" name="discount_value" class="form-control" placeholder="e.g. 10.00" required style="border-radius:10px; padding:10px 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Description (optional)</label>
                            <textarea name="description" class="form-control" placeholder="Describe this discount..." style="border-radius:10px; padding:10px 14px; height:80px;"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" style="border-radius:10px; padding:10px 14px;">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">End Date <span class="text-danger">*</span></label>
                            <input type="date" name="end_date" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Apply Scope <span class="text-danger">*</span></label>
                            <select name="apply_scope" class="form-select" style="border-radius:10px; padding:10px 14px;">
                                <option value="One-way">One-way</option>
                                <option value="Local-taxi">Local-taxi</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-primary-custom w-100">
                            <i class="fas fa-save me-1"></i>Create Discount
                        </button>
                    </form>
                </div>
            </div>

            <!-- Discounts Listing Panel -->
            <div class="col-lg-8">
                <div class="partner-card">
                    <div class="partner-card-header flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                        <h4><i class="fas fa-list me-1" style="color:#FFB300;"></i>All Discounts</h4>
                        
                        <!-- Search Form -->
                        <form method="GET" style="max-width:260px; width:100%;">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search discounts..." value="<?= htmlspecialchars($search) ?>" style="border-radius:10px 0 0 10px; font-size:0.88rem;">
                                <button class="btn btn-secondary" type="submit" style="border-radius:0 10px 10px 0; background:#FFB300; border:none; color:black;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="table-responsive" style="border-radius:12px; border:1px solid #eaedf1;">
                        <table class="monitor-table text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Validity</th>
                                    <th>Scope</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($discounts)): ?>
                                    <tr>
                                        <td colspan="8" class="text-muted py-4">No discounts found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($discounts as $idx => $discount): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td style="font-weight:600;"><?= htmlspecialchars($discount['name']) ?></td>
                                            <td><?= htmlspecialchars(ucfirst($discount['discount_type'])) ?></td>
                                            <td style="font-weight:600;">
                                                <?= $discount['discount_type'] === 'percentage' ? htmlspecialchars(round($discount['discount_value'])) . '%' : '₹' . htmlspecialchars($discount['discount_value']) ?>
                                            </td>
                                            <td style="font-size:0.82rem; color:#666;">
                                                <?= date('d M Y', strtotime($discount['start_date'])) ?> to <?= date('d M Y', strtotime($discount['end_date'])) ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($discount['apply_scope']) ?></span></td>
                                            <td>
                                                <span class="status-pill <?= $discount['status'] ?>">
                                                    <?= htmlspecialchars($discount['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-inline-flex gap-2">
                                                    <!-- Edit Trigger -->
                                                    <button class="btn-action btn-edit" onclick="triggerEdit(<?= htmlspecialchars(json_encode($discount)) ?>)">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    <!-- Toggle Status -->
                                                    <?php if ($discount['status'] === 'active'): ?>
                                                        <a href="discount_management.php?action=deactivate&id=<?= $discount['id'] ?>" class="btn-action btn-block" onclick="return confirm('Deactivate discount: <?= addslashes(htmlspecialchars($discount['name'])) ?>?')">
                                                            <i class="fas fa-ban"></i> Deactivate
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="discount_management.php?action=activate&id=<?= $discount['id'] ?>" class="btn-action btn-unblock" onclick="return confirm('Activate discount: <?= addslashes(htmlspecialchars($discount['name'])) ?>?')">
                                                            <i class="fas fa-check-circle"></i> Activate
                                                        </a>
                                                    <?php endif; ?>

                                                    <!-- Delete -->
                                                    <a href="discount_management.php?action=delete&id=<?= $discount['id'] ?>" class="btn-action btn-block" style="background-color:rgba(220, 53, 69, 0.2); color:#dc3545;" onclick="return confirm('Are you sure you want to permanently delete: <?= addslashes(htmlspecialchars($discount['name'])) ?>?')">
                                                        <i class="fas fa-trash"></i>
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
            </div>
        </div>

    </main>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" style="border-bottom:1px solid #eaedf1;">
                <h5 class="modal-title" id="editModalLabel" style="font-weight:700;"><i class="fas fa-edit me-2" style="color:#FFB300"></i>Edit Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding:24px;">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Discount Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit-name" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Discount Type <span class="text-danger">*</span></label>
                        <select name="discount_type" id="edit-discount_type" class="form-select" style="border-radius:10px; padding:10px 14px;">
                            <option value="percentage">Percentage (%)</option>
                            <option value="fixed">Fixed Amount (₹)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Discount Value <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="discount_value" id="edit-discount_value" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Description (optional)</label>
                        <textarea name="description" id="edit-description" class="form-control" style="border-radius:10px; padding:10px 14px; height:80px;"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Status <span class="text-danger">*</span></label>
                        <select name="status" id="edit-status" class="form-select" style="border-radius:10px; padding:10px 14px;">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="start_date" id="edit-start_date" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">End Date <span class="text-danger">*</span></label>
                        <input type="date" name="end_date" id="edit-end_date" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Apply Scope <span class="text-danger">*</span></label>
                        <select name="apply_scope" id="edit-apply_scope" class="form-select" style="border-radius:10px; padding:10px 14px;">
                            <option value="One-way">One-way</option>
                            <option value="Local-taxi">Local-taxi</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer" style="border-top:1px solid #eaedf1; padding:16px 24px;">
                    <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#editModal" style="border-radius:10px;">Cancel</button>
                    <button type="submit" class="btn-primary-custom">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function triggerEdit(discount) {
        document.getElementById('edit-id').value = discount.id;
        document.getElementById('edit-name').value = discount.name;
        document.getElementById('edit-discount_type').value = discount.discount_type;
        document.getElementById('edit-discount_value').value = discount.discount_value;
        document.getElementById('edit-description').value = discount.description;
        document.getElementById('edit-status').value = discount.status;
        document.getElementById('edit-start_date').value = discount.start_date;
        document.getElementById('edit-end_date').value = discount.end_date;
        document.getElementById('edit-apply_scope').value = discount.apply_scope;
        
        var editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }

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
