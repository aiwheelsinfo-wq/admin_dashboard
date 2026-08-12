<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['partner_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Check credentials against partners table
            $stmt = mysqli_prepare($conn, "SELECT id, password, company_name FROM partners WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $partner = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($partner && password_verify($password, $partner['password'])) {
                // Set session
                $_SESSION['partner_id'] = $partner['id'];
                $_SESSION['partner_email'] = $email;
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid email address or password.';
            }
        } catch (Exception $e) {
            $error = 'An error occurred during authentication: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Rentox API Developer Console</title>
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
            padding: 24px 16px;
            overflow-x: hidden;
        }

        .login-page-wrapper {
            width: 100%;
            max-width: 1100px;
            margin: auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .portal-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 48px;
            align-items: center;
        }

        /* LEFT COLUMN: BRAND & DEVELOPER PLATFORM */
        .brand-left-panel {
            display: flex;
            flex-direction: column;
            gap: 24px;
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
            font-size: 2.5rem;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #FFFFFF 30%, #A5B4FC 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .hero-desc {
            font-size: 1.05rem;
            color: var(--text-secondary);
            line-height: 1.6;
            max-width: 500px;
        }

        .feature-highlights {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 4px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
            color: #E2E8F0;
            font-weight: 500;
        }

        .feature-item i {
            color: var(--success-color);
            font-size: 1rem;
        }

        /* Decorative Code Visual Card */
        .code-preview-card {
            background: #070B14;
            border: 1px solid rgba(99, 102, 241, 0.2);
            border-radius: 16px;
            padding: 20px;
            font-family: var(--font-code);
            font-size: 0.85rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            margin-top: 8px;
            position: relative;
            overflow: hidden;
        }

        .code-card-header {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .dot-red { background: #EF4444; }
        .dot-yellow { background: #F59E0B; }
        .dot-green { background: #10B981; }
        .code-title { margin-left: 10px; font-size: 0.75rem; color: var(--text-secondary); }

        .code-block {
            line-height: 1.6;
            color: #CBD5E1;
            white-space: pre-wrap;
        }

        .code-block .method { color: #60A5FA; font-weight: 700; }
        .code-block .path { color: #F8FAFC; }
        .code-block .header-name { color: #94A3B8; }
        .code-block .status-200 { color: #34D399; font-weight: 700; }
        .code-block .key { color: #A5B4FC; }
        .code-block .str { color: #FBBF24; }

        /* RIGHT COLUMN: LOGIN CARD PANEL */
        .login-right-panel {
            display: flex;
            justify-content: center;
        }

        .login-card {
            width: 100%;
            max-width: 450px;
            background: var(--panel-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid var(--card-border);
            border-radius: 22px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.45);
            padding: 36px 32px;
            display: flex;
            flex-direction: column;
            gap: 20px;
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

        .sub-heading {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-accent);
        }

        .supporting-text {
            font-size: 0.88rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-top: 2px;
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

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .label-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #CBD5E1;
        }

        .forgot-link {
            color: var(--primary-accent);
            font-size: 0.82rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .forgot-link:hover {
            color: #A5B4FC;
            text-decoration: underline;
        }

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
            min-height: 46px;
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

        .btn-toggle-eye:hover {
            color: #FFF;
        }

        /* Custom Remember Me Checkbox */
        .remember-row {
            display: flex;
            align-items: center;
            margin-top: 4px;
        }

        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .checkbox-container input {
            display: none;
        }

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

        .checkbox-container input:checked ~ .checkmark::after {
            display: block;
        }

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
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
        }

        .btn-primary-cta:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(99, 102, 241, 0.5);
            background: linear-gradient(135deg, #818CF8 0%, var(--primary-accent) 100%);
        }

        .btn-primary-cta:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
        }

        .security-indicator {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .security-indicator i {
            color: var(--success-color);
            font-size: 0.9rem;
            margin-top: 2px;
        }

        .security-indicator strong {
            color: #D1D5DB;
            display: block;
        }

        .card-footer-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 14px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.85rem;
        }

        .footer-link-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--text-secondary);
            flex-wrap: wrap;
            gap: 4px;
        }

        .highlight-link {
            color: var(--primary-accent);
            font-weight: 700;
            text-decoration: none;
        }

        .highlight-link:hover {
            text-decoration: underline;
        }

        .subtle-link {
            color: var(--text-secondary);
            font-weight: 500;
            text-decoration: none;
        }

        .subtle-link:hover {
            color: #FFF;
            text-decoration: underline;
        }

        /* Minimal Footer */
        .portal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 10px;
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

        .footer-links a:hover {
            color: #FFF;
        }

        .footer-links .sep {
            opacity: 0.3;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 992px) {
            .portal-grid {
                grid-template-columns: 1fr;
                gap: 36px;
            }
            .brand-left-panel {
                padding-right: 0;
                text-align: center;
                align-items: center;
            }
            .hero-tagline { font-size: 2rem; }
            .feature-highlights { align-items: center; }
            .code-preview-card { display: none; }
        }

        @media (max-width: 576px) {
            body { padding: 16px 12px; }
            .login-card { padding: 28px 20px; border-radius: 18px; }
            .welcome-heading { font-size: 1.5rem; }
            .portal-footer { flex-direction: column; text-align: center; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="login-page-wrapper">
        
        <!-- MAIN PORTAL GRID -->
        <div class="portal-grid">

            <!-- LEFT COLUMN: BRAND & DEVELOPER PLATFORM -->
            <div class="brand-left-panel">
                <div class="brand-header">
                    <i class="fa-solid fa-terminal brand-logo-icon"></i>
                    <div class="brand-title-wrap">
                        <span class="brand-title">Rentox API</span>
                        <span class="brand-sub">SERVICE</span>
                    </div>
                </div>

                <h1 class="hero-tagline">Build. Integrate. Scale.</h1>
                <p class="hero-desc">
                    Connect your platform to Rentox through reliable APIs built for modern travel and mobility businesses.
                </p>

                <div class="feature-highlights">
                    <div class="feature-item">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Production API Access</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Sandbox Testing Environment</span>
                    </div>
                    <div class="feature-item">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Real-time API Monitoring</span>
                    </div>
                </div>

                <!-- Decorative API Code Visual -->
                <div class="code-preview-card">
                    <div class="code-card-header">
                        <span class="dot dot-red"></span>
                        <span class="dot dot-yellow"></span>
                        <span class="dot dot-green"></span>
                        <span class="code-title">HTTP Request Sample</span>
                    </div>
                    <pre class="code-block"><code><span class="method">GET</span> <span class="path">/api/v1/vehicles</span>

<span class="header-name">Authorization:</span> Bearer ••••••••••••

<span class="status-200">200 OK</span>

{
  <span class="key">"status"</span>: <span class="str">"success"</span>,
  <span class="key">"vehicles"</span>: [ ... ]
}</code></pre>
                </div>
            </div>

            <!-- RIGHT COLUMN: LOGIN CARD PANEL -->
            <div class="login-right-panel">
                <div class="login-card">

                    <div class="card-top">
                        <div class="logo-badge">
                            <i class="fa-solid fa-terminal"></i>
                            <span>Rentox API Service</span>
                        </div>
                        <h2 class="welcome-heading">Welcome back</h2>
                        <p class="sub-heading">Sign in to Developer Console</p>
                        <p class="supporting-text">Access your API credentials, documentation, usage analytics, and partner account.</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" id="loginAlert">
                            <i class="fa-solid fa-circle-exclamation alert-icon"></i>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#FFF; margin-bottom:2px;">Invalid email or password</strong>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="loginForm">
                        <!-- Business Email Input -->
                        <div class="form-group">
                            <label class="form-label" for="email">Business Email</label>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-envelope input-icon"></i>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    class="form-control" 
                                    placeholder="Enter your business email" 
                                    required 
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    autocomplete="email"
                                >
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div class="form-group">
                            <div class="label-row">
                                <label class="form-label" for="password">Password</label>
                                <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                            </div>
                            <div class="input-icon-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="form-control" 
                                    placeholder="Enter your password" 
                                    required
                                    autocomplete="current-password"
                                >
                                <button type="button" class="btn-toggle-eye" id="togglePasswordBtn" onclick="togglePasswordVisibility()" title="Show/Hide password" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me Checkbox -->
                        <div class="remember-row">
                            <label class="checkbox-container">
                                <input type="checkbox" name="remember_me" id="rememberMe">
                                <span class="checkmark"></span>
                                <span class="checkbox-label">Remember me</span>
                            </label>
                        </div>

                        <!-- Primary Login Button -->
                        <button type="submit" class="btn-primary-cta" id="btnSubmit">
                            <span>→ Sign in to Developer Console</span>
                        </button>
                    </form>

                    <!-- Security Indicator -->
                    <div class="security-indicator">
                        <i class="fa-solid fa-lock"></i>
                        <div>
                            <strong>Secure partner authentication</strong>
                            <p>Your credentials are encrypted and protected.</p>
                        </div>
                    </div>

                    <!-- Registration & Sandbox Links -->
                    <div class="card-footer-links">
                        <div class="footer-link-row">
                            <span>Don't have a partner account?</span>
                            <a href="register.php" class="highlight-link">Apply for Partner Access →</a>
                        </div>
                        <div class="footer-link-row" style="margin-top:4px;">
                            <span>Need to test the API first?</span>
                            <a href="dashboard.php#docs" class="subtle-link">Explore Sandbox Documentation →</a>
                        </div>
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
        function togglePasswordVisibility() {
            const pwd = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.className = 'fa-solid fa-eye-slash';
            } else {
                pwd.type = 'password';
                icon.className = 'fa-solid fa-eye';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('btnSubmit');
            if (btn) {
                btn.disabled = true;
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.85';
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing in...';
            }
        });
    </script>
</body>
</html>
