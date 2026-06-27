<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// --- Action Handlers ---

// 1. Add Category
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    $car_type = trim($_POST['car_type'] ?? '');
    if (empty($car_type)) {
        $_SESSION['error_msg'] = "Car category name cannot be empty.";
    } else {
        // Prepare to check duplicate
        $dup_stmt = $conn->prepare("SELECT id FROM car_categories WHERE car_type = ?");
        $dup_stmt->bind_param("s", $car_type);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        
        if ($dup_result->num_rows > 0) {
            $_SESSION['error_msg'] = "Category '" . htmlspecialchars($car_type) . "' already exists.";
        } else {
            $stmt = $conn->prepare("INSERT INTO car_categories (car_type, status) VALUES (?, 'active')");
            $stmt->bind_param("s", $car_type);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Category added successfully!";
            } else {
                $_SESSION['error_msg'] = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
        $dup_stmt->close();
    }
    header("Location: car_categories.php");
    exit();
}

// 2. Edit Category
if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id = (int)($_POST['id'] ?? 0);
    $car_type = trim($_POST['car_type'] ?? '');
    
    if (empty($car_type)) {
        $_SESSION['error_msg'] = "Car category name cannot be empty.";
    } elseif ($id <= 0) {
        $_SESSION['error_msg'] = "Invalid category ID.";
    } else {
        // Prepare to check duplicate excluding current category
        $dup_stmt = $conn->prepare("SELECT id FROM car_categories WHERE car_type = ? AND id != ?");
        $dup_stmt->bind_param("si", $car_type, $id);
        $dup_stmt->execute();
        $dup_result = $dup_stmt->get_result();
        
        if ($dup_result->num_rows > 0) {
            $_SESSION['error_msg'] = "Category '" . htmlspecialchars($car_type) . "' already exists.";
        } else {
            $stmt = $conn->prepare("UPDATE car_categories SET car_type = ? WHERE id = ?");
            $stmt->bind_param("si", $car_type, $id);
            if ($stmt->execute()) {
                $_SESSION['success_msg'] = "Category updated successfully!";
            } else {
                $_SESSION['error_msg'] = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
        $dup_stmt->close();
    }
    header("Location: car_categories.php");
    exit();
}

// 3. Toggle Status (Block / Unblock)
if (isset($_GET['action']) && in_array($_GET['action'], ['block', 'unblock']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['action'] === 'block' ? 'blocked' : 'active';
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE car_categories SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Category status updated to " . ucfirst($status) . " successfully!";
        } else {
            $_SESSION['error_msg'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: car_categories.php");
    exit();
}

// --- Fetch Categories ---
$search = trim($_GET['search'] ?? '');
$categories = [];

if ($search !== '') {
    $search_wildcard = "%" . $search . "%";
    $q_stmt = $conn->prepare("SELECT * FROM car_categories WHERE car_type LIKE ? ORDER BY car_type ASC");
    $q_stmt->bind_param("s", $search_wildcard);
    $q_stmt->execute();
    $result = $q_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $q_stmt->close();
} else {
    $result = mysqli_query($conn, "SELECT * FROM car_categories ORDER BY car_type ASC");
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
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
    <title>Car Category Management — Agni Car Rental</title>
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
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            border-radius: 10px;
            font-size: 0.88rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .btn-primary-custom:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
        }
        /* Badges */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-pill.active { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.blocked { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; }
    </style>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container">
        <img src="images/logo.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-tags me-2"></i> Car Categories
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
            <li><a href="car_categories.php" id="car_categories_menu" style="background-color: #465c71;"><i class="fas fa-tags me-2"></i> Car Categories</a></li>
            <li><a href="discount_management.php" id="discount_management_menu"><i class="fas fa-percent me-2"></i> Discount Management</a></li>
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
            <!-- Create Category Form Panel -->
            <div class="col-lg-4">
                <div class="partner-card">
                    <div class="partner-card-header">
                        <h4><i class="fas fa-plus me-1" style="color:#007bff"></i>Add New Category</h4>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label" style="font-weight:600; color:#555;">Car Type Name <span class="text-danger">*</span></label>
                            <input type="text" name="car_type" class="form-control" placeholder="e.g. Hatchback, Sedan, SUV" required style="border-radius:10px; padding:10px 14px;">
                        </div>
                        <button type="submit" class="btn-primary-custom w-100">
                            <i class="fas fa-save me-1"></i>Create Category
                        </button>
                    </form>
                </div>
            </div>

            <!-- Categories Listing Panel -->
            <div class="col-lg-8">
                <div class="partner-card">
                    <div class="partner-card-header flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                        <h4><i class="fas fa-tags me-1" style="color:#17a2b8;"></i>All Car Categories</h4>
                        
                        <!-- Search Form -->
                        <form method="GET" style="max-width:260px; width:100%;">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search categories..." value="<?= htmlspecialchars($search) ?>" style="border-radius:10px 0 0 10px; font-size:0.88rem;">
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
                                    <th>#</th>
                                    <th>Car Type</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-muted py-4">No categories found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $idx => $cat): ?>
                                        <tr>
                                            <td><?= $idx + 1 ?></td>
                                            <td style="font-weight:600;"><?= htmlspecialchars($cat['car_type']) ?></td>
                                            <td>
                                                <span class="status-pill <?= $cat['status'] ?>">
                                                    <?= htmlspecialchars($cat['status']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size:0.82rem; color:#666;">
                                                <?= date('d M Y H:i', strtotime($cat['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="d-inline-flex gap-2">
                                                    <!-- Edit Trigger -->
                                                    <button class="btn-action btn-edit" onclick="triggerEdit(<?= $cat['id'] ?>, '<?= addslashes(htmlspecialchars($cat['car_type'])) ?>')">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    <!-- Block/Unblock -->
                                                    <?php if ($cat['status'] === 'active'): ?>
                                                        <a href="car_categories.php?action=block&id=<?= $cat['id'] ?>" class="btn-action btn-block" onclick="return confirm('Are you sure you want to block category: <?= addslashes(htmlspecialchars($cat['car_type'])) ?>?')">
                                                            <i class="fas fa-ban"></i> Block
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="car_categories.php?action=unblock&id=<?= $cat['id'] ?>" class="btn-action btn-unblock" onclick="return confirm('Are you sure you want to unblock category: <?= addslashes(htmlspecialchars($cat['car_type'])) ?>?')">
                                                            <i class="fas fa-check-circle"></i> Unblock
                                                        </a>
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

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px; border:none; box-shadow:0 10px 30px rgba(0,0,0,0.1);">
            <div class="modal-header" style="border-bottom:1px solid #eaedf1;">
                <h5 class="modal-title" id="editModalLabel" style="font-weight:700;"><i class="fas fa-edit me-2" style="color:#ffc107"></i>Edit Category Name</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body" style="padding:24px;">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit-id">
                    <div class="mb-3">
                        <label class="form-label" style="font-weight:600; color:#555;">Car Type Name <span class="text-danger">*</span></label>
                        <input type="text" name="car_type" id="edit-car_type" class="form-control" required style="border-radius:10px; padding:10px 14px;">
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #eaedf1; padding:16px 24px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
                    <button type="submit" class="btn-primary-custom">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function triggerEdit(id, val) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-car_type').value = val;
        
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
