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

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner_name   = trim($_POST['partner_name']           ?? '');
    $company_name   = trim($_POST['company_name']           ?? '');
    $contact_person = trim($_POST['contact_person']         ?? '');
    $mobile         = trim($_POST['mobile_number']          ?? '');
    $email          = trim($_POST['email']                  ?? '');
    $rate_min       = (int)($_POST['rate_limit_per_minute'] ?? 60);
    $rate_day       = (int)($_POST['rate_limit_per_day']    ?? 10000);
    $notes          = trim($_POST['notes']                  ?? '');

    if (!$partner_name || !$company_name || !$contact_person || !$mobile || !$email) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        try {
            $upd = mysqli_prepare($conn, "UPDATE partners SET partner_name=?, company_name=?, contact_person=?, mobile_number=?, email=?, rate_limit_per_minute=?, rate_limit_per_day=?, notes=? WHERE id=?");
            mysqli_stmt_bind_param($upd, 'sssssiisi', $partner_name, $company_name, $contact_person, $mobile, $email, $rate_min, $rate_day, $notes, $id);
            if (mysqli_stmt_execute($upd)) {
                $success = 'Partner updated successfully!';
                // Refresh
                $stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, 'i', $id);
                mysqli_stmt_execute($stmt);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
            } else {
                $error = 'Update failed: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($upd);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = 'Error: A partner with this email address already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Partner — Rentox Vendor & Driver</title>
    <link rel="icon" type="image/png" href="../images/pnglogoagni.png">
    <link rel="stylesheet" href="../css/Dashboard_styles.css?v=2.0">
    <link rel="stylesheet" href="../css/partner_styles.css?v=1.3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<!-- Top Nav -->
<nav class="top-nav">
    <div class="logo-container"><img src="../images/logo_rentox.png" alt="Logo" class="logo"></div>
    <h1 class="dashboard-heading">Edit Partner</h1>
    <div class="right-nav">
        <a href="../dashboard.php" class="home-btn"><i class="fas fa-home me-2"></i> Home</a>
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
            <li class="mt-4 pt-3 border-top" style="border-color: rgba(255, 255, 255, 0.15) !important;">
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="color: #ff4b2b; font-weight: 600;">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
                <form id="logout-form" action="../logout.php" method="POST" style="display: none;"></form>
            </li>
        </ul>
    </nav>
    <main class="content" style="padding: 0;">
        <div class="partner-page" style="padding-top: 20px;">
            <div style="max-width:760px;margin:0 auto;">
                <div class="partner-header">
                    <div>
                        <h2><i class="fas fa-edit me-2"></i>Edit: <?= htmlspecialchars($p['partner_name']) ?></h2>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="view.php?id=<?= $id ?>" class="btn-partner-info"><i class="fas fa-eye me-1"></i> View</a>
                        <a href="index.php" class="btn-partner-info"><i class="fas fa-arrow-left me-1"></i> Back</a>
                    </div>
                </div>

        <?php if ($error): ?><div class="alert alert-danger" style="border-radius:12px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" style="border-radius:12px;"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <!-- Key Management Section -->
        <div class="partner-card mb-3">
            <div class="partner-card-header">
                <h4><i class="fas fa-key me-2" style="color:#FFAA00"></i>API Credentials</h4>
            </div>
            <label style="color:#888;font-size:0.75rem;text-transform:uppercase;">Current API Key</label>
            <div class="api-key-display">
                <code id="api-key-display"><?= htmlspecialchars($p['api_key']) ?></code>
                <button class="copy-btn" onclick="copyText('api-key-display', this)"><i class="fas fa-copy"></i> Copy</button>
                <button class="btn-partner-warning" id="regenApiKey" data-id="<?= $id ?>" style="font-size:0.78rem;padding:5px 12px;"><i class="fas fa-sync me-1"></i>Regenerate</button>
            </div>
            <label style="color:#888;font-size:0.75rem;text-transform:uppercase;margin-top:12px;">Current Secret Key</label>
            <div class="api-key-display">
                <code id="secret-key-display">••••••••••••••••••••••••••••••••••••••••••••••••</code>
                <button class="copy-btn" id="revealSecret"><i class="fas fa-eye"></i> Reveal</button>
                <button class="btn-partner-danger" id="regenSecretKey" data-id="<?= $id ?>" style="font-size:0.78rem;padding:5px 12px;"><i class="fas fa-sync me-1"></i>Regenerate</button>
            </div>
            <div id="keysMsg" style="margin-top:10px;font-size:0.85rem;color:#00D68F;display:none;"></div>
        </div>

        <!-- Edit Form -->
        <div class="partner-card">
            <form method="POST" class="partner-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Partner Name <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="partner_name" class="form-control" required value="<?= htmlspecialchars($p['partner_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Company Name <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="company_name" class="form-control" required value="<?= htmlspecialchars($p['company_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Contact Person <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="contact_person" class="form-control" required value="<?= htmlspecialchars($p['contact_person']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Mobile Number <span style="color:#FF3D71">*</span></label>
                        <input type="tel" name="mobile_number" class="form-control" required value="<?= htmlspecialchars($p['mobile_number']) ?>">
                    </div>
                    <div class="col-12">
                        <label>Email <span style="color:#FF3D71">*</span></label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($p['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Requests per Minute</label>
                        <input type="number" name="rate_limit_per_minute" class="form-control" value="<?= $p['rate_limit_per_minute'] ?>" min="1">
                    </div>
                    <div class="col-md-6">
                        <label>Requests per Day</label>
                        <input type="number" name="rate_limit_per_day" class="form-control" value="<?= $p['rate_limit_per_day'] ?>" min="1">
                    </div>
                    <div class="col-12">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($p['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-partner-primary" style="width:100%;justify-content:center;">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </form>
        </div>
        </div>
    </main>
</div>
<div class="partner-toast" id="toast"></div>
<script>
const PARTNER_ID = <?= $id ?>;

function copyText(id, btn) {
    const text = document.getElementById(id).textContent;
    if (text.includes('•')) { alert('Please reveal the key first.'); return; }
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
    });
}

function showToast(msg, type) {
    const t = document.getElementById('toast');
    t.className = 'partner-toast toast-' + type + ' show';
    t.innerHTML = msg;
    setTimeout(() => t.classList.remove('show'), 3500);
}

// Reveal secret key
$('#revealSecret').on('click', function() {
    $.post('generate_keys.php', JSON.stringify({partner_id: PARTNER_ID, type: 'reveal'}), 'json')
    // We can't re-show the key without re-fetching — just inform user to use regenerate
    .fail(() => {});
    showToast('ℹ️ For security, keys cannot be revealed. Use Regenerate to create a new one.', 'info');
});

// Regenerate API key
$('#regenApiKey').on('click', function() {
    if (!confirm('Regenerate API Key? The old key will stop working immediately.')) return;
    $.ajax({ url: 'generate_keys.php', method: 'POST', contentType:'application/json',
        data: JSON.stringify({partner_id: PARTNER_ID, type: 'api_key'}),
        success(res) {
            if (res.status) {
                document.getElementById('api-key-display').textContent = res.api_key;
                showToast('✅ New API Key generated!', 'success');
            } else { showToast('❌ ' + res.message, 'error'); }
        }
    });
});

// Regenerate Secret key
$('#regenSecretKey').on('click', function() {
    if (!confirm('Regenerate Secret Key? The old key will stop working immediately.')) return;
    $.ajax({ url: 'generate_keys.php', method: 'POST', contentType:'application/json',
        data: JSON.stringify({partner_id: PARTNER_ID, type: 'secret_key'}),
        success(res) {
            if (res.status) {
                document.getElementById('secret-key-display').textContent = res.secret_key;
                showToast('✅ New Secret Key generated! Copy it now.', 'success');
            } else { showToast('❌ ' + res.message, 'error'); }
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
