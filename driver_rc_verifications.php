<?php
session_start();
include 'db_connect.php';

// Ensure table exists
$tableSql = "CREATE TABLE IF NOT EXISTS `driver_rc_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rc_number` VARCHAR(30) NOT NULL UNIQUE,
  `owner_name` VARCHAR(100),
  `maker_model` VARCHAR(150),
  `maker_description` VARCHAR(100),
  `registration_date` DATE,
  `fit_up_to` DATE,
  `insurance_policy_number` VARCHAR(100),
  `insurance_upto` DATE,
  `insurance_company` VARCHAR(150),
  `fuel_type` VARCHAR(50),
  `color` VARCHAR(50),
  `seat_capacity` VARCHAR(10),
  `permit_number` VARCHAR(100),
  `permit_valid_upto` DATE,
  `rc_status` VARCHAR(50) DEFAULT 'ACTIVE',
  `permanent_address` TEXT,
  `verification_status` ENUM('VERIFIED', 'EXPIRED', 'REJECTED', 'MANUAL_APPROVED') DEFAULT 'VERIFIED',
  `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $tableSql);

// Auto-sync vehicle RCs from main drivers table into driver_rc_verifications if not present
$syncSql = "INSERT IGNORE INTO driver_rc_verifications (rc_number, owner_name, fit_up_to, insurance_policy_number, insurance_upto, permit_number, permit_valid_upto, verification_status)
    SELECT TRIM(rc_no), 
           IFNULL(rc_name, 'Owner'), 
           IF(fitness_certificate_doe IS NULL OR fitness_certificate_doe = '0000-00-00', '2030-01-01', fitness_certificate_doe),
           IFNULL(insurnce_number, ''),
           IF(insurnce_doe IS NULL OR insurnce_doe = '0000-00-00', '2030-01-01', insurnce_doe),
           IFNULL(texi_permit_no, ''),
           IF(texi_permit_doe IS NULL OR texi_permit_doe = '0000-00-00', '2030-01-01', texi_permit_doe),
           'VERIFIED'
    FROM drivers 
    WHERE rc_no IS NOT NULL AND TRIM(rc_no) != '' AND LOWER(TRIM(rc_no)) != 'null' AND CHAR_LENGTH(TRIM(rc_no)) >= 5";
mysqli_query($conn, $syncSql);

// Handle Admin Actions (Manual Approval / Reject / Delete)
$msg = "";
$msgType = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action_rc = mysqli_real_escape_string($conn, $_POST['rc_number'] ?? '');
    
    if ($_POST['action'] === 'approve_manual') {
        $updateSql = "UPDATE driver_rc_verifications SET verification_status='MANUAL_APPROVED' WHERE rc_number='$action_rc'";
        if (mysqli_query($conn, $updateSql)) {
            mysqli_query($conn, "UPDATE drivers SET status='filled' WHERE rc_no='$action_rc'");
            $msg = "Vehicle RC $action_rc approved manually by Admin successfully!";
            $msgType = "success";
        }
    } elseif ($_POST['action'] === 'reject') {
        $updateSql = "UPDATE driver_rc_verifications SET verification_status='REJECTED' WHERE rc_number='$action_rc'";
        if (mysqli_query($conn, $updateSql)) {
            $msg = "Vehicle RC $action_rc marked as REJECTED.";
            $msgType = "warning";
        }
    } elseif ($_POST['action'] === 'delete') {
        $deleteSql = "DELETE FROM driver_rc_verifications WHERE rc_number='$action_rc'";
        if (mysqli_query($conn, $deleteSql)) {
            $msg = "Vehicle RC $action_rc record deleted successfully!";
            $msgType = "danger";
        }
    }
}

// Fetch Metrics Counts
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_rc_verifications");
$totalCount = mysqli_fetch_assoc($totalQuery)['cnt'] ?? 0;

$verifiedQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_rc_verifications WHERE verification_status IN ('VERIFIED', 'MANUAL_APPROVED') AND (insurance_upto >= CURDATE() OR insurance_upto IS NULL) AND (fit_up_to >= CURDATE() OR fit_up_to IS NULL)");
$verifiedCount = mysqli_fetch_assoc($verifiedQuery)['cnt'] ?? 0;

$expiredQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_rc_verifications WHERE (insurance_upto < CURDATE() OR fit_up_to < CURDATE() OR verification_status='EXPIRED')");
$expiredCount = mysqli_fetch_assoc($expiredQuery)['cnt'] ?? 0;

$manualQuery = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM driver_rc_verifications WHERE verification_status='MANUAL_APPROVED'");
$manualCount = mysqli_fetch_assoc($manualQuery)['cnt'] ?? 0;

// Filters & Search
$statusFilter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$searchQuery = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$whereClause = "WHERE 1=1";
if (!empty($statusFilter)) {
    if ($statusFilter === 'EXPIRED') {
        $whereClause .= " AND (insurance_upto < CURDATE() OR fit_up_to < CURDATE() OR verification_status='EXPIRED')";
    } else {
        $whereClause .= " AND v.verification_status='$statusFilter'";
    }
}
if (!empty($searchQuery)) {
    $whereClause .= " AND (v.rc_number LIKE '%$searchQuery%' OR v.owner_name LIKE '%$searchQuery%' OR v.maker_model LIKE '%$searchQuery%' OR v.permanent_address LIKE '%$searchQuery%')";
}

$sql = "SELECT v.*, d.phone_number as driver_phone, d.agency_name, d.vehicle_type as driver_vehicle_type 
        FROM driver_rc_verifications v 
        LEFT JOIN drivers d ON (REPLACE(REPLACE(UPPER(d.rc_no), ' ', ''), '-', '') = REPLACE(REPLACE(UPPER(v.rc_number), ' ', ''), '-', '')) 
        $whereClause 
        ORDER BY v.verified_at DESC";
$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle RC Verifications - Admin Dashboard</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --success: #059669;
            --warning: #D97706;
            --danger: #DC2626;
            --text-muted: #64748B;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #F8FAFC;
            color: #0F172A;
        }

        .top-navbar {
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            padding: 14px 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }

        .top-navbar img {
            height: 38px;
        }

        .stat-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.05);
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
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="top-navbar d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard.php" class="navbar-brand m-0">
                <img src="images/logo_rentox.png" alt="Rentox Logo" onerror="this.src='images/pnglogoagni.png'">
            </a>
            <h5 class="m-0 fw-bold text-dark">Vehicle RC Verifications</h5>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary btn-sm rounded-3"><i class="fas fa-home me-1"></i> Dashboard</a>
            <a href="driver_dl_verifications.php" class="btn btn-outline-primary btn-sm rounded-3"><i class="fas fa-id-card me-1"></i> DL Verifications</a>
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
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-number"><?php echo $totalCount; ?></div>
                    <div class="stat-label">Total Verified RCs</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <div class="stat-number"><?php echo $verifiedCount; ?></div>
                    <div class="stat-label">Active Approved Vehicles</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="stat-number"><?php echo $expiredCount; ?></div>
                    <div class="stat-label">Expired Fitness/Insurance</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="stat-card">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="fas fa-user-check"></i>
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
                        <input type="text" name="search" class="form-control border-start-0" placeholder="Search by RC Number, Owner, Model..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">Filter by Status: All</option>
                        <option value="VERIFIED" <?php if($statusFilter==='VERIFIED') echo 'selected'; ?>>🟢 Active / Verified</option>
                        <option value="EXPIRED" <?php if($statusFilter==='EXPIRED') echo 'selected'; ?>>🔴 Expired Insurance/Fitness</option>
                        <option value="MANUAL_APPROVED" <?php if($statusFilter==='MANUAL_APPROVED') echo 'selected'; ?>>🔵 Manual Approved</option>
                        <option value="REJECTED" <?php if($statusFilter==='REJECTED') echo 'selected'; ?>>🟠 Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-dark fw-bold rounded-3"><i class="fas fa-filter me-1"></i> Apply Filter</button>
                    <a href="driver_rc_verifications.php" class="btn btn-outline-secondary rounded-3">Reset</a>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle border-top">
                    <thead class="table-light">
                        <tr>
                            <th>Vehicle / Owner</th>
                            <th>RC Number</th>
                            <th>Maker & Model</th>
                            <th>Fitness Expiry</th>
                            <th>Insurance Expiry</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): 
                                $today = date('Y-m-d');
                                $isInsExpired = (!empty($row['insurance_upto']) && $row['insurance_upto'] < $today);
                                $isFitExpired = (!empty($row['fit_up_to']) && $row['fit_up_to'] < $today);
                                $phoneNum = $row['driver_phone'] ?? '';
                                $vehicleModel = trim(($row['maker_description'] ?? '') . ' ' . ($row['maker_model'] ?? ''));
                                if (empty($vehicleModel)) $vehicleModel = $row['driver_vehicle_type'] ?? 'N/A';
                            ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars(!empty($row['owner_name']) ? $row['owner_name'] : 'N/A'); ?></div>
                                            <?php if(!empty($phoneNum)): ?>
                                                <div class="small text-primary"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($phoneNum); ?></div>
                                            <?php endif; ?>
                                            <?php if(!empty($row['agency_name'])): ?>
                                                <small class="text-muted"><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($row['agency_name']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-dark font-monospace px-2 py-1 fs-6">
                                            <?php echo htmlspecialchars($row['rc_number']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($vehicleModel); ?></div>
                                        <small class="text-muted">Fuel: <?php echo htmlspecialchars(!empty($row['fuel_type']) ? $row['fuel_type'] : 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold <?php echo $isFitExpired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo !empty($row['fit_up_to']) ? date('d M Y', strtotime($row['fit_up_to'])) : 'N/A'; ?>
                                        </div>
                                        <?php if($isFitExpired): ?>
                                            <small class="badge bg-danger bg-opacity-10 text-danger p-1">Fitness Expired</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold <?php echo $isInsExpired ? 'text-danger' : 'text-success'; ?>">
                                            <?php echo !empty($row['insurance_upto']) ? date('d M Y', strtotime($row['insurance_upto'])) : 'N/A'; ?>
                                        </div>
                                        <?php if($isInsExpired): ?>
                                            <small class="badge bg-danger bg-opacity-10 text-danger p-1">Ins. Expired</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($isInsExpired || $isFitExpired) {
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
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <button class="btn btn-outline-primary btn-sm rounded-3" data-bs-toggle="modal" data-bs-target="#rcModal<?php echo $row['id']; ?>" title="View Details">
                                                <i class="fas fa-eye me-1"></i> View
                                            </button>
                                            <?php if($row['verification_status'] !== 'MANUAL_APPROVED'): ?>
                                                <form method="POST" action="" class="d-inline">
                                                    <input type="hidden" name="rc_number" value="<?php echo htmlspecialchars($row['rc_number']); ?>">
                                                    <input type="hidden" name="action" value="approve_manual">
                                                    <button type="submit" class="btn btn-outline-success btn-sm rounded-3" title="Approve Manually">
                                                        <i class="fas fa-user-check me-1"></i> Approve
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete RC record for <?php echo htmlspecialchars($row['rc_number']); ?>?');">
                                                <input type="hidden" name="rc_number" value="<?php echo htmlspecialchars($row['rc_number']); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-outline-danger btn-sm rounded-3" title="Delete Record">
                                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>

                                        <!-- Full Vehicle Details Modal -->
                                        <div class="modal fade text-start" id="rcModal<?php echo $row['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                                <div class="modal-content rounded-4 border-0">
                                                    <div class="modal-header border-bottom-0 pb-0">
                                                        <h5 class="modal-title fw-bold"><i class="fas fa-car text-success me-2"></i> Vehicle RC Full Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body p-4">
                                                        <div class="text-center mb-3">
                                                            <div class="badge bg-secondary bg-opacity-10 text-dark font-monospace px-3 py-2 fs-5 mb-2">
                                                                <?php echo htmlspecialchars($row['rc_number']); ?>
                                                            </div>
                                                            <h4 class="fw-bold m-0"><?php echo htmlspecialchars(!empty($row['owner_name']) ? $row['owner_name'] : 'N/A'); ?></h4>
                                                            <div class="text-muted"><?php echo htmlspecialchars($vehicleModel); ?></div>
                                                        </div>
                                                        <hr>
                                                        <div class="row g-3 fs-6">
                                                            <?php if(!empty($phoneNum)): ?>
                                                                <div class="col-md-6"><strong>Owner Phone:</strong><br><span class="text-primary fw-bold"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($phoneNum); ?></span></div>
                                                            <?php endif; ?>
                                                            <?php if(!empty($row['agency_name'])): ?>
                                                                <div class="col-md-6"><strong>Agency / Fleet:</strong><br><span class="fw-semibold"><?php echo htmlspecialchars($row['agency_name']); ?></span></div>
                                                            <?php endif; ?>
                                                            <div class="col-md-6"><strong>Maker Description:</strong><br><?php echo htmlspecialchars(!empty($row['maker_description']) ? $row['maker_description'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Model:</strong><br><?php echo htmlspecialchars(!empty($row['maker_model']) ? $row['maker_model'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Registration Date:</strong><br><?php echo !empty($row['registration_date']) ? date('d M Y', strtotime($row['registration_date'])) : 'N/A'; ?></div>
                                                            <div class="col-md-6"><strong>Fitness Expiry:</strong><br><span class="<?php echo $isFitExpired?'text-danger':'text-success'; ?> fw-bold"><?php echo !empty($row['fit_up_to']) ? date('d M Y', strtotime($row['fit_up_to'])) : 'N/A'; ?></span></div>
                                                            <div class="col-md-6"><strong>Insurance Policy No:</strong><br><?php echo htmlspecialchars(!empty($row['insurance_policy_number']) ? $row['insurance_policy_number'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Insurance Expiry:</strong><br><span class="<?php echo $isInsExpired?'text-danger':'text-success'; ?> fw-bold"><?php echo !empty($row['insurance_upto']) ? date('d M Y', strtotime($row['insurance_upto'])) : 'N/A'; ?></span></div>
                                                            <div class="col-md-6"><strong>Insurance Company:</strong><br><?php echo htmlspecialchars(!empty($row['insurance_company']) ? $row['insurance_company'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Fuel Type:</strong><br><?php echo htmlspecialchars(!empty($row['fuel_type']) ? $row['fuel_type'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Color:</strong><br><?php echo htmlspecialchars(!empty($row['color']) ? $row['color'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Seating Capacity:</strong><br><?php echo htmlspecialchars(!empty($row['seat_capacity']) ? $row['seat_capacity'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Taxi Permit No:</strong><br><?php echo htmlspecialchars(!empty($row['permit_number']) ? $row['permit_number'] : 'N/A'); ?></div>
                                                            <div class="col-md-6"><strong>Permit Valid Expiry:</strong><br><?php echo !empty($row['permit_valid_upto']) ? date('d M Y', strtotime($row['permit_valid_upto'])) : 'N/A'; ?></div>
                                                            <div class="col-12">
                                                                <div class="p-3 bg-light rounded-3 border">
                                                                    <strong class="text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i> Registered Address:</strong><br>
                                                                    <span class="text-secondary fw-medium"><?php echo htmlspecialchars(!empty($row['permanent_address']) ? $row['permanent_address'] : 'N/A'); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="col-12"><small class="text-muted">Verification Timestamp: <?php echo htmlspecialchars($row['verified_at']); ?></small></div>
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
                                    <i class="fas fa-car fa-3x mb-3 text-secondary opacity-50"></i>
                                    <h5>No Vehicle RC records found</h5>
                                    <p class="small m-0">When drivers or vendors verify their Vehicle RC, their details will appear here automatically.</p>
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
