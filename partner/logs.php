<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../adminlogin.php"); exit(); }
require_once __DIR__ . '/../db_connect.php';

// Filters
$filter_partner  = (int)($_GET['partner_id'] ?? 0);
$filter_api      = trim($_GET['api_name']    ?? '');
$filter_status   = trim($_GET['status']      ?? '');
$filter_from     = trim($_GET['date_from']   ?? '');
$filter_to       = trim($_GET['date_to']     ?? '');
$page            = max(1, (int)($_GET['page'] ?? 1));
$per_page        = 25;
$offset          = ($page - 1) * $per_page;

// Build WHERE clause
$where   = ['1=1'];
$params  = [];
$types   = '';

if ($filter_partner) { $where[] = 'l.partner_id = ?'; $params[] = $filter_partner; $types .= 'i'; }
if ($filter_api)     { $where[] = 'l.api_name = ?';   $params[] = $filter_api;     $types .= 's'; }
if ($filter_status)  { $where[] = 'l.status = ?';     $params[] = $filter_status;  $types .= 's'; }
if ($filter_from)    { $where[] = 'DATE(l.created_at) >= ?'; $params[] = $filter_from; $types .= 's'; }
if ($filter_to)      { $where[] = 'DATE(l.created_at) <= ?'; $params[] = $filter_to;   $types .= 's'; }

$where_sql = implode(' AND ', $where);

// Total count
$count_sql = "SELECT COUNT(*) AS total FROM partner_api_logs l WHERE $where_sql";
$count_stmt = mysqli_prepare($conn, $count_sql);
if ($params) { mysqli_stmt_bind_param($count_stmt, $types, ...$params); }
mysqli_stmt_execute($count_stmt);
$total_rows = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['total'];
mysqli_stmt_close($count_stmt);
$total_pages = max(1, ceil($total_rows / $per_page));

// Fetch logs
$logs_sql = "SELECT l.*, p.partner_name FROM partner_api_logs l
             LEFT JOIN partners p ON p.id = l.partner_id
             WHERE $where_sql ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$log_params = array_merge($params, [$per_page, $offset]);
$log_types  = $types . 'ii';
$ls = mysqli_prepare($conn, $logs_sql);
if ($log_params) { mysqli_stmt_bind_param($ls, $log_types, ...$log_params); }
mysqli_stmt_execute($ls);
$logs_result = mysqli_stmt_get_result($ls);
$logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) $logs[] = $row;
mysqli_stmt_close($ls);

// Get all partners for filter dropdown
$partners_res = mysqli_query($conn, "SELECT id, partner_name FROM partners ORDER BY partner_name");
$all_partners = [];
while ($row = mysqli_fetch_assoc($partners_res)) $all_partners[] = $row;

$api_names = ['search-cab','get-fare','book-cab','booking-status','cancel-booking','driver-details','trip-details'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Logs — Agni Car Rental</title>
    <link rel="icon" type="image/png" href="../images/pnglogoagni.png">
    <link rel="stylesheet" href="../css/Dashboard_styles.css">
    <link rel="stylesheet" href="../css/partner_styles.css?v=1.3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo"></div>
    <h1 class="dashboard-heading">API Logs</h1>
    <div class="center-nav">
        <a href="../dashboard.php" class="home-btn"><i class="fas fa-home me-2"></i> Home</a>
    </div>
    <div class="right-nav">
        <form action="../logout.php" method="POST" class="logout-form d-inline">
            <button type="submit" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</button>
        </form>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
</nav>

<div class="container-fluid separator"></div>

<div class="dashboard-container">
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
            <li><a href="monitor.php" id="partner_monitor"><i class="fas fa-desktop me-2"></i> Partner Monitor</a></li>
            <li><a href="../car_categories.php" id="car_categories_menu"><i class="fas fa-tags me-2"></i> Car Categories</a></li>
            <li><a href="../discount_management.php" id="discount_management_menu"><i class="fas fa-percent me-2"></i> Discount Management</a></li>
        </ul>
    </nav>
    <main class="content" style="padding: 0;">
        <div class="partner-page" style="padding-top: 20px;">
            <div class="partner-header">
                <div>
                    <h2><i class="fas fa-history me-2"></i>API Request Logs</h2>
                    <div class="breadcrumb-text">Showing <?= number_format($total_rows) ?> total log entries</div>
                </div>
                <div>
                    <a href="index.php" class="btn-partner-info"><i class="fas fa-arrow-left me-1"></i> Back to Partners</a>
                </div>
            </div>

    <!-- Filter Bar -->
    <form method="GET" class="filter-bar">
        <div class="form-group">
            <label>Partner</label>
            <select name="partner_id">
                <option value="">All Partners</option>
                <?php foreach ($all_partners as $pt): ?>
                    <option value="<?= $pt['id'] ?>" <?= $filter_partner == $pt['id'] ? 'selected' : '' ?>><?= htmlspecialchars($pt['partner_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>API Name</label>
            <select name="api_name">
                <option value="">All APIs</option>
                <?php foreach ($api_names as $an): ?>
                    <option value="<?= $an ?>" <?= $filter_api === $an ? 'selected' : '' ?>><?= $an ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="success"      <?= $filter_status==='success'      ?'selected':'' ?>>Success</option>
                <option value="error"        <?= $filter_status==='error'        ?'selected':'' ?>>Error</option>
                <option value="blocked"      <?= $filter_status==='blocked'      ?'selected':'' ?>>Blocked</option>
                <option value="rate_limited" <?= $filter_status==='rate_limited' ?'selected':'' ?>>Rate Limited</option>
            </select>
        </div>
        <div class="form-group">
            <label>From Date</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filter_from) ?>">
        </div>
        <div class="form-group">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filter_to) ?>">
        </div>
        <button type="submit" class="btn-partner-primary" style="margin-top:auto;"><i class="fas fa-filter me-1"></i>Filter</button>
        <a href="logs.php" class="btn-partner-info" style="margin-top:auto;"><i class="fas fa-times me-1"></i>Clear</a>
    </form>

    <!-- Logs Table -->
    <div class="partner-card">
        <?php if (empty($logs)): ?>
            <p style="text-align:center;color:#666;padding:40px;"><i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:12px;opacity:0.3;"></i>No logs found for selected filters.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="partner-table" style="font-size:0.82rem;">
                <thead>
                    <tr><th>#</th><th>Partner</th><th>API</th><th>Method</th><th>IP Address</th><th>Status</th><th>Date & Time</th><th>Detail</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $i => $log): ?>
                    <?php
                    $cls = match($log['status']) {
                        'success'      => 'badge-success',
                        'error'        => 'badge-error',
                        'rate_limited' => 'badge-rate',
                        'blocked'      => 'badge-blocked-log',
                        default        => 'badge-rate'
                    };
                    ?>
                    <tr>
                        <td><?= $offset + $i + 1 ?></td>
                        <td style="font-weight:600;"><?= htmlspecialchars($log['partner_name'] ?? 'Unknown') ?></td>
                        <td><code style="color:#0056b3;background:none;padding:0;"><?= htmlspecialchars($log['api_name']) ?></code></td>
                        <td><?= htmlspecialchars($log['method']) ?></td>
                        <td style="font-family:monospace;"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td><span class="<?= $cls ?>"><?= ucfirst(str_replace('_',' ',$log['status'])) ?></span></td>
                        <td><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td>
                            <button class="btn-partner-info" style="font-size:0.75rem;padding:4px 10px;" onclick="showLog(<?= $log['id'] ?>,'<?= addslashes(htmlspecialchars($log['request_data'] ?? '{}')) ?>','<?= addslashes(htmlspecialchars($log['response_data'] ?? '{}')) ?>')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="partner-pagination mt-3">
            <?php
            $base_url = '?' . http_build_query(array_filter([
                'partner_id' => $filter_partner,
                'api_name'   => $filter_api,
                'status'     => $filter_status,
                'date_from'  => $filter_from,
                'date_to'    => $filter_to,
            ]));
            for ($pg = 1; $pg <= $total_pages; $pg++):
                $active = $pg === $page ? 'active' : '';
            ?>
                <a href="<?= $base_url ?>&page=<?= $pg ?>" class="page-btn <?= $active ?>"><?= $pg ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    </div>
</div>
    </main>
</div>

<!-- Log Detail Modal -->
<div id="logModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9999;overflow-y:auto;padding:30px;">
    <div style="max-width:700px;margin:auto;background:#16213e;border:1px solid rgba(108,99,255,0.3);border-radius:16px;padding:24px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h5 style="color:#fff;margin:0;"><i class="fas fa-code me-2" style="color:#6C63FF"></i>Log Detail</h5>
            <button onclick="document.getElementById('logModal').style.display='none'" style="background:none;border:none;color:#888;font-size:1.4rem;cursor:pointer;">&times;</button>
        </div>
        <div>
            <label style="color:#888;font-size:0.75rem;text-transform:uppercase;">Request Data</label>
            <pre id="logReq" style="background:#0f0f23;border-radius:8px;padding:14px;font-size:0.8rem;color:#4ECDC4;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;"></pre>
            <label style="color:#888;font-size:0.75rem;text-transform:uppercase;margin-top:12px;">Response Data</label>
            <pre id="logRes" style="background:#0f0f23;border-radius:8px;padding:14px;font-size:0.8rem;color:#00D68F;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;"></pre>
        </div>
    </div>
</div>

<script>
function showLog(id, req, res) {
    try { document.getElementById('logReq').textContent = JSON.stringify(JSON.parse(req), null, 2); } catch(e) { document.getElementById('logReq').textContent = req; }
    try { document.getElementById('logRes').textContent = JSON.stringify(JSON.parse(res), null, 2); } catch(e) { document.getElementById('logRes').textContent = res; }
    document.getElementById('logModal').style.display = 'block';
}

// Hamburger menu toggle
$(document).ready(function() {
    const hamburger = $('#hamburger');
    const sidebar = $('#sidebar');
    hamburger.on('click', () => {
        sidebar.toggleClass('active');
        hamburger.attr('aria-expanded', sidebar.hasClass('active'));
    });
    $(document).on('click', (e) => {
        if (!sidebar.is(e.target) && !sidebar.has(e.target).length && !hamburger.is(e.target) && !hamburger.has(e.target).length) {
            sidebar.removeClass('active');
            hamburger.attr('aria-expanded', 'false');
        }
    });
});
</script>
</body>
</html>
