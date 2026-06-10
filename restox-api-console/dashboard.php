<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

// Auth Check (must be logged in as admin)
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Handle AJAX/POST request to add partner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_partner') {
    $company_name       = trim($_POST['company_name'] ?? '');
    $company_owner_name = trim($_POST['company_owner_name'] ?? '');
    $contact_person     = trim($_POST['contact_person'] ?? '');
    $contact_number     = trim($_POST['contact_number'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $gst_number         = trim($_POST['gst_number'] ?? '');

    if (!$company_name || !$company_owner_name || !$contact_person || !$contact_number || !$email || !$gst_number) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email already exists
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM partners WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 's', $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = 'A partner with this email address is already registered.';
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                $partner_name = $company_name;
                $notes = 'Registered via Redox API Service Console';

                $stmt = mysqli_prepare($conn, 
                    "INSERT INTO partners (partner_name, company_name, company_owner_name, contact_person, mobile_number, email, gst_number, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
                );
                
                mysqli_stmt_bind_param($stmt, 'ssssssss', 
                    $partner_name, $company_name, $company_owner_name, $contact_person, $contact_number, $email, $gst_number, $notes
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Partner request added successfully!';
                } else {
                    $error = 'Failed to add partner: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
    
    // Return JSON if AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode([
            'success' => empty($error),
            'message' => empty($error) ? $success : $error
        ]);
        exit;
    }
}

// Fetch all partners
$partners = [];
$result = mysqli_query($conn, "SELECT * FROM partners ORDER BY created_at DESC");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $partners[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Redox API Service</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --primary-accent: #6c63ff;
            --primary-glow: rgba(108, 99, 255, 0.25);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --error-color: #ef4444;
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(108, 99, 255, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
            color: var(--text-primary);
            min-height: 100vh;
        }

        /* Ambient background circles */
        .ambient-blur {
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            filter: blur(100px);
            z-index: -1;
            opacity: 0.5;
        }
        .blur-1 { top: 5%; left: 5%; background: var(--primary-accent); }
        .blur-2 { bottom: 5%; right: 5%; background: #10b981; }

        /* Top Nav */
        .top-nav {
            backdrop-filter: blur(12px);
            background-color: rgba(11, 15, 25, 0.85);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        @media (max-width: 576px) {
            .top-nav { padding: 16px 20px; }
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-icon {
            font-size: 1.8rem;
            color: var(--primary-accent);
            text-shadow: 0 0 10px var(--primary-glow);
        }

        .logo-text {
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .user-nav-badge {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .company-badge {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--card-border);
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 500;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-logout {
            background: transparent;
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 0.88rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--error-color);
            color: #fff;
        }

        /* Dashboard Container */
        .dashboard-container {
            width: 100%;
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 24px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .header-panel {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-title-sec h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }

        .header-title-sec p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin: 0;
        }

        .btn-primary-action {
            background: linear-gradient(135deg, var(--primary-accent) 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
        }

        .btn-primary-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.5);
            background: linear-gradient(135deg, #818cf8 0%, var(--primary-accent) 100%);
            color: #fff;
        }

        /* Search Section */
        .search-bar-card {
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 16px 24px;
            margin-bottom: 24px;
            backdrop-filter: blur(12px);
        }

        .search-input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .search-icon {
            position: absolute;
            left: 16px;
            color: var(--text-muted);
        }

        .search-control {
            background-color: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            color: #fff;
            padding: 10px 16px 10px 46px;
            font-size: 0.95rem;
            width: 100%;
            outline: none;
            transition: all 0.3s ease;
        }

        .search-control:focus {
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px var(--primary-glow);
            background-color: rgba(255, 255, 255, 0.06);
        }

        /* Table Design */
        .panel-card {
            backdrop-filter: blur(16px);
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow-x: auto;
        }

        .partner-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .partner-table th {
            color: #fff;
            font-weight: 600;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            background-color: rgba(0,0,0,0.15);
            text-align: left;
        }

        .partner-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }

        .partner-table tr:hover {
            background-color: rgba(255,255,255,0.01);
        }

        /* Badges */
        .badge-state {
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
        }

        .badge-state-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-state-active {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-state-blocked {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--error-color);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .credentials-wrapper {
            display: flex;
            flex-direction: column;
            gap: 6px;
            max-width: 320px;
        }

        .credential-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 4px 8px;
            border-radius: 6px;
            gap: 8px;
        }

        .credential-code {
            font-family: monospace;
            font-size: 0.8rem;
            color: #818cf8;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
        }

        .btn-mini-copy {
            background: transparent;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 0.82rem;
            padding: 2px 6px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .btn-mini-copy:hover {
            color: #fff;
            background-color: rgba(255,255,255,0.1);
        }

        /* Glassmorphic Modal */
        .modal-content-glass {
            background-color: #111827 !important;
            border: 1px solid var(--card-border) !important;
            border-radius: 20px !important;
            color: #fff !important;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5) !important;
        }

        .modal-header-glass {
            border-bottom: 1px solid rgba(255,255,255,0.05) !important;
            padding: 20px 24px !important;
        }

        .modal-footer-glass {
            border-top: 1px solid rgba(255,255,255,0.05) !important;
            padding: 16px 24px !important;
        }

        .form-label-glass {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .form-control-glass {
            background-color: rgba(255, 255, 255, 0.03) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            border-radius: 10px !important;
            color: #fff !important;
            padding: 10px 14px !important;
            font-size: 0.95rem !important;
        }

        .form-control-glass:focus {
            background-color: rgba(255, 255, 255, 0.05) !important;
            border-color: var(--primary-accent) !important;
            box-shadow: 0 0 0 3px var(--primary-glow) !important;
        }

        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--success-color);
            color: #fff;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 1050;
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <!-- Top Nav -->
    <header class="top-nav">
        <div class="logo-container">
            <i class="fa-solid fa-terminal logo-icon"></i>
            <span class="logo-text">REDOX API SERVICE</span>
        </div>
        <div class="user-nav-badge">
            <div class="company-badge">
                <i class="fa-solid fa-user-shield" style="color: var(--primary-accent);"></i>
                <span>Admin Console</span>
            </div>
            <a href="login.php?action=logout" class="btn-logout">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="dashboard-container">
        
        <!-- Header Section -->
        <div class="header-panel">
            <div class="header-title-sec">
                <h2>B2B API Partner Management</h2>
                <p>Register new integration partners, monitor status, and view access credentials.</p>
            </div>
            <button class="btn-primary-action" data-bs-toggle="modal" data-bs-target="#addPartnerModal">
                <i class="fa-solid fa-plus"></i> Add Partner
            </button>
        </div>

        <!-- Search Card -->
        <div class="search-bar-card">
            <div class="search-input-wrapper">
                <i class="fa-solid fa-magnifying-glass search-icon"></i>
                <input type="text" id="searchInput" class="search-control" placeholder="Search partner by company name, owner, contact person, or email...">
            </div>
        </div>

        <!-- Partners Table Panel -->
        <div class="panel-card">
            <?php if (empty($partners)): ?>
                <div style="text-align: center; padding: 60px 0; color: var(--text-muted);">
                    <i class="fa-solid fa-handshake" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                    <p>No integration partners registered yet. Click <strong>Add Partner</strong> to create one.</p>
                </div>
            <?php else: ?>
                <table class="partner-table" id="partnersTable">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Company Owner</th>
                            <th>Contact Person</th>
                            <th>Contact Phone</th>
                            <th>Business Email</th>
                            <th>GSTIN</th>
                            <th>Status</th>
                            <th>API Access Credentials</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $p): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($p['company_name']) ?></strong></td>
                                <td><?= htmlspecialchars($p['company_owner_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($p['contact_person']) ?></td>
                                <td><?= htmlspecialchars($p['mobile_number']) ?></td>
                                <td><?= htmlspecialchars($p['email']) ?></td>
                                <td><code style="background:rgba(0,0,0,0.15); padding:3px 6px; border-radius:4px; font-size:0.82rem; color:#818cf8;"><?= htmlspecialchars($p['gst_number'] ?? 'N/A') ?></code></td>
                                <td>
                                    <?php
                                    $statusClass = match($p['status']) {
                                        'pending' => 'badge-state-pending',
                                        'active'  => 'badge-state-active',
                                        'blocked' => 'badge-state-blocked',
                                        default   => 'badge-state-pending'
                                    };
                                    ?>
                                    <span class="badge-state <?= $statusClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($p['status'] !== 'active'): ?>
                                        <span style="font-size: 0.8rem; color: var(--text-muted); font-style: italic;">
                                            <i class="fa-solid fa-lock me-1"></i> Keys will display once approved
                                        </span>
                                    <?php else: ?>
                                        <div class="credentials-wrapper">
                                            <div class="credential-item">
                                                <code class="credential-code" id="key-<?= $p['id'] ?>" title="<?= htmlspecialchars($p['api_key']) ?>"><?= htmlspecialchars($p['api_key']) ?></code>
                                                <button class="btn-mini-copy" onclick="copyValue('key-<?= $p['id'] ?>', this)" title="Copy API Key"><i class="fa-solid fa-copy"></i></button>
                                            </div>
                                            <div class="credential-item">
                                                <code class="credential-code" id="secret-<?= $p['id'] ?>" title="<?= htmlspecialchars($p['secret_key']) ?>"><?= htmlspecialchars($p['secret_key']) ?></code>
                                                <button class="btn-mini-copy" onclick="copyValue('secret-<?= $p['id'] ?>', this)" title="Copy Secret Key"><i class="fa-solid fa-copy"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>

    <!-- Add Partner Bootstrap Modal -->
    <div class="modal fade" id="addPartnerModal" tabindex="-1" aria-labelledby="addPartnerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-glass">
                <form id="addPartnerForm">
                    <input type="hidden" name="action" value="add_partner">
                    <div class="modal-header modal-header-glass">
                        <h5 class="modal-title" id="addPartnerModalLabel" style="font-weight:700;"><i class="fa-solid fa-user-plus me-2" style="color:var(--primary-accent);"></i>Add Partner Request</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div id="modalError" class="alert alert-danger d-none" style="border-radius:10px; font-size:0.88rem;"></div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">Company Name</label>
                            <input type="text" name="company_name" class="form-control form-control-glass" placeholder="e.g. Akbar Travels" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">Company Owner Name</label>
                            <input type="text" name="company_owner_name" class="form-control form-control-glass" placeholder="Full name of owner" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control form-control-glass" placeholder="Primary contact name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">Contact Mobile Number</label>
                            <input type="tel" name="contact_number" class="form-control form-control-glass" placeholder="+91 XXXXXXXXXX" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">Business Email</label>
                            <input type="email" name="email" class="form-control form-control-glass" placeholder="api@company.com" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label-glass">GST Number</label>
                            <input type="text" name="gst_number" class="form-control form-control-glass" placeholder="15-digit GSTIN" required>
                        </div>
                    </div>
                    <div class="modal-footer modal-footer-glass">
                        <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal" style="border-radius:10px; font-size:0.9rem;">Cancel</button>
                        <button type="submit" class="btn-primary-action" style="padding:10px 20px; font-size:0.9rem;">
                            <i class="fa-solid fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="copyToast" class="toast-notification">
        <i class="fa-solid fa-check-circle"></i>
        <span>Copied to clipboard!</span>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        // Live Search Filter
        $('#searchInput').on('input', function() {
            const q = $(this).val().toLowerCase();
            $('#partnersTable tbody tr').each(function() {
                $(this).toggle($(this).text().toLowerCase().includes(q));
            });
        });

        // Copy Clipboard Helper
        function copyValue(id, btn) {
            const textVal = document.getElementById(id).textContent;
            navigator.clipboard.writeText(textVal).then(() => {
                const toast = $('#copyToast');
                toast.addClass('show');
                
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i>';
                
                setTimeout(() => {
                    toast.removeClass('show');
                    btn.innerHTML = origHtml;
                }, 2000);
            });
        }

        // AJAX Form Submission
        $('#addPartnerForm').on('submit', function(e) {
            e.preventDefault();
            $('#modalError').addClass('d-none').text('');
            
            $.ajax({
                url: 'dashboard.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        // Reload window to refresh partner requests
                        window.location.reload();
                    } else {
                        $('#modalError').removeClass('d-none').text(res.message);
                    }
                },
                error: function() {
                    $('#modalError').removeClass('d-none').text('Network error occurred. Please try again.');
                }
            });
        });
    </script>
</body>
</html>
