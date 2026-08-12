<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

if (file_exists(__DIR__ . '/../../2025/razorpay_config.php')) {
    require_once __DIR__ . '/../../2025/razorpay_config.php';
}
if (!defined('RAZORPAY_ACTIVE_KEY')) {
    define('RAZORPAY_ACTIVE_KEY', 'rzp_test_GIqSfPJk12gAgz');
}

// Auth Check (must be logged in as partner)
if (!isset($_SESSION['partner_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['partner_id'];
$error = '';
$success = '';

// Handle Razorpay Payment Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_payment') {
    header('Content-Type: application/json');
    $payment_id = trim($_POST['razorpay_payment_id'] ?? '');

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid payment ID.']);
        exit;
    }

    try {
        // Fetch dynamic activation deposit required
        $stmt_get = mysqli_prepare($conn, "SELECT activation_deposit_required FROM partners WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_get, 'i', $id);
        mysqli_stmt_execute($stmt_get);
        $res_get = mysqli_stmt_get_result($stmt_get);
        $row_get = mysqli_fetch_assoc($res_get);
        mysqli_stmt_close($stmt_get);

        $dep_amount = (float)($row_get['activation_deposit_required'] ?? 10000.00);

        $stmt_pay = mysqli_prepare($conn, 
            "UPDATE partners 
             SET status = 'active', payment_status = 'paid', payment_id = ?, payment_amount = ?, wallet_balance = ?, paid_at = NOW() 
             WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt_pay, 'sddi', $payment_id, $dep_amount, $dep_amount, $id);
        if (mysqli_stmt_execute($stmt_pay)) {
            echo json_encode(['success' => true, 'message' => 'Payment of ₹' . number_format($dep_amount, 2) . ' verified successfully! Your API Production keys are now unlocked.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record payment in database.']);
        }
        mysqli_stmt_close($stmt_pay);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle Profile Update Request (AJAX or normal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $partner_name       = trim($_POST['partner_name'] ?? '');
    $company_name       = trim($_POST['company_name'] ?? '');
    $company_owner_name = trim($_POST['company_owner_name'] ?? '');
    $contact_person     = trim($_POST['contact_person'] ?? '');
    $contact_number     = trim($_POST['contact_number'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $gst_number         = trim($_POST['gst_number'] ?? '');

    if (!$partner_name || !$company_name || !$company_owner_name || !$contact_person || !$contact_number || !$email || !$gst_number) {
        $error = 'All fields are required to complete your profile.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    } else {
        try {
            // Check if email already exists for another partner
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM partners WHERE email = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 'si', $email, $id);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = 'This email address is already in use by another partner.';
            }
            mysqli_stmt_close($check_stmt);

            if (empty($error)) {
                $stmt = mysqli_prepare($conn, 
                    "UPDATE partners 
                     SET partner_name = ?, company_name = ?, company_owner_name = ?, contact_person = ?, mobile_number = ?, email = ?, gst_number = ? 
                     WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'sssssssi', 
                    $partner_name, $company_name, $company_owner_name, $contact_person, $contact_number, $email, $gst_number, $id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Profile details updated successfully!';
                    $_SESSION['partner_email'] = $email; // Update session email

                    // Automatically send email notification to Rentox Admin
                    send_admin_notification_email($company_name, $partner_name, $company_owner_name, $contact_person, $contact_number, $email, $gst_number);
                } else {
                    $error = 'Failed to update profile: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }

    // Return JSON response if AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode([
            'success' => empty($error),
            'message' => empty($error) ? $success : $error
        ]);
        exit;
    }
}

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

// Dynamic activation fee set by Super Admin
$activation_fee = (float)($p['activation_deposit_required'] ?? 10000.00);

// Query Partner Log Stats
$total_requests = 0;
$success_requests = 0;
$today_requests = 0;
$success_rate = 100.0;

$count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM partner_api_logs WHERE partner_id = ?");
if ($count_stmt) {
    mysqli_stmt_bind_param($count_stmt, 'i', $id);
    mysqli_stmt_execute($count_stmt);
    mysqli_stmt_bind_result($count_stmt, $total_requests);
    mysqli_stmt_fetch($count_stmt);
    mysqli_stmt_close($count_stmt);
}

// Success count
$success_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM partner_api_logs WHERE partner_id = ? AND status = 'success'");
if ($success_stmt) {
    mysqli_stmt_bind_param($success_stmt, 'i', $id);
    mysqli_stmt_execute($success_stmt);
    mysqli_stmt_bind_result($success_stmt, $success_requests);
    mysqli_stmt_fetch($success_stmt);
    mysqli_stmt_close($success_stmt);
}

if ($total_requests > 0) {
    $success_rate = round(($success_requests / $total_requests) * 100, 1);
}

// Daily activity
$today_stmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM partner_api_logs WHERE partner_id = ? AND DATE(created_at) = CURDATE()");
if ($today_stmt) {
    mysqli_stmt_bind_param($today_stmt, 'i', $id);
    mysqli_stmt_execute($today_stmt);
    mysqli_stmt_bind_result($today_stmt, $today_requests);
    mysqli_stmt_fetch($today_stmt);
    mysqli_stmt_close($today_stmt);
}

// Query latest 15 logs for Request Logs view
$recent_logs = [];
$logs_stmt = mysqli_prepare($conn, "SELECT * FROM partner_api_logs WHERE partner_id = ? ORDER BY created_at DESC LIMIT 15");
if ($logs_stmt) {
    mysqli_stmt_bind_param($logs_stmt, 'i', $id);
    mysqli_stmt_execute($logs_stmt);
    $logs_res = mysqli_stmt_get_result($logs_stmt);
    while ($row = mysqli_fetch_assoc($logs_res)) {
        $recent_logs[] = $row;
    }
    mysqli_stmt_close($logs_stmt);
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
    <title>Rentox Developer Dashboard</title>
    <!-- Google Fonts: Plus Jakarta Sans for premium typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Core Tailwind CSS Reset -->
    <style>
        :root {
            --primary-accent: #6366F1;
            --secondary-accent: #8B5CF6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --danger-color: #EF4444;
            --bg-color: #0B1120;
            --card-bg: #111827;
            --text-primary: #FFFFFF;
            --text-secondary: #94A3B8;
            --card-border: rgba(255, 255, 255, 0.08);
            --primary-glow: rgba(99, 102, 241, 0.15);
            --sidebar-width: 280px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.06) 0px, transparent 50%);
        }

        /* Ambient glowing dots */
        .ambient-glow {
            position: fixed;
            border-radius: 50%;
            filter: blur(140px);
            z-index: -1;
            opacity: 0.35;
            pointer-events: none;
        }
        .glow-1 {
            top: -10%;
            left: 20%;
            width: 450px;
            height: 450px;
            background: var(--primary-accent);
        }
        .glow-2 {
            bottom: 10%;
            right: 5%;
            width: 400px;
            height: 400px;
            background: var(--secondary-accent);
        }

        /* 2-Column SaaS Layout Grid */
        .app-container {
            display: flex;
            min-height: 100vh;
            position: relative;
        }

        /* Navigation Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: rgba(17, 24, 39, 0.85);
            border-right: 1px solid var(--card-border);
            backdrop-filter: blur(20px);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-brand {
            padding: 32px 28px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        }

        .brand-logo-icon {
            font-size: 1.7rem;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.4));
        }

        .brand-name {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #FFF, #D1D5DB);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-nav {
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .nav-item i {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .nav-item:hover {
            color: #FFF;
            background-color: rgba(255, 255, 255, 0.03);
        }

        .nav-item.active {
            color: #FFF;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
            border: 1px solid rgba(99, 102, 241, 0.25);
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.08);
        }

        .sidebar-footer {
            padding: 20px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.95rem;
            color: #FFF;
            border: 1px solid rgba(255, 255, 255, 0.15);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .user-company {
            font-size: 0.88rem;
            font-weight: 600;
            color: #FFF;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-role {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .btn-logout-sidebar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: rgba(239, 68, 68, 0.06);
            border: 1px solid rgba(239, 68, 68, 0.15);
            color: #FCA5A5;
            padding: 10px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .btn-logout-sidebar:hover {
            background-color: var(--danger-color);
            color: #FFF;
            border-color: var(--danger-color);
        }

        /* Main Workspace Area */
        .main-workspace {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Mobile Header */
        .mobile-header {
            display: none;
            background-color: rgba(17, 24, 39, 0.9);
            border-bottom: 1px solid var(--card-border);
            padding: 16px 24px;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 900;
            backdrop-filter: blur(16px);
        }

        .btn-menu-toggle {
            background: none;
            border: none;
            color: #FFF;
            font-size: 1.3rem;
            cursor: pointer;
        }

        /* Inner Page Layout */
        .workspace-content {
            padding: 40px;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            flex: 1;
        }

        .page-header {
            margin-bottom: 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page-title-desc h1 {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .page-title-desc p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Global Status Pills */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 99px;
            font-size: 0.82rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .pill-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: #FFB020;
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .pill-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: #34D399;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .pill-blocked {
            background-color: rgba(239, 68, 68, 0.1);
            color: #F87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        /* General Card Design */
        .panel-card {
            background-color: rgba(17, 24, 39, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            backdrop-filter: blur(16px);
            padding: 30px;
            margin-bottom: 28px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #FFF;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary-accent);
        }

        /* Banner Notifications */
        .alert-banner {
            border-radius: 16px;
            padding: 20px 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            line-height: 1.5;
            background-color: rgba(245, 158, 11, 0.06);
            border: 1px solid rgba(245, 158, 11, 0.15);
        }

        .alert-banner-icon {
            font-size: 1.4rem;
            color: var(--warning-color);
            margin-top: 2px;
        }

        .alert-banner-content {
            flex: 1;
        }

        .alert-banner-title {
            font-weight: 700;
            color: #FFF;
            margin-bottom: 4px;
            font-size: 0.98rem;
        }

        .alert-banner-desc {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        /* Tab Transition */
        .tab-section {
            animation: fadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .d-none {
            display: none !important;
        }

        /* Breadcrumb & Hero Header */
        .activation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            gap: 20px;
            flex-wrap: wrap;
        }
        .breadcrumb-nav {
            font-size: 0.82rem;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .breadcrumb-nav .active { color: var(--primary-accent); font-weight: 600; }
        .hero-title { font-size: 1.8rem; font-weight: 800; color: #FFF; margin-bottom: 6px; }
        .hero-subtitle { font-size: 0.95rem; color: var(--text-secondary); max-width: 680px; line-height: 1.5; }

        /* Large Subscription / Payment Hero Card */
        .activation-hero-card {
            background: linear-gradient(135deg, rgba(17, 24, 39, 0.95) 0%, rgba(15, 23, 42, 0.85) 100%);
            border: 1px solid rgba(99, 102, 241, 0.25);
            border-radius: 20px;
            padding: 36px;
            margin-bottom: 28px;
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            gap: 40px;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }
        .activation-hero-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #6366F1, #8B5CF6, #10B981);
        }

        .access-type-tag {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 6px 14px; border-radius: 20px; background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.25); font-size: 0.82rem; font-weight: 700;
            color: #A5B4FC; margin-bottom: 16px;
        }
        .access-type-tag.tag-active {
            background: rgba(16, 185, 129, 0.12); border-color: rgba(16, 185, 129, 0.25); color: #34D399;
        }

        .hero-card-heading { font-size: 1.5rem; font-weight: 800; color: #FFF; margin-bottom: 10px; }
        .hero-card-text { font-size: 0.95rem; color: var(--text-secondary); line-height: 1.55; margin-bottom: 24px; }

        .price-block { margin-bottom: 24px; }
        .price-val { font-size: 2.6rem; font-weight: 800; color: #FFF; font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.5px; }
        .price-sub { font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-top: 2px; }
        .price-note { font-size: 0.8rem; color: #64748B; margin-top: 4px; font-weight: 500; }

        .btn-pay-hero {
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            color: #FFF; border: none; border-radius: 12px; padding: 16px 32px;
            font-size: 1.05rem; font-weight: 800; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.35); transition: all 0.25s ease; width: 100%; max-width: 420px;
        }
        .btn-pay-hero:hover {
            transform: translateY(-2px); box-shadow: 0 12px 30px rgba(16, 185, 129, 0.5); filter: brightness(1.08);
        }
        .pay-trust-note { font-size: 0.82rem; color: var(--text-secondary); margin-top: 12px; display: flex; align-items: center; gap: 6px; }

        /* Right side benefits list */
        .benefits-title { font-size: 1rem; font-weight: 700; color: #FFF; margin-bottom: 16px; }
        .benefits-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
        .benefits-list li { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; color: #D1D5DB; }
        .benefits-list li i { color: var(--success-color); font-size: 0.95rem; }

        /* 3-Step Stepper Component */
        .stepper-card {
            background: rgba(17, 24, 39, 0.6); border: 1px solid var(--card-border);
            border-radius: 18px; padding: 24px 30px; margin-bottom: 28px;
            display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap;
        }
        .step-item { display: flex; align-items: center; gap: 16px; flex: 1; min-width: 200px; }
        .step-num {
            width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 1rem; font-weight: 800; font-family: monospace; border: 2px solid var(--card-border);
            background: rgba(255,255,255,0.03); color: var(--text-secondary); flex-shrink: 0;
        }
        .step-completed .step-num { background: rgba(16, 185, 129, 0.15); border-color: var(--success-color); color: #34D399; }
        .step-current .step-num { background: linear-gradient(135deg, var(--primary-accent), var(--secondary-accent)); border-color: transparent; color: #FFF; box-shadow: 0 0 15px rgba(99,102,241,0.5); }

        .step-title { font-size: 0.95rem; font-weight: 700; color: #FFF; }
        .step-desc { font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px; }

        .step-divider { width: 40px; height: 2px; background: var(--card-border); flex-shrink: 0; }
        @media (max-width: 992px) { .step-divider { display: none; } }

        /* Summary & Security Grid */
        .summary-trust-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        @media (max-width: 768px) { .summary-trust-grid { grid-template-columns: 1fr; } .activation-hero-card { grid-template-columns: 1fr; gap: 28px; padding: 24px; } }

        .summary-table { display: flex; flex-direction: column; gap: 12px; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 0.88rem; }
        .summary-row.total-row { border-bottom: none; padding-top: 8px; font-weight: 800; font-size: 1rem; }
        .s-label { color: var(--text-secondary); }
        .s-val { color: #FFF; font-weight: 600; }
        .s-val.badge-green { color: #34D399; background: rgba(16,185,129,0.12); padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; }
        .s-val.badge-yellow { color: #FBBF24; background: rgba(245,158,11,0.12); padding: 2px 8px; border-radius: 6px; font-size: 0.8rem; }
        .price-highlight { font-size: 1.3rem; color: #34D399; font-weight: 800; }

        .trust-indicators-grid { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px; }
        .trust-pill { background: rgba(255,255,255,0.04); border: 1px solid var(--card-border); padding: 8px 14px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: #D1D5DB; display: flex; align-items: center; gap: 6px; }
        .trust-pill i { color: var(--success-color); }

        /* Grid Metrics Rows */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 28px;
        }

        .metric-card {
            background-color: rgba(17, 24, 39, 0.65);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            backdrop-filter: blur(12px);
            position: relative;
            overflow: hidden;
            transition: transform 0.25s ease, border-color 0.25s ease;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            border-color: rgba(99, 102, 241, 0.3);
        }

        .metric-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .metric-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: #FFF;
        }

        .metric-desc {
            font-size: 0.78rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .metric-desc i {
            font-size: 0.7rem;
        }

        .metric-card-glow {
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-glow) 0%, transparent 100%);
            border-radius: 0 0 0 100%;
            pointer-events: none;
        }

        /* Info Lists / Key Values */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }

        .info-cell {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-bottom: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
        }

        .info-label {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-size: 0.98rem;
            font-weight: 500;
            color: #FFF;
        }

        /* Buttons & Interactions */
        .btn-primary-action {
            background: linear-gradient(135deg, var(--primary-accent) 0%, var(--secondary-accent) 100%);
            color: #FFF;
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.25);
            transition: all 0.25s ease;
            text-decoration: none;
        }

        .btn-primary-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
            filter: brightness(1.1);
        }

        .btn-outline-action {
            background-color: transparent;
            border: 1px solid var(--card-border);
            color: var(--text-secondary);
            border-radius: 10px;
            padding: 10px 18px;
            font-size: 0.88rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-outline-action:hover {
            border-color: rgba(255, 255, 255, 0.2);
            color: #FFF;
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Credentials Display Box */
        .key-input-wrapper {
            background-color: rgba(0, 0, 0, 0.25);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            max-width: 600px;
        }

        .key-content {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.95rem;
            color: var(--success-color);
            word-break: break-all;
            flex: 1;
            letter-spacing: 0.5px;
        }

        .key-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-key-icon {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1rem;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .btn-key-icon:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #FFF;
        }

        /* Data Tables */
        .logs-table-wrapper {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--card-border);
        }

        .logs-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.88rem;
        }

        .logs-table th {
            background-color: rgba(255, 255, 255, 0.015);
            color: var(--text-secondary);
            font-weight: 600;
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            text-transform: uppercase;
            font-size: 0.78rem;
            letter-spacing: 0.5px;
        }

        .logs-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-secondary);
        }

        .logs-table tbody tr {
            transition: background-color 0.15s ease;
        }

        .logs-table tbody tr:hover {
            background-color: rgba(255, 255, 255, 0.015);
        }

        /* Badge elements */
        .method-badge {
            font-weight: 700;
            font-size: 0.72rem;
            padding: 3px 8px;
            border-radius: 4px;
            display: inline-block;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .badge-post { background-color: rgba(99, 102, 241, 0.15); color: #818CF8; }
        .badge-get { background-color: rgba(16, 185, 129, 0.15); color: #34D399; }
        
        .status-badge {
            font-weight: 600;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .status-success { background-color: rgba(16, 185, 129, 0.1); color: #34D399; }
        .status-error { background-color: rgba(239, 68, 68, 0.1); color: #F87171; }

        /* Collapsible Log Payload block */
        .log-details-row {
            background-color: rgba(0, 0, 0, 0.15);
        }

        .payload-container {
            padding: 18px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .payload-container {
                grid-template-columns: 1fr;
            }
        }

        .payload-block {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .payload-title {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .payload-code {
            background-color: #05070B;
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 12px;
            font-family: monospace;
            font-size: 0.78rem;
            color: #C0CAF5;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }

        /* Settings Form CSS styling */
        .settings-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }

        @media (max-width: 768px) {
            .settings-form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-secondary);
        }

        .form-label span {
            color: var(--danger-color);
        }

        .form-control-glass {
            background-color: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            color: #FFF;
            padding: 12px 16px;
            font-size: 0.95rem;
            font-family: inherit;
            outline: none;
            transition: all 0.25s ease;
        }

        .form-control-glass:focus {
            background-color: rgba(255, 255, 255, 0.04);
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        /* API Reference Styling */
        .api-ref-container {
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        .endpoint-card {
            border: 1px solid var(--card-border);
            border-radius: 16px;
            background-color: rgba(17, 24, 39, 0.4);
            overflow: hidden;
        }

        .endpoint-header {
            padding: 16px 24px;
            background-color: rgba(255, 255, 255, 0.01);
            border-bottom: 1px solid var(--card-border);
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .endpoint-url {
            font-family: monospace;
            font-weight: 600;
            font-size: 0.95rem;
            color: #FFF;
        }

        .endpoint-desc {
            padding: 24px;
            font-size: 0.92rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .endpoint-docs-grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            border-top: 1px solid var(--card-border);
        }

        @media (max-width: 992px) {
            .endpoint-docs-grid {
                grid-template-columns: 1fr;
            }
        }

        .params-section {
            padding: 24px;
            border-right: 1px solid var(--card-border);
        }

        @media (max-width: 992px) {
            .params-section {
                border-right: none;
                border-bottom: 1px solid var(--card-border);
            }
        }

        .params-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #FFF;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .params-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
        }

        .params-table td {
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            vertical-align: top;
        }

        .param-name {
            font-family: monospace;
            color: var(--secondary-accent);
            font-weight: 600;
            width: 130px;
        }

        .param-type {
            color: var(--text-secondary);
            font-style: italic;
            width: 70px;
        }

        .param-desc {
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .param-required {
            color: var(--danger-color);
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
        }

        .code-section {
            padding: 24px;
            background-color: #060913;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .code-snippet-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .code-snippet-title {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .code-snippet-box {
            background-color: #03050B;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 14px;
            font-family: monospace;
            font-size: 0.78rem;
            color: #E2E8F0;
            white-space: pre-wrap;
            overflow-x: auto;
            position: relative;
            line-height: 1.5;
        }

        /* Toast notifications */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: linear-gradient(135deg, var(--primary-accent) 0%, var(--secondary-accent) 100%);
            color: #FFF;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.35);
            display: flex;
            align-items: center;
            gap: 10px;
            transform: translateY(120px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            z-index: 2000;
        }

        .toast-notification.show {
            transform: translateY(0);
            opacity: 1;
        }

        .toast-error {
            background: var(--danger-color);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.35);
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-workspace {
                margin-left: 0;
            }
            .mobile-header {
                display: flex;
            }
            .workspace-content {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="app-container">

        <!-- Left Navigation Sidebar -->
        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <i class="fa-solid fa-terminal brand-logo-icon"></i>
                <span class="brand-name">Rentox API</span>
            </div>

            <nav class="sidebar-nav">
                <a href="#overview" class="nav-item active" onclick="switchTab('#overview')">
                    <i class="fa-solid fa-chart-line"></i> Dashboard
                </a>
                <a href="#keys" class="nav-item" onclick="switchTab('#keys')">
                    <i class="fa-solid fa-key"></i> API Credentials
                </a>
                <a href="payments.php" class="nav-item">
                    <i class="fa-solid fa-credit-card"></i> Payments & Billing
                </a>
                <a href="test-trips.php" class="nav-item">
                    <i class="fa-solid fa-flask-vial"></i> Test Trips Simulator
                </a>
                <a href="#logs" class="nav-item" onclick="switchTab('#logs')">
                    <i class="fa-solid fa-terminal"></i> Activity Logs
                </a>
                <a href="#docs" class="nav-item" onclick="switchTab('#docs')">
                    <i class="fa-solid fa-book"></i> API Documentation
                </a>
                <a href="#settings" class="nav-item" onclick="switchTab('#settings')">
                    <i class="fa-solid fa-sliders"></i> Account Settings
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="user-avatar">
                        <?= strtoupper(substr($p['company_name'], 0, 2)) ?>
                    </div>
                    <div class="user-info">
                        <span class="user-company"><?= htmlspecialchars($p['company_name']) ?></span>
                        <span class="user-role">B2B Integration</span>
                    </div>
                </div>
                <a href="?action=logout" class="btn-logout-sidebar">
                    <i class="fa-solid fa-sign-out-alt"></i> Log Out
                </a>
            </div>
        </aside>

        <!-- Main Workspace -->
        <main class="main-workspace">
            
            <!-- Mobile Header Bar -->
            <header class="mobile-header">
                <button class="btn-menu-toggle" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars" id="menuIcon"></i>
                </button>
                <div style="display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-terminal brand-logo-icon" style="font-size:1.3rem;"></i>
                    <span class="brand-name" style="font-size:1.1rem;">Rentox API</span>
                </div>
                <div class="user-avatar" style="width:32px; height:32px; font-size:0.75rem;">
                    <?= strtoupper(substr($p['company_name'], 0, 2)) ?>
                </div>
            </header>

            <div class="workspace-content">

                <!-- 1. OVERVIEW TAB PANEL -->
                <section class="tab-section" id="tab-overview">
                    
                    <!-- 1. Breadcrumb & Subscription Header -->
                    <div class="activation-header">
                        <div>
                            <div class="breadcrumb-nav">
                                <span>Dashboard</span> <i class="fa-solid fa-chevron-right" style="font-size:0.7rem;"></i> <span class="active">API Activation</span>
                            </div>
                            <h1 class="hero-title">Activate Your API Access</h1>
                            <p class="hero-subtitle">Complete your one-time activation payment to unlock production API access and start integrating with our platform.</p>
                        </div>
                        <div>
                            <?php if ($p['status'] === 'pending'): ?>
                                <span class="status-pill pill-pending"><i class="fa-solid fa-hourglass-half"></i> Pending Review</span>
                            <?php elseif ($p['status'] === 'blocked'): ?>
                                <span class="status-pill pill-blocked"><i class="fa-solid fa-ban"></i> Access Blocked</span>
                            <?php elseif (($p['payment_status'] ?? '') === 'paid' || $p['status'] === 'active'): ?>
                                <span class="status-pill pill-active"><i class="fa-solid fa-circle-check"></i> API Access Activated</span>
                            <?php else: ?>
                                <span class="status-pill pill-active" style="background:rgba(16,185,129,0.15); color:#34D399; border:1px solid rgba(16,185,129,0.3);"><i class="fa-solid fa-circle-check"></i> Account Approved</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php 
                    $is_partner_paid = (($p['payment_status'] ?? '') === 'paid' || $p['status'] === 'active');
                    if (!$is_partner_paid): 
                    ?>
                        <!-- 2. Subscription / Payment Hero Card -->
                        <div class="activation-hero-card">
                            <div class="hero-card-left">
                                <div class="access-type-tag">
                                    <i class="fa-solid fa-bolt" style="color:var(--warning-color);"></i> Production API Access
                                </div>
                                <h2 class="hero-card-heading">Production API Access</h2>
                                <p class="hero-card-text">
                                    Your partner account has been approved. Activate your production API credentials with a one-time payment.
                                </p>

                                <div class="price-block">
                                    <div class="price-val">₹<?= number_format($activation_fee) ?></div>
                                    <div class="price-sub">One-time activation fee</div>
                                    <div class="price-note">No monthly subscription • One-time payment</div>
                                </div>

                                <button class="btn-pay-hero" onclick="payWithRazorpay()" id="btnPayHero">
                                    <i class="fa-solid fa-shield-halved"></i> Pay ₹<?= number_format($activation_fee) ?> & Activate API
                                </button>
                                <div class="pay-trust-note">
                                    <i class="fa-solid fa-lock"></i> Secure payment powered by Razorpay
                                </div>
                            </div>

                            <div class="hero-card-right">
                                <h4 class="benefits-title">What's included:</h4>
                                <ul class="benefits-list">
                                    <li><i class="fa-solid fa-circle-check"></i> Production API access</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Live API keys</li>
                                    <li><i class="fa-solid fa-circle-check"></i> API authentication</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Partner API documentation</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Request monitoring</li>
                                    <li><i class="fa-solid fa-circle-check"></i> API usage statistics</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Technical integration support</li>
                                </ul>
                            </div>
                        </div>

                        <!-- 3. Activation Progress (3-Step Stepper) -->
                        <div class="stepper-card">
                            <div class="step-item step-completed">
                                <div class="step-num"><i class="fa-solid fa-check"></i></div>
                                <div class="step-content">
                                    <div class="step-title">01 • Account Approved</div>
                                    <div class="step-desc">Your partner account has been approved.</div>
                                </div>
                            </div>
                            <div class="step-divider"></div>
                            <div class="step-item step-current">
                                <div class="step-num">02</div>
                                <div class="step-content">
                                    <div class="step-title">Payment</div>
                                    <div class="step-desc">Complete the ₹<?= number_format($activation_fee) ?> activation payment.</div>
                                </div>
                            </div>
                            <div class="step-divider"></div>
                            <div class="step-item step-pending">
                                <div class="step-num">03</div>
                                <div class="step-content">
                                    <div class="step-title">API Activated</div>
                                    <div class="step-desc">Production API keys become available.</div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Payment Summary & Trust / Security Row -->
                        <div class="summary-trust-grid">
                            <!-- Payment Summary Card -->
                            <div class="panel-card summary-card" style="margin-bottom:0;">
                                <h3 class="card-title" style="margin-bottom:20px;">
                                    <i class="fa-solid fa-receipt" style="color:var(--primary-accent);"></i> Activation Summary
                                </h3>
                                <div class="summary-table">
                                    <div class="summary-row">
                                        <span class="s-label">Account Status</span>
                                        <span class="s-val badge-green">Approved</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="s-label">Activation Fee</span>
                                        <span class="s-val">₹<?= number_format($activation_fee) ?></span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="s-label">Billing Type</span>
                                        <span class="s-val">One-time payment</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="s-label">Payment Status</span>
                                        <span class="s-val badge-yellow">Pending</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="s-label">API Environment</span>
                                        <span class="s-val">Production</span>
                                    </div>
                                    <div class="summary-row total-row">
                                        <span class="s-label">Total Payable</span>
                                        <span class="s-val price-highlight">₹<?= number_format($activation_fee) ?></span>
                                    </div>
                                </div>
                                <button class="btn-pay-hero" onclick="payWithRazorpay()" style="width:100%; margin-top:20px; font-size:0.95rem; padding:12px;">
                                    <i class="fa-solid fa-bolt"></i> Pay & Activate
                                </button>
                            </div>

                            <!-- Trust / Security Card -->
                            <div class="panel-card trust-card" style="margin-bottom:0;">
                                <h3 class="card-title" style="margin-bottom:14px; color:var(--success-color);">
                                    <i class="fa-solid fa-shield-halved"></i> Your payment is secure
                                </h3>
                                <p style="color:var(--text-secondary); font-size:0.88rem; line-height:1.6; margin-bottom:24px;">
                                    Payments are securely processed through Razorpay. Your payment information is handled by the payment provider and is not stored on our platform.
                                </p>
                                <div class="trust-indicators-grid">
                                    <div class="trust-pill"><i class="fa-solid fa-lock"></i> Secure Payment</div>
                                    <div class="trust-pill"><i class="fa-solid fa-circle-check"></i> Verified Account</div>
                                    <div class="trust-pill"><i class="fa-solid fa-bolt"></i> Instant Activation</div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- 7. Paid / Active State Hero Card -->
                        <div class="activation-hero-card active-state-card">
                            <div class="hero-card-left">
                                <div class="access-type-tag tag-active">
                                    <i class="fa-solid fa-circle-check" style="color:var(--success-color);"></i> Production API Active
                                </div>
                                <h2 class="hero-card-heading">API Access Activated</h2>
                                <p class="hero-card-text">
                                    Your production API access is now active.
                                </p>

                                <div style="margin:20px 0;">
                                    <span class="info-label" style="display:block; margin-bottom:8px;">Production API Key</span>
                                    <div class="key-input-wrapper" style="max-width:440px;">
                                        <code class="key-content" id="overviewApiKeyPlain">••••••••••••••••••••••••••••••••••••••••</code>
                                        <div class="key-actions">
                                            <button class="btn-key-icon" onclick="toggleKeyVisibility('overviewApiKeyPlain', '<?= htmlspecialchars($p['api_key']) ?>', this)" title="Show/Hide">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-key-icon" onclick="copyToClipboard('<?= htmlspecialchars($p['api_key']) ?>')" title="Copy to Clipboard">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:24px;">
                                    <button class="btn-primary-action" onclick="switchTab('#keys')">
                                        <i class="fa-solid fa-key"></i> View API Keys
                                    </button>
                                    <button class="btn-outline-action" onclick="switchTab('#docs')">
                                        <i class="fa-solid fa-book"></i> View Documentation
                                    </button>
                                </div>

                                <div class="price-note" style="margin-top:20px; font-size:0.85rem; color:var(--text-secondary);">
                                    Activated on: <strong><?= !empty($p['paid_at']) ? date('d M Y', strtotime($p['paid_at'])) : date('d M Y') ?></strong>
                                </div>
                            </div>

                            <div class="hero-card-right">
                                <h4 class="benefits-title">What's included:</h4>
                                <ul class="benefits-list">
                                    <li><i class="fa-solid fa-circle-check"></i> Production API access (Active)</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Live API keys</li>
                                    <li><i class="fa-solid fa-circle-check"></i> API authentication</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Partner API documentation</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Request monitoring</li>
                                    <li><i class="fa-solid fa-circle-check"></i> API usage statistics</li>
                                    <li><i class="fa-solid fa-circle-check"></i> Technical integration support</li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 6. Existing Dashboard Metrics (Moved & Renamed) -->
                    <div style="margin: 36px 0 20px;">
                        <h3 style="font-size:1.15rem; font-weight:800; color:#FFF; display:flex; align-items:center; gap:10px;">
                            <i class="fa-solid fa-chart-line" style="color:var(--primary-accent);"></i> API Usage Overview
                        </h3>
                    </div>

                    <!-- Metrics Stats Grid -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">Integration Status</span>
                            <span class="metric-value" style="font-size:1.2rem; margin-top:6px; font-weight:700;">
                                <?php if ($p['status'] === 'pending'): ?>
                                    Under Review
                                <?php elseif ($p['status'] === 'blocked'): ?>
                                    Suspended
                                <?php else: ?>
                                    Live / Active
                                <?php endif; ?>
                            </span>
                            <span class="metric-desc">
                                <?php if ($p['status'] === 'pending'): ?>
                                    <i class="fa-solid fa-circle" style="color:var(--warning-color)"></i> Pending administrator activation
                                <?php elseif ($p['status'] === 'blocked'): ?>
                                    <i class="fa-solid fa-circle" style="color:var(--danger-color)"></i> Integration access suspended
                                <?php else: ?>
                                    <i class="fa-solid fa-circle" style="color:var(--success-color)"></i> API requests authorized
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">Total Requests</span>
                            <span class="metric-value" style="font-size:1.5rem;"><?= number_format($total_requests) ?></span>
                            <span class="metric-desc"><i class="fa-solid fa-chart-bar"></i> Total lifetime API calls</span>
                        </div>

                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">API Success Rate</span>
                            <span class="metric-value" style="font-size:1.5rem;"><?= $success_rate ?>%</span>
                            <span class="metric-desc"><i class="fa-solid fa-circle-check" style="color:var(--success-color)"></i> Successful responses</span>
                        </div>

                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">Requests Today</span>
                            <span class="metric-value" style="font-size:1.5rem;"><?= number_format($today_requests) ?></span>
                            <span class="metric-desc"><i class="fa-solid fa-bolt" style="color:var(--primary-accent)"></i> Since 12:00 AM local</span>
                        </div>
                    </div>

                    <!-- Profile Details Card -->
                    <div class="panel-card">
                        <div class="card-header-flex">
                            <h3 class="card-title"><i class="fa-solid fa-building"></i> Partner Company Information</h3>
                            <button class="btn-outline-action" onclick="switchTab('#settings')">
                                <i class="fa-solid fa-user-pen"></i> Edit Profile
                            </button>
                        </div>
                        
                        <div class="info-grid">
                            <div class="info-cell">
                                <span class="info-label">Partner Legal Name</span>
                                <span class="info-value"><?= htmlspecialchars($p['partner_name'] ?? 'Not set') ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Brand Name / Agency</span>
                                <span class="info-value"><?= htmlspecialchars($p['company_name']) ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Company Owner</span>
                                <span class="info-value"><?= htmlspecialchars($p['company_owner_name'] ?? 'Not set') ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">GST / Tax Registration</span>
                                <span class="info-value" style="font-family:monospace; color:var(--primary-accent);"><?= htmlspecialchars($p['gst_number'] ?? 'Not set') ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Contact Person</span>
                                <span class="info-value"><?= htmlspecialchars($p['contact_person']) ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Contact Mobile</span>
                                <span class="info-value"><?= htmlspecialchars($p['mobile_number'] ?? 'Not set') ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Authorized Business Email</span>
                                <span class="info-value"><?= htmlspecialchars($p['email']) ?></span>
                            </div>
                            <div class="info-cell">
                                <span class="info-label">Partner Account Created</span>
                                <span class="info-value"><?= date('d F Y', strtotime($p['created_at'])) ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 2. API KEYS TAB PANEL -->
                <section class="tab-section d-none" id="tab-keys">
                    <div class="page-header">
                        <div class="page-title-desc">
                            <h1>API Credentials</h1>
                            <p>Secure keys to authenticate your B2B console integrations.</p>
                        </div>
                    </div>

                    <div class="panel-card">
                        <?php if ($p['status'] === 'pending'): ?>
                            <div style="text-align: center; padding: 40px 10px;">
                                <i class="fa-solid fa-lock" style="font-size: 3.5rem; margin-bottom: 20px; color: var(--text-secondary); opacity: 0.35;"></i>
                                <h3 style="font-size: 1.25rem; margin-bottom: 8px;">API Access Under Review</h3>
                                <p style="color: var(--text-secondary); max-width: 460px; margin: 0 auto 24px; font-size: 0.95rem;">Your credential keys will be generated automatically and shown here as soon as the administrator approves your partner request.</p>
                                <span class="status-pill pill-pending"><i class="fa-solid fa-hourglass-half"></i> Awaiting Verification</span>
                            </div>
                        <?php else: ?>
                            <!-- 1. SANDBOX TEST CREDENTIALS -->
                            <div style="margin-bottom: 36px; padding-bottom: 28px; border-bottom: 1px dashed rgba(255, 255, 255, 0.1);">
                                <h3 class="card-title" style="margin-bottom: 8px; color: var(--warning-color); display:flex; align-items:center; gap:10px;">
                                    <i class="fa-solid fa-vial-circle-check"></i> 🧪 Sandbox Test Credentials (Development Mode)
                                </h3>
                                <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 20px;">
                                    Use these Sandbox credentials to safely test your web integration. Bookings created with <code style="color:var(--warning-color)">TEST_</code> keys generate test trips (e.g. <code style="color:var(--warning-color)">TEST-PB...</code>) and <strong>do not dispatch real drivers</strong>.
                                </p>

                                <div style="margin-bottom: 20px;">
                                    <span class="info-label" style="margin-bottom: 8px; display:block;">Sandbox X-API-Key (Testing)</span>
                                    <div class="key-input-wrapper" style="border-color: rgba(245, 158, 11, 0.3);">
                                        <code class="key-content" id="testApiKeyPlain" style="color: var(--warning-color);">••••••••••••••••••••••••••••••••••••••••</code>
                                        <div class="key-actions">
                                            <button class="btn-key-icon" onclick="toggleKeyVisibility('testApiKeyPlain', 'TEST_<?= htmlspecialchars($p['api_key']) ?>', this)" title="Show/Hide">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-key-icon" onclick="copyToClipboard('TEST_<?= htmlspecialchars($p['api_key']) ?>')" title="Copy to Clipboard">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <span class="info-label" style="margin-bottom: 8px; display:block;">Sandbox X-Secret-Key</span>
                                    <div class="key-input-wrapper" style="border-color: rgba(245, 158, 11, 0.3);">
                                        <code class="key-content" id="testSecretKeyPlain" style="color: var(--warning-color);">••••••••••••••••••••••••••••••••••••••••</code>
                                        <div class="key-actions">
                                            <button class="btn-key-icon" onclick="toggleKeyVisibility('testSecretKeyPlain', '<?= htmlspecialchars($p['secret_key']) ?>', this)" title="Show/Hide">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-key-icon" onclick="copyToClipboard('<?= htmlspecialchars($p['secret_key']) ?>')" title="Copy to Clipboard">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. LIVE PRODUCTION CREDENTIALS -->
                            <h3 class="card-title" style="margin-bottom: 8px;"><i class="fa-solid fa-shield-halved"></i> 🚀 Live Access Keys (Production Mode)</h3>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 20px;">
                                Use these Live production keys on your live website. All bookings created with these keys dispatch real drivers and trigger live driver alerts.
                            </p>

                            <?php 
                            $is_partner_paid = (($p['payment_status'] ?? '') === 'paid' || $p['status'] === 'active');
                            if (!$is_partner_paid): 
                            ?>
                                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 14px; padding: 20px 24px; margin-top: 10px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
                                    <div style="display:flex; align-items:flex-start; gap:14px;">
                                        <i class="fa-solid fa-lock" style="color: var(--warning-color); font-size: 1.6rem; margin-top: 2px;"></i>
                                        <div>
                                            <h4 style="color:#FFF; font-weight:700; font-size:0.98rem; margin-bottom:4px;">Live Production Keys Locked</h4>
                                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom:0;">Complete your <strong>₹<?= number_format($activation_fee, 2) ?> Setup Payment</strong> to activate Live Production Access Keys.</p>
                                        </div>
                                    </div>
                                    <a href="payments.php" class="btn-primary-action" style="background: linear-gradient(135deg, #10B981, #059669); font-size:0.88rem; padding:9px 18px;">
                                        <i class="fa-solid fa-credit-card"></i> Complete Setup Payment
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="margin-bottom: 28px;">
                                    <span class="info-label" style="margin-bottom: 8px; display:block;">Production X-API-Key</span>
                                    <div class="key-input-wrapper">
                                        <code class="key-content" id="apiKeyPlain">••••••••••••••••••••••••••••••••••••••••</code>
                                        <div class="key-actions">
                                            <button class="btn-key-icon" onclick="toggleKeyVisibility('apiKeyPlain', '<?= htmlspecialchars($p['api_key']) ?>', this)" title="Show/Hide">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-key-icon" onclick="copyToClipboard('<?= htmlspecialchars($p['api_key']) ?>')" title="Copy to Clipboard">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div style="margin-bottom: 28px;">
                                    <span class="info-label" style="margin-bottom: 8px; display:block;">Production X-Secret-Key</span>
                                    <div class="key-input-wrapper">
                                        <code class="key-content" id="secretKeyPlain">••••••••••••••••••••••••••••••••••••••••</code>
                                        <div class="key-actions">
                                            <button class="btn-key-icon" onclick="toggleKeyVisibility('secretKeyPlain', '<?= htmlspecialchars($p['secret_key']) ?>', this)" title="Show/Hide">
                                                <i class="fa-solid fa-eye"></i>
                                            </button>
                                            <button class="btn-key-icon" onclick="copyToClipboard('<?= htmlspecialchars($p['secret_key']) ?>')" title="Copy to Clipboard">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div style="background: rgba(99, 102, 241, 0.04); border: 1px solid rgba(99, 102, 241, 0.15); border-radius: 12px; padding: 18px; display:flex; gap:14px; align-items:flex-start; margin-top:24px;">
                                <i class="fa-solid fa-circle-info" style="color:var(--primary-accent); font-size:1.2rem; margin-top:2px;"></i>
                                <div style="font-size: 0.88rem; line-height: 1.5; color: var(--text-secondary);">
                                    <p style="color:#FFF; font-weight:600; margin-bottom:4px;">Rate Limits Authorized:</p>
                                    Requests are capped at <strong><?= $p['rate_limit_per_minute'] ?> per minute</strong> and <strong><?= number_format($p['rate_limit_per_day']) ?> per day</strong>. Please ensure the authentication parameters are provided inside the HTTP headers of all API payloads.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 3. ACTIVITY LOGS TAB PANEL -->
                <section class="tab-section d-none" id="tab-logs">
                    <div class="page-header">
                        <div class="page-title-desc">
                            <h1>Developer Activity Logs</h1>
                            <p>Real-time log of the last 15 requests processed by Rentox API engine.</p>
                        </div>
                    </div>

                    <div class="panel-card">
                        <h3 class="card-title" style="margin-bottom: 24px;"><i class="fa-solid fa-terminal"></i> Requests Monitor</h3>
                        
                        <?php if (empty($recent_logs)): ?>
                            <div style="text-align: center; padding: 40px 10px; color: var(--text-secondary);">
                                <i class="fa-solid fa-clock-rotate-left" style="font-size: 3rem; opacity: 0.25; margin-bottom: 16px;"></i>
                                <p>No API transactions recorded yet.</p>
                                <p style="font-size: 0.85rem; margin-top: 4px;">Integrate endpoints to start monitoring live connection traffic.</p>
                            </div>
                        <?php else: ?>
                            <div class="logs-table-wrapper">
                                <table class="logs-table">
                                    <thead>
                                        <tr>
                                            <th>Status</th>
                                            <th>Method</th>
                                            <th>Endpoint</th>
                                            <th>Client IP</th>
                                            <th>Timestamp</th>
                                            <th style="text-align:right;">Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_logs as $idx => $log): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($log['status'] === 'success'): ?>
                                                        <span class="status-badge status-success"><i class="fa-solid fa-circle-check"></i> Success</span>
                                                    <?php else: ?>
                                                        <span class="status-badge status-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($log['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="method-badge <?= strtolower($log['method']) === 'post' ? 'badge-post' : 'badge-get' ?>">
                                                        <?= htmlspecialchars($log['method']) ?>
                                                    </span>
                                                </td>
                                                <td style="font-family:monospace; color:#FFF;"><?= htmlspecialchars($log['api_name']) ?></td>
                                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                                <td><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
                                                <td style="text-align:right;">
                                                    <button class="btn-outline-action" style="padding:6px 12px; font-size:0.78rem; border-radius:6px;" onclick="toggleLogPayload('log-row-<?= $idx ?>', this)">
                                                        <i class="fa-solid fa-chevron-down"></i> Expand
                                                    </button>
                                                </td>
                                            </tr>
                                            <!-- Collapsible Details Row -->
                                            <tr class="log-details-row d-none" id="log-row-<?= $idx ?>">
                                                <td colspan="6">
                                                    <div class="payload-container">
                                                        <div class="payload-block">
                                                            <span class="payload-title">Request Payload</span>
                                                            <pre class="payload-code"><code><?php
                                                                if (!empty($log['request_data'])) {
                                                                    $dec = json_decode($log['request_data'], true);
                                                                    echo htmlspecialchars($dec ? json_encode($dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $log['request_data']);
                                                                } else {
                                                                    echo "None";
                                                                }
                                                            ?></code></pre>
                                                        </div>
                                                        <div class="payload-block">
                                                            <span class="payload-title">Response Payload</span>
                                                            <pre class="payload-code"><code><?php
                                                                if (!empty($log['response_data'])) {
                                                                    $dec = json_decode($log['response_data'], true);
                                                                    echo htmlspecialchars($dec ? json_encode($dec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : $log['response_data']);
                                                                } else {
                                                                    echo "None";
                                                                }
                                                            ?></code></pre>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- 4. API DOCUMENTATION TAB PANEL -->
                <section class="tab-section d-none" id="tab-docs">
                    <div class="page-header">
                        <div class="page-title-desc">
                            <h1>API Documentation & Integration Guide</h1>
                            <p>Complete technical guide for headers, authentication modes, endpoints, code snippets, and error handling.</p>
                        </div>
                    </div>

                    <div class="api-ref-container">

                        <!-- 1. Connection & Header Parameters -->
                        <div class="panel-card" style="margin-bottom:24px;">
                            <h3 class="card-title" style="margin-bottom:14px;"><i class="fa-solid fa-network-wired" style="color:var(--primary-accent);"></i> Base Connection & Required HTTP Headers</h3>
                            <p style="color:var(--text-secondary); font-size:0.92rem; line-height:1.6; margin-bottom:16px;">
                                All API requests must be sent over HTTPS to our primary endpoint domain. Your request must include authentication headers on every single request.
                            </p>
                            <div class="key-input-wrapper" style="max-width:100%; margin-bottom:20px;">
                                <code class="key-content" style="color:#FFF; font-weight:700;">https://agnicarrental.com/admin2025/partner/api</code>
                                <button class="btn-key-icon" onclick="copyToClipboard('https://agnicarrental.com/admin2025/partner/api')" title="Copy Base URL">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>

                            <h4 style="font-size:1rem; margin-bottom:12px; color:#FFF;">Required HTTP Headers Table</h4>
                            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:12px; overflow:hidden; margin-bottom:16px;">
                                <table class="custom-table" style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:rgba(255,255,255,0.03);">
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Header Name</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Type</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Required</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Example Header Value</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Description</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><code style="color:var(--primary-accent); font-weight:700;">X-API-Key</code></td>
                                            <td style="padding:14px 16px;">String</td>
                                            <td style="padding:14px 16px;"><span style="color:var(--danger-color); font-weight:700;">REQUIRED</span></td>
                                            <td style="padding:14px 16px;"><code style="font-size:0.82rem; color:#A5B4FC;"><?= htmlspecialchars($p['api_key'] ?? 'SDFFDDF_45CFF4A65129DCF...') ?></code></td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Your B2B Partner API Access Key. Use <code style="color:var(--warning-color)">TEST_</code> prefix for Sandbox.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><code style="color:var(--primary-accent); font-weight:700;">X-Secret-Key</code></td>
                                            <td style="padding:14px 16px;">String</td>
                                            <td style="padding:14px 16px;"><span style="color:var(--danger-color); font-weight:700;">REQUIRED</span></td>
                                            <td style="padding:14px 16px;"><code style="font-size:0.82rem; color:#A5B4FC;"><?= htmlspecialchars($p['secret_key'] ?? '715a8d6f8c1e9442a6...') ?></code></td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Your B2B Partner API Secret Key for request authentication.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><code style="color:var(--success-color); font-weight:700;">Content-Type</code></td>
                                            <td style="padding:14px 16px;">String</td>
                                            <td style="padding:14px 16px;"><span style="color:var(--warning-color); font-weight:700;">POST Requests</span></td>
                                            <td style="padding:14px 16px;"><code style="font-size:0.82rem;">application/json</code></td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Mandatory for all POST request payloads. Body must be valid JSON.</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:14px 16px;"><code style="color:var(--secondary-accent); font-weight:700;">Accept</code></td>
                                            <td style="padding:14px 16px;">String</td>
                                            <td style="padding:14px 16px;"><span style="color:var(--text-secondary)">Optional</span></td>
                                            <td style="padding:14px 16px;"><code style="font-size:0.82rem;">application/json</code></td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Informs the server to return JSON-formatted responses.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 2. Sandbox vs Production Environment Header Rules -->
                        <div class="panel-card" style="margin-bottom:24px; border-color:rgba(99,102,241,0.3); background:linear-gradient(135deg, rgba(99,102,241,0.06), rgba(17,24,39,0.9));">
                            <h3 class="card-title" style="margin-bottom:14px;"><i class="fa-solid fa-sliders" style="color:var(--warning-color);"></i> Sandbox vs Production Environment Setup</h3>
                            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:20px; margin-top:16px;">
                                <div style="background:rgba(245,158,11,0.08); border:1px solid rgba(245,158,11,0.25); border-radius:12px; padding:20px;">
                                    <h4 style="color:var(--warning-color); font-size:1.05rem; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                                        <i class="fa-solid fa-vial"></i> 🧪 1. Sandbox Test Mode
                                    </h4>
                                    <p style="font-size:0.88rem; color:var(--text-secondary); line-height:1.5; margin-bottom:12px;">
                                        Prefix your <code style="color:#FFF">X-API-Key</code> with <strong><code>TEST_</code></strong>:
                                    </p>
                                    <code style="display:block; background:rgba(0,0,0,0.4); padding:8px 12px; border-radius:6px; font-size:0.8rem; color:var(--warning-color); font-family:var(--font-mono); margin-bottom:12px;">
                                        X-API-Key: TEST_<?= htmlspecialchars($p['api_key'] ?? 'SDFFDDF_45CFF4A65129DCF...') ?>
                                    </code>
                                    <ul style="font-size:0.85rem; color:var(--text-secondary); padding-left:18px; line-height:1.6;">
                                        <li>Bypasses city bounds (allows worldwide location testing).</li>
                                        <li>Generates test trip reference numbers (e.g. <code>TEST-PB...</code>).</li>
                                        <li>Does <strong>not</strong> notify live drivers or incur real charges.</li>
                                    </ul>
                                </div>

                                <div style="background:rgba(16,185,129,0.08); border:1px solid rgba(16,185,129,0.25); border-radius:12px; padding:20px;">
                                    <h4 style="color:var(--success-color); font-size:1.05rem; margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                                        <i class="fa-solid fa-rocket"></i> 🚀 2. Live Production Mode
                                    </h4>
                                    <p style="font-size:0.88rem; color:var(--text-secondary); line-height:1.5; margin-bottom:12px;">
                                        Use your standard Production Key <strong>without</strong> the <code>TEST_</code> prefix:
                                    </p>
                                    <code style="display:block; background:rgba(0,0,0,0.4); padding:8px 12px; border-radius:6px; font-size:0.8rem; color:var(--success-color); font-family:var(--font-mono); margin-bottom:12px;">
                                        X-API-Key: <?= htmlspecialchars($p['api_key'] ?? 'SDFFDDF_45CFF4A65129DCF...') ?>
                                    </code>
                                    <ul style="font-size:0.85rem; color:var(--text-secondary); padding-left:18px; line-height:1.6;">
                                        <li>Enforces city boundaries & active fleet availability.</li>
                                        <li>Generates real booking reference numbers (e.g. <code>PB...</code>).</li>
                                        <li>Dispatches <strong>real-time push notifications</strong> to live vendor drivers.</li>
                                        <li>Requires completed ₹10,000 API activation payment.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- 3. API Endpoints Reference -->

                        <!-- Endpoint 1: Get Fare -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-get">GET</span>
                                <span class="endpoint-url">/get-fare.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Calculate trip fare estimates, kilometer rates, driver allowances, toll estimates, and GST tax breakdowns.
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Query Parameters</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">from_address <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Pickup location city or address</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">to_address <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Drop location city or address</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">trip_type <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">"One-way" or "Round-trip"</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">car_type <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">"Sedan", "Ertiga", "Innova", "Crysta"</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">distance_km <span class="param-required">*</span></td>
                                            <td class="param-type">float</td>
                                            <td class="param-desc">Trip distance in kilometers</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Request</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X GET \&quot;https://agnicarrental.com/admin2025/partner/api/get-fare.php?from_address=Mumbai&to_address=Pune&trip_type=One-way&car_type=Sedan&distance_km=147.9\&quot; \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot;')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X GET "https://agnicarrental.com/admin2025/partner/api/get-fare.php?from_address=Mumbai&to_address=Pune&trip_type=One-way&car_type=Sedan&distance_km=147.9" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY"</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint 2: Book Cab -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-post">POST</span>
                                <span class="endpoint-url">/book-cab.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Create a new cab booking for a customer and dispatch live driver push notifications.
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Body Parameters (JSON)</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">from_address <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Pickup address location</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">to_address <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Drop destination address</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">trip_type <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">"One-way" or "Round-trip"</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">car_type <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">"Sedan", "Ertiga", "Innova", "Crysta"</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">user_name <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Passenger full name</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">user_mobile <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">10-digit mobile number</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">partner_booking_ref <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Your internal reference ID</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Request</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X POST https://agnicarrental.com/admin2025/partner/api/book-cab.php \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot; \\\n  -H \&quot;Content-Type: application/json\&quot; \\\n  -d \'{\n    \&quot;from_address\&quot;: \&quot;Mumbai Airport, Mumbai\&quot;,\n    \&quot;to_address\&quot;: \&quot;Pune Railway Station, Pune\&quot;,\n    \&quot;trip_type\&quot;: \&quot;One-way\&quot;,\n    \&quot;car_type\&quot;: \&quot;Sedan\&quot;,\n    \&quot;distance_km\&quot;: 147.9,\n    \&quot;date\&quot;: \&quot;2026-08-15\&quot;,\n    \&quot;time\&quot;: \&quot;10:00\&quot;,\n    \&quot;user_name\&quot;: \&quot;Ramesh Kumar\&quot;,\n    \&quot;user_mobile\&quot;: \&quot;9876543210\&quot;,\n    \&quot;partner_booking_ref\&quot;: \&quot;REF-783990\&quot;\n  }\'\')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X POST https://agnicarrental.com/admin2025/partner/api/book-cab.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "from_address": "Mumbai Airport, Mumbai",
    "to_address": "Pune Railway Station, Pune",
    "trip_type": "One-way",
    "car_type": "Sedan",
    "distance_km": 147.9,
    "date": "2026-08-15",
    "time": "10:00",
    "user_name": "Ramesh Kumar",
    "user_mobile": "9876543210",
    "partner_booking_ref": "REF-783990"
  }'</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint 3: Booking Status -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-get">GET</span>
                                <span class="endpoint-url">/booking-status.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Fetch real-time status of a booking (Pending, Accepted, Started, Completed, Cancelled).
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Query Parameters</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">booking_id <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Booking ID (e.g. PB85AE2481640)</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Request</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X GET \&quot;https://agnicarrental.com/admin2025/partner/api/booking-status.php?booking_id=PB85AE2481640\&quot; \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot;')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X GET "https://agnicarrental.com/admin2025/partner/api/booking-status.php?booking_id=PB85AE2481640" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY"</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Multi-Language Code Snippets -->
                        <div class="panel-card" style="margin-bottom:24px;">
                            <h3 class="card-title" style="margin-bottom:16px;"><i class="fa-solid fa-code" style="color:var(--secondary-accent);"></i> Multi-Language Code Examples</h3>
                            
                            <h4 style="font-size:0.95rem; color:#FFF; margin-bottom:8px;">Node.js / JavaScript (Fetch API):</h4>
                            <pre class="code-snippet-box" style="margin-bottom:20px;"><code>const response = await fetch('https://agnicarrental.com/admin2025/partner/api/get-fare.php?from_address=Mumbai&to_address=Pune&trip_type=One-way&car_type=Sedan&distance_km=147.9', {
    method: 'GET',
    headers: {
        'X-API-Key': 'YOUR_API_KEY',
        'X-Secret-Key': 'YOUR_SECRET_KEY'
    }
});
const data = await response.json();
console.log(data);</code></pre>

                            <h4 style="font-size:0.95rem; color:#FFF; margin-bottom:8px;">Python (Requests):</h4>
                            <pre class="code-snippet-box" style="margin-bottom:20px;"><code>import requests

headers = {
    'X-API-Key': 'YOUR_API_KEY',
    'X-Secret-Key': 'YOUR_SECRET_KEY',
    'Content-Type': 'application/json'
}

response = requests.get(
    'https://agnicarrental.com/admin2025/partner/api/get-fare.php',
    params={'from_address': 'Mumbai', 'to_address': 'Pune', 'trip_type': 'One-way', 'car_type': 'Sedan', 'distance_km': 147.9},
    headers=headers
)
print(response.json())</code></pre>

                            <h4 style="font-size:0.95rem; color:#FFF; margin-bottom:8px;">PHP (cURL):</h4>
                            <pre class="code-snippet-box"><code>$ch = curl_init("https://agnicarrental.com/admin2025/partner/api/get-fare.php?from_address=Mumbai&to_address=Pune&trip_type=One-way&car_type=Sedan&distance_km=147.9");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: YOUR_API_KEY',
    'X-Secret-Key: YOUR_SECRET_KEY'
]);
$result = curl_exec($ch);
curl_close($ch);
$data = json_decode($result, true);</code></pre>
                        </div>

                        <!-- 5. HTTP Response & Error Codes -->
                        <div class="panel-card">
                            <h3 class="card-title" style="margin-bottom:16px;"><i class="fa-solid fa-triangle-exclamation" style="color:var(--danger-color);"></i> HTTP Status Codes & Error Definitions</h3>
                            <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); border-radius:12px; overflow:hidden;">
                                <table class="custom-table" style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:rgba(255,255,255,0.03);">
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">HTTP Status</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Response Status</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Reason / Cause</th>
                                            <th style="padding:12px 16px; font-size:0.8rem; color:var(--text-secondary);">Recommended Solution</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><span class="status-pill pill-completed">200 OK</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--success-color)">true</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Request processed successfully.</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Parse returned JSON <code style="color:#FFF">data</code> object.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><span class="status-pill pill-pending">400 Bad Request</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--warning-color)">false</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Missing or invalid request parameters.</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Check error message for missing parameter name.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><span class="status-pill pill-blocked">401 Unauthorized</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--danger-color)">false</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Invalid API Key or Secret Key header.</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Check <code style="color:#FFF">X-API-Key</code> and <code style="color:#FFF">X-Secret-Key</code> headers.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><span class="status-pill pill-blocked">403 Forbidden</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--danger-color)">false</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Live API key used before completing ₹10,000 fee.</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Pay ₹10,000 fee in Payments & Billing page or use Sandbox.</td>
                                        </tr>
                                        <tr style="border-bottom:1px solid var(--border-color);">
                                            <td style="padding:14px 16px;"><span class="status-pill pill-pending">429 Too Many Requests</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--warning-color)">false</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Rate limit exceeded (60 req/min).</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Throttle requests or contact support for higher limits.</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:14px 16px;"><span class="status-pill pill-blocked">500 Server Error</span></td>
                                            <td style="padding:14px 16px; font-weight:700; color:var(--danger-color)">false</td>
                                            <td style="padding:14px 16px; font-size:0.88rem; color:var(--text-secondary);">Internal database or backend error.</td>
                                            <td style="padding:14px 16px; font-size:0.88rem;">Retry request or contact Rentox developer support.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </section>

                <!-- 5. ACCOUNT SETTINGS TAB PANEL -->
                <section class="tab-section d-none" id="tab-settings">
                    <div class="page-header">
                        <div class="page-title-desc">
                            <h1>Account & Profile Settings</h1>
                            <p>Update your legal company details and contact parameters.</p>
                        </div>
                    </div>

                    <div class="panel-card">
                        <h3 class="card-title" style="margin-bottom: 24px;"><i class="fa-solid fa-sliders"></i> Edit Partner Information</h3>
                        
                        <div id="settingsAlert" class="alert-banner d-none" style="margin-bottom:24px;">
                            <i class="fa-solid fa-circle-info alert-banner-icon" id="settingsAlertIcon"></i>
                            <div class="alert-banner-content">
                                <h4 class="alert-banner-title" id="settingsAlertTitle">System Status</h4>
                                <p class="alert-banner-desc" id="settingsAlertDesc" style="margin-bottom:0;"></p>
                            </div>
                        </div>

                        <form id="settingsProfileForm">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="settings-form-grid">
                                <div class="form-group">
                                    <label class="form-label" for="settings_partner_name">Partner Legal Name <span>*</span></label>
                                    <input type="text" id="settings_partner_name" name="partner_name" class="form-control-glass" value="<?= htmlspecialchars($p['partner_name'] ?? '') ?>" placeholder="e.g. Akbar Travels" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settings_company_name">Company / Agency Name <span>*</span></label>
                                    <input type="text" id="settings_company_name" name="company_name" class="form-control-glass" value="<?= htmlspecialchars($p['company_name']) ?>" placeholder="e.g. Akbar Rentals Inc." required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settings_owner_name">Company Owner Name <span>*</span></label>
                                    <input type="text" id="settings_owner_name" name="company_owner_name" class="form-control-glass" value="<?= htmlspecialchars($p['company_owner_name'] ?? '') ?>" placeholder="Full name of agency owner" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settings_gst">GST Number <span>*</span></label>
                                    <input type="text" id="settings_gst" name="gst_number" class="form-control-glass" value="<?= htmlspecialchars($p['gst_number'] ?? '') ?>" placeholder="15-digit GSTIN ID" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settings_contact_person">Primary Contact Person <span>*</span></label>
                                    <input type="text" id="settings_contact_person" name="contact_person" class="form-control-glass" value="<?= htmlspecialchars($p['contact_person']) ?>" placeholder="Contact representative" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="settings_mobile">Contact Mobile Number <span>*</span></label>
                                    <input type="tel" id="settings_mobile" name="contact_number" class="form-control-glass" value="<?= htmlspecialchars($p['mobile_number'] ?? '') ?>" placeholder="+91 XXXXXXXXXX" required>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="settings_email">Authorized Business Email <span>*</span></label>
                                    <input type="email" id="settings_email" name="email" class="form-control-glass" value="<?= htmlspecialchars($p['email']) ?>" placeholder="api@company.com" required style="width:100%;">
                                </div>
                            </div>

                            <div style="display:flex; justify-content:flex-end;">
                                <button type="submit" class="btn-primary-action">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <!-- Global Toast Notification -->
    <div id="clipboardToast" class="toast-notification">
        <i class="fa-solid fa-check-circle" id="toastIcon"></i>
        <span id="toastMessage">Copied successfully!</span>
    </div>

    <script>
        // Toggle Sidebar on Mobile viewports
        function toggleSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const menuIcon = document.getElementById('menuIcon');
            sidebar.classList.toggle('open');
            if (sidebar.classList.contains('open')) {
                menuIcon.className = 'fa-solid fa-xmark';
            } else {
                menuIcon.className = 'fa-solid fa-bars';
            }
        }

        // Tab Switching logic
        function switchTab(hashId) {
            // Remove active class from all sidebars
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                if (item.getAttribute('href') === hashId) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Hide all tab sections
            const sections = document.querySelectorAll('.tab-section');
            sections.forEach(sec => {
                const targetId = 'tab-' + hashId.substring(1);
                if (sec.id === targetId) {
                    sec.classList.remove('d-none');
                } else {
                    sec.classList.add('d-none');
                }
            });

            // Close mobile menu if active
            const sidebar = document.getElementById('appSidebar');
            if (sidebar.classList.contains('open')) {
                toggleSidebar();
            }

            // Sync hash in URL
            if (window.location.hash !== hashId) {
                window.location.hash = hashId;
            }
        }

        // Initialize Tab based on URL Hash
        window.addEventListener('DOMContentLoaded', () => {
            const activeHash = window.location.hash || '#overview';
            switchTab(activeHash);
            
            // Trigger background mail runner asynchronously to process any spooled emails (like admin notifications)
            fetch('mail_runner.php').catch(err => console.error('Mail runner trigger failed:', err));
        });

        // Toggle visibility of credentials
        function toggleKeyVisibility(elemId, actualValue, btn) {
            const codeElem = document.getElementById(elemId);
            const icon = btn.querySelector('i');
            if (codeElem.innerText.includes('•')) {
                codeElem.innerText = actualValue;
                codeElem.style.color = 'var(--text-primary)';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                codeElem.innerText = '••••••••••••••••••••••••••••••••••••••••';
                codeElem.style.color = 'var(--success-color)';
                icon.className = 'fa-solid fa-eye';
            }
        }

        // Copy plain text directly to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast("Copied to clipboard!", false);
            }).catch(() => {
                showToast("Failed to copy", true);
            });
        }

        // Show toast box
        function showToast(msg, isError = false) {
            const toast = document.getElementById('clipboardToast');
            const icon = document.getElementById('toastIcon');
            const text = document.getElementById('toastMessage');

            text.innerText = msg;
            if (isError) {
                toast.classList.add('toast-error');
                icon.className = 'fa-solid fa-circle-exclamation';
            } else {
                toast.classList.remove('toast-error');
                icon.className = 'fa-solid fa-circle-check';
            }

            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 2500);
        }

        // Toggle expanding activity log JSON codes
        function toggleLogPayload(rowId, btn) {
            const detailRow = document.getElementById(rowId);
            const icon = btn.querySelector('i');
            detailRow.classList.toggle('d-none');
            if (detailRow.classList.contains('d-none')) {
                btn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> Expand';
            } else {
                btn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Collapse';
            }
        }

        // Submit form profile details inline via AJAX
        document.getElementById('settingsProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const alertBox = document.getElementById('settingsAlert');
            const alertIcon = document.getElementById('settingsAlertIcon');
            const alertTitle = document.getElementById('settingsAlertTitle');
            const alertDesc = document.getElementById('settingsAlertDesc');
            
            const btn = this.querySelector('button[type="submit"]');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving Details...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.8';

            // Reset classes
            alertBox.className = 'alert-banner d-none';

            const formData = new FormData(this);
            formData.append('HTTP_X_REQUESTED_WITH', 'xmlhttprequest'); // force ajax flag if needed

            // Call AJAX request
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alertBox.classList.remove('d-none');
                if (data.success) {
                    alertBox.style.backgroundColor = 'rgba(16, 185, 129, 0.06)';
                    alertBox.style.borderColor = 'rgba(16, 185, 129, 0.15)';
                    alertIcon.className = 'fa-solid fa-circle-check';
                    alertIcon.style.color = 'var(--success-color)';
                    alertTitle.innerText = 'Profile Updated';
                    alertTitle.style.color = '#FFF';
                    alertDesc.innerText = data.message;
                    alertDesc.style.color = 'var(--text-secondary)';
                    showToast("Profile details saved!");
                    
                    // Trigger the mail runner immediately to process the admin notification email
                    fetch('mail_runner.php').catch(() => {});

                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    btn.innerHTML = origHtml;
                    btn.style.pointerEvents = 'auto';
                    btn.style.opacity = '1';
                    
                    alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.06)';
                    alertBox.style.borderColor = 'rgba(239, 68, 68, 0.15)';
                    alertIcon.className = 'fa-solid fa-circle-exclamation';
                    alertIcon.style.color = 'var(--danger-color)';
                    alertTitle.innerText = 'Error Updating Profile';
                    alertTitle.style.color = '#FFF';
                    alertDesc.innerText = data.message;
                    alertDesc.style.color = 'var(--text-secondary)';
                    showToast(data.message, true);
                }
            })
            .catch(err => {
                btn.innerHTML = origHtml;
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';

                alertBox.classList.remove('d-none');
                alertBox.style.backgroundColor = 'rgba(239, 68, 68, 0.06)';
                alertBox.style.borderColor = 'rgba(239, 68, 68, 0.15)';
                alertIcon.className = 'fa-solid fa-circle-exclamation';
                alertIcon.style.color = 'var(--danger-color)';
                alertTitle.innerText = 'Network Error';
                alertTitle.style.color = '#FFF';
                alertDesc.innerText = 'A server or network error occurred. Please try again.';
                alertDesc.style.color = 'var(--text-secondary)';
                showToast("Network connection error", true);
            });
        });
    </script>

    <!-- Razorpay Checkout SDK & Payment Handler -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function payWithRazorpay() {
            const options = {
                "key": "<?= RAZORPAY_ACTIVE_KEY ?>",
                "amount": <?= (int)($activation_fee * 100) ?>,
                "currency": "INR",
                "name": "Redox API Service",
                "description": "B2B Partner API Integration & Activation Fee",
                "handler": function (response) {
                    if (response.razorpay_payment_id) {
                        verifyPartnerPayment(response.razorpay_payment_id);
                    }
                },
                "prefill": {
                    "name": "<?= htmlspecialchars($p['contact_person'] ?? $p['partner_name']) ?>",
                    "email": "<?= htmlspecialchars($p['email']) ?>",
                    "contact": "<?= htmlspecialchars($p['mobile_number']) ?>"
                },
                "theme": {
                    "color": "#6c63ff"
                }
            };
            const rzp = new Razorpay(options);
            rzp.open();
        }

        function verifyPartnerPayment(paymentId) {
            showToast("Verifying ₹10,000 payment...", false);
            const formData = new FormData();
            formData.append('action', 'verify_payment');
            formData.append('razorpay_payment_id', paymentId);

            fetch('dashboard.php', { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, false);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast(data.message || 'Payment verification failed', true);
                    }
                })
                .catch(err => showToast('Error: ' + err.message, true));
        }
    </script>
</body>
</html>
