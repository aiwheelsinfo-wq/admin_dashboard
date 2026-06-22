<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: adminlogin.php");
    exit();
}
require_once __DIR__ . '/db_connect.php';

// ─── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');
    $rows = $conn->query("
        SELECT b.id, b.mobile, b.from_address, b.to_address, b.date, b.time,
               b.booking_status, b.cancellation_reason, b.cancelled_at,
               b.paid_amount, b.cancellation_charge, b.refund_amount, b.refund_status,
               b.vendor_compensation, b.vendor_compensation_status,
               b.booked_at,
               TIMESTAMPDIFF(HOUR, b.cancelled_at, CONCAT(b.date,' ',b.time)) AS hours_before_pickup,
               u.name AS customer_name, d.full_name AS driver_name
        FROM bookings b
        LEFT JOIN users u ON b.booker_id = u.phone_number
        LEFT JOIN drivers d ON b.driver_id = d.phone_number
        WHERE b.booking_status IN ('Cancelled','Customer Cancelled','Cancellation Requested')
          AND DATE(b.cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
        ORDER BY b.cancelled_at DESC
    ");
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cancellation_report_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Customer','Mobile','From','To','Trip Date','Trip Time',
                   'Status','Reason','Cancelled At','Hours Before Pickup',
                   'Advance Paid','Charge','Refund Amount','Refund Status',
                   'Vendor Comp','Comp Status','Driver']);
    while ($r = $rows->fetch_assoc()) {
        fputcsv($out, [
            $r['id'], $r['customer_name'] ?: '-', $r['mobile'],
            $r['from_address'], $r['to_address'] ?: 'Local',
            $r['date'], $r['time'], $r['booking_status'], $r['cancellation_reason'],
            $r['cancelled_at'], $r['hours_before_pickup'],
            $r['paid_amount'], $r['cancellation_charge'], $r['refund_amount'],
            $r['refund_status'] ?: 'N/A', $r['vendor_compensation'],
            $r['vendor_compensation_status'] ?: 'N/A', $r['driver_name'] ?: '-'
        ]);
    }
    fclose($out);
    exit();
}

// ─── Date Filter ──────────────────────────────────────────────────────────────
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');

// ─── KPI Queries ──────────────────────────────────────────────────────────────
$kpi = $conn->query("
    SELECT
        COUNT(*) AS total_cancelled,
        SUM(paid_amount) AS total_advance_collected,
        SUM(refund_amount) AS total_refund_given,
        SUM(cancellation_charge) AS total_charge_kept,
        SUM(vendor_compensation) AS total_vendor_comp,
        SUM(CASE WHEN refund_status = 'Pending Approval' OR refund_status IS NULL THEN 1 ELSE 0 END) AS pending_refunds,
        SUM(CASE WHEN refund_status = 'Processing' THEN 1 ELSE 0 END) AS processing_refunds,
        SUM(CASE WHEN refund_status = 'Completed' THEN 1 ELSE 0 END) AS completed_refunds,
        SUM(CASE WHEN vendor_compensation > 0 AND (vendor_compensation_status IS NULL OR vendor_compensation_status != 'Paid') THEN 1 ELSE 0 END) AS unpaid_vendor_comp,
        AVG(CASE WHEN cancelled_at IS NOT NULL AND date IS NOT NULL
            THEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) END) AS avg_hours_before_pickup
    FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled','Cancellation Requested')
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// ─── Logical Issues (flag anomalies) ─────────────────────────────────────────
$issues = [];

// Issue 1: Refund amount > advance paid
$res = $conn->query("
    SELECT id, paid_amount, refund_amount FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND refund_amount > paid_amount AND refund_amount > 0
");
if ($res && $res->num_rows > 0) {
    while ($r = $res->fetch_assoc()) {
        $issues[] = ['severity'=>'critical','icon'=>'exclamation-triangle','msg'=>
            "Booking #".$r['id'].": Refund (₹".$r['refund_amount'].") EXCEEDS advance paid (₹".$r['paid_amount']."). Logic error in refund calculation."];
    }
}

// Issue 2: Completed refund with zero refund amount where advance > 0
$res2 = $conn->query("
    SELECT id, paid_amount, refund_status FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND refund_status = 'Completed' AND (refund_amount = 0 OR refund_amount IS NULL) AND paid_amount > 0
");
if ($res2 && $res2->num_rows > 0) {
    while ($r = $res2->fetch_assoc()) {
        $issues[] = ['severity'=>'warning','icon'=>'info-circle','msg'=>
            "Booking #".$r['id'].": Marked 'Refund Completed' but refund_amount is ₹0 even though ₹".$r['paid_amount']." was paid."];
    }
}

// Issue 3: No cancellation_reason stored
$res3 = $conn->query("
    SELECT COUNT(*) AS cnt FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND (cancellation_reason IS NULL OR cancellation_reason = '' OR cancellation_reason = 'Not Specified')
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
");
if ($res3) { $r3 = $res3->fetch_assoc(); if ($r3['cnt'] > 0) {
    $issues[] = ['severity'=>'info','icon'=>'question-circle','msg'=>
        $r3['cnt']." cancellation(s) have no reason recorded — customers skipped the reason field or used 'Not Specified'."];
}}

// Issue 4: Vendor comp pending but booking already fully settled
$res4 = $conn->query("
    SELECT COUNT(*) AS cnt FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND refund_status = 'Completed'
      AND vendor_compensation > 0
      AND (vendor_compensation_status IS NULL OR vendor_compensation_status != 'Paid')
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
");
if ($res4) { $r4 = $res4->fetch_assoc(); if ($r4['cnt'] > 0) {
    $issues[] = ['severity'=>'warning','icon'=>'wallet','msg'=>
        $r4['cnt']." booking(s) have refund marked Completed but vendor compensation is still UNPAID. Settle vendor accounts."];
}}

// Issue 5: Cancelled bookings with no cancelled_at timestamp
$res5 = $conn->query("
    SELECT COUNT(*) AS cnt FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND cancelled_at IS NULL
");
if ($res5) { $r5 = $res5->fetch_assoc(); if ($r5['cnt'] > 0) {
    $issues[] = ['severity'=>'critical','icon'=>'clock','msg'=>
        $r5['cnt']." cancelled booking(s) have no 'cancelled_at' timestamp — data integrity issue."];
}}

// Issue 6: Cancellation Requested older than 24 hours (admin not acting)
$res6 = $conn->query("
    SELECT id, cancelled_at FROM bookings
    WHERE booking_status = 'Cancellation Requested'
      AND cancelled_at < NOW() - INTERVAL 24 HOUR
");
if ($res6 && $res6->num_rows > 0) {
    $ids = [];
    while ($r = $res6->fetch_assoc()) $ids[] = '#'.$r['id'];
    $issues[] = ['severity'=>'warning','icon'=>'hourglass-half','msg'=>
        count($ids)." cancellation request(s) pending for >24 hours without admin action: ".implode(', ',$ids)];
}

// ─── Time-bracket breakdown ───────────────────────────────────────────────────
$brackets = $conn->query("
    SELECT
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) >= 48 THEN 1 ELSE 0 END) AS h48plus,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) >= 24
                  AND TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) < 48 THEN 1 ELSE 0 END) AS h24_48,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) >= 12
                  AND TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) < 24 THEN 1 ELSE 0 END) AS h12_24,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) >= 6
                  AND TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) < 12 THEN 1 ELSE 0 END) AS h6_12,
        SUM(CASE WHEN TIMESTAMPDIFF(HOUR, cancelled_at, CONCAT(date,' ',time)) < 6 THEN 1 ELSE 0 END) AS h_below6
    FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND cancelled_at IS NOT NULL AND date IS NOT NULL
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
")->fetch_assoc();

// ─── Top cancellation reasons ─────────────────────────────────────────────────
$reasons_res = $conn->query("
    SELECT cancellation_reason, COUNT(*) AS cnt
    FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND cancellation_reason IS NOT NULL AND cancellation_reason != ''
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY cancellation_reason ORDER BY cnt DESC LIMIT 8
");
$reasons = [];
while ($r = $reasons_res->fetch_assoc()) $reasons[] = $r;

// ─── Day-by-day trend (last 14 days or filtered range) ───────────────────────
$trend_res = $conn->query("
    SELECT DATE(cancelled_at) AS day, COUNT(*) AS cnt, SUM(refund_amount) AS refunds
    FROM bookings
    WHERE booking_status IN ('Cancelled','Customer Cancelled')
      AND cancelled_at IS NOT NULL
      AND DATE(cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
    GROUP BY DATE(cancelled_at) ORDER BY day ASC
");
$trend_days = []; $trend_counts = []; $trend_refunds = [];
while ($r = $trend_res->fetch_assoc()) {
    $trend_days[]   = $r['day'];
    $trend_counts[] = (int)$r['cnt'];
    $trend_refunds[]= round($r['refunds'], 2);
}

// ─── All cancelled bookings for table ────────────────────────────────────────
$all_res = $conn->query("
    SELECT b.id, u.name AS customer_name, b.mobile, b.from_address, b.to_address,
           b.date, b.time, b.booking_status, b.cancellation_reason, b.cancelled_at,
           b.paid_amount, b.cancellation_charge, b.refund_amount, b.refund_status,
           b.vendor_compensation, b.vendor_compensation_status,
           d.full_name AS driver_name,
           TIMESTAMPDIFF(HOUR, b.cancelled_at, CONCAT(b.date,' ',b.time)) AS hours_before
    FROM bookings b
    LEFT JOIN users u ON b.booker_id = u.phone_number
    LEFT JOIN drivers d ON b.driver_id = d.phone_number
    WHERE b.booking_status IN ('Cancelled','Customer Cancelled','Cancellation Requested')
      AND DATE(b.cancelled_at) BETWEEN '$dateFrom' AND '$dateTo'
    ORDER BY b.cancelled_at DESC
    LIMIT 200
");
$all_bookings = [];
while ($r = $all_res->fetch_assoc()) $all_bookings[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cancellation Report — Rentox Admin</title>
<link rel="icon" type="image/png" href="images/pnglogoagni.png">
<link rel="stylesheet" href="css/Dashboard_styles.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    body { font-family: 'Outfit', 'Segoe UI', system-ui, sans-serif !important; background:#f6f8fa; }
    .content { padding: 24px !important; }

    /* KPI Cards */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
    .kpi-card {
        background: #fff; border-radius: 16px; padding: 20px 18px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.04);
        display: flex; flex-direction: column; gap: 6px; position: relative; overflow: hidden;
    }
    .kpi-card::before { content:''; position:absolute; top:0; left:0; width:4px; height:100%; border-radius:4px 0 0 4px; }
    .kpi-card.red::before   { background: linear-gradient(#ef5350, #c62828); }
    .kpi-card.green::before { background: linear-gradient(#43a047, #1b5e20); }
    .kpi-card.orange::before{ background: linear-gradient(#fb8c00, #e65100); }
    .kpi-card.blue::before  { background: linear-gradient(#1e88e5, #0d47a1); }
    .kpi-card.purple::before{ background: linear-gradient(#8e24aa, #4a148c); }
    .kpi-card.teal::before  { background: linear-gradient(#00897b, #004d40); }
    .kpi-icon { font-size: 1.6rem; opacity: .18; position: absolute; bottom: 14px; right: 16px; }
    .kpi-label { font-size: 0.72rem; font-weight: 600; color: #888; text-transform: uppercase; letter-spacing:.04em; }
    .kpi-value { font-size: 1.65rem; font-weight: 800; color: #1a1a2e; line-height: 1; }
    .kpi-sub { font-size: 0.72rem; color: #aaa; }

    /* Issue Alerts */
    .issue-list { display:flex; flex-direction:column; gap:10px; margin-bottom:24px; }
    .issue-item {
        display:flex; align-items:flex-start; gap:12px; padding:14px 18px;
        border-radius:12px; font-size:0.85rem; font-weight:500; line-height:1.45;
    }
    .issue-item.critical { background:rgba(239,83,80,.08); border:1px solid rgba(239,83,80,.2); color:#c62828; }
    .issue-item.warning  { background:rgba(251,140,0,.08); border:1px solid rgba(251,140,0,.2); color:#e65100; }
    .issue-item.info     { background:rgba(30,136,229,.08); border:1px solid rgba(30,136,229,.2); color:#1565c0; }
    .issue-item i { font-size:1rem; margin-top:2px; flex-shrink:0; }
    .all-good { padding:18px; border-radius:12px; background:rgba(67,160,71,.08); border:1px solid rgba(67,160,71,.2); color:#2e7d32; font-weight:600; font-size:.9rem; }

    /* Charts Grid */
    .charts-grid { display:grid; grid-template-columns:2fr 1fr; gap:20px; margin-bottom:24px; }
    .chart-card { background:#fff; border-radius:16px; padding:22px; box-shadow:0 4px 20px rgba(0,0,0,.04); border:1px solid rgba(0,0,0,.04); }
    .chart-card h5 { font-size:.95rem; font-weight:700; color:#333; margin-bottom:16px; }
    @media(max-width:900px){ .charts-grid { grid-template-columns:1fr; } }

    /* Reason bars */
    .reason-bar-wrap { display:flex; flex-direction:column; gap:8px; }
    .reason-bar { display:flex; align-items:center; gap:10px; font-size:.8rem; }
    .reason-label { width:140px; flex-shrink:0; color:#555; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .reason-track { flex:1; height:8px; background:#f0f0f0; border-radius:99px; overflow:hidden; }
    .reason-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#e91e63,#f06292); }
    .reason-count { width:28px; text-align:right; font-weight:700; color:#333; }

    /* Bracket table */
    .bracket-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; }
    .bracket-cell { background:#f8f9fa; border-radius:10px; padding:12px 8px; text-align:center; border:1px solid #eaedf1; }
    .bracket-cell .bc-label { font-size:.7rem; font-weight:600; color:#777; text-transform:uppercase; margin-bottom:4px; }
    .bracket-cell .bc-num { font-size:1.4rem; font-weight:800; color:#1a1a2e; }
    .bracket-cell .bc-refund { font-size:.7rem; color:#888; }
    @media(max-width:700px){ .bracket-grid { grid-template-columns: repeat(3,1fr); } }

    /* Main table */
    .report-table-wrap { background:#fff; border-radius:16px; padding:20px; box-shadow:0 4px 20px rgba(0,0,0,.04); border:1px solid rgba(0,0,0,.04); }
    .monitor-table { width:100%; border-collapse:separate; border-spacing:0; }
    .monitor-table th { background:#f8f9fa; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:#555; padding:10px 12px; }
    .monitor-table td { padding:10px 12px; font-size:.82rem; color:#333; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
    .monitor-table tr:last-child td { border-bottom:none; }
    .monitor-table tr:hover td { background:#fafbff; }

    .status-pill { padding:3px 10px; border-radius:99px; font-size:.68rem; font-weight:700; text-transform:uppercase; display:inline-block; }
    .sp-cancelled   { background:rgba(239,83,80,.12); color:#c62828; }
    .sp-customer    { background:rgba(239,83,80,.12); color:#b71c1c; }
    .sp-requested   { background:rgba(251,140,0,.12); color:#e65100; }
    .sp-green       { background:rgba(67,160,71,.12); color:#2e7d32; }
    .sp-blue        { background:rgba(30,136,229,.12); color:#1565c0; }
    .sp-grey        { background:rgba(0,0,0,.07); color:#555; }

    /* Hours badge */
    .hours-badge { padding:2px 8px; border-radius:99px; font-size:.68rem; font-weight:700; }
    .hours-red  { background:rgba(239,83,80,.12); color:#c62828; }
    .hours-orange { background:rgba(251,140,0,.12); color:#e65100; }
    .hours-green { background:rgba(67,160,71,.12); color:#2e7d32; }

    /* Misc */
    .page-title { font-size:1.4rem; font-weight:800; color:#1a1a2e; }
    .section-title { font-size:1rem; font-weight:700; color:#333; margin-bottom:12px; display:flex; align-items:center; gap:8px; }
    .partner-card { background:#fff; border-radius:16px; padding:22px; margin-bottom:24px; box-shadow:0 4px 20px rgba(0,0,0,.04); border:1px solid rgba(0,0,0,.04); }
    .btn-filter { background:linear-gradient(135deg,#4f46e5,#7c3aed); color:#fff; border:none; padding:9px 20px; border-radius:10px; font-weight:600; font-size:.85rem; cursor:pointer; }
    .btn-export { background:linear-gradient(135deg,#00897b,#004d40); color:#fff; border:none; padding:9px 20px; border-radius:10px; font-weight:600; font-size:.85rem; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:6px; }
    .btn-export:hover { color:#fff; opacity:.9; }
    .search-input { padding:8px 14px; border:1px solid #dee2e6; border-radius:10px; font-size:.85rem; outline:none; }
    .search-input:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.1); }
</style>
</head>
<body>
<div class="wrapper">
    <?php include 'sidebar.php'; ?>
    <main class="content">

        <!-- Header -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <div class="page-title"><i class="fas fa-chart-bar me-2" style="color:#4f46e5;"></i>Cancellation Report</div>
                <div style="font-size:.8rem;color:#888;">Deep analysis of all cancellations, refunds, and system health</div>
            </div>
            <a href="cancellation_policy_management.php" class="btn-export" style="background:linear-gradient(135deg,#6c757d,#343a40);">
                <i class="fas fa-cog"></i> Manage Policy
            </a>
        </div>

        <!-- Date Filter -->
        <div class="partner-card">
            <form method="GET" class="d-flex align-items-end gap-3 flex-wrap">
                <div>
                    <label class="form-label" style="font-size:.8rem;font-weight:600;color:#555;">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="search-input form-control" style="min-width:150px;">
                </div>
                <div>
                    <label class="form-label" style="font-size:.8rem;font-weight:600;color:#555;">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="search-input form-control" style="min-width:150px;">
                </div>
                <button type="submit" class="btn-filter"><i class="fas fa-filter me-1"></i>Apply Filter</button>
                <a href="?date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>&export=csv" class="btn-export">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
                <a href="?" class="btn" style="font-size:.82rem; color:#888;">Clear</a>
            </form>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card red">
                <div class="kpi-label">Total Cancelled</div>
                <div class="kpi-value"><?= number_format($kpi['total_cancelled']) ?></div>
                <div class="kpi-sub">In selected period</div>
                <i class="fas fa-ban kpi-icon" style="color:#ef5350;"></i>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-label">Advance Collected</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_advance_collected'], 0) ?></div>
                <div class="kpi-sub">Total paid before cancel</div>
                <i class="fas fa-rupee-sign kpi-icon" style="color:#fb8c00;"></i>
            </div>
            <div class="kpi-card green">
                <div class="kpi-label">Refund Given</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_refund_given'], 0) ?></div>
                <div class="kpi-sub">Returned to customers</div>
                <i class="fas fa-undo kpi-icon" style="color:#43a047;"></i>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-label">Charge Kept</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_charge_kept'], 0) ?></div>
                <div class="kpi-sub">Cancellation revenue</div>
                <i class="fas fa-hand-holding-usd kpi-icon" style="color:#1e88e5;"></i>
            </div>
            <div class="kpi-card purple">
                <div class="kpi-label">Vendor Comp.</div>
                <div class="kpi-value">₹<?= number_format($kpi['total_vendor_comp'], 0) ?></div>
                <div class="kpi-sub">Driver compensation</div>
                <i class="fas fa-car kpi-icon" style="color:#8e24aa;"></i>
            </div>
            <div class="kpi-card teal">
                <div class="kpi-label">Avg. Hours Before</div>
                <div class="kpi-value"><?= number_format($kpi['avg_hours_before_pickup'], 1) ?>h</div>
                <div class="kpi-sub">Avg time of cancellation</div>
                <i class="fas fa-clock kpi-icon" style="color:#00897b;"></i>
            </div>
            <div class="kpi-card orange">
                <div class="kpi-label">Pending Refunds</div>
                <div class="kpi-value"><?= $kpi['pending_refunds'] ?></div>
                <div class="kpi-sub">Awaiting action</div>
                <i class="fas fa-hourglass-half kpi-icon" style="color:#fb8c00;"></i>
            </div>
            <div class="kpi-card blue">
                <div class="kpi-label">Processing</div>
                <div class="kpi-value"><?= $kpi['processing_refunds'] ?></div>
                <div class="kpi-sub">In progress</div>
                <i class="fas fa-spinner kpi-icon" style="color:#1e88e5;"></i>
            </div>
            <div class="kpi-card green">
                <div class="kpi-label">Refunds Done</div>
                <div class="kpi-value"><?= $kpi['completed_refunds'] ?></div>
                <div class="kpi-sub">Completed successfully</div>
                <i class="fas fa-check-double kpi-icon" style="color:#43a047;"></i>
            </div>
            <div class="kpi-card red">
                <div class="kpi-label">Unpaid Comp.</div>
                <div class="kpi-value"><?= $kpi['unpaid_vendor_comp'] ?></div>
                <div class="kpi-sub">Drivers awaiting payment</div>
                <i class="fas fa-wallet kpi-icon" style="color:#ef5350;"></i>
            </div>
        </div>

        <!-- Logical Issues Panel -->
        <div class="partner-card">
            <div class="section-title">
                <i class="fas fa-shield-alt" style="color:#ef5350;"></i>
                System Health &amp; Logical Issues
                <?php if (!empty($issues)): ?>
                    <span class="badge bg-danger ms-1"><?= count($issues) ?> Issue<?= count($issues)>1?'s':'' ?></span>
                <?php endif; ?>
            </div>
            <?php if (empty($issues)): ?>
                <div class="all-good"><i class="fas fa-check-circle me-2"></i>No logical issues found. Cancellation system is healthy!</div>
            <?php else: ?>
                <div class="issue-list">
                    <?php foreach ($issues as $issue): ?>
                        <div class="issue-item <?= $issue['severity'] ?>">
                            <i class="fas fa-<?= $issue['icon'] ?>"></i>
                            <span><?= htmlspecialchars($issue['msg']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Charts Row -->
        <div class="charts-grid">
            <!-- Trend Chart -->
            <div class="chart-card">
                <h5><i class="fas fa-chart-line me-2" style="color:#4f46e5;"></i>Daily Cancellation Trend</h5>
                <?php if (empty($trend_days)): ?>
                    <p class="text-muted" style="font-size:.85rem;">No data for this period.</p>
                <?php else: ?>
                    <canvas id="trendChart" height="90"></canvas>
                <?php endif; ?>
            </div>

            <!-- Reason breakdown -->
            <div class="chart-card">
                <h5><i class="fas fa-list-ul me-2" style="color:#e91e63;"></i>Top Cancellation Reasons</h5>
                <?php if (empty($reasons)): ?>
                    <p class="text-muted" style="font-size:.85rem;">No reason data available.</p>
                <?php else: ?>
                    <?php $maxR = max(array_column($reasons, 'cnt')) ?: 1; ?>
                    <div class="reason-bar-wrap">
                        <?php foreach ($reasons as $r): ?>
                        <div class="reason-bar">
                            <div class="reason-label" title="<?= htmlspecialchars($r['cancellation_reason']) ?>">
                                <?= htmlspecialchars(mb_strimwidth($r['cancellation_reason'], 0, 22, '…')) ?>
                            </div>
                            <div class="reason-track">
                                <div class="reason-fill" style="width:<?= round($r['cnt']/$maxR*100) ?>%"></div>
                            </div>
                            <div class="reason-count"><?= $r['cnt'] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Time Bracket Breakdown -->
        <div class="partner-card">
            <div class="section-title"><i class="fas fa-clock me-2" style="color:#00897b;"></i>Cancellations by Time Before Pickup</div>
            <div class="bracket-grid">
                <div class="bracket-cell">
                    <div class="bc-label">&gt;48 Hours</div>
                    <div class="bc-num"><?= $brackets['h48plus'] ?? 0 ?></div>
                    <div class="bc-refund">100% refund bracket</div>
                </div>
                <div class="bracket-cell">
                    <div class="bc-label">24–48 Hours</div>
                    <div class="bc-num"><?= $brackets['h24_48'] ?? 0 ?></div>
                    <div class="bc-refund">75% refund bracket</div>
                </div>
                <div class="bracket-cell">
                    <div class="bc-label">12–24 Hours</div>
                    <div class="bc-num"><?= $brackets['h12_24'] ?? 0 ?></div>
                    <div class="bc-refund">50% refund bracket</div>
                </div>
                <div class="bracket-cell">
                    <div class="bc-label">6–12 Hours</div>
                    <div class="bc-num"><?= $brackets['h6_12'] ?? 0 ?></div>
                    <div class="bc-refund">25% refund bracket</div>
                </div>
                <div class="bracket-cell" style="border-color:rgba(239,83,80,.3); background:rgba(239,83,80,.04);">
                    <div class="bc-label" style="color:#c62828;">&lt;6 Hours</div>
                    <div class="bc-num" style="color:#c62828;"><?= $brackets['h_below6'] ?? 0 ?></div>
                    <div class="bc-refund">0% refund (last minute)</div>
                </div>
            </div>
        </div>

        <!-- Full Bookings Table -->
        <div class="report-table-wrap">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="section-title mb-0"><i class="fas fa-table me-2" style="color:#4f46e5;"></i>Detailed Cancellation Log (<?= count($all_bookings) ?>)</div>
                <input type="text" id="tableSearch" class="search-input" placeholder="Search by ID, name, reason…" style="width:240px;">
            </div>
            <div class="table-responsive" style="border-radius:12px;border:1px solid #eaedf1;">
                <table class="monitor-table" id="reportTable">
                    <thead>
                        <tr>
                            <th>#ID</th>
                            <th>Customer</th>
                            <th>Route</th>
                            <th>Trip Date/Time</th>
                            <th>Hours Before</th>
                            <th>Advance</th>
                            <th>Charge</th>
                            <th>Refund</th>
                            <th>Refund Status</th>
                            <th>Reason</th>
                            <th>Driver</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_bookings)): ?>
                            <tr><td colspan="12" class="text-center text-muted py-4">No cancellations in this date range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_bookings as $b):
                                $h = $b['hours_before'];
                                $hClass = $h === null ? 'sp-grey' : ($h < 6 ? 'hours-red' : ($h < 24 ? 'hours-orange' : 'hours-green'));
                                $sClass = ($b['booking_status'] === 'Cancelled') ? 'sp-cancelled'
                                        : (($b['booking_status'] === 'Customer Cancelled') ? 'sp-customer' : 'sp-requested');
                                $rClass = ($b['refund_status'] === 'Completed') ? 'sp-green'
                                        : (($b['refund_status'] === 'Processing') ? 'sp-blue' : 'sp-grey');
                            ?>
                            <tr>
                                <td style="font-weight:700;">#<?= $b['id'] ?></td>
                                <td style="font-size:.78rem;">
                                    <strong><?= htmlspecialchars($b['customer_name'] ?: 'Unknown') ?></strong><br>
                                    <span style="color:#888;"><?= htmlspecialchars($b['mobile']) ?></span>
                                </td>
                                <td style="font-size:.75rem;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    <?= htmlspecialchars($b['from_address']) ?> → <?= htmlspecialchars($b['to_address'] ?: 'Local') ?>
                                </td>
                                <td style="font-size:.76rem;">
                                    <?= date('d M Y', strtotime($b['date'])) ?><br>
                                    <span style="color:#888;"><?= htmlspecialchars($b['time']) ?></span>
                                </td>
                                <td>
                                    <?php if ($h !== null): ?>
                                        <span class="hours-badge <?= $hClass ?>"><?= $h ?>h</span>
                                    <?php else: ?>
                                        <span class="hours-badge sp-grey">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-weight:700;">₹<?= number_format($b['paid_amount'], 0) ?></td>
                                <td style="font-weight:700; color:#c62828;">₹<?= number_format($b['cancellation_charge'], 0) ?></td>
                                <td style="font-weight:700; color:#2e7d32;">₹<?= number_format($b['refund_amount'], 0) ?></td>
                                <td><span class="status-pill <?= $rClass ?>"><?= htmlspecialchars($b['refund_status'] ?: 'Pending') ?></span></td>
                                <td style="font-size:.75rem;max-width:110px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($b['cancellation_reason']) ?>">
                                    <?= htmlspecialchars($b['cancellation_reason'] ?: '—') ?>
                                </td>
                                <td style="font-size:.78rem;"><?= htmlspecialchars($b['driver_name'] ?: '—') ?></td>
                                <td><span class="status-pill <?= $sClass ?>"><?= htmlspecialchars($b['booking_status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>

<script>
// Sidebar toggle
$(document).ready(function() {
    const hamburger = $('#hamburger'), sidebar = $('#sidebar');
    hamburger.on('click', () => sidebar.toggleClass('active'));
    $(document).on('click', (e) => {
        if (!sidebar.is(e.target) && !sidebar.has(e.target).length && !hamburger.is(e.target) && !hamburger.has(e.target).length) {
            sidebar.removeClass('active');
        }
    });
});

// Table search
document.getElementById('tableSearch').addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#reportTable tbody tr').forEach(tr => {
        tr.style.display = tr.innerText.toLowerCase().includes(q) ? '' : 'none';
    });
});

// Trend Chart
<?php if (!empty($trend_days)): ?>
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($trend_days) ?>,
        datasets: [{
            label: 'Cancellations',
            data: <?= json_encode($trend_counts) ?>,
            backgroundColor: 'rgba(239,83,80,0.18)',
            borderColor: '#ef5350',
            borderWidth: 2,
            borderRadius: 6,
            yAxisID: 'y'
        },{
            label: 'Refund (₹)',
            data: <?= json_encode($trend_refunds) ?>,
            type: 'line',
            borderColor: '#43a047',
            backgroundColor: 'transparent',
            borderWidth: 2.5,
            pointRadius: 4,
            pointBackgroundColor: '#43a047',
            tension: 0.4,
            yAxisID: 'y2'
        }]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { labels: { font: { family: 'Outfit', size: 11 } } } },
        scales: {
            y  : { position:'left',  title:{ display:true, text:'# Cancellations', font:{family:'Outfit',size:10} }, grid:{color:'#f0f0f0'} },
            y2 : { position:'right', title:{ display:true, text:'Refund ₹', font:{family:'Outfit',size:10} }, grid:{display:false} },
            x  : { grid:{display:false}, ticks:{ font:{family:'Outfit',size:10} } }
        }
    }
});
<?php endif; ?>
</script>
</body>
</html>
