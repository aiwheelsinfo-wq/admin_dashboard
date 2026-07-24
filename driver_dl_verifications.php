<?php
session_start();
include 'db_connect.php';

// Ensure table exists
$tableSql = "CREATE TABLE IF NOT EXISTS `driver_dl_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dl_number` VARCHAR(30) NOT NULL UNIQUE,
  `dob` DATE NOT NULL,
  `holder_name` VARCHAR(100),
  `issue_date` DATE,
  `expiry_date` DATE,
  `vehicle_classes` VARCHAR(255),
  `has_lmv` TINYINT(1) DEFAULT 1,
  `dl_photo_path` VARCHAR(255),
  `permanent_address` TEXT,
  `verification_status` ENUM('VERIFIED', 'EXPIRED', 'REJECTED', 'MANUAL_APPROVED') DEFAULT 'VERIFIED',
  `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $tableSql);

// Handle Admin Action (Manual Approval / Override / Block)
$msg = "";
$msgType = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action_dl = mysqli_real_escape_string($conn, $_POST['dl_number'] ?? '');
    
    if ($_POST['action'] === 'approve_manual') {
        $updateSql = "UPDATE driver_dl_verifications SET verification_status='MANUAL_APPROVED' WHERE dl_number='$action_dl'";
        if (mysqli_query($conn, $updateSql)) {
            // Also update driver status in main drivers table if exists
            mysqli_query($conn, "UPDATE drivers SET status='filled' WHERE license_no='$action_dl'");
            $msg = "Driver DL $action_dl approved manually by Admin successfully!";
            $msgType = "success";
        }
    } elseif ($_POST['action'] === 'reject') {
        $updateSql = "UPDATE driver_dl_verifications SET verification_status='REJECTED' WHERE dl_number='$action_dl'";
        if (mysqli_query($conn, $updateSql)) {
            $msg = "Driver DL $action_dl marked as REJECTED.";
            $msgType = "warning";
        }
    }
}

// Fetch Metrics Counts
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_dl_verifications");
$totalCount = mysqli_fetch_assoc($totalQuery)['cnt'] ?? 0;

$verifiedQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_dl_verifications WHERE verification_status='VERIFIED' AND (expiry_date >= CURDATE() OR expiry_date IS NULL)");
$verifiedCount = mysqli_fetch_assoc($verifiedQuery)['cnt'] ?? 0;

$expiredQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_dl_verifications WHERE expiry_date < CURDATE() OR verification_status='EXPIRED'");
$expiredCount = mysqli_fetch_assoc($expiredQuery)['cnt'] ?? 0;

$manualQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_dl_verifications WHERE verification_status='MANUAL_APPROVED'");
$manualCount = mysqli_fetch_assoc($manualQuery)['cnt'] ?? 0;

// Fetch All DL Verification Records
$statusFilter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$whereClause = "WHERE 1=1";
if (!empty($statusFilter)) {
    if ($statusFilter === 'EXPIRED') {
        $whereClause .= " AND (expiry_date < CURDATE() OR verification_status='EXPIRED')";
    } else {
        $whereClause .= " AND verification_status='$statusFilter'";
    }
}
if (!empty($searchQuery)) {
    $whereClause .= " AND (dl_number LIKE '%$searchQuery%' OR holder_name LIKE '%$searchQuery%' OR permanent_address LIKE '%$searchQuery%')";
}

$sql = "SELECT * FROM driver_dl_verifications $whereClause ORDER BY verified_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver DL Verifications - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2563EB;
            --primary-dark: #1D4ED8;
            --success: #059669;
            --warning: #D97706;
            --danger: #DC2626;
            --bg-body: #F8FAFC;
            --card-bg: #FFFFFF;
            --text-dark: #0F172A;
            --text-muted: #64748B;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-dark);
        }

        .navbar-brand img {
            height: 38px;
        }

        .top-navbar {
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 12px 24px;
        }

        .stat-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 12px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
        }

        .content-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .driver-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #E2E8F0;
        }

        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-verified { background: #D1FAE5; color: #047857; }
        .badge-expired { background: #FEE2E2; color: #B91C1C; }
        .badge-manual { background: #DBEAFE; color: #1E40AF; }
        .badge-rejected { background: #FEF3C7; color: #B45309; }

        .table > :not(caption) > * > * {
            padding: 14px 16px;
            vertical-align: middle;
        }

        .btn-action {
            padding: 6px 14px;
            font-size: 0.82rem;
            font-weight: 700;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="top-navbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="navbar-brand m-0">
                <img src="images/logo_rentox.png" alt="Rentox Logo" onerror="this.src='images/pnglogoagni.png'">
            </a>
            <h5 class="m-0 fw-bold text-dark">Driving License Verifications</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3"><i class="fas fa-home me-1"></i> Dashboard</a>
            <a href="regForm.php" class="btn btn-primary btn-sm rounded-3"><i class="fas fa-plus me-1"></i> Add Driver</a>
        </div>
    </nav>

    <div class="container-fluid px-4 py-4">

        <!-- Alert Notification -->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?php echo $msgType; ?> alert-dismissible fade show rounded-3" role="alert">
                <i class="fas fa-info-circle me-2"></i> <?php echo $msg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- KPI Metrics Header Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalCount; ?></div>
                    <div class="stat-label">Total Verified Licenses</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number"><?php echo $verifiedCount; ?></div>
                    <div class="stat-label">Active Approved</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number"><?php echo $expiredCount; ?></div>
                    <div class="stat-label">Expired Licenses</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stat-number"><?php echo $manualCount; ?></div>
                    <div class="stat-label">Manual Admin Approvals</div>
                </div>
            </div>
        </div>

        <!-- Main Data Table Container -->
        <div class="content-card">
            
            <!-- Filters & Search Toolbar -->
            <form method="GET" action="" class="row g-3 mb-4 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by Name, DL Number..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Filter by Status: All</option>
                        <option value="VERIFIED" <?php if($statusFilter==='VERIFIED') echo 'selected'; ?>>🟢 Active / Verified</option>
                        <option value="EXPIRED" <?php if($statusFilter==='EXPIRED') echo 'selected'; ?>>🔴 Expired</option>
                        <option value="MANUAL_APPROVED" <?php if($statusFilter==='MANUAL_APPROVED') echo 'selected'; ?>>🔵 Manual Approved</option>
                        <option value="REJECTED" <?php if($statusFilter==='REJECTED') echo 'selected'; ?>>🟠 Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark fw-bold rounded-3"><i class="fas fa-filter me-1"></i> Apply Filter</button>
                    <a href="driver_dl_verifications.php" class="btn btn-outline-secondary rounded-3">Reset</a>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle border-top">
                    <thead class="table-light">
                        <tr>
                            <th>Driver / Photo</th>
                            <th>DL Number</th>
                            <th>Issue Date</th>
                            <th>Expiry Date</th>
                            <th>Category</th>
                            <th>Verification Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): 
                                $isExpired = (!empty($row['expiry_date']) && strtotime($row['expiry_date']) < strtotime(date('Y-m-d')));
                                $photoPath = !empty($row['dl_photo_path']) ? $row['dl_photo_path'] : 'images/default_avatar.png';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="<?php echo htmlspecialchars($photoPath); ?>" class="driver-avatar" alt="Photo" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($row['holder_name']); ?>&background=2563EB&color=fff'">
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['holder_name'] ?? 'N/A'); ?></div>
                                                <small class="text-muted">DOB: <?php echo htmlspecialchars($row['dob']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark font-monospace px-2 py-1 fs-6">
                                            <?php echo htmlspecialchars($row['dl_number']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo !empty($row['issue_date']) ? date('d M Y', strtotime($row['issue_date'])) : 'N/A'; ?></td>
                                    <td>
                                        <div class="fw-bold <?php echo $isExpired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo !empty($row['expiry_date']) ? date('d M Y', strtotime($row['expiry_date'])) : 'N/A'; ?>
                                        </div>
                                        <?php if($isExpired): ?>
                                            <small class="badge bg-danger bg-opacity-10 text-danger p-1">Expired</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info bg-opacity-10 text-info fw-bold">
                                            <?php echo ($row['has_lmv'] ? 'LMV (CAR)' : 'NON-LMV'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($isExpired) {
                                            echo '<span class="badge-status badge-expired"><i class="fas fa-clock me-1"></i> EXPIRED</span>';
                                        } elseif ($row['verification_status'] === 'MANUAL_APPROVED') {
                                            echo '<span class="badge-status badge-manual"><i class="fas fa-user-check me-1"></i> MANUAL APPROVED</span>';
                                        } elseif ($row['verification_status'] === 'VERIFIED') {
                                            echo '<span class="badge-status badge-verified"><i class="fas fa-check-circle me-1"></i> GOVT VERIFIED</span>';
                                        } else {
                                            echo '<span class="badge-status badge-rejected"><i class="fas fa-times-circle me-1"></i> REJECTED</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle rounded-3" type="button" data-bs-toggle="dropdown">
                                                Manage
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#dlModal<?php echo $row['id']; ?>">
                                                        <i class="fas fa-eye text-primary me-2"></i> View Full Details
                                                    </button>
                                                </li>
                                                <?php if($row['verification_status'] !== 'MANUAL_APPROVED'): ?>
                                                    <li>
                                                        <form method="POST" action="">
                                                            <input type="hidden" name="dl_number" value="<?php echo htmlspecialchars($row['dl_number']); ?>">
                                                            <input type="hidden" name="action" value="approve_manual">
                                                            <button type="submit" class="dropdown-item text-success fw-bold">
                                                                <i class="fas fa-user-shield me-2"></i> Approve Manually
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <form method="POST" action="">
                                                        <input type="hidden" name="dl_number" value="<?php echo htmlspecialchars($row['dl_number']); ?>">
                                                        <input type="hidden" name="action" value="reject">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-ban me-2"></i> Mark Rejected
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Full Details Modal -->
                                        <div class="modal fade text-start" id="dlModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content rounded-4 border-0">
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h5 class="modal-title fw-bold">Driving License Verification Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="text-center mb-3">
                                                            <img src="<?php echo htmlspecialchars($photoPath); ?>" class="rounded-circle border" style="width: 90px; height: 90px; object-fit: cover;" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($row['holder_name']); ?>&background=2563EB&color=fff'">
                                                            <h5 class="fw-bold mt-2 mb-0"><?php echo htmlspecialchars($row['holder_name']); ?></h5>
                                                            <small class="text-muted">DL: <?php echo htmlspecialchars($row['dl_number']); ?></small>
                                                        </div>
                                                        <hr>
                                                        <div class="row g-3 fs-6">
                                                            <div class="col-6"><strong>Date of Birth:</strong><br><?php echo htmlspecialchars($row['dob']); ?></div>
                                                            <div class="col-6"><strong>Issue Date:</strong><br><?php echo htmlspecialchars($row['issue_date']); ?></div>
                                                            <div class="col-6"><strong>Expiry Date:</strong><br><span class="<?php echo $isExpired?'text-danger':'text-success'; ?> fw-bold"><?php echo htmlspecialchars($row['expiry_date']); ?></span></div>
                                                            <div class="col-6"><strong>Vehicle Class:</strong><br>LMV (Car / Jeep)</div>
                                                            <div class="col-12"><strong>Permanent Address:</strong><br><span class="text-muted"><?php echo htmlspecialchars($row['permanent_address'] ?? 'N/A'); ?></span></div>
                                                            <div class="col-12"><strong>Verification Timestamp:</strong><br><span class="text-muted"><?php echo htmlspecialchars($row['verified_at']); ?></span></div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer border-top-0 pt-0">
                                                        <button type="button" class="btn btn-secondary btn-sm rounded-3" data-bs-dismiss="modal">Close</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h5>No Driving License records found</h5>
                                    <p class="small m-0">When drivers verify their DL in the app, their details will appear here automatically.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
