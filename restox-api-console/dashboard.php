<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

// Auth Check (must be logged in as partner)
if (!isset($_SESSION['partner_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['partner_id'];

// Fetch latest partner details
$stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$p = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$p) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Log out action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit();
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

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(108, 99, 255, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

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
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 24px;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Status Banner */
        .status-banner {
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            gap: 20px;
            line-height: 1.5;
        }

        .status-banner-pending {
            background: rgba(245, 158, 11, 0.08);
            border: 1px solid rgba(245, 158, 11, 0.2);
            color: #ffb74d;
        }

        .status-banner-active {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
        }

        .status-banner-blocked {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .status-icon {
            font-size: 2.2rem;
            flex-shrink: 0;
        }

        .status-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: #fff;
        }

        .status-desc {
            font-size: 0.95rem;
            opacity: 0.85;
        }

        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1.2fr 2fr;
            gap: 28px;
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-card {
            backdrop-filter: blur(16px);
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .panel-title {
            font-size: 1.15rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 12px;
        }

        /* Info Item Details */
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.95rem;
            color: var(--text-primary);
            word-break: break-all;
        }

        /* Key display boxes */
        .key-box-group {
            margin-bottom: 24px;
        }

        .key-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .key-display-wrapper {
            background-color: rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        .key-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.92rem;
            color: var(--success-color);
            word-break: break-all;
            flex: 1;
        }

        .btn-copy {
            background: rgba(108, 99, 255, 0.15);
            border: 1px solid rgba(108, 99, 255, 0.3);
            color: #a5b4fc;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-copy:hover {
            background: var(--primary-accent);
            color: #fff;
        }

        /* Endpoint Reference Table */
        .endpoint-table-container {
            margin-top: 15px;
            overflow-x: auto;
        }

        .endpoint-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            text-align: left;
        }

        .endpoint-table th {
            color: var(--text-muted);
            font-weight: 600;
            padding: 10px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            text-transform: uppercase;
            font-size: 0.78rem;
        }

        .endpoint-table td {
            padding: 12px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
        }

        .method-badge {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 0.72rem;
            font-weight: 700;
            display: inline-block;
            width: 44px;
            text-align: center;
        }

        .endpoint-path {
            font-family: monospace;
            color: #818cf8;
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
            z-index: 1000;
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
                <i class="fa-solid fa-circle-user" style="color: var(--primary-accent);"></i>
                <span><?= htmlspecialchars($p['company_name']) ?></span>
            </div>
            <a href="?action=logout" class="btn-logout">
                <i class="fa-solid fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="dashboard-container">
        
        <!-- Status Banner -->
        <?php if ($p['status'] === 'pending'): ?>
            <div class="status-banner status-banner-pending">
                <i class="fa-solid fa-hourglass-half status-icon"></i>
                <div>
                    <h3 class="status-title">Application Pending Approval</h3>
                    <p class="status-desc">Your B2B registration request is currently being verified. Once approved, your API credentials and endpoints reference will automatically display below.</p>
                </div>
            </div>
        <?php elseif ($p['status'] === 'blocked'): ?>
            <div class="status-banner status-banner-blocked">
                <i class="fa-solid fa-ban status-icon"></i>
                <div>
                    <h3 class="status-title">API Access Suspended</h3>
                    <p class="status-desc">Your credentials have been suspended. Please contact our integration support team to resolve this issue.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="status-banner status-banner-active">
                <i class="fa-solid fa-circle-check status-icon"></i>
                <div>
                    <h3 class="status-title">API Integration Active</h3>
                    <p class="status-desc">Your credentials are live. You may begin sending API requests using the credentials and paths documented below.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Grid -->
        <div class="dashboard-grid">
            
            <!-- Left Side: Profile Info -->
            <section class="panel-card">
                <h3 class="panel-title"><i class="fa-solid fa-building"></i> Profile Details</h3>
                <div class="info-list">
                    <div class="info-item">
                        <span class="info-label">Company Name</span>
                        <span class="info-value"><?= htmlspecialchars($p['company_name']) ?></span>
                    </div>
                    <?php if (!empty($p['company_owner_name'])): ?>
                        <div class="info-item">
                            <span class="info-label">Company Owner</span>
                            <span class="info-value"><?= htmlspecialchars($p['company_owner_name']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($p['gst_number'])): ?>
                        <div class="info-item">
                            <span class="info-label">GST Number</span>
                            <span class="info-value" style="font-family: monospace; color:#818cf8;"><?= htmlspecialchars($p['gst_number']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Contact Name</span>
                        <span class="info-value"><?= htmlspecialchars($p['contact_person']) ?></span>
                    </div>
                    <?php if (!empty($p['mobile_number'])): ?>
                        <div class="info-item">
                            <span class="info-label">Contact Mobile</span>
                            <span class="info-value"><?= htmlspecialchars($p['mobile_number']) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">Business Email</span>
                        <span class="info-value"><?= htmlspecialchars($p['email']) ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Account Created</span>
                        <span class="info-value"><?= date('d F Y', strtotime($p['created_at'])) ?></span>
                    </div>
                </div>
            </section>

            <!-- Right Side: API Access & Credentials -->
            <section class="panel-card">
                <?php if ($p['status'] !== 'active'): ?>
                    <h3 class="panel-title"><i class="fa-solid fa-key"></i> API Access Credentials</h3>
                    <div style="text-align: center; padding: 40px 0; color: var(--text-muted);">
                        <i class="fa-solid fa-lock" style="font-size: 3rem; margin-bottom: 16px; opacity: 0.3;"></i>
                        <p>Credentials will be generated and shown here after your application is approved.</p>
                    </div>
                <?php else: ?>
                    <h3 class="panel-title"><i class="fa-solid fa-key"></i> Live API Credentials</h3>
                    
                    <div class="key-box-group">
                        <label class="key-label">API Key (X-API-Key)</label>
                        <div class="key-display-wrapper">
                            <code class="key-code" id="live-api-key"><?= htmlspecialchars($p['api_key']) ?></code>
                            <button class="btn-copy" onclick="copyValue('live-api-key', this)"><i class="fa-solid fa-copy"></i> Copy</button>
                        </div>
                    </div>

                    <div class="key-box-group">
                        <label class="key-label">Secret Key (X-Secret-Key)</label>
                        <div class="key-display-wrapper">
                            <code class="key-code" id="live-secret-key"><?= htmlspecialchars($p['secret_key']) ?></code>
                            <button class="btn-copy" onclick="copyValue('live-secret-key', this)"><i class="fa-solid fa-copy"></i> Copy</button>
                        </div>
                    </div>

                    <div style="background: rgba(108, 99, 255, 0.06); border: 1px solid rgba(108, 99, 255, 0.15); border-radius: 12px; padding: 14px; font-size: 0.85rem; color: #a5b4fc; margin-bottom: 28px;">
                        <i class="fa-solid fa-circle-info"></i>
                        Rate limits: <strong><?= $p['rate_limit_per_minute'] ?> req/min</strong> and <strong><?= number_format($p['rate_limit_per_day']) ?> req/day</strong>. Include authorization parameters inside request headers.
                    </div>

                    <h3 class="panel-title" style="margin-top: 20px;"><i class="fa-solid fa-book"></i> Endpoint Reference</h3>
                    <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 12px;">Base URL: <code style="font-family: monospace; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; color:#fff;">https://agnicarrental.com/admin2025/partner/api</code></p>
                    
                    <div class="endpoint-table-container">
                        <table class="endpoint-table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Endpoint Route</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="method-badge">POST</span></td>
                                    <td><span class="endpoint-path">/search-cab.php</span></td>
                                    <td>Search available vehicles</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge">POST</span></td>
                                    <td><span class="endpoint-path">/get-fare.php</span></td>
                                    <td>Calculate tariff estimation</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge">POST</span></td>
                                    <td><span class="endpoint-path">/book-cab.php</span></td>
                                    <td>Submit cab booking</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">GET</span></td>
                                    <td><span class="endpoint-path">/booking-status.php</span></td>
                                    <td>Check current cab status</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge">POST</span></td>
                                    <td><span class="endpoint-path">/cancel-booking.php</span></td>
                                    <td>Cancel existing booking</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">GET</span></td>
                                    <td><span class="endpoint-path">/driver-details.php</span></td>
                                    <td>Get driver coordinates</td>
                                </tr>
                                <tr>
                                    <td><span class="method-badge" style="background: rgba(16, 185, 129, 0.15); color: #34d399;">GET</span></td>
                                    <td><span class="endpoint-path">/trip-details.php</span></td>
                                    <td>Retrieve trip records</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        </div>

    </main>

    <!-- Toast Notification -->
    <div id="copyToast" class="toast-notification">
        <i class="fa-solid fa-check-circle"></i>
        <span>Copied to clipboard!</span>
    </div>

    <script>
        function copyValue(id, btn) {
            const codeText = document.getElementById(id).innerText;
            navigator.clipboard.writeText(codeText).then(() => {
                const toast = document.getElementById('copyToast');
                toast.classList.add('show');
                
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
                
                setTimeout(() => {
                    toast.classList.remove('show');
                    btn.innerHTML = origHtml;
                }, 2000);
            });
        }
    </script>
</body>
</html>
