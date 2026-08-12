<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['partner_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name     = trim($_POST['company_name'] ?? '');
    $contact_person   = trim($_POST['contact_person'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (!$company_name || !$contact_person || !$email || !$password || !$confirm_password) {
        $error = 'All compulsory fields must be filled out.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match. Please verify your password confirmation.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if email already exists
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM partners WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 's', $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = 'This business email address is already registered. Please sign in instead.';
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                // Generate 6-digit OTP
                $otp = (string)rand(100000, 999999);
                $otp_expiry = time() + 600; // 10 minutes from now

                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Store in temporary session
                $_SESSION['temp_reg'] = [
                    'company_name'   => $company_name,
                    'contact_person' => $contact_person,
                    'email'          => $email,
                    'password'       => $hashed_password,
                    'otp'            => $otp,
                    'otp_expiry'     => $otp_expiry
                ];

                // Send OTP Email
                if (send_otp_email($email, $otp, $contact_person)) {
                    header("Location: verify-otp.php");
                    exit();
                } else {
                    $error = 'Failed to send verification email. Please check your email address or try again.';
                    unset($_SESSION['temp_reg']);
                }
            }
        } catch (Exception $e) {
            $error = 'An error occurred during registration: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create B2B Partner Account - Rentox API Service</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: rgba(15, 23, 42, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #F8FAFC;
            --text-secondary: #94A3B8;
            --primary-accent: #6366F1;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --success-color: #10B981;
            --error-color: #EF4444;
            --input-bg: rgba(15, 23, 42, 0.6);
            --input-border: rgba(255, 255, 255, 0.12);
            --input-focus: #818CF8;
            --font-main: 'Plus Jakarta Sans', sans-serif;
            --font-code: 'JetBrains Mono', monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.14) 0%, transparent 40%),
                radial-gradient(circle at 90% 80%, rgba(16, 185, 129, 0.12) 0%, transparent 40%),
                radial-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px);
            background-size: 100% 100%, 100% 100%, 28px 28px;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 32px 16px;
            overflow-x: hidden;
        }

        .portal-wrapper {
            width: 100%;
            max-width: 1140px;
            margin: auto;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 48px;
            align-items: start;
        }

        /* LEFT COLUMN: ONBOARDING & BENEFITS */
        .left-panel {
            display: flex;
            flex-direction: column;
            gap: 28px;
            padding-right: 12px;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo-icon {
            font-size: 1.8rem;
            color: var(--primary-accent);
            background: rgba(99, 102, 241, 0.12);
            padding: 10px;
            border-radius: 12px;
            border: 1px solid rgba(99, 102, 241, 0.25);
        }

        .brand-title-wrap {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .brand-title {
            font-size: 1.4rem;
            font-weight: 800;
            color: #FFF;
            letter-spacing: -0.3px;
        }

        .brand-sub {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--primary-accent);
            letter-spacing: 2px;
        }

        .hero-tagline {
            font-size: 2.3rem;
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -0.8px;
            background: linear-gradient(135deg, #FFFFFF 30%, #A5B4FC 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 1.02rem;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* Benefits Grid Cards */
        .benefits-grid {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .benefit-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            transition: all 0.25s ease;
        }

        .benefit-card:hover {
            border-color: rgba(99, 102, 241, 0.3);
            background: rgba(15, 23, 42, 0.85);
            transform: translateX(4px);
        }

        .benefit-icon {
            font-size: 1.25rem;
            color: var(--primary-accent);
            background: rgba(99, 102, 241, 0.12);
            padding: 10px;
            border-radius: 10px;
            flex-shrink: 0;
        }

        .benefit-content h4 {
            font-size: 0.98rem;
            font-weight: 700;
            color: #FFF;
            margin-bottom: 3px;
        }

        .benefit-content p {
            font-size: 0.86rem;
            color: var(--text-secondary);
            line-height: 1.45;
        }

        /* Vertical Application Process Stepper */
        .process-section {
            background: rgba(7, 11, 20, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 18px;
            padding: 24px;
        }

        .process-title {
            font-size: 1rem;
            font-weight: 700;
            color: #FFF;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stepper-vertical {
            display: flex;
            flex-direction: column;
            gap: 0;
            position: relative;
        }

        .step-row {
            display: flex;
            gap: 16px;
            position: relative;
            padding-bottom: 24px;
        }

        .step-row:last-child {
            padding-bottom: 0;
        }

        .step-row:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 17px;
            top: 36px;
            bottom: 0;
            width: 2px;
            background: rgba(255, 255, 255, 0.08);
        }

        .step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.04);
            border: 1.5px solid var(--card-border);
            color: var(--text-secondary);
            font-size: 0.85rem;
            font-weight: 700;
            font-family: var(--font-code);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            z-index: 1;
        }

        .step-row.active .step-circle {
            background: linear-gradient(135deg, var(--primary-accent), #4F46E5);
            border-color: transparent;
            color: #FFF;
            box-shadow: 0 0 15px rgba(99, 102, 241, 0.4);
        }

        .step-info h5 {
            font-size: 0.92rem;
            font-weight: 700;
            color: #FFF;
            margin-bottom: 2px;
        }

        .step-info p {
            font-size: 0.82rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Left Footer Security Badge */
        .trust-badge-left {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(16, 185, 129, 0.06);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.82rem;
            color: var(--text-secondary);
        }

        .trust-badge-left i {
            color: var(--success-color);
            font-size: 1rem;
            margin-top: 1px;
        }

        /* RIGHT COLUMN: REGISTRATION CARD */
        .right-panel {
            display: flex;
            justify-content: center;
        }

        .register-card {
            width: 100%;
            max-width: 480px;
            background: var(--panel-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.45);
            padding: 36px 32px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .card-top {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .logo-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(99, 102, 241, 0.1);
            border: 1px solid rgba(99, 102, 241, 0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #A5B4FC;
            width: fit-content;
            margin-bottom: 8px;
        }

        .welcome-heading {
            font-size: 1.75rem;
            font-weight: 800;
            color: #FFF;
            letter-spacing: -0.5px;
        }

        .supporting-text {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .alert {
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 0.88rem;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.4;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #FCA5A5;
        }

        .alert-icon {
            font-size: 1.2rem;
            color: #EF4444;
            margin-top: 1px;
        }

        /* Form Sections */
        #regForm {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .form-section-divider {
            font-size: 0.75rem;
            font-weight: 800;
            letter-spacing: 1.2px;
            color: #A5B4FC;
            margin-top: 10px;
            margin-bottom: 2px;
            text-transform: uppercase;
            padding-bottom: 6px;
            border-bottom: 1px dashed rgba(165, 180, 252, 0.25);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-section-divider:first-of-type {
            margin-top: 0;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #CBD5E1;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label span.req { color: var(--error-color); }

        .input-icon-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-secondary);
            font-size: 0.95rem;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            font-family: inherit;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: #FFF;
            padding: 14px 14px 14px 42px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.25s ease;
            min-height: 48px;
        }

        .form-control:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background-color: rgba(15, 23, 42, 0.9);
        }

        .btn-toggle-eye {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 6px;
            font-size: 0.95rem;
            border-radius: 6px;
            transition: color 0.2s ease;
        }

        .btn-toggle-eye:hover { color: #FFF; }

        /* Password Strength Meter */
        .strength-meter-wrap {
            margin-top: 2px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .strength-bar-bg {
            height: 4px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 4px;
            overflow: hidden;
        }

        .strength-bar-fill {
            height: 100%;
            width: 0%;
            background: #EF4444;
            transition: all 0.3s ease;
        }

        .strength-text-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            color: var(--text-secondary);
        }

        .strength-text { font-weight: 700; color: #94A3B8; }

        /* Custom Checkbox */
        .checkbox-container {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .checkbox-container input { display: none; }

        .checkmark {
            width: 18px;
            height: 18px;
            border: 1px solid var(--input-border);
            border-radius: 5px;
            background: var(--input-bg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .checkbox-container input:checked ~ .checkmark {
            background: var(--primary-accent);
            border-color: var(--primary-accent);
        }

        .checkmark::after {
            content: "\f00c";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 0.7rem;
            color: #FFF;
            display: none;
        }

        .checkbox-container input:checked ~ .checkmark::after { display: block; }

        .btn-primary-cta {
            background: linear-gradient(135deg, var(--primary-accent) 0%, #4F46E5 100%);
            color: #FFF;
            border: none;
            border-radius: 12px;
            padding: 15px 24px;
            font-family: inherit;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: all 0.25s ease;
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.35);
            min-height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 4px;
        }

        .btn-primary-cta:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.5);
            background: linear-gradient(135deg, #818CF8 0%, var(--primary-accent) 100%);
        }

        .btn-primary-cta:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .application-notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        .application-notice i {
            color: var(--primary-accent);
            font-size: 0.9rem;
            margin-top: 2px;
        }

        .card-footer-links {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.85rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 6px;
        }

        .highlight-link {
            color: var(--primary-accent);
            font-weight: 700;
            text-decoration: none;
        }

        .highlight-link:hover { text-decoration: underline; }

        /* Minimal Footer */
        .portal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 10px 0;
            font-size: 0.82rem;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .footer-links a:hover { color: #FFF; }
        .footer-links .sep { opacity: 0.3; }

        /* RESPONSIVE DESIGN */
        @media (max-width: 992px) {
            .portal-grid {
                grid-template-columns: 1fr;
                gap: 40px;
            }
            .left-panel {
                padding-right: 0;
            }
            .hero-tagline { font-size: 2rem; }
        }

        @media (max-width: 576px) {
            body { padding: 20px 12px; }
            .register-card { padding: 28px 20px; border-radius: 18px; }
            .welcome-heading { font-size: 1.5rem; }
            .portal-footer { flex-direction: column; text-align: center; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="portal-wrapper">
        
        <!-- MAIN PORTAL GRID -->
        <div class="portal-grid">

            <!-- LEFT COLUMN: ONBOARDING & BENEFITS -->
            <div class="left-panel">
                
                <div class="brand-header">
                    <i class="fa-solid fa-terminal brand-logo-icon"></i>
                    <div class="brand-title-wrap">
                        <span class="brand-title">Rentox API</span>
                        <span class="brand-sub">SERVICE</span>
                    </div>
                </div>

                <div>
                    <h1 class="hero-tagline">Build Your Integration With Rentox</h1>
                    <p class="hero-desc" style="margin-top:10px;">
                        Create your B2B partner account and get access to our developer ecosystem, sandbox environment, API documentation, and production integration tools.
                    </p>
                </div>

                <!-- 3 Benefit Items -->
                <div class="benefits-grid">
                    <div class="benefit-card">
                        <i class="fa-solid fa-bolt benefit-icon"></i>
                        <div class="benefit-content">
                            <h4>Sandbox API Access</h4>
                            <p>Test your integration safely with sandbox credentials before going live.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <i class="fa-solid fa-key benefit-icon"></i>
                        <div class="benefit-content">
                            <h4>Developer Credentials</h4>
                            <p>Manage your API keys, secrets, and integration settings from one secure portal.</p>
                        </div>
                    </div>

                    <div class="benefit-card">
                        <i class="fa-solid fa-chart-line benefit-icon"></i>
                        <div class="benefit-content">
                            <h4>API Monitoring</h4>
                            <p>Track API request volume, success rates, usage statistics, and trip logs in real time.</p>
                        </div>
                    </div>
                </div>

                <!-- 4-Step Vertical Application Stepper -->
                <div class="process-section">
                    <h3 class="process-title">
                        <i class="fa-solid fa-route" style="color:var(--primary-accent);"></i> Application Process
                    </h3>

                    <div class="stepper-vertical">
                        <div class="step-row active">
                            <div class="step-circle">01</div>
                            <div class="step-info">
                                <h5>Submit Application</h5>
                                <p>Create your B2B partner account with your business credentials.</p>
                            </div>
                        </div>

                        <div class="step-row">
                            <div class="step-circle">02</div>
                            <div class="step-info">
                                <h5>Admin Review</h5>
                                <p>Our admin team reviews and approves your partner profile.</p>
                            </div>
                        </div>

                        <div class="step-row">
                            <div class="step-circle">03</div>
                            <div class="step-info">
                                <h5>Sandbox Access</h5>
                                <p>Start testing your API integration immediately after approval.</p>
                            </div>
                        </div>

                        <div class="step-row">
                            <div class="step-circle">04</div>
                            <div class="step-info">
                                <h5>Production Activation</h5>
                                <p>Complete the setup deposit payment to unlock live production keys.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Security & Trust Badge -->
                <div class="trust-badge-left">
                    <i class="fa-solid fa-shield-halved"></i>
                    <div>
                        <strong style="color:#FFF; display:block; margin-bottom:2px;">Secure Partner Registration</strong>
                        <span>Your account information is protected and securely processed.</span>
                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN: REGISTRATION CARD PANEL -->
            <div class="right-panel">
                <div class="register-card">

                    <div class="card-top">
                        <div class="logo-badge">
                            <i class="fa-solid fa-terminal"></i>
                            <span>Rentox API Service</span>
                        </div>
                        <h2 class="welcome-heading">Create Your B2B Account</h2>
                        <p class="supporting-text">Apply for partner access and start building your integration.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fa-solid fa-circle-exclamation alert-icon"></i>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#FFF; margin-bottom:2px;">Registration Error</strong>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="regForm">
                        
                        <!-- SECTION 1: CONTACT INFORMATION -->
                        <div class="form-section-divider">Contact Information</div>

                        <div class="form-group">
                            <label class="form-label" for="contact_person">Full Name <span class="req">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-user input-icon"></i>
                                <input 
                                    type="text" 
                                    id="contact_person" 
                                    name="contact_person" 
                                    class="form-control" 
                                    placeholder="Enter your full name" 
                                    required 
                                    value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>"
                                    autocomplete="name"
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Business Email <span class="req">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control" 
                                    placeholder="name@company.com" 
                                    required 
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    autocomplete="email"
                                >
                            </div>
                        </div>

                        <!-- SECTION 2: COMPANY INFORMATION -->
                        <div class="form-section-divider">Company Information</div>

                        <div class="form-group">
                            <label class="form-label" for="company_name">Company Name <span class="req">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-building input-icon"></i>
                                <input 
                                    type="text" 
                                    id="company_name" 
                                    name="company_name" 
                                    class="form-control" 
                                    placeholder="Enter your registered company name" 
                                    required 
                                    value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>"
                                    autocomplete="organization"
                                >
                            </div>
                        </div>

                        <!-- SECTION 3: ACCOUNT SECURITY -->
                        <div class="form-section-divider">Account Security</div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password <span class="req">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-control" 
                                    placeholder="Create a password" 
                                    required
                                    oninput="checkPasswordStrength()"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="btn-toggle-eye" onclick="toggleEye('password', 'eyeIcon1')" title="Show/Hide Password" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye" id="eyeIcon1"></i>
                                </button>
                            </div>

                            <!-- Password Strength Indicator -->
                            <div class="strength-meter-wrap">
                                <div class="strength-bar-bg">
                                    <div class="strength-bar-fill" id="strengthBar"></div>
                                </div>
                                <div class="strength-text-row">
                                    <span>Password strength: <strong class="strength-text" id="strengthText">Weak</strong></span>
                                    <span id="charCheck" style="color:var(--text-secondary);"><i class="fa-solid fa-circle-check"></i> Min. 6 characters</span>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="form-control" 
                                    placeholder="Confirm your password" 
                                    required
                                    oninput="checkPasswordMatch()"
                                    autocomplete="new-password"
                                >
                                <button type="button" class="btn-toggle-eye" onclick="toggleEye('confirm_password', 'eyeIcon2')" title="Show/Hide Password" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                                </button>
                            </div>
                            <span id="matchError" style="color:#FCA5A5; font-size:0.78rem; display:none;"><i class="fa-solid fa-triangle-exclamation"></i> Passwords do not match</span>
                        </div>

                        <!-- Terms & Privacy Agreement -->
                        <div style="margin-top:4px;">
                            <label class="checkbox-container">
                                <input type="checkbox" id="termsCheck" onchange="toggleSubmitBtn()">
                                <span class="checkmark"></span>
                                <span>I agree to the <a href="#" style="color:var(--primary-accent); text-decoration:none; font-weight:600;">Terms of Service</a> and <a href="#" style="color:var(--primary-accent); text-decoration:none; font-weight:600;">Privacy Policy</a>.</span>
                            </label>
                        </div>

                        <!-- Primary CTA Button -->
                        <button type="submit" class="btn-primary-cta" id="btnSubmit" disabled>
                            <span>Create Partner Account →</span>
                        </button>

                    </form>

                    <!-- Application Notice -->
                    <div class="application-notice">
                        <i class="fa-solid fa-lock"></i>
                        <div>
                            <strong style="color:#D1D5DB; display:block; margin-bottom:2px;">Your information is securely submitted for partner review.</strong>
                            <p style="margin:0;">After registration, your account will remain pending until approved by the Rentox admin team.</p>
                        </div>
                    </div>

                    <!-- Footer Link -->
                    <div class="card-footer-links">
                        <span>Already have a partner account?</span>
                        <a href="login.php" class="highlight-link">Sign in to Developer Console →</a>
                    </div>

                </div>
            </div>

        </div>

        <!-- PAGE FOOTER -->
        <footer class="portal-footer">
            <div class="footer-copy">&copy; 2026 Rentox API Service</div>
            <div class="footer-links">
                <a href="#">Privacy</a>
                <span class="sep">•</span>
                <a href="#">Terms</a>
                <span class="sep">•</span>
                <a href="dashboard.php#docs">API Documentation</a>
                <span class="sep">•</span>
                <a href="#">Support</a>
            </div>
        </footer>

    </div>

    <script>
        function toggleEye(inputId, iconId) {
            const pwd = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        }

        function checkPasswordStrength() {
            const pwd = document.getElementById('password').value;
            const bar = document.getElementById('strengthBar');
            const text = document.getElementById('strengthText');
            const charCheck = document.getElementById('charCheck');

            if (pwd.length >= 6) {
                charCheck.style.color = '#34D399';
            } else {
                charCheck.style.color = 'var(--text-secondary)';
            }

            if (pwd.length === 0) {
                bar.style.width = '0%';
                text.innerText = 'Weak';
                text.style.color = '#94A3B8';
            } else if (pwd.length < 6) {
                bar.style.width = '30%';
                bar.style.background = '#EF4444';
                text.innerText = 'Weak';
                text.style.color = '#EF4444';
            } else if (pwd.length < 10) {
                bar.style.width = '65%';
                bar.style.background = '#F59E0B';
                text.innerText = 'Medium';
                text.style.color = '#F59E0B';
            } else {
                bar.style.width = '100%';
                bar.style.background = '#10B981';
                text.innerText = 'Strong';
                text.style.color = '#10B981';
            }
            checkPasswordMatch();
        }

        function checkPasswordMatch() {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const matchError = document.getElementById('matchError');

            if (confirm.length > 0 && pwd !== confirm) {
                matchError.style.display = 'inline-block';
            } else {
                matchError.style.display = 'none';
            }
        }

        function toggleSubmitBtn() {
            const termsCheck = document.getElementById('termsCheck');
            const btnSubmit = document.getElementById('btnSubmit');
            btnSubmit.disabled = !termsCheck.checked;
        }

        document.getElementById('regForm').addEventListener('submit', function(e) {
            const pwd = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            const termsCheck = document.getElementById('termsCheck');

            if (pwd !== confirm) {
                e.preventDefault();
                alert('Passwords do not match. Please verify your password entries.');
                return false;
            }

            if (!termsCheck.checked) {
                e.preventDefault();
                alert('Please agree to the Terms of Service and Privacy Policy.');
                return false;
            }

            const btn = document.getElementById('btnSubmit');
            if (btn) {
                btn.disabled = true;
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.85';
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating Account...';
            }
        });
    </script>
</body>
</html>
