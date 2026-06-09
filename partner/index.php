<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../adminlogin.php");
    exit();
}
require_once __DIR__ . '/../db_connect.php';

// Fetch all partners with usage stats
$partners_sql = "
    SELECT p.*,
        (SELECT COUNT(*) FROM partner_api_logs l WHERE l.partner_id = p.id) AS total_requests,
        (SELECT COUNT(*) FROM partner_api_logs l WHERE l.partner_id = p.id AND l.status = 'success') AS success_requests,
        (SELECT COUNT(*) FROM partner_api_logs l WHERE l.partner_id = p.id AND DATE(l.created_at) = CURDATE()) AS today_requests
    FROM partners p
    ORDER BY p.created_at DESC
";
$result   = mysqli_query($conn, $partners_sql);
$partners = [];
while ($row = mysqli_fetch_assoc($result)) {
    $partners[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner API Management — Agni Car Rental</title>
    <link rel="icon" type="image/png" href="../images/pnglogoagni.png">
    <link rel="stylesheet" href="../css/Dashboard_styles.css">
    <link rel="stylesheet" href="../css/partner_styles.css?v=1.3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo"></div>
    <h1 class="dashboard-heading">Partner API Management</h1>
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
        </ul>
    </nav>
    <main class="content" style="padding: 0;">
        <div class="partner-page" style="padding-top: 20px;">

    <!-- Header -->
    <div class="partner-header">
        <div>
            <h2><i class="fas fa-handshake me-2"></i>Partner API Management</h2>
            <div class="breadcrumb-text">Manage partner access, API keys, and usage statistics</div>
        </div>
        <div class="d-flex gap-2">
            <a href="logs.php" class="btn-partner-info"><i class="fas fa-list me-1"></i>API Logs</a>
            <a href="add.php"  class="btn-partner-primary"><i class="fas fa-plus me-1"></i>Add Partner</a>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="partner-stats">
        <div class="stat-card">
            <span class="stat-icon">🤝</span>
            <div class="stat-value"><?= count($partners) ?></div>
            <div class="stat-label">Total Partners</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-value"><?= count(array_filter($partners, fn($p) => $p['status'] === 'active')) ?></div>
            <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🚫</span>
            <div class="stat-value"><?= count(array_filter($partners, fn($p) => $p['status'] === 'blocked')) ?></div>
            <div class="stat-label">Blocked</div>
        </div>
        <?php
        $total_req = array_sum(array_column($partners, 'total_requests'));
        $today_req = array_sum(array_column($partners, 'today_requests'));
        ?>
        <div class="stat-card">
            <span class="stat-icon">📊</span>
            <div class="stat-value"><?= number_format($total_req) ?></div>
            <div class="stat-label">Total API Calls</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-value"><?= number_format($today_req) ?></div>
            <div class="stat-label">Today's Calls</div>
        </div>
    </div>

    <!-- Partners Table -->
    <div class="partner-card">
        <div class="partner-card-header">
            <h4><i class="fas fa-users-cog me-2" style="color:#6C63FF"></i>All Partners</h4>
            <input type="text" id="searchInput" class="form-control" style="max-width:250px;background:#fff;border:1px solid var(--partner-border);color:#333;border-radius:8px;" placeholder="🔍 Search partner...">
        </div>

        <?php if (empty($partners)): ?>
            <div style="text-align:center;padding:60px;color:#888;">
                <i class="fas fa-handshake" style="font-size:3rem;margin-bottom:16px;display:block;opacity:0.3;"></i>
                No partners yet. <a href="add.php" style="color:#6C63FF;">Add your first partner →</a>
            </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="partner-table" id="partnersTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Partner</th>
                        <th>Contact</th>
                        <th>API Key</th>
                        <th>Status</th>
                        <th>Requests</th>
                        <th>Today</th>
                        <th>Rate Limit</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($partners as $i => $p): ?>
                    <tr id="row-<?= $p['id'] ?>">
                        <td><?= $i + 1 ?></td>
                        <td>
                            <div style="font-weight:600;color:#333;"><?= htmlspecialchars($p['partner_name']) ?></div>
                            <div style="font-size:0.78rem;color:#666;"><?= htmlspecialchars($p['company_name']) ?></div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($p['contact_person']) ?></div>
                            <div style="font-size:0.78rem;color:#888;"><?= htmlspecialchars($p['mobile_number']) ?></div>
                        </td>
                        <td>
                            <code style="font-size:0.75rem;color:#0056b3;background:#f1f3f5;padding:3px 8px;border-radius:5px;">
                                <?= substr(htmlspecialchars($p['api_key']), 0, 18) ?>...
                            </code>
                        </td>
                        <td>
                            <span class="badge-<?= $p['status'] === 'active' ? 'active' : 'blocked' ?>" id="badge-<?= $p['id'] ?>">
                                <?= ucfirst($p['status']) ?>
                            </span>
                        </td>
                        <td><?= number_format($p['total_requests']) ?></td>
                        <td><?= number_format($p['today_requests']) ?></td>
                        <td style="font-size:0.78rem;color:#888;">
                            <?= $p['rate_limit_per_minute'] ?>/min<br>
                            <?= number_format($p['rate_limit_per_day']) ?>/day
                        </td>
                        <td style="font-size:0.78rem;"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        <td>
                            <div class="d-flex gap-1 flex-wrap">
                                <a href="view.php?id=<?= $p['id'] ?>" class="btn-partner-info" title="View"><i class="fas fa-eye"></i></a>
                                <a href="edit.php?id=<?= $p['id'] ?>" class="btn-partner-warning" title="Edit"><i class="fas fa-edit"></i></a>
                                <button class="btn-toggle-status <?= $p['status'] === 'active' ? 'btn-partner-danger' : 'btn-partner-success' ?>"
                                    data-id="<?= $p['id'] ?>"
                                    data-status="<?= $p['status'] ?>"
                                    title="<?= $p['status'] === 'active' ? 'Block' : 'Unblock' ?>">
                                    <i class="fas <?= $p['status'] === 'active' ? 'fa-ban' : 'fa-check-circle' ?>"></i>
                                </button>
                                <button class="btn-partner-danger btn-delete" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['partner_name']) ?>" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
    </main>
</div>

<!-- Toast -->
<div class="partner-toast" id="toast"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Search filter
$('#searchInput').on('input', function() {
    const q = $(this).val().toLowerCase();
    $('#partnersTable tbody tr').each(function() {
        $(this).toggle($(this).text().toLowerCase().includes(q));
    });
});

// Toast helper
function showToast(msg, type = 'info') {
    const t = $('#toast');
    t.removeClass('toast-success toast-error toast-info show').addClass('toast-' + type + ' show').html(msg);
    setTimeout(() => t.removeClass('show'), 3500);
}

// Block/Unblock
$(document).on('click', '.btn-toggle-status', function() {
    const $btn = $(this);
    const id   = $btn.data('id');
    const cur  = $btn.data('status');
    const action = cur === 'active' ? 'block' : 'unblock';

    if (!confirm(`Are you sure you want to ${action} this partner?`)) return;

    $.ajax({
        url: 'toggle_status.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ partner_id: id, action }),
        success(res) {
            if (res.status) {
                const isBlocked = res.new_status === 'blocked';
                $(`#badge-${id}`).attr('class', isBlocked ? 'badge-blocked' : 'badge-active').text(isBlocked ? 'Blocked' : 'Active');
                $btn.data('status', res.new_status)
                    .attr('class', isBlocked ? 'btn-partner-success btn-toggle-status' : 'btn-partner-danger btn-toggle-status')
                    .attr('title', isBlocked ? 'Unblock' : 'Block')
                    .html(`<i class="fas ${isBlocked ? 'fa-check-circle' : 'fa-ban'}"></i>`);
                showToast('✅ ' + res.message, 'success');
            } else {
                showToast('❌ ' + res.message, 'error');
            }
        },
        error() { showToast('❌ Request failed', 'error'); }
    });
});

// Delete
$(document).on('click', '.btn-delete', function() {
    const id   = $(this).data('id');
    const name = $(this).data('name');
    if (!confirm(`Delete "${name}"? This will remove all API logs and cannot be undone.`)) return;
    $.ajax({
        url: 'delete.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ partner_id: id }),
        success(res) {
            if (res.status) {
                $(`#row-${id}`).fadeOut(400, function() { $(this).remove(); });
                showToast('🗑️ Partner deleted', 'success');
            } else {
                showToast('❌ ' + res.message, 'error');
            }
        }
    });
});

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
