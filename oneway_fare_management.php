<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../2025/MigrationRunner.php';
require_once __DIR__ . '/../2025/FareCache.php';
require_once __DIR__ . '/../2025/OneWayAuditLogger.php';
require_once __DIR__ . '/../2025/OneWayFareCalculator.php';

// Auto-run migrations to ensure isolated tables exist
MigrationRunner::run($conn);

$adminId = $_SESSION['admin_id'] ?? 'admin';

// -------------------------------------------------------------
// AJAX: Live Simulator API
// -------------------------------------------------------------
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'simulate_fare') {
    header('Content-Type: application/json');
    $carTypeId = (int)($_POST['car_type_id'] ?? 0);
    $distance = (float)($_POST['distance_km'] ?? 0);
    $pickup = trim($_POST['pickup_address'] ?? '');
    $drop = trim($_POST['drop_address'] ?? '');

    if ($distance <= 0) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid distance (> 0 KM)']);
        exit();
    }

    $result = OneWayFareCalculator::calculate($conn, $carTypeId, $distance, $pickup, $drop);
    echo json_encode(['success' => true, 'data' => $result]);
    exit();
}

// -------------------------------------------------------------
// 1. Action: Update Master Global Settings (with Optimistic Locking)
// -------------------------------------------------------------
if (isset($_POST['action']) && $_POST['action'] === 'update_global_settings') {
    $submittedVersion = (int)($_POST['row_version'] ?? 1);
    
    // Fetch previous values for audit log
    $prevRes = mysqli_query($conn, "SELECT * FROM `one_way_global_settings` WHERE `id` = 1 LIMIT 1");
    $prevRow = $prevRes ? mysqli_fetch_assoc($prevRes) : [];

    $masterActive = isset($_POST['master_engine_active']) ? 1 : 0;
    $allowanceActive = isset($_POST['driver_allowance_active']) ? 1 : 0;
    $discountActive = isset($_POST['discount_active']) ? 1 : 0;
    $discountType = $_POST['discount_type'] ?? 'percentage';
    $discountValue = (float)($_POST['discount_value'] ?? 0);
    $gstActive = isset($_POST['gst_active']) ? 1 : 0;
    $gstMode = $_POST['gst_mode'] ?? 'split';
    $gstPercent = (float)($_POST['gst_percent'] ?? 5.0);
    $cgstPercent = (float)($_POST['cgst_percent'] ?? 2.5);
    $sgstPercent = (float)($_POST['sgst_percent'] ?? 2.5);
    $igstPercent = (float)($_POST['igst_percent'] ?? 5.0);
    $parkingActive = isset($_POST['parking_active']) ? 1 : 0;
    $defaultParking = (float)($_POST['default_parking_amount'] ?? 0.0);
    $tollActive = isset($_POST['toll_auto_estimate']) ? 1 : 0;
    $tollRate = (float)($_POST['toll_per_km_rate'] ?? 2.25);

    // Validation
    if ($gstPercent < 0 || $gstPercent > 28 || $discountValue < 0 || $tollRate < 0 || $defaultParking < 0) {
        $_SESSION['error_msg'] = "Invalid input values. Please ensure all rates are within standard bounds.";
        header("Location: oneway_fare_management.php");
        exit();
    }

    $stmt = $conn->prepare(
        "UPDATE `one_way_global_settings` SET 
            `master_engine_active` = ?,
            `driver_allowance_active` = ?,
            `discount_active` = ?,
            `discount_type` = ?,
            `discount_value` = ?,
            `gst_active` = ?,
            `gst_mode` = ?,
            `gst_percent` = ?,
            `cgst_percent` = ?,
            `sgst_percent` = ?,
            `igst_percent` = ?,
            `parking_active` = ?,
            `default_parking_amount` = ?,
            `toll_auto_estimate` = ?,
            `toll_per_km_rate` = ?,
            `row_version` = `row_version` + 1,
            `updated_by` = ?
        WHERE `id` = 1 AND `row_version` = ?"
    );

    $stmt->bind_param(
        "iiisdsddddsididsi",
        $masterActive, $allowanceActive, $discountActive, $discountType, $discountValue,
        $gstActive, $gstMode, $gstPercent, $cgstPercent, $sgstPercent, $igstPercent,
        $parkingActive, $defaultParking, $tollActive, $tollRate, $adminId, $submittedVersion
    );

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        FareCache::flushAll();

        // Fetch new values
        $newRes = mysqli_query($conn, "SELECT * FROM `one_way_global_settings` WHERE `id` = 1 LIMIT 1");
        $newRow = $newRes ? mysqli_fetch_assoc($newRes) : [];

        OneWayAuditLogger::record($conn, (string)$adminId, 'global_settings_update', 1, $prevRow, $newRow);
        $_SESSION['success_msg'] = "One-Way Global Fare Settings updated successfully!";
    } else {
        $_SESSION['error_msg'] = "Conflict detected! Settings were modified by another admin session. Please reload and try again.";
    }
    $stmt->close();
    header("Location: oneway_fare_management.php");
    exit();
}

// -------------------------------------------------------------
// 2. Action: Save / Edit Vehicle Rule
// -------------------------------------------------------------
if (isset($_POST['action']) && in_array($_POST['action'], ['add_vehicle_rule', 'edit_vehicle_rule'])) {
    $isEdit = $_POST['action'] === 'edit_vehicle_rule';
    $carTypeId = (int)($_POST['car_type_id'] ?? 0);
    $kmRate = (float)($_POST['km_rate'] ?? 13.0);
    $minDist = (float)($_POST['min_distance_km'] ?? 100.0);
    $allowShort = (float)($_POST['driver_allowance_short'] ?? 300.0);
    $allowLong = (float)($_POST['driver_allowance_long'] ?? 400.0);
    $threshold = (float)($_POST['distance_threshold_km'] ?? 200.0);
    $displayOrder = (int)($_POST['display_order'] ?? 1);
    $submittedVersion = (int)($_POST['row_version'] ?? 1);

    // Fetch car category name
    $catRes = mysqli_query($conn, "SELECT `car_type` FROM `car_categories` WHERE `id` = $carTypeId LIMIT 1");
    $catLabel = ($catRes && $cRow = mysqli_fetch_assoc($catRes)) ? $cRow['car_type'] : 'Vehicle';

    if ($carTypeId <= 0 || $kmRate <= 0) {
        $_SESSION['error_msg'] = "Please select a valid vehicle and set a KM rate greater than 0.";
    } else {
        if ($isEdit) {
            $ruleId = (int)($_POST['rule_id'] ?? 0);
            $prevRes = mysqli_query($conn, "SELECT * FROM `one_way_vehicle_rules` WHERE `id` = $ruleId LIMIT 1");
            $prevRow = $prevRes ? mysqli_fetch_assoc($prevRes) : [];

            $stmt = $conn->prepare(
                "UPDATE `one_way_vehicle_rules` SET
                    `car_type_id` = ?,
                    `car_type_label` = ?,
                    `km_rate` = ?,
                    `min_distance_km` = ?,
                    `driver_allowance_short` = ?,
                    `driver_allowance_long` = ?,
                    `distance_threshold_km` = ?,
                    `display_order` = ?,
                    `row_version` = `row_version` + 1
                WHERE `id` = ? AND `row_version` = ?"
            );
            $stmt->bind_param("isdddddiii", $carTypeId, $catLabel, $kmRate, $minDist, $allowShort, $allowLong, $threshold, $displayOrder, $ruleId, $submittedVersion);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                FareCache::flushAll();
                $newRes = mysqli_query($conn, "SELECT * FROM `one_way_vehicle_rules` WHERE `id` = $ruleId LIMIT 1");
                $newRow = $newRes ? mysqli_fetch_assoc($newRes) : [];
                OneWayAuditLogger::record($conn, (string)$adminId, 'vehicle_rule_update', $ruleId, $prevRow, $newRow);
                $_SESSION['success_msg'] = "Vehicle rate for {$catLabel} updated successfully!";
            } else {
                $_SESSION['error_msg'] = "Conflict detected! This vehicle rule was modified in another session.";
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO `one_way_vehicle_rules` 
                (`car_type_id`, `car_type_label`, `km_rate`, `min_distance_km`, `driver_allowance_short`, `driver_allowance_long`, `distance_threshold_km`, `is_active`, `display_order`, `row_version`)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1)
                ON DUPLICATE KEY UPDATE 
                `km_rate` = VALUES(`km_rate`), `min_distance_km` = VALUES(`min_distance_km`), `driver_allowance_short` = VALUES(`driver_allowance_short`), `driver_allowance_long` = VALUES(`driver_allowance_long`)"
            );
            $stmt->bind_param("isdddddi", $carTypeId, $catLabel, $kmRate, $minDist, $allowShort, $allowLong, $threshold, $displayOrder);
            if ($stmt->execute()) {
                FareCache::flushAll();
                $newId = $stmt->insert_id ?: $carTypeId;
                OneWayAuditLogger::record($conn, (string)$adminId, 'vehicle_rule_create', (int)$newId, [], ['car_type' => $catLabel, 'km_rate' => $kmRate]);
                $_SESSION['success_msg'] = "Vehicle rate rule for {$catLabel} saved successfully!";
            } else {
                $_SESSION['error_msg'] = "Database error: " . $conn->error;
            }
            $stmt->close();
        }
    }
    header("Location: oneway_fare_management.php");
    exit();
}

// -------------------------------------------------------------
// 3. Action: Toggle Vehicle Rule Status (Active / Inactive)
// -------------------------------------------------------------
if (isset($_GET['action']) && in_array($_GET['action'], ['activate_vehicle', 'deactivate_vehicle']) && isset($_GET['id'])) {
    $ruleId = (int)$_GET['id'];
    $newStatus = ($_GET['action'] === 'activate_vehicle') ? 1 : 0;
    
    $prevRes = mysqli_query($conn, "SELECT * FROM `one_way_vehicle_rules` WHERE `id` = $ruleId LIMIT 1");
    $prevRow = $prevRes ? mysqli_fetch_assoc($prevRes) : [];

    $stmt = $conn->prepare("UPDATE `one_way_vehicle_rules` SET `is_active` = ?, `row_version` = `row_version` + 1 WHERE `id` = ?");
    $stmt->bind_param("ii", $newStatus, $ruleId);
    if ($stmt->execute()) {
        FareCache::flushAll();
        OneWayAuditLogger::record($conn, (string)$adminId, 'vehicle_rule_toggle', $ruleId, $prevRow, ['is_active' => $newStatus]);
        $_SESSION['success_msg'] = "Vehicle rule status updated!";
    } else {
        $_SESSION['error_msg'] = "Failed to update vehicle rule status.";
    }
    $stmt->close();
    header("Location: oneway_fare_management.php");
    exit();
}

// -------------------------------------------------------------
// Data Fetching for View
// -------------------------------------------------------------
$settingsRes = mysqli_query($conn, "SELECT * FROM `one_way_global_settings` WHERE `id` = 1 LIMIT 1");
$settings = $settingsRes ? mysqli_fetch_assoc($settingsRes) : OneWayFareCalculator::getGlobalSettings($conn);

$rulesRes = mysqli_query($conn, "SELECT r.*, c.status as cat_status FROM `one_way_vehicle_rules` r LEFT JOIN `car_categories` c ON r.car_type_id = c.id ORDER BY r.display_order ASC, r.id ASC");
$rules = [];
while ($row = mysqli_fetch_assoc($rulesRes)) {
    $rules[] = $row;
}

$categoriesRes = mysqli_query($conn, "SELECT * FROM `car_categories` WHERE `status` = 'active' ORDER BY `id` ASC");
$categories = [];
while ($row = mysqli_fetch_assoc($categoriesRes)) {
    $categories[] = $row;
}

$auditRes = mysqli_query($conn, "SELECT * FROM `one_way_fare_audit_log` ORDER BY `id` DESC LIMIT 20");
$auditLogs = [];
if ($auditRes) {
    while ($row = mysqli_fetch_assoc($auditRes)) {
        $auditLogs[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One-Way Fare Management | Rentox Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #1E3A8A;
            --primary-dark: #172554;
            --primary-light: #EFF6FF;
            --accent: #F59E0B;
            --success: #10B981;
            --danger: #EF4444;
            --surface: #FFFFFF;
            --bg: #F8FAFC;
            --border: #E2E8F0;
            --text-main: #0F172A;
            --text-muted: #64748B;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
        }

        .navbar {
            background-color: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.8rem 1.5rem;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .card-header {
            background: transparent;
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.25rem;
            font-weight: 700;
        }

        .engine-banner {
            background: linear-gradient(135deg, #1E3A8A 0%, #3B82F6 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .toggle-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.25rem;
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .form-switch .form-check-input {
            width: 2.75rem;
            height: 1.4rem;
            cursor: pointer;
        }
        .form-switch .form-check-input:checked {
            background-color: #10B981;
            border-color: #10B981;
        }

        .badge-active {
            background-color: #DCFCE7;
            color: #166534;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }
        .badge-inactive {
            background-color: #F1F5F9;
            color: #64748B;
            font-weight: 600;
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
        }

        .table > :not(caption) > * > * {
            padding: 0.9rem 1rem;
            vertical-align: middle;
        }

        .simulator-box {
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 12px;
            padding: 1.5rem;
        }
        .sim-result-row {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #E2E8F0;
            font-size: 0.9rem;
        }
        .sim-result-total {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0 0 0;
            font-size: 1.15rem;
            font-weight: 800;
            color: var(--primary);
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
                <span class="bg-primary text-white p-2 rounded-3"><i class="fa-solid fa-car-side"></i></span>
                <span class="fw-bold fs-5 text-dark">Rentox<span class="text-primary">Admin</span></span>
            </a>
            <div class="d-flex align-items-center gap-3">
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Dashboard</a>
                <span class="badge bg-light text-dark border p-2"><i class="fa-solid fa-user-shield me-1"></i> <?= htmlspecialchars($adminId) ?></span>
                <a href="logout.php" class="btn btn-danger btn-sm"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4 px-lg-5">

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check fs-5 me-2"></i>
                <div><?= htmlspecialchars($_SESSION['success_msg']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_msg']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">
                <i class="fa-solid fa-circle-exclamation fs-5 me-2"></i>
                <div><?= htmlspecialchars($_SESSION['error_msg']) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_msg']); ?>
        <?php endif; ?>

        <!-- Master Status Banner -->
        <div class="engine-banner shadow-sm mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h4 class="fw-bold mb-0 text-white">One-Way Dynamic Fare Engine (v2)</h4>
                        <span class="badge <?= $settings['master_engine_active'] ? 'bg-success' : 'bg-secondary' ?> px-3 py-1">
                            <?= $settings['master_engine_active'] ? 'ACTIVE (V2 ENGINE)' : 'DISABLED (FALLBACK)' ?>
                        </span>
                    </div>
                    <p class="mb-0 text-white-50 fs-6">
                        Isolated pricing rules for one-way trips with granular control over driver allowances, taxes, discounts, and parking.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-light fw-bold px-3" data-bs-toggle="modal" data-bs-target="#auditModal">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i> Audit Logs
                    </button>
                    <a href="#simulatorSection" class="btn btn-warning fw-bold px-3 text-dark">
                        <i class="fa-solid fa-bolt me-1"></i> Test Live Simulator
                    </a>
                </div>
            </div>
        </div>

        <!-- FORM: Global Master Settings & Toggles -->
        <form method="POST" action="oneway_fare_management.php">
            <input type="hidden" name="action" value="update_global_settings">
            <input type="hidden" name="row_version" value="<?= (int)($settings['row_version'] ?? 1) ?>">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-sliders me-2 text-primary"></i>Global Control Toggles</h5>
                <button type="submit" class="btn btn-primary fw-bold shadow-sm px-4">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
                </button>
            </div>

            <div class="row g-3 mb-4">
                <!-- 1. Master Engine Switch -->
                <div class="col-md-6 col-xl-3">
                    <div class="toggle-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-power-off text-primary me-2"></i>Master Engine</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="master_engine_active" id="masterSwitch" <?= $settings['master_engine_active'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <p class="text-muted small mb-2">When OFF, quotes automatically fallback to legacy tripCostTable with zero downtime.</p>
                        </div>
                        <span class="badge <?= $settings['master_engine_active'] ? 'badge-active' : 'badge-inactive' ?> w-100 text-center">
                            <?= $settings['master_engine_active'] ? 'New Engine Enabled' : 'Fallback Active' ?>
                        </span>
                    </div>
                </div>

                <!-- 2. Driver Allowance Switch -->
                <div class="col-md-6 col-xl-3">
                    <div class="toggle-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-id-card-clip text-warning me-2"></i>Driver Allowance</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="driver_allowance_active" id="allowanceSwitch" <?= $settings['driver_allowance_active'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <p class="text-muted small mb-2">Toggle driver allowance inclusion. Short & Long rates are customized per vehicle below.</p>
                        </div>
                        <span class="badge <?= $settings['driver_allowance_active'] ? 'badge-active' : 'badge-inactive' ?> w-100 text-center">
                            <?= $settings['driver_allowance_active'] ? 'Allowance Enabled' : 'Allowance Excluded' ?>
                        </span>
                    </div>
                </div>

                <!-- 3. Tax / GST Switch -->
                <div class="col-md-6 col-xl-3">
                    <div class="toggle-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-receipt text-success me-2"></i>Tax / GST Control</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="gst_active" id="gstSwitch" <?= $settings['gst_active'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="mb-2">
                                <select name="gst_mode" class="form-select form-select-sm mb-2" id="gstModeSelect">
                                    <option value="split" <?= ($settings['gst_mode'] ?? '') === 'split' ? 'selected' : '' ?>>Intra/Inter-State Split (CGST/SGST/IGST)</option>
                                    <option value="flat" <?= ($settings['gst_mode'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat Rate (%)</option>
                                </select>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rate %</span>
                                    <input type="number" step="0.1" min="0" max="28" name="gst_percent" class="form-control" value="<?= htmlspecialchars($settings['gst_percent']) ?>">
                                </div>
                            </div>
                        </div>
                        <span class="badge <?= $settings['gst_active'] ? 'badge-active' : 'badge-inactive' ?> w-100 text-center">
                            <?= $settings['gst_active'] ? 'GST Active (' . $settings['gst_percent'] . '%)' : 'Tax Exempt (0%)' ?>
                        </span>
                    </div>
                </div>

                <!-- 4. Parking & Toll Switch -->
                <div class="col-md-6 col-xl-3">
                    <div class="toggle-card">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-square-parking text-info me-2"></i>Parking & Toll</span>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="parking_active" id="parkingSwitch" <?= $settings['parking_active'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text">Parking ₹</span>
                                <input type="number" step="10" min="0" name="default_parking_amount" class="form-control" value="<?= htmlspecialchars($settings['default_parking_amount']) ?>">
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Toll/KM ₹</span>
                                <input type="number" step="0.25" min="0" name="toll_per_km_rate" class="form-control" value="<?= htmlspecialchars($settings['toll_per_km_rate']) ?>">
                                <div class="input-group-text bg-white">
                                    <input class="form-check-input mt-0" type="checkbox" name="toll_auto_estimate" title="Auto Toll" <?= $settings['toll_auto_estimate'] ? 'checked' : '' ?>>
                                </div>
                            </div>
                        </div>
                        <span class="badge <?= $settings['parking_active'] ? 'badge-active' : 'badge-inactive' ?> w-100 text-center mt-2">
                            <?= $settings['parking_active'] ? 'Parking ₹' . $settings['default_parking_amount'] : 'No Default Parking' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Discount Sub-Card -->
            <div class="card mb-4 p-3 shadow-sm bg-white">
                <div class="row align-items-center g-3">
                    <div class="col-md-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fa-solid fa-tag fs-4 text-warning"></i>
                            <div>
                                <h6 class="fw-bold mb-0">One-Way Promotional Discount</h6>
                                <small class="text-muted">Apply instant checkout discounts</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="discount_active" id="discountSwitch" <?= $settings['discount_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold small" for="discountSwitch">Enable Discount</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select name="discount_type" class="form-select form-select-sm">
                            <option value="percentage" <?= ($settings['discount_type'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                            <option value="fixed" <?= ($settings['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>Fixed Flat Amount (₹)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Discount Value</span>
                            <input type="number" step="0.5" min="0" name="discount_value" class="form-control" value="<?= htmlspecialchars($settings['discount_value']) ?>">
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- PER-VEHICLE FARE RULES TABLE -->
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-taxi me-2 text-primary"></i>Per-Vehicle Rate Configuration</h5>
                    <small class="text-muted">Custom KM rate and driver allowance thresholds per car category</small>
                </div>
                <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#vehicleModal" onclick="openAddModal()">
                    <i class="fa-solid fa-plus me-1"></i> Add Vehicle Rate
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-uppercase small text-muted">
                            <th>Vehicle Category</th>
                            <th>Rate / KM</th>
                            <th>Min Distance</th>
                            <th>Short Allowance (< 200 KM)</th>
                            <th>Long Allowance (≥ 200 KM)</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No vehicle rules configured yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rules as $r): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($r['car_type_label']) ?></div>
                                        <small class="text-muted">ID: #<?= $r['car_type_id'] ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary fs-6">₹<?= number_format($r['km_rate'], 2) ?></span> <span class="text-muted small">/ KM</span>
                                    </td>
                                    <td><?= number_format($r['min_distance_km'], 0) ?> KM</td>
                                    <td><span class="badge bg-light text-dark border">₹<?= number_format($r['driver_allowance_short'], 0) ?></span></td>
                                    <td><span class="badge bg-light text-dark border">₹<?= number_format($r['driver_allowance_long'], 0) ?></span></td>
                                    <td>
                                        <?php if ($r['is_active']): ?>
                                            <span class="badge badge-active"><i class="fa-solid fa-circle-check me-1"></i>Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-primary btn-sm me-1" onclick='openEditModal(<?= json_encode($r) ?>)'>
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <?php if ($r['is_active']): ?>
                                            <a href="oneway_fare_management.php?action=deactivate_vehicle&id=<?= $r['id'] ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Deactivate this vehicle rule?')">
                                                <i class="fa-solid fa-ban"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="oneway_fare_management.php?action=activate_vehicle&id=<?= $r['id'] ?>" class="btn btn-outline-success btn-sm">
                                                <i class="fa-solid fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ⚡ LIVE INTERACTIVE FARE SIMULATOR -->
        <div class="card shadow-sm mb-4" id="simulatorSection">
            <div class="card-header bg-light">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-bolt me-2 text-warning"></i>⚡ Live Interactive Fare Simulator</h5>
                <small class="text-muted">Test real-time calculation and verify breakdowns before customer requests</small>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Select Vehicle</label>
                        <select id="simCarType" class="form-select">
                            <?php foreach ($rules as $r): ?>
                                <option value="<?= $r['car_type_id'] ?>"><?= htmlspecialchars($r['car_type_label']) ?> (₹<?= $r['km_rate'] ?>/KM)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Trip Distance (KM)</label>
                        <input type="number" id="simDistance" class="form-control" value="250" min="1" step="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Pickup Location</label>
                        <input type="text" id="simPickup" class="form-control" value="Kochi, Kerala, India">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold">Drop Location</label>
                        <input type="text" id="simDrop" class="form-control" value="Trivandrum, Kerala, India">
                    </div>
                </div>

                <div class="d-flex justify-content-end mb-3">
                    <button type="button" class="btn btn-warning fw-bold text-dark px-4 shadow-sm" onclick="runSimulation()">
                        <i class="fa-solid fa-calculator me-1"></i> Calculate Test Fare
                    </button>
                </div>

                <!-- Simulation Output Box -->
                <div class="simulator-box" id="simResultBox">
                    <div class="text-center text-muted py-3">
                        <i class="fa-solid fa-arrow-pointer fs-3 mb-2"></i>
                        <div>Click <strong>"Calculate Test Fare"</strong> above to see instant breakdown.</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL: Add / Edit Vehicle Rule -->
    <div class="modal fade" id="vehicleModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="oneway_fare_management.php">
                    <input type="hidden" name="action" id="modalAction" value="add_vehicle_rule">
                    <input type="hidden" name="rule_id" id="modalRuleId" value="">
                    <input type="hidden" name="row_version" id="modalRowVersion" value="1">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="modalTitle">Configure Vehicle Rate</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Vehicle Category</label>
                            <select name="car_type_id" id="modalCarTypeId" class="form-select" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['car_type']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Rate per KM (₹)</label>
                                <input type="number" step="0.5" min="1" name="km_rate" id="modalKmRate" class="form-control" required value="13.0">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Min Distance (KM)</label>
                                <input type="number" step="10" min="0" name="min_distance_km" id="modalMinDist" class="form-control" required value="100">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Short Allowance (< 200 KM)</label>
                                <input type="number" step="10" min="0" name="driver_allowance_short" id="modalAllowShort" class="form-control" required value="300">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Long Allowance (≥ 200 KM)</label>
                                <input type="number" step="10" min="0" name="driver_allowance_long" id="modalAllowLong" class="form-control" required value="400">
                            </div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Threshold (KM)</label>
                                <input type="number" step="10" min="0" name="distance_threshold_km" id="modalThreshold" class="form-control" required value="200">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Display Order</label>
                                <input type="number" min="1" name="display_order" id="modalOrder" class="form-control" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Vehicle Rule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL: Audit Logs History -->
    <div class="modal fade" id="auditModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Audit Log History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Changes (Before / After)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($auditLogs)): ?>
                                    <tr><td colspan="4" class="text-center py-3 text-muted">No audit logs recorded yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($auditLogs as $log): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                                            <td class="fw-semibold"><?= htmlspecialchars($log['admin_id']) ?></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($log['action_type']) ?></span></td>
                                            <td>
                                                <pre class="mb-0 bg-light p-1 rounded" style="max-height: 80px; overflow-y: auto; font-size: 11px;"><?= htmlspecialchars($log['new_values']) ?></pre>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openAddModal() {
            document.getElementById('modalAction').value = 'add_vehicle_rule';
            document.getElementById('modalRuleId').value = '';
            document.getElementById('modalTitle').innerText = 'Add New Vehicle Rate Rule';
            document.getElementById('modalKmRate').value = '13.0';
            document.getElementById('modalMinDist').value = '100';
            document.getElementById('modalAllowShort').value = '300';
            document.getElementById('modalAllowLong').value = '400';
            document.getElementById('modalThreshold').value = '200';
            document.getElementById('modalOrder').value = '1';
            document.getElementById('modalRowVersion').value = '1';
        }

        function openEditModal(rule) {
            document.getElementById('modalAction').value = 'edit_vehicle_rule';
            document.getElementById('modalRuleId').value = rule.id;
            document.getElementById('modalTitle').innerText = 'Edit ' + rule.car_type_label + ' Rate Rule';
            document.getElementById('modalCarTypeId').value = rule.car_type_id;
            document.getElementById('modalKmRate').value = rule.km_rate;
            document.getElementById('modalMinDist').value = rule.min_distance_km;
            document.getElementById('modalAllowShort').value = rule.driver_allowance_short;
            document.getElementById('modalAllowLong').value = rule.driver_allowance_long;
            document.getElementById('modalThreshold').value = rule.distance_threshold_km;
            document.getElementById('modalOrder').value = rule.display_order;
            document.getElementById('modalRowVersion').value = rule.row_version || 1;
            
            new bootstrap.Modal(document.getElementById('vehicleModal')).show();
        }

        function runSimulation() {
            const carTypeId = document.getElementById('simCarType').value;
            const distance = document.getElementById('simDistance').value;
            const pickup = document.getElementById('simPickup').value;
            const drop = document.getElementById('simDrop').value;
            const resBox = document.getElementById('simResultBox');

            resBox.innerHTML = '<div class="text-center py-3 text-primary"><i class="fa-solid fa-spinner fa-spin fs-4 mb-2"></i><div>Calculating live breakdown...</div></div>';

            const formData = new FormData();
            formData.append('ajax_action', 'simulate_fare');
            formData.append('car_type_id', carTypeId);
            formData.append('distance_km', distance);
            formData.append('pickup_address', pickup);
            formData.append('drop_address', drop);

            fetch('oneway_fare_management.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    const d = res.data;
                    resBox.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar me-2 text-primary"></i>Live Simulation: ${d.car_type} (${d.distance_km} KM)</h6>
                            <span class="badge ${d.master_engine_active ? 'bg-success' : 'bg-secondary'}">${d.master_engine_active ? 'Engine v2 Active' : 'Fallback'}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">Base KM Charge (${d.chargeable_km} KM @ ₹${d.km_rate}/KM):</span>
                            <span class="fw-semibold">₹${d.base_km_charge.toLocaleString('en-IN')}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">Driver Allowance (${d.driver_allowance_active ? 'Active' : 'Disabled'}):</span>
                            <span class="fw-semibold">₹${d.driver_allowance.toLocaleString('en-IN')}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">Estimated Toll (${d.chargeable_km} KM):</span>
                            <span class="fw-semibold">₹${d.toll_charge.toLocaleString('en-IN')}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">Parking Surcharge:</span>
                            <span class="fw-semibold">₹${d.parking_charge.toLocaleString('en-IN')}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">Subtotal (Pre-Tax):</span>
                            <span class="fw-bold">₹${d.subtotal.toLocaleString('en-IN')}</span>
                        </div>
                        <div class="sim-result-row">
                            <span class="text-muted">GST / Tax (${d.gst_breakdown.mode || 'Active'} - ${d.gst_breakdown.rate}%):</span>
                            <span class="fw-semibold text-danger">+ ₹${d.gst_amount.toLocaleString('en-IN')}</span>
                        </div>
                        ${d.discount_amount > 0 ? `
                        <div class="sim-result-row">
                            <span class="text-muted">Promotional Discount:</span>
                            <span class="fw-semibold text-success">- ₹${d.discount_amount.toLocaleString('en-IN')}</span>
                        </div>` : ''}
                        <div class="sim-result-total">
                            <span>TOTAL ESTIMATED FARE:</span>
                            <span>₹${d.final_fare.toLocaleString('en-IN')}</span>
                        </div>
                    `;
                } else {
                    resBox.innerHTML = `<div class="alert alert-danger mb-0">${res.message || 'Simulation error'}</div>`;
                }
            })
            .catch(err => {
                resBox.innerHTML = `<div class="alert alert-danger mb-0">Network error while running simulation: ${err.message}</div>`;
            });
        }
    </script>
</body>
</html>
