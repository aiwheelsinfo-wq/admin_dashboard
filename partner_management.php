<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// AJAX POST handler to update partner commission rate, deposit required, or wallet balance
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'update_partner_pricing') {
        $partner_id = (int)($_POST['partner_id'] ?? 0);
        $commission_rate = (float)($_POST['commission_rate'] ?? 10.00);
        $deposit_required = (float)($_POST['deposit_required'] ?? 10000.00);
        $wallet_balance = (float)($_POST['wallet_balance'] ?? 10000.00);

        if ($partner_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid Partner ID.']);
            exit;
        }

        $stmt = mysqli_prepare($conn, "UPDATE partners SET commission_rate = ?, activation_deposit_required = ?, wallet_balance = ? WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'dddi', $commission_rate, $deposit_required, $wallet_balance, $partner_id);
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => "Partner #{$partner_id} settings updated: Deposit ₹" . number_format($deposit_required, 2) . ", Commission {$commission_rate}%"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update partner settings.']);
            }
            mysqli_stmt_close($stmt);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database query preparation failed.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// Fetch all registered partners
$partners = [];
$query = "SELECT * FROM partners ORDER BY id DESC";
$res = mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $partners[] = $row;
    }
}

// Compute Summary Metrics
$total_partners = count($partners);
$active_partners = 0;
$total_deposits_collected = 0.00;
$total_wallet_balances = 0.00;

foreach ($partners as $pt) {
    if (($pt['payment_status'] ?? '') === 'paid' || ($pt['status'] ?? '') === 'active') {
        $active_partners++;
        $total_deposits_collected += (float)($pt['payment_amount'] ?? 10000.00);
    }
    $total_wallet_balances += (float)($pt['wallet_balance'] ?? 10000.00);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B Partner Commission & Deposit Manager | Admin Console</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome Icons & Bootstrap -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-base: #F4F6F9;
            --bg-card: #FFFFFF;
            --border-color: #E5E7EB;
            --primary-accent: #2563EB;
            --secondary-accent: #3B82F6;
            --success-color: #059669;
            --warning-color: #D97706;
            --danger-color: #DC2626;
            --text-main: #1F2937;
            --text-secondary: #6B7280;
            --font-main: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            padding: 30px;
        }

        .container-custom { max-width: 1240px; margin: 0 auto; }

        /* Top Navigation Header Bar */
        .top-admin-header {
            background: #FFFFFF; border-radius: 16px; padding: 20px 28px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05); margin-bottom: 28px;
            display: flex; justify-content: space-between; align-items: center; border: 1px solid var(--border-color);
        }
        .header-title-sec h1 { font-size: 1.6rem; font-weight: 800; color: #111827; margin: 0; }
        .header-title-sec p { color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-bottom: 0; }

        .btn-back-home {
            background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB;
            border-radius: 10px; padding: 10px 18px; font-size: 0.9rem; font-weight: 600;
            text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;
        }
        .btn-back-home:hover { background: #E5E7EB; color: #111827; }

        /* Metrics Cards Grid */
        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 28px; }
        .metric-card {
            background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;
            padding: 24px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); transition: transform 0.2s ease;
        }
        .metric-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06); }
        .metric-label { color: var(--text-secondary); font-size: 0.82rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .metric-val { font-size: 1.85rem; font-weight: 800; margin: 10px 0 4px; color: #111827; }
        .metric-desc { font-size: 0.82rem; color: var(--text-secondary); font-weight: 500; }

        /* Table Card Panel */
        .panel-card {
            background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 16px;
            padding: 28px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
        }
        .card-title { font-size: 1.2rem; font-weight: 800; color: #111827; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }

        table.custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        table.custom-table th { padding: 14px 16px; font-size: 0.8rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; border-bottom: 2px solid var(--border-color); background: #F9FAFB; }
        table.custom-table td { padding: 16px; border-bottom: 1px solid var(--border-color); font-size: 0.92rem; vertical-align: middle; }
        table.custom-table tr:hover { background: #F9FAFB; }

        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 700; }
        .badge-active { background: #DEF7EC; color: #03543F; border: 1px solid #BCF0DA; }
        .badge-pending { background: #FEF08A; color: #713F12; border: 1px solid #FDE047; }

        .btn-edit {
            background: linear-gradient(135deg, #2563EB, #1D4ED8); color: #FFF;
            border: none; border-radius: 8px; padding: 9px 18px; font-size: 0.86rem; font-weight: 700;
            cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .btn-edit:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4); background: #1D4ED8; }

        /* Edit Settings Modal Dialog */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(17, 24, 39, 0.6); backdrop-filter: blur(8px);
            display: none; justify-content: center; align-items: center; z-index: 3000; padding: 20px;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: #FFFFFF; border: 1px solid var(--border-color);
            border-radius: 20px; width: 100%; max-width: 520px; padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15); position: relative;
        }
        .modal-title-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color); }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 0.85rem; font-weight: 700; color: #374151; margin-bottom: 8px; }
        .form-input {
            width: 100%; background: #F9FAFB; border: 1px solid #D1D5DB;
            border-radius: 10px; padding: 12px 16px; color: #111827; font-size: 0.95rem; font-family: var(--font-mono); font-weight: 600;
        }
        .form-input:focus { outline: none; border-color: var(--primary-accent); background: #FFFFFF; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15); }

        .btn-save {
            width: 100%; background: linear-gradient(135deg, #059669, #047857); color: #FFF;
            border: none; border-radius: 12px; padding: 14px; font-size: 1rem; font-weight: 700;
            cursor: pointer; display: inline-flex; justify-content: center; align-items: center; gap: 8px;
            box-shadow: 0 6px 20px rgba(5, 150, 105, 0.3); transition: all 0.2s ease; margin-top: 10px;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(5, 150, 105, 0.45); }

        /* Toast Notification */
        #toast {
            position: fixed; bottom: 30px; right: 30px; background: #111827; color: #FFF;
            border: 1px solid var(--primary-accent); padding: 16px 22px; border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25); display: none; z-index: 4000; font-size: 0.9rem; font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="container-custom">

        <!-- Header Bar -->
        <div class="top-admin-header">
            <div class="header-title-sec">
                <h1>🎛️ B2B Partner Commission & Deposit Manager</h1>
                <p>Super Admin Console • Configure dynamic activation fees, custom commission rates, and partner wallet balances.</p>
            </div>
            <a href="dashboard.php" class="btn-back-home">
                <i class="fa-solid fa-arrow-left"></i> Back to Main Dashboard
            </a>
        </div>

        <!-- Metrics Grid -->
        <div class="metrics-grid">
            <div class="metric-card">
                <span class="metric-label">Total Registered Partners</span>
                <div class="metric-val" style="color:var(--primary-accent);"><?= $total_partners ?></div>
                <span class="metric-desc"><i class="fa-solid fa-check-circle" style="color:var(--success-color);"></i> <?= $active_partners ?> Live Active Accounts</span>
            </div>

            <div class="metric-card">
                <span class="metric-label">Total Activation Deposits</span>
                <div class="metric-val" style="color:var(--success-color);">₹<?= number_format($total_deposits_collected, 2) ?></div>
                <span class="metric-desc"><i class="fa-solid fa-wallet" style="color:var(--success-color);"></i> Onboarding setup fees collected</span>
            </div>

            <div class="metric-card">
                <span class="metric-label">Total Partner Wallet Balance</span>
                <div class="metric-val" style="color:var(--warning-color);">₹<?= number_format($total_wallet_balances, 2) ?></div>
                <span class="metric-desc"><i class="fa-solid fa-coins" style="color:var(--warning-color);"></i> Active prepaid funds in system</span>
            </div>
        </div>

        <!-- Partner Settings Table -->
        <div class="panel-card">
            <div class="card-title">
                <i class="fa-solid fa-sliders" style="color:var(--primary-accent);"></i> B2B Partner Pricing & Commission Rules
            </div>

            <div style="overflow-x:auto;">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Partner & ID</th>
                            <th>Contact Details</th>
                            <th>Status</th>
                            <th>Required Activation Fee</th>
                            <th>Commission Rate</th>
                            <th>Current Wallet Balance</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($partners as $partner): 
                            $is_active = (($partner['payment_status'] ?? '') === 'paid' || ($partner['status'] ?? '') === 'active');
                            $dep_req = (float)($partner['activation_deposit_required'] ?? 10000.00);
                            $comm_rate = (float)($partner['commission_rate'] ?? 10.00);
                            $w_bal = (float)($partner['wallet_balance'] ?? 10000.00);
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight:800; color:#111827; font-size:0.98rem;"><?= htmlspecialchars($partner['partner_name'] ?? 'Partner #' . $partner['id']) ?></div>
                                    <div style="font-size:0.78rem; color:var(--primary-accent); font-family:var(--font-mono); margin-top:2px; font-weight:700;">ID: #<?= $partner['id'] ?></div>
                                </td>
                                <td>
                                    <div style="font-size:0.88rem; color:#1F2937; font-weight:600;"><?= htmlspecialchars($partner['email'] ?? 'N/A') ?></div>
                                    <div style="font-size:0.8rem; color:var(--text-secondary); font-family:var(--font-mono); margin-top:2px;"><?= htmlspecialchars($partner['phone'] ?? 'N/A') ?></div>
                                </td>
                                <td>
                                    <?php if ($is_active): ?>
                                        <span class="status-badge badge-active"><i class="fa-solid fa-check"></i> Active / Paid</span>
                                    <?php else: ?>
                                        <span class="status-badge badge-pending"><i class="fa-solid fa-clock"></i> Pending Payment</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-weight:700; color:#111827; font-family:var(--font-mono);">₹<?= number_format($dep_req, 2) ?></span>
                                </td>
                                <td>
                                    <span style="font-weight:800; color:var(--warning-color); font-family:var(--font-mono); background:#FEF3C7; padding:4px 10px; border-radius:6px;"><?= number_format($comm_rate, 2) ?>%</span>
                                </td>
                                <td>
                                    <span style="font-weight:800; color:var(--success-color); font-family:var(--font-mono); background:#D1FAE5; padding:4px 10px; border-radius:6px;">₹<?= number_format($w_bal, 2) ?></span>
                                </td>
                                <td style="text-align:right;">
                                    <button class="btn-edit" onclick='openEditModal(<?= json_encode($partner) ?>)'>
                                        <i class="fa-solid fa-pen-to-square"></i> Edit Pricing
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Edit Settings Modal Dialog -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-title-bar">
                <h3 style="font-size:1.15rem; font-weight:800; color:#111827; margin:0;">
                    <i class="fa-solid fa-sliders" style="color:var(--primary-accent);"></i> Edit Partner Rules
                </h3>
                <button type="button" onclick="closeEditModal()" style="background:none; border:none; color:var(--text-secondary); cursor:pointer; font-size:1.2rem;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="editForm" onsubmit="savePartnerSettings(event)">
                <input type="hidden" id="modal_partner_id">
                
                <div class="form-group">
                    <label class="form-label">Partner Name</label>
                    <input type="text" id="modal_partner_name" class="form-input" readonly style="background:#F3F4F6; cursor:not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">Required Activation Deposit Fee (₹)</label>
                    <input type="number" step="100" id="modal_deposit_required" class="form-input" required placeholder="10000.00">
                    <span style="font-size:0.78rem; color:var(--text-secondary); margin-top:4px; display:block;">One-time onboarding fee charged via Razorpay (Default: ₹10,000).</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Commission Rate (%)</label>
                    <input type="number" step="0.1" min="0" max="100" id="modal_commission_rate" class="form-input" required placeholder="10.00">
                    <span style="font-size:0.78rem; color:var(--text-secondary); margin-top:4px; display:block;">Percentage deducted from wallet balance per completed trip (Default: 10%).</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Prepaid Wallet Balance (₹)</label>
                    <input type="number" step="10" id="modal_wallet_balance" class="form-input" required placeholder="10000.00">
                    <span style="font-size:0.78rem; color:var(--text-secondary); margin-top:4px; display:block;">Current available wallet funds for trip fee deductions.</span>
                </div>

                <button type="submit" class="btn-save">
                    <i class="fa-solid fa-floppy-disk"></i> Save Dynamic Settings
                </button>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast"></div>

    <script>
        function showToast(msg, isError = false) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.borderColor = isError ? 'var(--danger-color)' : 'var(--success-color)';
            t.style.display = 'block';
            setTimeout(() => { t.style.display = 'none'; }, 4000);
        }

        function openEditModal(partner) {
            if (!partner) return;
            document.getElementById('modal_partner_id').value = partner.id;
            document.getElementById('modal_partner_name').value = partner.partner_name || ('Partner #' + partner.id);
            document.getElementById('modal_deposit_required').value = parseFloat(partner.activation_deposit_required || 10000.00).toFixed(2);
            document.getElementById('modal_commission_rate').value = parseFloat(partner.commission_rate || 10.00).toFixed(2);
            document.getElementById('modal_wallet_balance').value = parseFloat(partner.wallet_balance || 10000.00).toFixed(2);
            
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        async function savePartnerSettings(e) {
            e.preventDefault();
            const partner_id = document.getElementById('modal_partner_id').value;
            const deposit_required = document.getElementById('modal_deposit_required').value;
            const commission_rate = document.getElementById('modal_commission_rate').value;
            const wallet_balance = document.getElementById('modal_wallet_balance').value;

            const formData = new URLSearchParams();
            formData.append('action', 'update_partner_pricing');
            formData.append('partner_id', partner_id);
            formData.append('deposit_required', deposit_required);
            formData.append('commission_rate', commission_rate);
            formData.append('wallet_balance', wallet_balance);

            try {
                const response = await fetch('partner_management.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                const res = await response.json();
                if (res.success) {
                    showToast(res.message);
                    closeEditModal();
                    setTimeout(() => { location.reload(); }, 1200);
                } else {
                    showToast(res.message, true);
                }
            } catch (err) {
                showToast('Failed to connect to server.', true);
            }
        }
    </script>
</body>
</html>
