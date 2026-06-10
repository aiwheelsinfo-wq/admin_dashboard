<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../adminlogin.php"); exit(); }
require_once __DIR__ . '/../db_connect.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

// Fetch partner
$stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$p) { header("Location: index.php"); exit(); }

// Stats
$stats_sql = "SELECT
    COUNT(*) AS total,
    SUM(status = 'success')      AS success_count,
    SUM(status = 'error')        AS error_count,
    SUM(status = 'blocked')      AS blocked_count,
    SUM(status = 'rate_limited') AS rate_count,
    SUM(DATE(created_at) = CURDATE()) AS today_count
    FROM partner_api_logs WHERE partner_id = ?";
$ss = mysqli_prepare($conn, $stats_sql);
mysqli_stmt_bind_param($ss, 'i', $id);
mysqli_stmt_execute($ss);
$stats = mysqli_fetch_assoc(mysqli_stmt_get_result($ss));
mysqli_stmt_close($ss);

// Active/cancelled bookings
$bk = mysqli_prepare($conn, "SELECT SUM(status NOT IN ('cancelled','partner_deleted')) AS active_bk, SUM(status='cancelled') AS cancelled_bk FROM partner_bookings WHERE partner_id=?");
mysqli_stmt_bind_param($bk, 'i', $id);
mysqli_stmt_execute($bk);
$bk_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($bk));
mysqli_stmt_close($bk);

// Recent logs (last 20)
$logs_stmt = mysqli_prepare($conn, "SELECT * FROM partner_api_logs WHERE partner_id = ? ORDER BY created_at DESC LIMIT 20");
mysqli_stmt_bind_param($logs_stmt, 'i', $id);
mysqli_stmt_execute($logs_stmt);
$logs_result = mysqli_stmt_get_result($logs_stmt);
$logs = [];
while ($row = mysqli_fetch_assoc($logs_result)) $logs[] = $row;
mysqli_stmt_close($logs_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['partner_name']) ?> — Partner Detail</title>
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
    <h1 class="dashboard-heading"><?= htmlspecialchars($p['partner_name']) ?></h1>
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
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 style="margin: 0; color: #fff;"><i class="fas fa-info-circle me-2"></i> Partner Overview</h3>
                <div class="d-flex gap-2">
                    <a href="edit.php?id=<?= $id ?>" class="btn-partner-warning"><i class="fas fa-edit me-1"></i> Edit</a>
                    <a href="index.php" class="btn-partner-info"><i class="fas fa-arrow-left me-1"></i> Back</a>
                </div>
            </div>

    <!-- Stats Dashboard -->
    <div class="partner-stats">
        <div class="stat-card">
            <span class="stat-icon">📊</span>
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-label">Total Requests</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">✅</span>
            <div class="stat-value"><?= number_format($stats['success_count']) ?></div>
            <div class="stat-label">Successful</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">❌</span>
            <div class="stat-value"><?= number_format($stats['error_count']) ?></div>
            <div class="stat-label">Failed</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">📅</span>
            <div class="stat-value"><?= number_format($stats['today_count']) ?></div>
            <div class="stat-label">Today</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🚕</span>
            <div class="stat-value"><?= number_format($bk_stats['active_bk'] ?? 0) ?></div>
            <div class="stat-label">Active Bookings</div>
        </div>
        <div class="stat-card">
            <span class="stat-icon">🚫</span>
            <div class="stat-value"><?= number_format($bk_stats['cancelled_bk'] ?? 0) ?></div>
            <div class="stat-label">Cancelled</div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Partner Info -->
        <div class="col-lg-5">
            <div class="partner-card h-100">
                <div class="partner-card-header">
                    <h4><i class="fas fa-building me-2" style="color:#6C63FF"></i>Partner Details</h4>
                    <span class="badge-<?= $p['status'] === 'active' ? 'active' : 'blocked' ?>" id="status-badge">
                        <?= ucfirst($p['status']) ?>
                    </span>
                </div>
                <div class="info-grid">
                    <div class="info-item"><label>Partner Name</label><span><?= htmlspecialchars($p['partner_name']) ?></span></div>
                    <div class="info-item"><label>Company</label><span><?= htmlspecialchars($p['company_name']) ?></span></div>
                    <div class="info-item"><label>Company Owner</label><span><?= htmlspecialchars($p['company_owner_name'] ?? 'N/A') ?></span></div>
                    <div class="info-item"><label>Contact Person</label><span><?= htmlspecialchars($p['contact_person']) ?></span></div>
                    <div class="info-item"><label>Mobile</label><span><?= htmlspecialchars($p['mobile_number']) ?></span></div>
                    <div class="info-item"><label>Email</label><span><?= htmlspecialchars($p['email']) ?></span></div>
                    <div class="info-item"><label>GST Number</label><span><code style="font-size:0.85rem; background:rgba(0,0,0,0.15); padding:2px 6px; border-radius:4px; color:#0056b3;"><?= htmlspecialchars($p['gst_number'] ?? 'N/A') ?></code></span></div>
                    <div class="info-item"><label>Rate Limit</label><span><?= $p['rate_limit_per_minute'] ?>/min · <?= number_format($p['rate_limit_per_day']) ?>/day</span></div>
                    <div class="info-item"><label>Member Since</label><span><?= date('d M Y', strtotime($p['created_at'])) ?></span></div>
                </div>

                <?php if ($p['notes']): ?>
                <div style="margin-top:16px;background:rgba(255,255,255,0.04);border-radius:8px;padding:12px;font-size:0.85rem;color:#aaa;">
                    <strong style="color:#888;">Notes:</strong> <?= htmlspecialchars($p['notes']) ?>
                </div>
                <?php endif; ?>

                <!-- Block/Unblock -->
                <div class="mt-4 d-flex gap-2">
                    <button id="toggleStatusBtn"
                        class="<?= $p['status'] === 'active' ? 'btn-partner-danger' : 'btn-partner-success' ?>"
                        data-id="<?= $id ?>" data-status="<?= $p['status'] ?>">
                        <i class="fas <?= $p['status'] === 'active' ? 'fa-ban' : 'fa-check-circle' ?> me-1"></i>
                        <?= $p['status'] === 'active' ? 'Block Partner' : 'Unblock Partner' ?>
                    </button>
                    <a href="edit.php?id=<?= $id ?>" class="btn-partner-warning">
                        <i class="fas fa-edit me-1"></i>Edit
                    </a>
                </div>
            </div>
        </div>

        <!-- API Keys -->
        <div class="col-lg-7">
            <div class="partner-card">
                <div class="partner-card-header">
                    <h4><i class="fas fa-key me-2" style="color:#FFAA00"></i>API Credentials</h4>
                </div>
                <?php if ($p['status'] === 'pending'): ?>
                    <div style="background:rgba(255,152,0,0.08); border:1px dashed rgba(255,152,0,0.3); border-radius:8px; padding:16px; color:#ffb74d; font-size:0.9rem; margin-bottom:15px; display:flex; align-items:center; gap:8px;">
                        <i class="fas fa-hourglass-half"></i>
                        <span>This partner request is pending approval. API keys have not been generated yet.</span>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn-partner-success btn-approve" data-id="<?= $id ?>" data-company="<?= htmlspecialchars($p['company_name']) ?>">
                            <i class="fas fa-check-circle me-1"></i> Approve & Generate Keys
                        </button>
                    </div>
                <?php else: ?>
                    <label style="color:#888;font-size:0.75rem;text-transform:uppercase;">API Key</label>
                    <div class="api-key-display">
                        <code id="api-key-display"><?= htmlspecialchars($p['api_key']) ?></code>
                        <button class="copy-btn" onclick="copyText('api-key-display',this)"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                    <label style="color:#888;font-size:0.75rem;text-transform:uppercase;margin-top:12px;">Secret Key</label>
                    <div class="api-key-display">
                        <code id="secret-display">••••••••••••••••••••••••••••••••••••••••</code>
                    </div>
                    <div class="mt-3 d-flex gap-2 flex-wrap">
                        <a href="edit.php?id=<?= $id ?>" class="btn-partner-warning btn-sm"><i class="fas fa-sync me-1"></i>Regenerate Keys</a>
                        <a href="logs.php?partner_id=<?= $id ?>" class="btn-partner-info"><i class="fas fa-list me-1"></i>View Logs</a>
                    </div>
                <?php endif; ?>

                <!-- API Endpoints Reference -->
                <div style="margin-top:20px;background:#f8f9fa;border:1px solid var(--partner-border);border-radius:10px;padding:16px;">
                    <div style="font-size:0.78rem;color:var(--partner-muted);margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px;">API Endpoints</div>
                    <?php
                    $endpoints = [
                        ['POST','search-cab','Search available cabs'],
                        ['POST','get-fare','Get fare estimate'],
                        ['POST','book-cab','Create booking'],
                        ['GET', 'booking-status','Track booking status'],
                        ['POST','cancel-booking','Cancel booking'],
                        ['GET', 'driver-details','Get driver info'],
                        ['GET', 'trip-details','Full trip details'],
                    ];
                    foreach ($endpoints as [$method, $ep, $desc]):
                    ?>
                    <div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid var(--partner-border);">
                        <span style="background:rgba(0,123,255,0.15);color:#0056b3;border-radius:4px;padding:2px 8px;font-size:0.7rem;font-weight:700;width:38px;text-align:center;"><?= $method ?></span>
                        <code style="color:#0056b3;font-size:0.8rem;flex:1;background:none;padding:0;">/partner/api/<?= $ep ?>.php</code>
                        <span style="font-size:0.75rem;color:#555;"><?= $desc ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent API Logs -->
    <div class="partner-card mt-3">
        <div class="partner-card-header">
            <h4><i class="fas fa-history me-2" style="color:#4ECDC4"></i>Recent API Logs (Last 20)</h4>
            <a href="logs.php?partner_id=<?= $id ?>" class="btn-partner-info" style="font-size:0.8rem;">View All Logs</a>
        </div>
        <?php if (empty($logs)): ?>
            <p style="text-align:center;color:#666;padding:30px;">No API calls logged yet.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="partner-table">
                <thead>
                    <tr><th>#</th><th>API</th><th>Method</th><th>IP Address</th><th>Status</th><th>Date & Time</th></tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $i => $log): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><code style="color:#0056b3;background:none;padding:0;font-size:0.82rem;"><?= htmlspecialchars($log['api_name']) ?></code></td>
                        <td><?= htmlspecialchars($log['method']) ?></td>
                        <td style="font-family:monospace;font-size:0.8rem;"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td>
                            <?php
                            $cls = match($log['status']) {
                                'success'      => 'badge-success',
                                'error'        => 'badge-error',
                                'rate_limited' => 'badge-rate',
                                'blocked'      => 'badge-blocked-log',
                                default        => 'badge-rate'
                            };
                            ?>
                            <span class="<?= $cls ?>"><?= ucfirst(str_replace('_',' ',$log['status'])) ?></span>
                        </td>
                        <td style="font-size:0.8rem;"><?= date('d M H:i:s', strtotime($log['created_at'])) ?></td>
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
<div class="partner-toast" id="toast"></div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#1a1a2e; color:#fff; border: 2px solid #00D68F; border-radius:16px; padding: 10px;">
      <div class="modal-header" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
        <h5 class="modal-title" id="approvalModalLabel" style="color:#00D68F; font-weight:700;"><i class="fas fa-key me-2"></i>API Credentials Generated</h5>
      </div>
      <div class="modal-body">
        <p style="color:#ff9800; font-size:0.85rem; margin-bottom:16px;">⚠️ Copy these keys now! The secret key will not be shown again in full.</p>
        
        <div class="mb-3">
          <label style="color:#888; font-size:0.75rem; text-transform:uppercase; font-weight:600;">API Key</label>
          <div class="api-key-display" style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.3); padding:10px 14px; border-radius:8px; margin-top:4px;">
            <code id="modal-api-key" style="color:#00D68F; font-size:0.85rem; word-break:break-all;"></code>
            <button class="copy-btn" onclick="copyText('modal-api-key', this)" style="background:#6c63ff; color:#fff; border:none; padding:4px 10px; border-radius:6px; font-size:0.8rem; cursor:pointer;"><i class="fas fa-copy"></i> Copy</button>
          </div>
        </div>

        <div class="mb-3">
          <label style="color:#888; font-size:0.75rem; text-transform:uppercase; font-weight:600;">Secret Key</label>
          <div class="api-key-display" style="display:flex; justify-content:space-between; align-items:center; background:rgba(0,0,0,0.3); padding:10px 14px; border-radius:8px; margin-top:4px;">
            <code id="modal-secret-key" style="color:#00D68F; font-size:0.85rem; word-break:break-all;"></code>
            <button class="copy-btn" onclick="copyText('modal-secret-key', this)" style="background:#6c63ff; color:#fff; border:none; padding:4px 10px; border-radius:6px; font-size:0.8rem; cursor:pointer;"><i class="fas fa-copy"></i> Copy</button>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top: 1px solid rgba(255,255,255,0.1);">
        <button type="button" class="btn-partner-primary" onclick="window.location.reload();" style="border-radius:8px; font-size:0.9rem; padding:8px 16px;">Done & Reload</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Approve Request AJAX
$(document).on('click', '.btn-approve', function() {
    const id = $(this).data('id');
    const company = $(this).data('company');
    
    if (!confirm(`Are you sure you want to approve "${company}" and generate API credentials?`)) return;
    
    $.ajax({
        url: 'approve_partner.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ partner_id: id }),
        success(res) {
            if (res.status) {
                $('#modal-api-key').text(res.api_key);
                $('#modal-secret-key').text(res.secret_key);
                
                const modal = new bootstrap.Modal(document.getElementById('approvalModal'));
                modal.show();
            } else {
                showToast('❌ ' + res.message, 'error');
            }
        },
        error() {
            showToast('❌ Request failed', 'error');
        }
    });
});

function copyText(id, btn) {
    navigator.clipboard.writeText(document.getElementById(id).textContent).then(() => {
        btn.innerHTML='<i class="fas fa-check"></i> Copied!'; btn.classList.add('copied');
        setTimeout(()=>{btn.innerHTML='<i class="fas fa-copy"></i> Copy';btn.classList.remove('copied');},2000);
    });
}
function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.className='partner-toast toast-'+type+' show'; t.innerHTML=msg;
    setTimeout(()=>t.classList.remove('show'),3500);
}
$('#toggleStatusBtn').on('click', function() {
    const $btn = $(this), id = $btn.data('id'), cur = $btn.data('status');
    const action = cur==='active'?'block':'unblock';
    if (!confirm(`${action === 'block' ? 'Block' : 'Unblock'} this partner?`)) return;
    $.ajax({url:'toggle_status.php',method:'POST',contentType:'application/json',
        data: JSON.stringify({partner_id:id, action}),
        success(res) {
            if (res.status) {
                const isBlocked = res.new_status==='blocked';
                $btn.data('status',res.new_status)
                    .attr('class', isBlocked?'btn-partner-success':'btn-partner-danger')
                    .html(`<i class="fas ${isBlocked?'fa-check-circle':'fa-ban'} me-1"></i>${isBlocked?'Unblock Partner':'Block Partner'}`);
                $('#status-badge').attr('class',isBlocked?'badge-blocked':'badge-active').text(isBlocked?'Blocked':'Active');
                showToast('✅ '+res.message,'success');
            } else { showToast('❌ '+res.message,'error'); }
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
