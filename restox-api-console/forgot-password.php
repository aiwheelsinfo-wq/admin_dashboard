<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['partner_id'])) {
    header("Location: dashboard.php");
    exit();
}

$step = $_GET['step'] ?? 'email';
$error = '';
$success = '';

// Step 1: Handle Email Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    $email = trim($_POST['email'] ?? '');

    if (!$email) {
        $error = 'Please enter your registered business email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $stmt = mysqli_prepare($conn, "SELECT id, partner_name, company_name FROM partners WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $partner = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($partner) {
                $otp = (string)rand(100000, 999999);
                $to_name = !empty($partner['partner_name']) ? $partner['partner_name'] : $partner['company_name'];

                $_SESSION['reset_pass'] = [
                    'email'        => $email,
                    'otp'          => $otp,
                    'partner_id'   => $partner['id'],
                    'partner_name' => $to_name,
                    'otp_expiry'   => time() + 600, // 10 minutes valid
                    'verified'     => false
                ];

                if (send_reset_password_otp_email($email, $otp, $to_name)) {
                    header("Location: forgot-password.php?step=verify");
                    exit();
                } else {
                    $error = 'Failed to send OTP email. Please try again or contact support.';
                }
            } else {
                $error = 'No partner account found with this email address.';
            }
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Resend OTP Action
if (isset($_GET['action']) && $_GET['action'] === 'resend' && isset($_SESSION['reset_pass'])) {
    if (isset($_SESSION['last_resend_time']) && (time() - $_SESSION['last_resend_time']) < 30) {
        $error = 'Please wait 30 seconds before requesting a new OTP.';
    } else {
        $new_otp = (string)rand(100000, 999999);
        $_SESSION['reset_pass']['otp'] = $new_otp;
        $_SESSION['reset_pass']['otp_expiry'] = time() + 600;
        $_SESSION['last_resend_time'] = time();

        $email = $_SESSION['reset_pass']['email'];
        $to_name = $_SESSION['reset_pass']['partner_name'];

        if (send_reset_password_otp_email($email, $new_otp, $to_name)) {
            $success = 'A fresh password reset OTP has been sent to your email!';
        } else {
            $error = 'Failed to resend OTP. Please try again.';
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => empty($error), 'message' => empty($error) ? $success : $error]);
        exit;
    }
}

// Step 2: Handle OTP Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    if (!isset($_SESSION['reset_pass'])) {
        header("Location: forgot-password.php");
        exit();
    }

    $digits = $_POST['otp_digits'] ?? [];
    $submitted_otp = implode('', array_map('trim', $digits));

    $sess = $_SESSION['reset_pass'];

    if (strlen($submitted_otp) !== 6 || !ctype_digit($submitted_otp)) {
        $error = 'Please enter a valid 6-digit OTP code.';
    } elseif (time() > $sess['otp_expiry']) {
        $error = 'This OTP has expired. Click "Resend Code" to get a fresh OTP.';
    } elseif ($submitted_otp !== $sess['otp']) {
        $error = 'Incorrect OTP code. Please check your email and try again.';
    } else {
        $_SESSION['reset_pass']['verified'] = true;
        header("Location: forgot-password.php?step=new_password");
        exit();
    }
}

// Step 3: Handle New Password Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    if (!isset($_SESSION['reset_pass']) || empty($_SESSION['reset_pass']['verified'])) {
        header("Location: forgot-password.php");
        exit();
    }

    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!$new_password || !$confirm_password) {
        $error = 'Please enter both password fields.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation password do not match.';
    } else {
        try {
            $hashed = password_hash($new_password, PASSWORD_BCRYPT);
            $partner_id = $_SESSION['reset_pass']['partner_id'];

            $stmt = mysqli_prepare($conn, "UPDATE partners SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $hashed, $partner_id);

            if (mysqli_stmt_execute($stmt)) {
                unset($_SESSION['reset_pass']);
                header("Location: forgot-password.php?step=success");
                exit();
            } else {
                $error = 'Failed to update password. Please try again.';
            }
            mysqli_stmt_close($stmt);
        } catch (Exception $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Redox API Service</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(17, 24, 39, 0.75);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --primary-accent: #6c63ff;
            --primary-glow: rgba(108, 99, 255, 0.35);
            --success-color: #10b981;
            --error-color: #ef4444;
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-focus: #818cf8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 0% 0%, rgba(108, 99, 255, 0.12) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow-x: hidden;
        }

        .ambient-blur {
            position: absolute; width: 400px; height: 400px; border-radius: 50%; filter: blur(100px); z-index: -1; opacity: 0.5;
        }
        .blur-1 { top: 10%; left: 10%; background: var(--primary-accent); }
        .blur-2 { bottom: 10%; right: 10%; background: #10b981; }

        .console-container {
            width: 100%; max-width: 460px;
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            padding: 40px; position: relative;
            animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(25px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; justify-content: center; }
        .logo-icon { font-size: 2rem; color: var(--primary-accent); text-shadow: 0 0 15px var(--primary-glow); }
        .logo-text {
            font-weight: 800; font-size: 1.5rem; letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        .header-section { margin-bottom: 28px; text-align: center; }
        .header-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 8px; color: #fff; }
        .header-subtitle { color: var(--text-secondary); font-size: 0.92rem; line-height: 1.5; }

        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
        .form-label { font-size: 0.88rem; font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }

        .form-control {
            font-family: inherit; background-color: var(--input-bg);
            border: 1px solid var(--input-border); border-radius: 12px;
            color: #fff; padding: 12px 16px; font-size: 0.95rem; outline: none; transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--input-focus); box-shadow: 0 0 0 4px var(--primary-glow); background-color: rgba(255, 255, 255, 0.05);
        }

        /* 6-Digit OTP Inputs */
        .otp-inputs { display: flex; justify-content: space-between; gap: 8px; margin: 20px 0; }
        .otp-field {
            width: 50px; height: 56px; font-family: 'JetBrains Mono', monospace; font-size: 1.5rem; font-weight: 700;
            text-align: center; background-color: var(--input-bg); border: 1px solid var(--input-border);
            border-radius: 12px; color: var(--success-color); outline: none; transition: all 0.25s ease;
        }
        .otp-field:focus { border-color: var(--success-color); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.2); background-color: rgba(255, 255, 255, 0.06); }

        .alert {
            border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; font-size: 0.9rem;
            display: flex; align-items: center; gap: 10px; line-height: 1.4;
        }
        .alert-danger { background-color: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #6ee7b7; }

        .btn-submit {
            display: flex; justify-content: center; align-items: center; gap: 10px;
            background: linear-gradient(135deg, var(--primary-accent) 0%, #4f46e5 100%);
            color: #fff; border: none; border-radius: 12px; padding: 14px 24px;
            font-family: inherit; font-size: 1rem; font-weight: 600; cursor: pointer; width: 100%;
            transition: all 0.3s ease; box-shadow: 0 4px 15px rgba(108, 99, 255, 0.35); margin-top: 10px;
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(108, 99, 255, 0.5); }

        .redirect-link { text-align: center; margin-top: 24px; font-size: 0.9rem; color: var(--text-secondary); }
        .redirect-link a { color: var(--primary-accent); text-decoration: none; font-weight: 600; }
        .redirect-link a:hover { text-decoration: underline; }

        .footer-note { text-align: center; font-size: 0.8rem; color: rgba(255, 255, 255, 0.2); margin-top: 32px; }
    </style>
</head>
<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <div class="console-container">
        <div class="logo-section">
            <i class="fa-solid fa-terminal logo-icon"></i>
            <span class="logo-text">REDOX API SERVICE</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" id="alertBox">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" id="successBox">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <!-- STEP 1: ENTER BUSINESS EMAIL -->
        <?php if ($step === 'email'): ?>
            <div class="header-section">
                <h2 class="header-title">Forgot Password?</h2>
                <p class="header-subtitle">Enter your registered business email address and we'll send you a 6-digit OTP code to reset your password.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="send_otp">
                <div class="form-group">
                    <label class="form-label" for="email"><i class="fa-solid fa-envelope"></i> Registered Business Email</label>
                    <input type="email" id="email" name="email" class="form-control" placeholder="email@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Send Reset Code
                </button>
            </form>

            <div class="redirect-link">
                Remembered your password? <a href="login.php">Back to Login</a>
            </div>

        <!-- STEP 2: ENTER VERIFICATION OTP -->
        <?php elseif ($step === 'verify'): ?>
            <div class="header-section">
                <h2 class="header-title">Verify OTP Code</h2>
                <p class="header-subtitle">We sent a 6-digit password reset OTP to <br><strong style="color:var(--primary-accent);"><?= htmlspecialchars($_SESSION['reset_pass']['email'] ?? 'your email') ?></strong></p>
            </div>

            <form method="POST" id="otpForm">
                <input type="hidden" name="action" value="verify_otp">
                
                <div class="otp-inputs">
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                    <input type="text" class="otp-field" name="otp_digits[]" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-shield-check"></i> Verify OTP Code
                </button>
            </form>

            <div class="redirect-link">
                Didn't get code? <a href="#" id="resendLink" onclick="resendOtp(event)">Resend OTP</a>
                <span style="display:block; margin-top:8px;"><a href="forgot-password.php" style="color:var(--text-secondary); font-size:0.85rem;">Change Email Address</a></span>
            </div>

            <script>
                // OTP Field Navigation & Paste Listener
                const fields = document.querySelectorAll('.otp-field');
                fields.forEach((f, idx) => {
                    f.addEventListener('input', (e) => {
                        if (e.target.value.length === 1 && idx < fields.length - 1) {
                            fields[idx + 1].focus();
                        }
                    });
                    f.addEventListener('keydown', (e) => {
                        if (e.key === 'Backspace' && !e.target.value && idx > 0) {
                            fields[idx - 1].focus();
                        }
                    });
                });
                if (fields[0]) {
                    fields[0].addEventListener('paste', (e) => {
                        const paste = (e.clipboardData || window.clipboardData).getData('text').trim();
                        if (/^\d{6}$/.test(paste)) {
                            paste.split('').forEach((char, i) => {
                                if (fields[i]) fields[i].value = char;
                            });
                            fields[fields.length - 1].focus();
                            e.preventDefault();
                        }
                    });
                }

                function resendOtp(e) {
                    e.preventDefault();
                    const link = document.getElementById('resendLink');
                    link.innerText = 'Sending...';
                    link.style.pointerEvents = 'none';

                    fetch('forgot-password.php?action=resend', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(r => r.json())
                        .then(data => {
                            alert(data.message);
                            link.innerText = 'Resend OTP';
                            link.style.pointerEvents = 'auto';
                        })
                        .catch(err => {
                            alert('Failed to resend: ' + err.message);
                            link.innerText = 'Resend OTP';
                            link.style.pointerEvents = 'auto';
                        });
                }
            </script>

        <!-- STEP 3: ENTER NEW PASSWORD -->
        <?php elseif ($step === 'new_password'): ?>
            <div class="header-section">
                <h2 class="header-title">Create New Password</h2>
                <p class="header-subtitle">Your OTP code was verified successfully. Set a strong new password for your account.</p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_password">
                
                <div class="form-group">
                    <label class="form-label" for="new_password"><i class="fa-solid fa-lock"></i> New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="At least 6 characters" required minlength="6">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password"><i class="fa-solid fa-shield-halved"></i> Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required minlength="6">
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-key"></i> Update Password & Save
                </button>
            </form>

        <!-- STEP 4: SUCCESS CARD -->
        <?php elseif ($step === 'success'): ?>
            <div class="header-section" style="margin-bottom:20px;">
                <i class="fa-solid fa-circle-check" style="font-size:3.5rem; color:var(--success-color); margin-bottom:16px;"></i>
                <h2 class="header-title">Password Reset Complete!</h2>
                <p class="header-subtitle">Your password has been updated successfully. You can now log into your developer console with your new password.</p>
            </div>

            <a href="login.php" class="btn-submit" style="text-decoration:none;">
                <i class="fa-solid fa-right-to-bracket"></i> Proceed to Login
            </a>
        <?php endif; ?>

        <div class="footer-note">
            &copy; 2026 Redox. All rights reserved. Redox API Service.
        </div>
    </div>
</body>
</html>
