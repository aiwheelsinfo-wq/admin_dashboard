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

// Handle AJAX Simulation Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $booking_id = trim($_POST['booking_id'] ?? '');

    if (empty($booking_id)) {
        echo json_encode(['success' => false, 'message' => 'Missing booking_id.']);
        exit;
    }

    if ($action === 'accept_trip') {
        $stmt_act = mysqli_prepare($conn, "UPDATE bookings SET booking_status = 'Confirmed', otp = '1234', vehicle_id = 'KL56B2117' WHERE booking_id = ? AND (is_test = 1 OR booking_id LIKE 'TEST-PB%')");
        mysqli_stmt_bind_param($stmt_act, 's', $booking_id);
        if (mysqli_stmt_execute($stmt_act)) {
            echo json_encode(['success' => true, 'message' => "Test Trip {$booking_id} accepted! Driver 'Rajesh Kumar (+91 9876543210)' assigned with Vehicle KL56B2117."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update trip status.']);
        }
        mysqli_stmt_close($stmt_act);
        exit;
    }

    if ($action === 'start_trip') {
        $starting_km = (int)($_POST['starting_km'] ?? 12450);
        $stmt_act = mysqli_prepare($conn, "UPDATE bookings SET booking_status = 'Started', starting_km = ?, starting_time = CURTIME(), starting_date = CURDATE() WHERE booking_id = ? AND (is_test = 1 OR booking_id LIKE 'TEST-PB%')");
        mysqli_stmt_bind_param($stmt_act, 'is', $starting_km, $booking_id);
        if (mysqli_stmt_execute($stmt_act)) {
            echo json_encode(['success' => true, 'message' => "Test Trip {$booking_id} started at {$starting_km} KM!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to start trip.']);
        }
        mysqli_stmt_close($stmt_act);
        exit;
    }

    if ($action === 'finish_trip') {
        require_once __DIR__ . '/wallet_helper.php';
        $closing_km = (int)($_POST['closing_km'] ?? 12575);
        $stmt_act = mysqli_prepare($conn, "UPDATE bookings SET booking_status = 'Completed', closing_km = ?, closing_time = CURTIME(), closing_date = CURDATE(), settlement_status = 'Paid' WHERE booking_id = ?");
        mysqli_stmt_bind_param($stmt_act, 'is', $closing_km, $booking_id);
        if (mysqli_stmt_execute($stmt_act)) {
            process_partner_trip_commission($conn, $booking_id);
            echo json_encode(['success' => true, 'message' => "Trip {$booking_id} finished successfully at {$closing_km} KM! Status marked as Completed. 10% API fee deducted."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to finish trip.']);
        }
        mysqli_stmt_close($stmt_act);
        exit;
    }

    if ($action === 'cancel_trip') {
        $stmt_act = mysqli_prepare($conn, "UPDATE bookings SET booking_status = 'Cancelled', cancellation_reason = 'Simulated by B2B Developer' WHERE booking_id = ? AND (is_test = 1 OR booking_id LIKE 'TEST-PB%')");
        mysqli_stmt_bind_param($stmt_act, 's', $booking_id);
        if (mysqli_stmt_execute($stmt_act)) {
            echo json_encode(['success' => true, 'message' => "Test Trip {$booking_id} cancelled!"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to cancel trip.']);
        }
        mysqli_stmt_close($stmt_act);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// Fetch test bookings list
$test_bookings = [];
$tb_query = "SELECT * FROM bookings WHERE is_test = 1 OR booking_id LIKE 'TEST-PB%' ORDER BY id DESC LIMIT 50";
$tb_res = mysqli_query($conn, $tb_query);
if ($tb_res) {
    while ($row = mysqli_fetch_assoc($tb_res)) {
        $test_bookings[] = $row;
    }
}

// Compute Summary Metrics
$total_test = count($test_bookings);
$pending_test = 0;
$ongoing_test = 0;
$completed_test = 0;

foreach ($test_bookings as $tb) {
    $st = strtolower($tb['booking_status'] ?? '');
    if ($st === 'pending' || $st === 'sandbox_test') $pending_test++;
    elseif ($st === 'confirmed' || $st === 'started' || $st === 'ongoing') $ongoing_test++;
    elseif ($st === 'completed') $completed_test++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sandbox Trip Simulator | Rentox B2B Console</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-base: #0B0F17;
            --bg-card: #111827;
            --bg-card-hover: #1F2937;
            --border-color: rgba(255, 255, 255, 0.08);
            --primary-accent: #6366F1;
            --primary-accent-hover: #4F46E5;
            --secondary-accent: #3B82F6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --text-main: #F9FAFB;
            --text-secondary: #9CA3AF;
            --sidebar-width: 260px;
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            z-index: -1;
            opacity: 0.25;
            pointer-events: none;
        }
        .glow-1 { top: -10%; left: 20%; width: 450px; height: 450px; background: var(--primary-accent); }
        .glow-2 { bottom: 10%; right: 5%; width: 400px; height: 400px; background: var(--warning-color); }

        .app-container { display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: rgba(17, 24, 39, 0.95);
            border-right: 1px solid var(--border-color);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }
        .sidebar-brand {
            padding: 28px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .brand-logo-icon {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .brand-name { font-size: 1.2rem; font-weight: 800; }

        .sidebar-nav { padding: 20px 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: 12px;
            color: var(--text-secondary); text-decoration: none;
            padding: 12px 16px; border-radius: 10px; font-size: 0.92rem; font-weight: 500;
            transition: all 0.2s ease;
        }
        .nav-item:hover { color: #FFF; background: rgba(255, 255, 255, 0.04); }
        .nav-item.active {
            color: #FFF; background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(59, 130, 246, 0.2));
            border: 1px solid rgba(99, 102, 241, 0.3); font-weight: 600;
        }

        .main-workspace { margin-left: var(--sidebar-width); flex: 1; padding: 40px; }

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color);
        }
        .page-header h1 { font-size: 1.6rem; font-weight: 800; margin-bottom: 6px; }
        .page-header p { color: var(--text-secondary); font-size: 0.92rem; }

        /* Metric Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 20px; position: relative; overflow: hidden;
        }
        .metric-label { color: var(--text-secondary); font-size: 0.82rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-value { font-size: 1.8rem; font-weight: 800; margin: 10px 0 4px; }
        .metric-desc { font-size: 0.82rem; color: var(--text-secondary); }

        /* Panel Card & Table */
        .panel-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            border-radius: 16px; padding: 24px; margin-bottom: 30px;
        }
        .card-header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .card-title { font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }

        .table-responsive { overflow-x: auto; }
        table.custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.custom-table th {
            padding: 14px 16px; font-size: 0.8rem; font-weight: 700; color: var(--text-secondary);
            text-transform: uppercase; border-bottom: 1px solid var(--border-color);
        }
        table.custom-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
        table.custom-table tr:hover { background: rgba(255, 255, 255, 0.02); }

        code.booking-badge {
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 600;
            background: rgba(245, 158, 11, 0.15); color: var(--warning-color);
            padding: 4px 8px; border-radius: 6px; border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .status-pill {
            display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;
            border-radius: 20px; font-size: 0.8rem; font-weight: 600;
        }
        .pill-pending { background: rgba(245, 158, 11, 0.15); color: var(--warning-color); }
        .pill-confirmed { background: rgba(59, 130, 246, 0.15); color: var(--secondary-accent); }
        .pill-started { background: rgba(99, 102, 241, 0.15); color: var(--primary-accent); }
        .pill-completed { background: rgba(16, 185, 129, 0.15); color: var(--success-color); }
        .pill-cancelled { background: rgba(239, 68, 68, 0.15); color: var(--danger-color); }

        /* Action Buttons */
        .btn-action {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.2s ease; margin-right: 4px;
        }
        .btn-accept { background: rgba(16, 185, 129, 0.2); color: var(--success-color); border: 1px solid rgba(16, 185, 129, 0.4); }
        .btn-accept:hover { background: var(--success-color); color: #FFF; }
        .btn-start { background: rgba(99, 102, 241, 0.2); color: var(--primary-accent); border: 1px solid rgba(99, 102, 241, 0.4); }
        .btn-start:hover { background: var(--primary-accent); color: #FFF; }
        .btn-finish { background: rgba(245, 158, 11, 0.2); color: var(--warning-color); border: 1px solid rgba(245, 158, 11, 0.4); }
        .btn-finish:hover { background: var(--warning-color); color: #000; }
        .btn-cancel { background: rgba(239, 68, 68, 0.15); color: var(--danger-color); border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-cancel:hover { background: var(--danger-color); color: #FFF; }

        /* Toast Notice */
        #toast {
            position: fixed; bottom: 30px; right: 30px; background: var(--bg-card);
            border: 1px solid var(--primary-accent); padding: 16px 20px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); display: none; z-index: 2000; font-size: 0.9rem;
        }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="app-container">

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-terminal brand-logo-icon"></i>
                <span class="brand-name">Rentox API</span>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php#overview" class="nav-item">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                <a href="dashboard.php#keys" class="nav-item">
                    <i class="fa-solid fa-key"></i> API Credentials
                </a>
                <a href="payments.php" class="nav-item">
                    <i class="fa-solid fa-credit-card"></i> Payments & Billing
                </a>
                <a href="test-trips.php" class="nav-item active">
                    <i class="fa-solid fa-flask-vial"></i> Test Trips Simulator
                </a>
                <a href="dashboard.php#logs" class="nav-item">
                    <i class="fa-solid fa-terminal"></i> Activity Logs
                </a>
                <a href="dashboard.php#docs" class="nav-item">
                    <i class="fa-solid fa-book"></i> API Documentation
                </a>
                <a href="dashboard.php#settings" class="nav-item">
                    <i class="fa-solid fa-sliders"></i> Account Settings
                </a>
            </nav>
        </aside>

        <!-- Main Workspace -->
        <main class="main-workspace">

            <div class="page-header">
                <div>
                    <h1>🧪 Sandbox Test Trips Simulator</h1>
                    <p>Simulate real-time driver acceptance, start trip, and complete trip events for your API integration.</p>
                </div>
                <button class="btn-action btn-start" onclick="location.reload()" style="padding: 10px 18px; font-size:0.9rem;">
                    <i class="fa-solid fa-rotate-right"></i> Refresh Trips List
                </button>
            </div>

            <!-- Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <span class="metric-label">Total Test Bookings</span>
                    <div class="metric-value"><?= $total_test ?></div>
                    <span class="metric-desc">Generated with TEST_ keys</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Pending Acceptance</span>
                    <div class="metric-value" style="color: var(--warning-color);"><?= $pending_test ?></div>
                    <span class="metric-desc">Awaiting test driver accept</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Active / Ongoing</span>
                    <div class="metric-value" style="color: var(--primary-accent);"><?= $ongoing_test ?></div>
                    <span class="metric-desc">Confirmed / In-transit</span>
                </div>
                <div class="metric-card">
                    <span class="metric-label">Completed Test Trips</span>
                    <div class="metric-value" style="color: var(--success-color);"><?= $completed_test ?></div>
                    <span class="metric-desc">Simulated successful trips</span>
                </div>
            </div>

            <!-- Table of Test Trips -->
            <div class="panel-card">
                <div class="card-header-flex">
                    <h3 class="card-title"><i class="fa-solid fa-list-check"></i> Recent Sandbox Test Bookings</h3>
                    <a href="https://agnicarrental.com/tester/" target="_blank" class="btn-action btn-accept" style="text-decoration:none;">
                        <i class="fa-solid fa-up-right-from-square"></i> Open Tester Web Console
                    </a>
                </div>

                <?php if (empty($test_bookings)): ?>
                    <div style="text-align:center; padding:50px 20px;">
                        <i class="fa-solid fa-flask" style="font-size:3rem; color:var(--text-secondary); opacity:0.3; margin-bottom:15px;"></i>
                        <h3>No Sandbox Test Trips Found</h3>
                        <p style="color:var(--text-secondary); max-width:450px; margin:8px auto 20px; font-size:0.9rem;">Create a test booking using your Sandbox API key (<code style="color:var(--warning-color)">TEST_<?= htmlspecialchars($p['api_key']) ?></code>) via API or Tester Web to simulate driver accept & finish events here.</p>
                        <a href="https://agnicarrental.com/tester/" target="_blank" class="btn-action btn-start" style="padding:10px 18px; text-decoration:none;">
                            <i class="fa-solid fa-plus"></i> Create Test Booking in Tester Web
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Route Details</th>
                                    <th>Trip & Car</th>
                                    <th>Fare</th>
                                    <th>Status</th>
                                    <th>Interactive Simulator Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_bookings as $b): 
                                    $st = strtolower($b['booking_status'] ?? 'pending');
                                ?>
                                    <tr>
                                        <td>
                                            <code class="booking-badge"><?= htmlspecialchars($b['booking_id']) ?></code>
                                            <div style="font-size:0.75rem; color:var(--text-secondary); margin-top:4px;">
                                                <?= date('d M Y, h:i A', strtotime($b['booked_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:#FFF;">🟢 <?= htmlspecialchars($b['from_address']) ?></div>
                                            <div style="font-size:0.85rem; color:var(--text-secondary); margin-top:2px;">🔴 <?= htmlspecialchars($b['to_address'] ?? 'N/A') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;"><?= htmlspecialchars($b['trip_type']) ?></div>
                                            <div style="font-size:0.82rem; color:var(--text-secondary);"><?= htmlspecialchars($b['car_type']) ?></div>
                                        </td>
                                        <td>
                                            <span style="font-weight:700; color:var(--success-color);">₹<?= number_format($b['total_amount'] ?? 0, 2) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($st === 'pending' || $st === 'sandbox_test'): ?>
                                                <span class="status-pill pill-pending"><i class="fa-solid fa-hourglass-half"></i> Pending Accept</span>
                                            <?php elseif ($st === 'confirmed'): ?>
                                                <span class="status-pill pill-confirmed"><i class="fa-solid fa-user-check"></i> Driver Accepted</span>
                                            <?php elseif ($st === 'started' || $st === 'ongoing'): ?>
                                                <span class="status-pill pill-started"><i class="fa-solid fa-car-side"></i> Trip Started</span>
                                            <?php elseif ($st === 'completed'): ?>
                                                <span class="status-pill pill-completed"><i class="fa-solid fa-circle-check"></i> Completed</span>
                                            <?php elseif ($st === 'cancelled'): ?>
                                                <span class="status-pill pill-cancelled"><i class="fa-solid fa-ban"></i> Cancelled</span>
                                            <?php else: ?>
                                                <span class="status-pill pill-pending"><?= htmlspecialchars($b['booking_status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($st === 'pending' || $st === 'sandbox_test'): ?>
                                                <button class="btn-action btn-accept" onclick="triggerAction('accept_trip', '<?= $b['booking_id'] ?>')">
                                                    <i class="fa-solid fa-user-check"></i> 1. Accept Test Trip
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($st === 'confirmed'): ?>
                                                <button class="btn-action btn-start" onclick="triggerAction('start_trip', '<?= $b['booking_id'] ?>')">
                                                    <i class="fa-solid fa-circle-play"></i> 2. Start Trip (12,450 KM)
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($st === 'started' || $st === 'ongoing' || $st === 'confirmed'): ?>
                                                <button class="btn-action btn-finish" onclick="triggerAction('finish_trip', '<?= $b['booking_id'] ?>')">
                                                    <i class="fa-solid fa-flag-checkered"></i> 3. Finish Trip (12,575 KM)
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($st !== 'completed' && $st !== 'cancelled'): ?>
                                                <button class="btn-action btn-cancel" onclick="triggerAction('cancel_trip', '<?= $b['booking_id'] ?>')">
                                                    <i class="fa-solid fa-xmark"></i> Cancel
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($st === 'completed'): ?>
                                                <span style="font-size:0.82rem; color:var(--success-color); font-weight:600;">
                                                    <i class="fa-solid fa-circle-check"></i> Trip Finished
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <!-- Toast Popup Notification -->
    <div id="toast"></div>

    <script>
        function showToast(msg, isSuccess = true) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.borderColor = isSuccess ? 'var(--success-color)' : 'var(--danger-color)';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        async function triggerAction(action, bookingId) {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('booking_id', bookingId);

            try {
                const res = await fetch('test-trips.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message, true);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    showToast(data.message || 'Action failed', false);
                }
            } catch (e) {
                showToast('Network error: ' + e.message, false);
            }
        }
    </script>
</body>
</html>
