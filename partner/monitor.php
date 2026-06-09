<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../adminlogin.php");
    exit();
}
require_once __DIR__ . '/../db_connect.php';

// --- Filter Parameters ---
$filter_start_date = trim($_GET['start_date'] ?? '');
$filter_end_date   = trim($_GET['end_date'] ?? '');
$filter_partner    = (int)($_GET['partner_id'] ?? 0);
$filter_status     = trim($_GET['booking_status'] ?? '');
$filter_vehicle    = trim($_GET['vehicle_type'] ?? '');
$search_query      = trim($_GET['q'] ?? '');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 15;
$offset = ($page - 1) * $limit;

// --- KPI Statistics (Partner Bookings only) ---
$active_partners_res   = mysqli_query($conn, "SELECT COUNT(*) AS count FROM partners WHERE status = 'active'");
$active_partners_count = mysqli_fetch_assoc($active_partners_res)['count'] ?? 0;

$kpi_sql = "
    SELECT
        SUM(CASE WHEN DATE(pb.created_at) = CURDATE() THEN 1 ELSE 0 END) AS bookings_today,
        SUM(CASE WHEN pb.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS bookings_week,
        SUM(CASE WHEN MONTH(pb.created_at) = MONTH(CURDATE()) AND YEAR(pb.created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS bookings_month,
        SUM(CASE WHEN DATE(pb.created_at) = CURDATE() THEN COALESCE(b.total_amount, 0) ELSE 0 END) AS revenue_today,
        SUM(CASE WHEN MONTH(pb.created_at) = MONTH(CURDATE()) AND YEAR(pb.created_at) = YEAR(CURDATE()) THEN COALESCE(b.total_amount, 0) ELSE 0 END) AS revenue_month,
        SUM(CASE WHEN b.booking_status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN b.booking_status = 'Confirmed' THEN 1 ELSE 0 END) AS confirmed_count,
        SUM(CASE WHEN b.booking_status = 'Completed' THEN 1 ELSE 0 END) AS completed_count,
        SUM(CASE WHEN b.booking_status IN ('Cancelled', 'Cancel') THEN 1 ELSE 0 END) AS cancelled_count
    FROM partner_bookings pb
    INNER JOIN bookings b ON pb.booking_id = b.booking_id
";
$kpi_res = mysqli_query($conn, $kpi_sql);
$kpi = mysqli_fetch_assoc($kpi_res);

// --- Partner Company Overview Table ---
$partners_sql = "
    SELECT
        p.id,
        p.company_name,
        p.partner_name,
        p.status AS api_status,
        COUNT(pb.id) AS total_bookings,
        SUM(CASE WHEN DATE(pb.created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_bookings,
        SUM(CASE WHEN MONTH(pb.created_at) = MONTH(CURDATE()) AND YEAR(pb.created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS monthly_bookings,
        SUM(COALESCE(b.total_amount, 0)) AS revenue_generated,
        COALESCE(
            (SELECT MAX(l.created_at) FROM partner_api_logs l WHERE l.partner_id = p.id),
            MAX(pb.created_at),
            p.created_at
        ) AS last_activity
    FROM partners p
    LEFT JOIN partner_bookings pb ON p.id = pb.partner_id
    LEFT JOIN bookings b ON pb.booking_id = b.booking_id
    GROUP BY p.id, p.company_name, p.partner_name, p.status
    ORDER BY total_bookings DESC
";
$partners_overview_res = mysqli_query($conn, $partners_sql);
$partners_overview = [];
while ($row = mysqli_fetch_assoc($partners_overview_res)) {
    $partners_overview[] = $row;
}

// --- Fetch Filter Dropdown Data ---
$all_partners_res = mysqli_query($conn, "SELECT id, company_name, partner_name FROM partners ORDER BY company_name");
$partners_list = [];
while ($row = mysqli_fetch_assoc($all_partners_res)) {
    $partners_list[] = $row;
}

$all_vehicles_res = mysqli_query($conn, "SELECT DISTINCT car_type FROM bookings WHERE car_type IS NOT NULL AND car_type != '' ORDER BY car_type");
$vehicle_types = [];
while ($row = mysqli_fetch_assoc($all_vehicles_res)) {
    $vehicle_types[] = $row['car_type'];
}

// --- Dynamic Booking List Search / Filter SQL Query ---
$where = ['1=1'];
$params = [];
$types = '';

if ($filter_start_date) {
    $where[] = 'DATE(pb.created_at) >= ?';
    $params[] = $filter_start_date;
    $types .= 's';
}
if ($filter_end_date) {
    $where[] = 'DATE(pb.created_at) <= ?';
    $params[] = $filter_end_date;
    $types .= 's';
}
if ($filter_partner) {
    $where[] = 'pb.partner_id = ?';
    $params[] = $filter_partner;
    $types .= 'i';
}
if ($filter_status) {
    $where[] = 'b.booking_status = ?';
    $params[] = $filter_status;
    $types .= 's';
}
if ($filter_vehicle) {
    $where[] = 'b.car_type = ?';
    $params[] = $filter_vehicle;
    $types .= 's';
}
if ($search_query) {
    $where[] = '(pb.booking_id LIKE ? OR u.name LIKE ? OR b.mobile LIKE ? OR b.from_address LIKE ? OR b.to_address LIKE ? OR d.full_name LIKE ?)';
    $search_wildcard = "%" . $search_query . "%";
    for ($i = 0; $i < 6; $i++) {
        $params[] = $search_wildcard;
        $types .= 's';
    }
}

$where_sql = implode(' AND ', $where);

// --- Export All Bookings as JSON if Requested ---
if (isset($_GET['action']) && $_GET['action'] === 'export_all_json') {
    $export_sql = "
        SELECT
            pb.booking_id,
            p.company_name,
            p.partner_name,
            COALESCE(u.name, 'N/A') AS customer_name,
            b.mobile AS customer_mobile,
            b.from_address AS pickup_location,
            b.to_address AS drop_location,
            b.car_type AS vehicle_type,
            COALESCE(d.full_name, 'Not Assigned') AS driver_name,
            b.total_amount AS fare_amount,
            b.booking_status,
            pb.created_at AS booking_date,
            b.trip_type
        FROM partner_bookings pb
        INNER JOIN bookings b ON pb.booking_id = b.booking_id
        INNER JOIN partners p ON pb.partner_id = p.id
        LEFT JOIN users u ON b.booker_id = u.phone_number
        LEFT JOIN drivers d ON b.driver_id = d.phone_number
        WHERE $where_sql
        ORDER BY pb.created_at DESC
    ";
    $export_stmt = mysqli_prepare($conn, $export_sql);
    if (!empty($params)) {
        mysqli_stmt_bind_param($export_stmt, $types, ...$params);
    }
    mysqli_stmt_execute($export_stmt);
    $export_res = mysqli_stmt_get_result($export_stmt);
    $all_bookings = [];
    while ($row = mysqli_fetch_assoc($export_res)) {
        $all_bookings[] = $row;
    }
    mysqli_stmt_close($export_stmt);
    
    header('Content-Type: application/json');
    echo json_encode($all_bookings);
    exit();
}


// 1. Pagination Row Count
$count_sql = "
    SELECT COUNT(*) AS total
    FROM partner_bookings pb
    INNER JOIN bookings b ON pb.booking_id = b.booking_id
    LEFT JOIN users u ON b.booker_id = u.phone_number
    LEFT JOIN drivers d ON b.driver_id = d.phone_number
    WHERE $where_sql
";
$count_stmt = mysqli_prepare($conn, $count_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($count_stmt, $types, ...$params);
}
mysqli_stmt_execute($count_stmt);
$total_bookings = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);

$total_pages = max(1, ceil($total_bookings / $limit));

// 2. Fetch Paginated Bookings
$bookings_sql = "
    SELECT
        pb.booking_id,
        p.company_name,
        p.partner_name,
        COALESCE(u.name, 'N/A') AS customer_name,
        b.mobile AS customer_mobile,
        b.from_address AS pickup_location,
        b.to_address AS drop_location,
        b.car_type AS vehicle_type,
        COALESCE(d.full_name, 'Not Assigned') AS driver_name,
        b.total_amount AS fare_amount,
        b.booking_status,
        pb.created_at AS booking_date,
        b.trip_type
    FROM partner_bookings pb
    INNER JOIN bookings b ON pb.booking_id = b.booking_id
    INNER JOIN partners p ON pb.partner_id = p.id
    LEFT JOIN users u ON b.booker_id = u.phone_number
    LEFT JOIN drivers d ON b.driver_id = d.phone_number
    WHERE $where_sql
    ORDER BY pb.created_at DESC
    LIMIT ? OFFSET ?
";

$log_types = $types . 'ii';
$log_params = array_merge($params, [$limit, $offset]);

$bookings_stmt = mysqli_prepare($conn, $bookings_sql);
if ($log_params) {
    mysqli_stmt_bind_param($bookings_stmt, $log_types, ...$log_params);
}
mysqli_stmt_execute($bookings_stmt);
$bookings_res = mysqli_stmt_get_result($bookings_stmt);
$bookings = [];
while ($row = mysqli_fetch_assoc($bookings_res)) {
    $bookings[] = $row;
}
mysqli_stmt_close($bookings_stmt);

// --- Analytics Data Fetches ---

// 1. Daily Booking Trends (Last 15 Days)
$trends_daily_sql = "
    SELECT
        DATE(pb.created_at) AS trend_date,
        COUNT(*) AS count
    FROM partner_bookings pb
    WHERE pb.created_at >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)
    GROUP BY DATE(pb.created_at)
    ORDER BY trend_date ASC
";
$trends_daily_res = mysqli_query($conn, $trends_daily_sql);
$trends_daily = [];
while ($row = mysqli_fetch_assoc($trends_daily_res)) {
    $trends_daily[] = [
        'label' => date('d M', strtotime($row['trend_date'])),
        'count' => (int)$row['count']
    ];
}

// 2. Monthly Revenue Trends (Last 6 Months)
$trends_monthly_sql = "
    SELECT
        DATE_FORMAT(pb.created_at, '%Y-%m') AS trend_month,
        SUM(b.total_amount) AS revenue
    FROM partner_bookings pb
    INNER JOIN bookings b ON pb.booking_id = b.booking_id
    WHERE pb.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(pb.created_at, '%Y-%m')
    ORDER BY trend_month ASC
";
$trends_monthly_res = mysqli_query($conn, $trends_monthly_sql);
$trends_monthly = [];
while ($row = mysqli_fetch_assoc($trends_monthly_res)) {
    $trends_monthly[] = [
        'label' => date('M Y', strtotime($row['trend_month'] . '-01')),
        'revenue' => (float)$row['revenue']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Booking Dashboard — Agni Car Rental</title>
    <link rel="icon" type="image/png" href="../images/pnglogoagni.png">
    <link rel="stylesheet" href="../css/Dashboard_styles.css?v=2.1">
    <link rel="stylesheet" href="../css/partner_styles.css?v=1.4">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Chart.js for premium quality analytical reporting -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jsPDF and jsPDF-AutoTable for client-side PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <style>
        /* Export Buttons - Premium Styling */
        .btn-export-excel {
            background: linear-gradient(135deg, #11998e, #38ef7d);
            color: #fff !important;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(56, 239, 125, 0.2);
        }
        .btn-export-excel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4);
            filter: brightness(1.05);
        }
        .btn-export-excel:active {
            transform: translateY(0);
        }
        .btn-export-pdf {
            background: linear-gradient(135deg, #ff416c, #ff4b2b);
            color: #fff !important;
            border: none;
            border-radius: 10px;
            padding: 8px 16px;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.25s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(255, 75, 43, 0.2);
        }
        .btn-export-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 75, 43, 0.4);
            filter: brightness(1.05);
        }
        .btn-export-pdf:active {
            transform: translateY(0);
        }

        body {
            font-family: 'Outfit', 'Segoe UI', system-ui, sans-serif !important;
            background-color: #f6f8fa;
        }
        .content {
            padding: 24px !important;
        }
        /* Dashboard Card Styling - Premium Glassmorphism */
        .kpi-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }
        .kpi-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            border: 1px solid rgba(108, 99, 255, 0.08);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }
        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 35px rgba(108, 99, 255, 0.12);
        }
        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 5px;
        }
        .kpi-card.today::before { background: linear-gradient(to bottom, #007bff, #0056b3); }
        .kpi-card.week::before { background: linear-gradient(to bottom, #6C63FF, #5a52e0); }
        .kpi-card.month::before { background: linear-gradient(to bottom, #17a2b8, #117a8b); }
        .kpi-card.revenue::before { background: linear-gradient(to bottom, #28a745, #1e7e34); }
        .kpi-card.active-comp::before { background: linear-gradient(to bottom, #20c997, #1ba87e); }
        .kpi-card.pending::before { background: linear-gradient(to bottom, #ffc107, #d39e00); }
        .kpi-card.confirmed::before { background: linear-gradient(to bottom, #007bff, #0056b3); }
        .kpi-card.completed::before { background: linear-gradient(to bottom, #28a745, #1e7e34); }
        .kpi-card.cancelled::before { background: linear-gradient(to bottom, #dc3545, #bd2130); }

        .kpi-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .kpi-card.today .kpi-icon { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .kpi-card.week .kpi-icon { background: rgba(108, 99, 255, 0.1); color: #6C63FF; }
        .kpi-card.month .kpi-icon { background: rgba(23, 162, 184, 0.1); color: #17a2b8; }
        .kpi-card.revenue .kpi-icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .kpi-card.active-comp .kpi-icon { background: rgba(32, 201, 151, 0.1); color: #20c997; }
        .kpi-card.pending .kpi-icon { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .kpi-card.confirmed .kpi-icon { background: rgba(0, 123, 255, 0.1); color: #007bff; }
        .kpi-card.completed .kpi-icon { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .kpi-card.cancelled .kpi-icon { background: rgba(220, 53, 69, 0.1); color: #dc3545; }

        .kpi-content {
            flex-grow: 1;
        }
        .kpi-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #2b2b2b;
            line-height: 1.2;
        }
        .kpi-label {
            font-size: 0.78rem;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }

        /* Filter Controls */
        .filter-panel {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            margin-bottom: 24px;
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: flex-end;
        }
        .filter-control {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .filter-control label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .filter-control input, .filter-control select {
            border: 1px solid #ced4da;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.88rem;
            background-color: #fff;
            color: #333;
            transition: all 0.2s ease;
        }
        .filter-control input:focus, .filter-control select:focus {
            border-color: #6C63FF;
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.15);
            outline: none;
        }

        /* Table Styling */
        .table-responsive {
            background: #fff;
            border-radius: 16px;
            padding: 8px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 25px rgba(0,0,0,0.02);
        }
        .monitor-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0;
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
        .monitor-table tbody tr:last-child td {
            border-bottom: none;
        }
        .monitor-table tbody tr {
            transition: all 0.2s ease;
        }
        .monitor-table tbody tr:hover {
            background-color: rgba(108, 99, 255, 0.03);
        }

        /* Chart Canvas Wrappers */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 28px;
        }
        .chart-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 6px 20px rgba(0,0,0,0.02);
        }
        .chart-card h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Status Badges */
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-align: center;
        }
        .status-pill.pending { background-color: rgba(255, 193, 7, 0.15); color: #d39e00; }
        .status-pill.confirmed { background-color: rgba(0, 123, 255, 0.15); color: #007bff; }
        .status-pill.completed { background-color: rgba(40, 167, 69, 0.15); color: #28a745; }
        .status-pill.cancelled { background-color: rgba(220, 53, 69, 0.15); color: #dc3545; }

        .api-pill {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .api-pill.active { background-color: rgba(40, 167, 69, 0.12); color: #28a745; }
        .api-pill.blocked { background-color: rgba(220, 53, 69, 0.12); color: #dc3545; }

        @media (max-width: 768px) {
            .chart-grid { grid-template-columns: 1fr; }
            .kpi-container { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .kpi-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- Top Navigation -->
<nav class="top-nav">
    <div class="logo-container">
        <img src="../images/logo_rentox.png" alt="Company Logo" class="logo">
    </div>
    <h1 class="dashboard-heading">
        <i class="fas fa-desktop me-2" style="font-size:1.6rem;"></i> Partner Monitor
    </h1>
    <div class="center-nav">
        <a href="../dashboard.php" class="home-btn"><i class="fas fa-home me-2"></i> Home</a>
    </div>
    <div class="right-nav">
        <form action="../logout.php" method="POST" class="logout-form">
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
            <li><a href="../dashboard.php?tab=driver" id="driver"><i class="fas fa-user me-2"></i> Driver</a></li>
            <li><a href="../dashboard.php?tab=cab" id="cab"><i class="fas fa-taxi me-2"></i> Cab</a></li>
            <li><a href="../dashboard.php?tab=booking" id="booking"><i class="fas fa-calendar me-2"></i> Booking</a></li>
            <li><a href="../dashboard.php?tab=completed" id="Complete"><i class="fas fa-calendar-check me-2"></i> Completed</a></li>
            <li>
                <a href="../dashboard.php?tab=newuser" id="newuser">
                    <i class="fa-solid fa-users me-2"></i> Customers
                </a>
            </li>
            <li><a href="https://agnicarrental.com/admin2025/bookacall/admin-bookings.php" id="bookacall"><i class="fa-solid fa-phone me-2"></i>BookACall</a></li>
            <li><a href="../dashboard.php?tab=blocked_customer" id="Blocked_Customer"><i class="fas fa-user-slash me-2"></i>Blocked Customer</a></li>
            <li><a href="../dashboard.php?tab=extract_data" id="Extract_Data"><i class="fas fa-file-excel me-2"></i> Extract Data</a></li>
            <li><a href="index.php" id="partner_api"><i class="fas fa-handshake me-2"></i> Partner API</a></li>
            <li><a href="monitor.php" id="partner_monitor" style="background-color: #465c71;"><i class="fas fa-desktop me-2"></i> Partner Monitor</a></li>
            <li><a href="../car_categories.php" id="car_categories_menu"><i class="fas fa-tags me-2"></i> Car Categories</a></li>
        </ul>
    </nav>

    <!-- Main Content Panel -->
    <main class="content">
        <!-- KPI Row 1: Summary Statistics -->
        <h4 class="mb-3" style="font-weight:700; color:#494487;"><i class="fas fa-chart-line me-2"></i>Partner KPI Overview</h4>
        <div class="kpi-container">
            <div class="kpi-card today">
                <div class="kpi-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['bookings_today'] ?? 0) ?></div>
                    <div class="kpi-label">Bookings Today</div>
                </div>
            </div>
            <div class="kpi-card week">
                <div class="kpi-icon"><i class="fas fa-calendar-week"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['bookings_week'] ?? 0) ?></div>
                    <div class="kpi-label">Bookings This Week</div>
                </div>
            </div>
            <div class="kpi-card month">
                <div class="kpi-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['bookings_month'] ?? 0) ?></div>
                    <div class="kpi-label">Bookings This Month</div>
                </div>
            </div>
            <div class="kpi-card revenue">
                <div class="kpi-icon"><i class="fas fa-rupee-sign"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value">₹<?= number_format($kpi['revenue_today'] ?? 0, 2) ?></div>
                    <div class="kpi-label">Revenue Today</div>
                </div>
            </div>
            <div class="kpi-card revenue">
                <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value">₹<?= number_format($kpi['revenue_month'] ?? 0, 2) ?></div>
                    <div class="kpi-label">Revenue This Month</div>
                </div>
            </div>
        </div>

        <!-- KPI Row 2: Status Breakdown & Partners -->
        <div class="kpi-container">
            <div class="kpi-card active-comp">
                <div class="kpi-icon"><i class="fas fa-handshake"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($active_partners_count) ?></div>
                    <div class="kpi-label">Active Partners</div>
                </div>
            </div>
            <div class="kpi-card pending">
                <div class="kpi-icon"><i class="fas fa-hourglass-start"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['pending_count'] ?? 0) ?></div>
                    <div class="kpi-label">Pending Bookings</div>
                </div>
            </div>
            <div class="kpi-card confirmed">
                <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['confirmed_count'] ?? 0) ?></div>
                    <div class="kpi-label">Confirmed Bookings</div>
                </div>
            </div>
            <div class="kpi-card completed">
                <div class="kpi-icon"><i class="fas fa-flag-checkered"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['completed_count'] ?? 0) ?></div>
                    <div class="kpi-label">Completed Bookings</div>
                </div>
            </div>
            <div class="kpi-card cancelled">
                <div class="kpi-icon"><i class="fas fa-times-circle"></i></div>
                <div class="kpi-content">
                    <div class="kpi-value"><?= number_format($kpi['cancelled_count'] ?? 0) ?></div>
                    <div class="kpi-label">Cancelled Bookings</div>
                </div>
            </div>
        </div>

        <!-- Partner Company Overview Grid -->
        <div class="partner-card mb-4">
            <div class="partner-card-header">
                <h4><i class="fas fa-users-cog me-2" style="color:#6C63FF;"></i>Partner Company Performance Overview</h4>
            </div>
            <div class="table-responsive">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Total Bookings</th>
                            <th>Today's Bookings</th>
                            <th>Monthly Bookings</th>
                            <th>Revenue Generated</th>
                            <th>Last Activity</th>
                            <th>API Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($partners_overview)): ?>
                            <tr>
                                <td colspan="7" class="text-muted py-4">No partner companies configured yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($partners_overview as $p_info): ?>
                                <tr>
                                    <td class="text-start">
                                        <div style="font-weight:600; color:#333;"><?= htmlspecialchars($p_info['company_name']) ?></div>
                                        <div style="font-size:0.75rem; color:#888;"><?= htmlspecialchars($p_info['partner_name']) ?></div>
                                    </td>
                                    <td style="font-weight:600;"><?= number_format($p_info['total_bookings']) ?></td>
                                    <td><?= number_format($p_info['today_bookings']) ?></td>
                                    <td><?= number_format($p_info['monthly_bookings']) ?></td>
                                    <td style="font-weight:600; color:#28a745;">₹<?= number_format($p_info['revenue_generated'], 2) ?></td>
                                    <td style="font-size:0.82rem; color:#666;">
                                        <?= $p_info['last_activity'] && $p_info['last_activity'] !== '1970-01-01' ? date('d M Y H:i', strtotime($p_info['last_activity'])) : 'N/A' ?>
                                    </td>
                                    <td>
                                        <span class="api-pill <?= $p_info['api_status'] === 'active' ? 'active' : 'blocked' ?>">
                                            <?= htmlspecialchars($p_info['api_status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Filter and Booking Monitoring Panel -->
        <div class="partner-card">
            <div class="partner-card-header flex-column flex-sm-row align-items-start align-items-sm-center gap-3">
                <h4 class="mb-0"><i class="fas fa-list-alt me-2" style="color:#17a2b8;"></i>Partner Booking Monitor</h4>
                
                <div class="d-flex gap-2 ms-sm-auto flex-wrap">
                    <button type="button" id="btnExportCSV" class="btn-export-excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </button>
                    <button type="button" id="btnExportPDF" class="btn-export-pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                </div>

                <!-- Simple Dynamic Search Box -->
                <form method="GET" class="w-100" style="max-width:320px; margin-bottom: 0;">
                    <div class="input-group">
                        <!-- Keep other filter states hidden -->
                        <?php if ($filter_start_date): ?><input type="hidden" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>"><?php endif; ?>
                        <?php if ($filter_end_date): ?><input type="hidden" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>"><?php endif; ?>
                        <?php if ($filter_partner): ?><input type="hidden" name="partner_id" value="<?= $filter_partner ?>"><?php endif; ?>
                        <?php if ($filter_status): ?><input type="hidden" name="booking_status" value="<?= htmlspecialchars($filter_status) ?>"><?php endif; ?>
                        <?php if ($filter_vehicle): ?><input type="hidden" name="vehicle_type" value="<?= htmlspecialchars($filter_vehicle) ?>"><?php endif; ?>
                        
                        <input type="text" name="q" class="form-control" style="border-radius:10px 0 0 10px;" placeholder="Search Booking ID, name, mobile..." value="<?= htmlspecialchars($search_query) ?>">
                        <button class="btn btn-secondary" style="border-radius:0 10px 10px 0; background:#6C63FF; border:none;" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Dynamic Filters -->
            <form method="GET" class="filter-panel">
                <!-- Retain current search query value -->
                <?php if ($search_query): ?><input type="hidden" name="q" value="<?= htmlspecialchars($search_query) ?>"><?php endif; ?>
                
                <div class="filter-grid">
                    <div class="filter-control">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= htmlspecialchars($filter_start_date) ?>">
                    </div>
                    <div class="filter-control">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= htmlspecialchars($filter_end_date) ?>">
                    </div>
                    <div class="filter-control">
                        <label>Partner Company</label>
                        <select name="partner_id">
                            <option value="">All Companies</option>
                            <?php foreach ($partners_list as $pl): ?>
                                <option value="<?= $pl['id'] ?>" <?= $filter_partner == $pl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pl['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-control">
                        <label>Booking Status</label>
                        <select name="booking_status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $filter_status === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Confirmed" <?= $filter_status === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="Completed" <?= $filter_status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Cancelled" <?= $filter_status === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="filter-control">
                        <label>Vehicle Type</label>
                        <select name="vehicle_type">
                            <option value="">All Types</option>
                            <?php foreach ($vehicle_types as $vt): ?>
                                <option value="<?= htmlspecialchars($vt) ?>" <?= $filter_vehicle === $vt ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn-partner-primary" style="padding:10px 18px;"><i class="fas fa-filter"></i> Apply</button>
                        <a href="monitor.php" class="btn-partner-info" style="padding:10px 18px; line-height:1.2; text-align:center;"><i class="fas fa-times"></i> Clear</a>
                    </div>
                </div>
            </form>

            <!-- Bookings List Table -->
            <div class="table-responsive">
                <table class="monitor-table text-center">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Company</th>
                            <th>Customer Name</th>
                            <th>Pickup / Drop</th>
                            <th>Vehicle Type</th>
                            <th>Driver Name</th>
                            <th>Fare Amount</th>
                            <th>Status</th>
                            <th>Booking Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="9" class="text-muted py-5">
                                    <i class="fas fa-inbox d-block fs-2 mb-2 opacity-50"></i>
                                    No bookings matching filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $b): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700; color:#0056b3;"><?= htmlspecialchars($b['booking_id']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;"><?= htmlspecialchars($b['company_name']) ?></div>
                                    </td>
                                    <td class="text-start">
                                        <div style="font-weight:500;"><?= htmlspecialchars($b['customer_name']) ?></div>
                                        <div style="font-size:0.75rem; color:#666;"><?= htmlspecialchars($b['customer_mobile']) ?></div>
                                    </td>
                                    <td class="text-start" style="max-width: 250px; font-size:0.8rem;">
                                        <div class="text-truncate" title="<?= htmlspecialchars($b['pickup_location']) ?>">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i> <?= htmlspecialchars($b['pickup_location']) ?>
                                        </div>
                                        <div class="text-truncate mt-1" title="<?= htmlspecialchars($b['drop_location']) ?>">
                                            <i class="fas fa-flag-checkered text-success me-1"></i> <?= htmlspecialchars($b['drop_location']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary" style="font-size: 0.72rem;"><?= htmlspecialchars($b['vehicle_type']) ?></span>
                                        <div style="font-size: 0.7rem; color: #888; margin-top:2px;"><?= htmlspecialchars($b['trip_type']) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars($b['driver_name']) ?></td>
                                    <td style="font-weight:700; color: #28a745;">
                                        <?php if ($b['trip_type'] === 'Round-Trip' && (float)$b['fare_amount'] <= 0): ?>
                                            <span style="color:#ff9800; font-size: 0.8rem;"><i class="fas fa-clock"></i> TBD</span>
                                        <?php else: ?>
                                            ₹<?= number_format($b['fare_amount'], 2) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = strtolower($b['booking_status']);
                                        if ($status_class === 'cancel') $status_class = 'cancelled';
                                        ?>
                                        <span class="status-pill <?= $status_class ?>">
                                            <?= htmlspecialchars($b['booking_status']) ?>
                                        </span>
                                    </td>
                                    <td style="font-size:0.8rem; color:#666;">
                                        <?= date('d M Y H:i', strtotime($b['booking_date'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="partner-pagination mt-4">
                    <?php
                    $pagination_params = array_filter([
                        'start_date'     => $filter_start_date,
                        'end_date'       => $filter_end_date,
                        'partner_id'     => $filter_partner,
                        'booking_status' => $filter_status,
                        'vehicle_type'   => $filter_vehicle,
                        'q'              => $search_query,
                    ]);
                    $query_string = http_build_query($pagination_params);
                    $base_link = "monitor.php?" . ($query_string ? $query_string . "&" : "");
                    ?>
                    
                    <a href="<?= $base_link ?>page=1" class="page-btn" <?= $page <= 1 ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>>&laquo; First</a>
                    <a href="<?= $base_link ?>page=<?= $page - 1 ?>" class="page-btn" <?= $page <= 1 ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>>&lsaquo; Prev</a>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page   = min($total_pages, $page + 2);
                    for ($pg = $start_page; $pg <= $end_page; $pg++):
                    ?>
                        <a href="<?= $base_link ?>page=<?= $pg ?>" class="page-btn <?= $pg === $page ? 'active' : '' ?>"><?= $pg ?></a>
                    <?php endfor; ?>
                    
                    <a href="<?= $base_link ?>page=<?= $page + 1 ?>" class="page-btn" <?= $page >= $total_pages ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>>Next &rsaquo;</a>
                    <a href="<?= $base_link ?>page=<?= $total_pages ?>" class="page-btn" <?= $page >= $total_pages ? 'style="pointer-events:none; opacity:0.5;"' : '' ?>>Last &raquo;</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Analytics Charts Grid -->
        <h4 class="mb-3 mt-4" style="font-weight:700; color:#494487;"><i class="fas fa-chart-pie me-2"></i>Analytics Reporting</h4>
        <div class="chart-grid">
            <!-- Bookings & Revenue per Company -->
            <div class="chart-card">
                <h4><i class="fas fa-chart-bar" style="color:#6C63FF"></i>Bookings & Revenue per Partner</h4>
                <div style="height:300px; position:relative;">
                    <canvas id="companyComparisonChart"></canvas>
                </div>
            </div>

            <!-- Daily booking trends -->
            <div class="chart-card">
                <h4><i class="fas fa-chart-line" style="color:#20c997"></i>Daily Booking Trends (Last 15 Days)</h4>
                <div style="height:300px; position:relative;">
                    <canvas id="dailyTrendsChart"></canvas>
                </div>
            </div>

            <!-- Monthly revenue trends -->
            <div class="chart-card">
                <h4><i class="fas fa-wallet" style="color:#28a745"></i>Monthly Revenue Trends (Last 6 Months)</h4>
                <div style="height:300px; position:relative;">
                    <canvas id="monthlyTrendsChart"></canvas>
                </div>
            </div>

            <!-- Top Performing Companies Leaderboard -->
            <div class="chart-card d-flex flex-column">
                <h4><i class="fas fa-trophy" style="color:#ffc107"></i>Top Performing Partners</h4>
                <div class="flex-grow-1 d-flex flex-column justify-content-center">
                    <?php
                    // Sort partners by revenue generated descending for leaderboard
                    $leaderboard = $partners_overview;
                    usort($leaderboard, function($a, $b) {
                        return $b['revenue_generated'] <=> $a['revenue_generated'];
                    });
                    $leaderboard = array_slice($leaderboard, 0, 5);
                    $rank = 1;
                    ?>
                    <?php if (empty($leaderboard) || $leaderboard[0]['total_bookings'] == 0): ?>
                        <p class="text-center text-muted py-4">No performance metrics recorded.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($leaderboard as $lead): ?>
                                <?php if ($lead['total_bookings'] > 0): ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <div style="width:30px; height:30px; border-radius:50%; background:<?= $rank === 1 ? '#ffc107' : ($rank === 2 ? '#ced4da' : ($rank === 3 ? '#cd7f32' : '#f8f9fa')) ?>; color:<?= $rank <= 3 ? '#fff' : '#555' ?>; display:flex; align-items:center; justify-content:center; font-weight:800; font-size:0.9rem;">
                                            <?= $rank++ ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div style="font-weight:600; font-size:0.92rem;"><?= htmlspecialchars($lead['company_name']) ?></div>
                                            <div style="font-size:0.75rem; color:#777;"><?= number_format($lead['total_bookings']) ?> bookings</div>
                                        </div>
                                        <div style="font-weight:700; color:#28a745;">₹<?= number_format($lead['revenue_generated'], 2) ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Dynamic data injection into Chart.js configs -->
<script>
    // Partners data overview for comparison charts
    const partnersData = <?= json_encode($partners_overview) ?>;
    
    // Daily Trends Data
    const dailyTrendsData = <?= json_encode($trends_daily) ?>;
    
    // Monthly Trends Data
    const monthlyTrendsData = <?= json_encode($trends_monthly) ?>;

    // Render Charts after DOM load
    $(document).ready(function() {
        // --- 1. Company Comparison Chart ---
        const companyNames = partnersData.map(p => p.company_name);
        const companyBookings = partnersData.map(p => parseInt(p.total_bookings));
        const companyRevenue = partnersData.map(p => parseFloat(p.revenue_generated));

        const ctxComp = document.getElementById('companyComparisonChart').getContext('2d');
        new Chart(ctxComp, {
            type: 'bar',
            data: {
                labels: companyNames,
                datasets: [
                    {
                        label: 'Total Bookings',
                        data: companyBookings,
                        backgroundColor: 'rgba(108, 99, 255, 0.75)',
                        borderColor: '#6C63FF',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Revenue Generated (₹)',
                        data: companyRevenue,
                        backgroundColor: 'rgba(40, 167, 69, 0.75)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: { display: true, text: 'Bookings Count' },
                        grid: { drawOnChartArea: false }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: { display: true, text: 'Revenue (₹)' },
                        grid: { drawOnChartArea: true }
                    }
                }
            }
        });

        // --- 2. Daily Booking Trends Chart ---
        const dailyLabels = dailyTrendsData.map(d => d.date);
        const dailyCounts = dailyTrendsData.map(d => d.count);

        const ctxDaily = document.getElementById('dailyTrendsChart').getContext('2d');
        new Chart(ctxDaily, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Bookings Per Day',
                    data: dailyCounts,
                    fill: true,
                    backgroundColor: 'rgba(32, 201, 151, 0.15)',
                    borderColor: '#20c997',
                    borderWidth: 2.5,
                    tension: 0.3,
                    pointBackgroundColor: '#20c997',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // --- 3. Monthly Revenue Trends Chart ---
        const monthlyLabels = monthlyTrendsData.map(m => m.label);
        const monthlyRevenues = monthlyTrendsData.map(m => m.revenue);

        const ctxMonthly = document.getElementById('monthlyTrendsChart').getContext('2d');
        new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Monthly Revenue (₹)',
                    data: monthlyRevenues,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: '#28a745',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Revenue (₹)' }
                    }
                }
            }
        });

        // Export Excel (CSV)
        $('#btnExportCSV').on('click', function() {
            const btn = $(this);
            const originalContent = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> Excel').prop('disabled', true);
            
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('action', 'export_all_json');
            
            fetch(`monitor.php?${searchParams.toString()}`)
                .then(res => {
                    if (!res.ok) throw new Error("Network response was not ok");
                    return res.json();
                })
                .then(data => {
                    if (!data || data.length === 0) {
                        alert("No bookings available to export.");
                        return;
                    }
                    
                    const headers = [
                        'Booking ID', 'Company Name', 'Customer Name', 'Customer Mobile', 
                        'Pickup Location', 'Drop Location', 'Vehicle Type', 'Trip Type', 
                        'Driver Name', 'Fare Amount', 'Booking Status', 'Booking Date'
                    ];
                    
                    const csvRows = [headers.join(',')];
                    
                    data.forEach(row => {
                        let fare = parseFloat(row.fare_amount);
                        let fareStr = (row.trip_type === 'Round-Trip' && fare <= 0) ? 'TBD' : fare.toFixed(2);
                        
                        const values = [
                            row.booking_id,
                            row.company_name,
                            row.customer_name,
                            row.customer_mobile,
                            row.pickup_location,
                            row.drop_location,
                            row.vehicle_type,
                            row.trip_type,
                            row.driver_name,
                            fareStr,
                            row.booking_status,
                            row.booking_date
                        ].map(val => `"${(val || '').toString().replace(/"/g, '""')}"`);
                        
                        csvRows.push(values.join(','));
                    });
                    
                    const csvString = '\ufeff' + csvRows.join('\n');
                    const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
                    const url = URL.createObjectURL(blob);
                    
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Partner_Bookings_${new Date().toISOString().slice(0, 10)}.csv`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                })
                .catch(err => {
                    console.error("Export error:", err);
                    alert("Failed to export Excel. Please try again.");
                })
                .finally(() => {
                    btn.html(originalContent).prop('disabled', false);
                });
        });

        // Export PDF
        $('#btnExportPDF').on('click', function() {
            const btn = $(this);
            const originalContent = btn.html();
            btn.html('<i class="fas fa-spinner fa-spin"></i> PDF').prop('disabled', true);
            
            const searchParams = new URLSearchParams(window.location.search);
            searchParams.set('action', 'export_all_json');
            
            fetch(`monitor.php?${searchParams.toString()}`)
                .then(res => {
                    if (!res.ok) throw new Error("Network response was not ok");
                    return res.json();
                })
                .then(data => {
                    if (!data || data.length === 0) {
                        alert("No bookings available to export.");
                        return;
                    }
                    
                    const { jsPDF } = window.jspdf;
                    const doc = new jsPDF({
                        orientation: 'landscape',
                        unit: 'mm',
                        format: 'a4'
                    });
                    
                    // Header Banner
                    doc.setFillColor(70, 92, 113);
                    doc.rect(0, 0, 297, 24, 'F');
                    
                    // Brand / Logo Text
                    doc.setTextColor(255, 255, 255);
                    doc.setFont('Helvetica', 'bold');
                    doc.setFontSize(16);
                    doc.text('AGNI CAR RENTAL', 14, 11);
                    
                    doc.setFont('Helvetica', 'normal');
                    doc.setFontSize(10);
                    doc.text('Partner Booking Monitor Report', 14, 18);
                    
                    // Metadata
                    doc.setFontSize(8.5);
                    doc.text(`Generated: ${new Date().toLocaleString()}`, 230, 10);
                    doc.text(`Total Bookings: ${data.length}`, 230, 15);
                    
                    // Filters
                    let filterTexts = [];
                    const startDate = searchParams.get('start_date');
                    const endDate = searchParams.get('end_date');
                    const status = searchParams.get('booking_status');
                    const vehicle = searchParams.get('vehicle_type');
                    
                    if (startDate || endDate) {
                        filterTexts.push(`Date Range: ${startDate || 'Any'} to ${endDate || 'Any'}`);
                    }
                    if (status) filterTexts.push(`Status: ${status}`);
                    if (vehicle) filterTexts.push(`Vehicle: ${vehicle}`);
                    
                    const filterInfo = filterTexts.length > 0 ? `Filters applied: ${filterTexts.join(' | ')}` : 'Filters applied: None';
                    doc.setTextColor(100, 100, 100);
                    doc.setFontSize(9);
                    doc.text(filterInfo, 14, 31);
                    
                    const tableHeaders = [
                        ['Booking ID', 'Company', 'Customer Info', 'Pickup & Drop Location', 'Vehicle / Trip', 'Driver', 'Fare', 'Status', 'Date']
                    ];
                    
                    const tableRows = data.map(row => {
                        let fare = parseFloat(row.fare_amount);
                        let fareStr = (row.trip_type === 'Round-Trip' && fare <= 0) ? 'TBD' : `INR ${fare.toFixed(2)}`;
                        
                        return [
                            row.booking_id || 'N/A',
                            row.company_name || 'N/A',
                            `${row.customer_name || 'N/A'}\n${row.customer_mobile || 'N/A'}`,
                            `P: ${row.pickup_location || 'N/A'}\nD: ${row.drop_location || 'N/A'}`,
                            `${row.vehicle_type || 'N/A'}\n(${row.trip_type || 'N/A'})`,
                            row.driver_name || 'N/A',
                            fareStr,
                            row.booking_status || 'N/A',
                            row.booking_date ? row.booking_date.slice(0, 16) : 'N/A'
                        ];
                    });
                    
                    doc.autoTable({
                        startY: 35,
                        head: tableHeaders,
                        body: tableRows,
                        theme: 'striped',
                        headStyles: {
                            fillColor: [70, 92, 113],
                            textColor: [255, 255, 255],
                            fontStyle: 'bold',
                            fontSize: 9,
                            halign: 'left'
                        },
                        bodyStyles: {
                            fontSize: 8,
                            valign: 'middle',
                            cellPadding: 3
                        },
                        columnStyles: {
                            0: { cellWidth: 22 },
                            1: { cellWidth: 25 },
                            2: { cellWidth: 32 },
                            3: { cellWidth: 60 },
                            4: { cellWidth: 30 },
                            5: { cellWidth: 28 },
                            6: { cellWidth: 20, halign: 'right' },
                            7: { cellWidth: 22, halign: 'center' },
                            8: { cellWidth: 25 }
                        },
                        margin: { left: 14, right: 14 },
                        didDrawPage: function(data) {
                            doc.setFontSize(8);
                            doc.setTextColor(150, 150, 150);
                            doc.text('Confidential - Agni Car Rental Admin Panel', 14, 203);
                            
                            let pageNum = doc.internal.getNumberOfPages();
                            doc.text(`Page ${pageNum}`, 275, 203);
                        }
                    });
                    
                    doc.save(`Partner_Bookings_Report_${new Date().toISOString().slice(0, 10)}.pdf`);
                })
                .catch(err => {
                    console.error("Export error:", err);
                    alert("Failed to export PDF. Please try again.");
                })
                .finally(() => {
                    btn.html(originalContent).prop('disabled', false);
                });
        });

        // Hamburger sidebar toggle
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
