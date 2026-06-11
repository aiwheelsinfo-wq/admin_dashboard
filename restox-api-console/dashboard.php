<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

// Auth Check (must be logged in as partner)
if (!isset($_SESSION['partner_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_SESSION['partner_id'];
$error = '';
$success = '';

// Handle Profile Update Request (AJAX or normal POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $partner_name       = trim($_POST['partner_name'] ?? '');
    $company_name       = trim($_POST['company_name'] ?? '');
    $company_owner_name = trim($_POST['company_owner_name'] ?? '');
    $contact_person     = trim($_POST['contact_person'] ?? '');
    $contact_number     = trim($_POST['contact_number'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $gst_number         = trim($_POST['gst_number'] ?? '');
    $address            = trim($_POST['address'] ?? '');
    $bank_details       = trim($_POST['bank_details'] ?? '');

    if (!$partner_name || !$company_name || !$company_owner_name || !$contact_person || !$contact_number || !$email || !$gst_number || !$address || !$bank_details) {
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
                // Fetch current partner info to keep existing document if no new file is uploaded
                $curr_stmt = mysqli_prepare($conn, "SELECT documents FROM partners WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($curr_stmt, 'i', $id);
                mysqli_stmt_execute($curr_stmt);
                $curr_res = mysqli_stmt_get_result($curr_stmt);
                $curr_partner = mysqli_fetch_assoc($curr_res);
                mysqli_stmt_close($curr_stmt);
                
                $documents = $curr_partner['documents'] ?? '';

                // Handle file upload if present
                if (isset($_FILES['documents_file']) && $_FILES['documents_file']['error'] === UPLOAD_ERR_OK) {
                    $filename = $_FILES['documents_file']['name'];
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $allowed_exts = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];
                    
                    if (!in_array($ext, $allowed_exts)) {
                        $error = 'Invalid file type. Allowed formats: PDF, DOC, DOCX, PNG, JPG, JPEG.';
                    } else {
                        // Ensure uploads directory exists
                        $upload_dir = __DIR__ . '/uploads';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $new_filename = uniqid('doc_') . '.' . $ext;
                        if (move_uploaded_file($_FILES['documents_file']['tmp_name'], $upload_dir . '/' . $new_filename)) {
                            $documents = $new_filename;
                        } else {
                            $error = 'Failed to save uploaded file.';
                        }
                    }
                } elseif (empty($documents)) {
                    $error = 'A verification document is required.';
                }

                if (empty($error)) {
                    $stmt = mysqli_prepare($conn, 
                        "UPDATE partners 
                         SET partner_name = ?, company_name = ?, company_owner_name = ?, contact_person = ?, mobile_number = ?, email = ?, gst_number = ?, address = ?, bank_details = ?, documents = ?
                         WHERE id = ?"
                    );
                    mysqli_stmt_bind_param($stmt, 'ssssssssssi', 
                        $partner_name, $company_name, $company_owner_name, $contact_person, $contact_number, $email, $gst_number, $address, $bank_details, $documents, $id
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'Profile details updated successfully!';
                        $_SESSION['partner_email'] = $email; // Update session email
                    } else {
                        $error = 'Failed to update profile: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($stmt);
                }
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

// Handle Request API Access Request (AJAX POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_api_access') {
    try {
        // Fetch latest partner details
        $stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $partner = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$partner) {
            $error = 'Partner account not found.';
        } elseif ($partner['status'] !== 'pending_profile') {
            $error = 'Your account status does not permit this request.';
        } else {
            // Validate all fields are complete
            $required_fields = [
                'partner_name',
                'company_name',
                'company_owner_name',
                'contact_person',
                'mobile_number',
                'email',
                'gst_number',
                'address',
                'bank_details',
                'documents'
            ];

            $incomplete = false;
            foreach ($required_fields as $field) {
                if (empty(trim($partner[$field] ?? ''))) {
                    $incomplete = true;
                    break;
                }
            }

            if ($incomplete) {
                $error = 'Please complete all profile fields and upload verification documents first.';
            } else {
                // Update status to pending
                $update_stmt = mysqli_prepare($conn, "UPDATE partners SET status = 'pending' WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, 'i', $id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = 'Your API Access Request has been submitted successfully!';
                    
                    // Dispatch email notification to Rentox Admin
                    send_admin_notification_email(
                        $partner['company_name'],
                        $partner['partner_name'],
                        $partner['company_owner_name'],
                        $partner['contact_person'],
                        $partner['mobile_number'],
                        $partner['email'],
                        $partner['gst_number']
                    );
                } else {
                    $error = 'Failed to submit request: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($update_stmt);
            }
        }
    } catch (Exception $e) {
        $error = 'Database error: ' . $e->getMessage();
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

// Calculate profile completion percentage (out of 10 fields)
$fields_to_check = [
    'partner_name',
    'company_name',
    'company_owner_name',
    'contact_person',
    'mobile_number',
    'email',
    'gst_number',
    'address',
    'bank_details',
    'documents'
];

$completed_count = 0;
foreach ($fields_to_check as $field) {
    if (!empty(trim($p[$field] ?? ''))) {
        $completed_count++;
    }
}
$completion_percent = ($completed_count / count($fields_to_check)) * 100;
$all_completed = ($completed_count === count($fields_to_check));


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
                    <div class="page-header">
                        <div class="page-title-desc">
                            <h1>Dashboard Overview</h1>
                            <p>Monitor your agency integration metrics and access profile details.</p>
                        </div>
                        <div>
                            <?php if ($p['status'] === 'pending_profile'): ?>
                                <span class="status-pill pill-pending" style="color:#94A3B8; background:rgba(148,163,184,0.1); border:1px solid rgba(148,163,184,0.2);"><i class="fa-solid fa-user-pen"></i> Profile Incomplete</span>
                            <?php elseif ($p['status'] === 'pending'): ?>
                                <span class="status-pill pill-pending"><i class="fa-solid fa-hourglass-half"></i> Pending Review</span>
                            <?php elseif ($p['status'] === 'blocked'): ?>
                                <span class="status-pill pill-blocked"><i class="fa-solid fa-ban"></i> Access Blocked</span>
                            <?php else: ?>
                                <span class="status-pill pill-active"><i class="fa-solid fa-check-circle"></i> Active Connection</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Access Request & Progress Indicator Panel -->
                    <?php if ($p['status'] === 'pending_profile'): ?>
                        <div class="panel-card" style="border: 1px solid rgba(99, 102, 241, 0.25); background: rgba(99, 102, 241, 0.03); padding: 24px;">
                            <div class="card-header-flex" style="margin-bottom: 16px;">
                                <h3 class="card-title"><i class="fa-solid fa-user-check"></i> Profile Completion</h3>
                                <span style="font-weight: 700; color: var(--primary-accent); font-size: 1.05rem;"><?= round($completion_percent) ?>% Complete</span>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div style="width: 100%; height: 10px; background: rgba(255, 255, 255, 0.05); border-radius: 99px; overflow: hidden; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.03);">
                                <div style="width: <?= $completion_percent ?>%; height: 100%; background: linear-gradient(90deg, var(--primary-accent), var(--secondary-accent)); border-radius: 99px; transition: width 0.5s ease-in-out;"></div>
                            </div>
                            
                            <div style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 280px;">
                                    <?php if ($all_completed): ?>
                                        <h4 style="color: var(--success-color); font-weight: 700; margin-bottom: 4px; font-size: 1rem;">
                                            <i class="fa-solid fa-circle-check"></i> Your profile is complete. You can now request API access.
                                        </h4>
                                        <p style="font-size: 0.88rem; color: var(--text-secondary);">Your integration details are fully updated. Submit your request to generate credentials.</p>
                                    <?php else: ?>
                                        <h4 style="color: var(--warning-color); font-weight: 700; margin-bottom: 4px; font-size: 1rem;">
                                            <i class="fa-solid fa-circle-exclamation"></i> Action Required: Complete Profile Settings
                                        </h4>
                                        <p style="font-size: 0.88rem; color: var(--text-secondary);">Please complete all profile details (Address, Bank Details, and Verification Document) in the Account Settings tab.</p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($all_completed): ?>
                                        <button class="btn-primary-action" id="btnRequestApiAccess" onclick="submitApiAccessRequest()">
                                            <i class="fa-solid fa-paper-plane"></i> Request API Access
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-primary-action" disabled style="opacity: 0.45; cursor: not-allowed; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.08); box-shadow: none;" onclick="switchTab('#settings')">
                                            <i class="fa-solid fa-lock"></i> Request API Access
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Metrics Stats Grid -->
                    <div class="metrics-grid">
                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">Integration Status</span>
                            <span class="metric-value" style="font-size:1.3rem; margin-top:6px; font-weight:700;">
                                <?php if ($p['status'] === 'pending_profile'): ?>
                                    Profile Setup
                                <?php elseif ($p['status'] === 'pending'): ?>
                                    Under Review
                                <?php elseif ($p['status'] === 'blocked'): ?>
                                    Suspended
                                <?php else: ?>
                                    Live / Active
                                <?php endif; ?>
                            </span>
                            <span class="metric-desc">
                                <?php if ($p['status'] === 'pending_profile'): ?>
                                    <i class="fa-solid fa-circle" style="color:var(--text-secondary)"></i> Profile incomplete
                                <?php elseif ($p['status'] === 'pending'): ?>
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
                            <span class="metric-value"><?= number_format($total_requests) ?></span>
                            <span class="metric-desc"><i class="fa-solid fa-chart-bar"></i> Total lifetime API calls</span>
                        </div>

                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">API Success Rate</span>
                            <span class="metric-value"><?= $success_rate ?>%</span>
                            <span class="metric-desc"><i class="fa-solid fa-circle-check" style="color:var(--success-color)"></i> Successful responses</span>
                        </div>

                        <div class="metric-card">
                            <div class="metric-card-glow"></div>
                            <span class="metric-label">Requests Today</span>
                            <span class="metric-value"><?= number_format($today_requests) ?></span>
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
                        <?php if ($p['status'] !== 'active'): ?>
                            <div style="text-align: center; padding: 40px 10px;">
                                <i class="fa-solid fa-lock" style="font-size: 3.5rem; margin-bottom: 20px; color: var(--text-secondary); opacity: 0.35;"></i>
                                <?php if ($p['status'] === 'pending_profile'): ?>
                                    <h3 style="font-size: 1.25rem; margin-bottom: 8px;">Profile Verification Required</h3>
                                    <p style="color: var(--text-secondary); max-width: 460px; margin: 0 auto 24px; font-size: 0.95rem;">Please complete your profile details and submit a request for API access from the main dashboard tab first.</p>
                                    <button class="btn-primary-action" onclick="switchTab('#overview')"><i class="fa-solid fa-chart-line"></i> Go to Dashboard</button>
                                <?php else: ?>
                                    <h3 style="font-size: 1.25rem; margin-bottom: 8px;">API Access Under Review</h3>
                                    <p style="color: var(--text-secondary); max-width: 460px; margin: 0 auto 24px; font-size: 0.95rem;">Your credential keys will be generated automatically and shown here as soon as the administrator approves your partner request.</p>
                                    <span class="status-pill pill-pending"><i class="fa-solid fa-hourglass-half"></i> Awaiting Verification</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <h3 class="card-title" style="margin-bottom: 24px;"><i class="fa-solid fa-shield-halved"></i> Live Access Keys</h3>

                            <div style="margin-bottom: 28px;">
                                <span class="info-label" style="margin-bottom: 8px; display:block;">X-API-Key</span>
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
                                <span class="info-label" style="margin-bottom: 8px; display:block;">X-Secret-Key</span>
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

                            <div style="background: rgba(99, 102, 241, 0.04); border: 1px solid rgba(99, 102, 241, 0.15); border-radius: 12px; padding: 18px; display:flex; gap:14px; align-items:flex-start;">
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
                            <h1>API Documentation Reference</h1>
                            <p>Integrate search, booking, tariff, and tracking endpoints on your B2B server.</p>
                        </div>
                    </div>

                    <div class="api-ref-container">

                        <!-- Base configuration alert -->
                        <div class="panel-card" style="margin-bottom:0;">
                            <h3 class="card-title" style="margin-bottom:14px;"><i class="fa-solid fa-network-wired"></i> Connection Parameters</h3>
                            <p style="color:var(--text-secondary); font-size:0.92rem; line-height:1.6; margin-bottom:16px;">
                                All endpoints are hosted on Rentox's primary domain. Base API URL:
                            </p>
                            <div class="key-input-wrapper" style="max-width:100%;">
                                <code class="key-content" style="color:#FFF;">https://agnicarrental.com/admin2025/partner/api</code>
                                <button class="btn-key-icon" onclick="copyToClipboard('https://agnicarrental.com/admin2025/partner/api')" title="Copy URL">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
                            <p style="color:var(--text-secondary); font-size:0.85rem; line-height:1.5; margin-top:8px;">
                                Required Headers:<br>
                                <code>X-API-Key: &lt;your_api_key&gt;</code><br>
                                <code>X-Secret-Key: &lt;your_secret_key&gt;</code><br>
                                <code>Content-Type: application/json</code>
                            </p>
                        </div>

                        <!-- Endpoint 1: Search Cab -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-post">POST</span>
                                <span class="endpoint-url">/search-cab.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Search for active vehicle fleets, rates, categories, and inventory parameters for specified dates and travel points.
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Body Parameters</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">pickup_city <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Pickup location city</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">drop_city <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Drop-off destination city</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">pickup_date <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Format: YYYY-MM-DD</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">pickup_time <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Format: HH:MM:SS</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">trip_type <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">"one_way" or "round_trip"</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Command</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X POST https://agnicarrental.com/admin2025/partner/api/search-cab.php \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot; \\\n  -H \&quot;Content-Type: application/json\&quot; \\\n  -d \'{\n    \&quot;pickup_city\&quot;: \&quot;Mumbai\&quot;,\n    \&quot;drop_city\&quot;: \&quot;Pune\&quot;,\n    \&quot;pickup_date\&quot;: \&quot;2026-06-12\&quot;,\n    \&quot;pickup_time\&quot;: \&quot;10:00:00\&quot;,\n    \&quot;trip_type\&quot;: \&quot;one_way\&quot;\n  }\'\')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X POST https://agnicarrental.com/admin2025/partner/api/search-cab.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "pickup_city": "Mumbai",
    "drop_city": "Pune",
    "pickup_date": "2026-06-12",
    "pickup_time": "10:00:00",
    "trip_type": "one_way"
  }'</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint 2: Get Fare -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-post">POST</span>
                                <span class="endpoint-url">/get-fare.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Fetch tariff estimations and route distance pricing options for specific vehicle categories.
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Body Parameters</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">category_id <span class="param-required">*</span></td>
                                            <td class="param-type">int</td>
                                            <td class="param-desc">ID of the car category</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">distance_km <span class="param-required">*</span></td>
                                            <td class="param-type">float</td>
                                            <td class="param-desc">Estimated transit distance</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Command</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X POST https://agnicarrental.com/admin2025/partner/api/get-fare.php \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot; \\\n  -H \&quot;Content-Type: application/json\&quot; \\\n  -d \'{\n    \&quot;category_id\&quot;: 3,\n    \&quot;distance_km\&quot;: 150.5\n  }\'\')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X POST https://agnicarrental.com/admin2025/partner/api/get-fare.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": 3,
    "distance_km": 150.5
  }'</code></pre>
                                </div>
                            </div>
                        </div>

                        <!-- Endpoint 3: Book Cab -->
                        <div class="endpoint-card">
                            <div class="endpoint-header">
                                <span class="method-badge badge-post">POST</span>
                                <span class="endpoint-url">/book-cab.php</span>
                            </div>
                            <div class="endpoint-desc">
                                Create an instant booking for a customer ride and trigger booking validation.
                            </div>
                            <div class="endpoint-docs-grid">
                                <div class="params-section">
                                    <h4 class="params-title">Body Parameters</h4>
                                    <table class="params-table">
                                        <tr>
                                            <td class="param-name">partner_booking_ref <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Your internal reference ID</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">user_mobile <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Customer contact phone</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">user_name <span class="param-required">*</span></td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Customer full name</td>
                                        </tr>
                                        <tr>
                                            <td class="param-name">user_email</td>
                                            <td class="param-type">string</td>
                                            <td class="param-desc">Customer email address</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="code-section">
                                    <div class="code-snippet-header">
                                        <span class="code-snippet-title">cURL Command</span>
                                        <button class="btn-key-icon" style="width:24px; height:24px;" onclick="copyToClipboard('curl -X POST https://agnicarrental.com/admin2025/partner/api/book-cab.php \\\n  -H \&quot;X-API-Key: YOUR_API_KEY\&quot; \\\n  -H \&quot;X-Secret-Key: YOUR_SECRET_KEY\&quot; \\\n  -H \&quot;Content-Type: application/json\&quot; \\\n  -d \'{\n    \&quot;partner_booking_ref\&quot;: \&quot;TX1289371\&quot;,\n    \&quot;user_mobile\&quot;: \&quot;9876543210\&quot;,\n    \&quot;user_name\&quot;: \&quot;Jane Doe\&quot;,\n    \&quot;user_email\&quot;: \&quot;jane@example.com\&quot;\n  }\'\')" title="Copy Command">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <pre class="code-snippet-box"><code>curl -X POST https://agnicarrental.com/admin2025/partner/api/book-cab.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-Secret-Key: YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "partner_booking_ref": "TX1289371",
    "user_mobile": "9876543210",
    "user_name": "Jane Doe",
    "user_email": "jane@example.com"
  }'</code></pre>
                                </div>
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

        // Submit API Access request inline via AJAX
        function submitApiAccessRequest() {
            const btn = document.getElementById('btnRequestApiAccess');
            if (!btn) return;
            
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.8';
            
            const formData = new FormData();
            formData.append('action', 'request_api_access');
            
            fetch('dashboard.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast("API Access Request Submitted!");
                    
                    // Trigger the mail runner immediately to process the spooled email
                    fetch('mail_runner.php').catch(() => {});
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    btn.innerHTML = origHtml;
                    btn.style.pointerEvents = 'auto';
                    btn.style.opacity = '1';
                    showToast(data.message, true);
                }
            })
            .catch(err => {
                btn.innerHTML = origHtml;
                btn.style.pointerEvents = 'auto';
                btn.style.opacity = '1';
                showToast("Network error occurred.", true);
            });
        }
    </script>
</body>
</html>

