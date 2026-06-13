<?php
session_start();
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/mailer.php';

// Auth redirect if already logged in
if (isset($_SESSION['partner_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Redirect back to registration if no temporary registration data exists
if (!isset($_SESSION['temp_reg'])) {
    header("Location: register.php");
    exit();
}

$temp = $_SESSION['temp_reg'];
$error = '';
$success = '';

// Handle Resend OTP Request
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    // Regulate resend frequency (min 30 seconds interval)
    if (isset($_SESSION['last_resend_time']) && (time() - $_SESSION['last_resend_time']) < 30) {
        $error = 'Please wait 30 seconds before requesting a new OTP.';
    } else {
        $new_otp = (string)rand(100000, 999999);
        $_SESSION['temp_reg']['otp'] = $new_otp;
        $_SESSION['temp_reg']['otp_expiry'] = time() + 600; // Extend by 10 mins
        $_SESSION['last_resend_time'] = time();

        if (send_otp_email($temp['email'], $new_otp, $temp['contact_person'])) {
            $success = 'A fresh verification OTP has been sent to your email!';
            // Refresh local reference
            $temp = $_SESSION['temp_reg'];
        } else {
            $error = 'Failed to resend verification email. Please try again.';
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

// Handle OTP submission verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Combine 6 input boxes or read single code string
    $digits = $_POST['otp_digits'] ?? [];
    $submitted_otp = implode('', array_map('trim', $digits));

    if (strlen($submitted_otp) !== 6 || !ctype_digit($submitted_otp)) {
        $error = 'Please enter a valid 6-digit OTP code.';
    } elseif (time() > $temp['otp_expiry']) {
        $error = 'This OTP has expired. Please request a new code.';
    } elseif ($submitted_otp !== $temp['otp']) {
        $error = 'The OTP code is incorrect. Please verify and try again.';
    } else {
        // Verification success -> write record to database
        try {
            $partner_name = $temp['company_name']; // Default partner name to company name
            $notes = 'Registered via Redox API Service B2B Console (Email Verified)';
            
            $stmt = mysqli_prepare($conn, 
                "INSERT INTO partners (partner_name, company_name, contact_person, email, password, status, notes)
                 VALUES (?, ?, ?, ?, ?, 'pending', ?)"
            );
            mysqli_stmt_bind_param($stmt, 'ssssss', 
                $partner_name, $temp['company_name'], $temp['contact_person'], $temp['email'], $temp['password'], $notes
            );

            if (mysqli_stmt_execute($stmt)) {
                $new_id = mysqli_insert_id($conn);
                
                // Automatically send email notification to Rentox Admin using PHPMailer
                send_admin_notification_email(
                    $temp['company_name'],
                    $partner_name,
                    '', // Owner Name
                    $temp['contact_person'],
                    '', // Contact Mobile
                    $temp['email'],
                    ''  // GST Number
                );

                // Clear temporary session data
                unset($_SESSION['temp_reg']);
                unset($_SESSION['last_resend_time']);

                // Auto-login verified partner
                $_SESSION['partner_id'] = $new_id;
                $_SESSION['partner_email'] = $temp['email'];
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Database insertion failed: ' . mysqli_error($conn);
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
    <title>Verify OTP - Redox API Service</title>
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
            --primary-accent: #6c63ff;
            --primary-glow: rgba(108, 99, 255, 0.35);
            --success-color: #10b981;
            --error-color: #ef4444;
            --input-bg: rgba(255, 255, 255, 0.03);
            --input-border: rgba(255, 255, 255, 0.1);
            --input-focus: #818cf8;
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
            justify-content: center;
            align-items: center;
            padding: 20px;
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
        .blur-1 { top: 10%; left: 10%; background: var(--primary-accent); }
        .blur-2 { bottom: 10%; right: 10%; background: #10b981; }

        .console-container {
            width: 100%;
            max-width: 480px;
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            background-color: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            padding: 40px;
            position: relative;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .logo-icon {
            font-size: 2rem;
            color: var(--primary-accent);
            text-shadow: 0 0 15px var(--primary-glow);
        }

        .logo-text {
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
            background: linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-section {
            margin-bottom: 30px;
        }

        .header-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.92rem;
            line-height: 1.5;
        }

        .header-subtitle strong {
            color: #fff;
        }

        /* 6 OTP Boxes Styling */
        .otp-inputs-wrapper {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 24px;
        }

        .otp-digit-field {
            width: 50px;
            height: 58px;
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: #fff;
            outline: none;
            transition: all 0.3s ease;
        }

        .otp-digit-field:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .d-none {
            display: none !important;
        }

        .alert {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
        }

        .btn-submit {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--primary-accent) 0%, #4f46e5 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px 24px;
            font-family: inherit;
            font-size: 0.98rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.35);
            margin-bottom: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.5);
            background: linear-gradient(135deg, #818cf8 0%, var(--primary-accent) 100%);
        }

        .timer-resend-section {
            text-align: center;
            font-size: 0.88rem;
            color: var(--text-secondary);
        }

        .btn-resend {
            background: none;
            border: none;
            color: var(--primary-accent);
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: color 0.2s;
        }

        .btn-resend:hover {
            color: #818cf8;
            text-decoration: underline;
        }

        .btn-resend[disabled] {
            color: var(--text-muted);
            cursor: not-allowed;
            text-decoration: none;
        }

        .redirect-link {
            text-align: center;
            margin-top: 24px;
            font-size: 0.88rem;
            color: var(--text-secondary);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            padding-top: 20px;
        }

        .redirect-link a {
            color: var(--primary-accent);
            text-decoration: none;
            font-weight: 600;
        }

        .redirect-link a:hover { text-decoration: underline; }
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

        <div class="header-section">
            <h2 class="header-title">Enter Verification Code</h2>
            <p class="header-subtitle">We have sent a 6-digit verification code to <strong><?= htmlspecialchars($temp['email']) ?></strong>. Please enter it below to complete your registration.</p>
        </div>

        <?php if ($error): ?>
            <div id="statusAlert" class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php elseif ($success): ?>
            <div id="statusAlert" class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php else: ?>
            <div id="statusAlert" class="alert alert-danger d-none">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span></span>
            </div>
        <?php endif; ?>

        <form method="POST" id="otpForm">
            <div class="otp-inputs-wrapper">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
                <input type="text" name="otp_digits[]" class="otp-digit-field" maxlength="1" pattern="[0-9]" required autocomplete="off">
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-circle-check"></i> Verify & Register
            </button>
        </form>

        <div class="timer-resend-section">
            <span id="timerText">Code expires in <strong id="timerCountdown">10:00</strong></span>
            <span id="resendPromptText" class="d-none">Didn't receive the code?</span>
            <button id="btnResendOtp" class="btn-resend d-none" onclick="resendOtp()">Resend OTP</button>
        </div>

        <div class="redirect-link">
            Need to change email? <a href="register.php">Start over</a>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script>
        $(document).ready(function() {
            const inputs = $(".otp-digit-field");
            
            // Handle automatic tab-focus shifting on input entry
            inputs.on("input", function(e) {
                const target = $(e.target);
                const val = target.val();
                
                // Allow only numbers
                if (!/^[0-9]$/.test(val)) {
                    target.val("");
                    return;
                }
                
                // Jump to next input field
                const next = target.next(".otp-digit-field");
                if (next.length) {
                    next.focus();
                }
            });
            
            // Handle backspace navigation between fields
            inputs.on("keydown", function(e) {
                const target = $(e.target);
                if (e.key === "Backspace" && target.val() === "") {
                    const prev = target.prev(".otp-digit-field");
                    if (prev.length) {
                        prev.focus();
                    }
                }
            });

            // Handle submission loading state
            $("#otpForm").on("submit", function() {
                const btn = $(this).find(".btn-submit");
                btn.html('<i class="fa-solid fa-spinner fa-spin"></i> Verifying...');
                btn.css("pointer-events", "none");
                btn.css("opacity", "0.85");
            });

            // Countdown Timer Configuration
            const expiryTimestamp = <?= $temp['otp_expiry'] ?>;
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const secondsLeft = expiryTimestamp - now;

                if (secondsLeft <= 0) {
                    $("#timerCountdown").text("00:00");
                    $("#timerText").addClass("d-none");
                    $("#resendPromptText").removeClass("d-none");
                    $("#btnResendOtp").removeClass("d-none");
                    clearInterval(timerInterval);
                } else {
                    const minutes = Math.floor(secondsLeft / 60);
                    const seconds = secondsLeft % 60;
                    const formattedMinutes = String(minutes).padStart(2, '0');
                    const formattedSeconds = String(seconds).padStart(2, '0');
                    $("#timerCountdown").text(`${formattedMinutes}:${formattedSeconds}`);
                }
            }

            // Start Countdown
            updateCountdown();
            const timerInterval = setInterval(updateCountdown, 1000);
            
            // Show resend button after 30 seconds even if not expired
            setTimeout(function() {
                $("#resendPromptText").removeClass("d-none");
                $("#btnResendOtp").removeClass("d-none");
            }, 30000);

            // Trigger background mail runner asynchronously to process the spooled OTP email
            fetch('mail_runner.php').catch(err => console.error('Mail runner trigger failed:', err));
        });

        // AJAX handler for resending OTP
        function resendOtp() {
            const alertBox = $("#statusAlert");
            alertBox.addClass("d-none").removeClass("alert-danger alert-success");
            $("#btnResendOtp").attr("disabled", true).text("Sending...");

            $.ajax({
                url: "verify-otp.php?action=resend",
                method: "GET",
                dataType: "json",
                headers: {"X-Requested-With": "XMLHttpRequest"},
                success: function(res) {
                    if (res.success) {
                        alertBox.removeClass("d-none").addClass("alert-success").find("span").text(res.message);
                        
                        // Trigger the mail runner immediately to process the resent OTP
                        fetch('mail_runner.php').catch(() => {});
                        
                        // Reload page in 1.5 seconds to refresh the timer countdown easily
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        alertBox.removeClass("d-none").addClass("alert-danger").find("span").text(res.message);
                        $("#btnResendOtp").removeAttr("disabled").text("Resend OTP");
                    }
                },
                error: function() {
                    alertBox.removeClass("d-none").addClass("alert-danger").find("span").text("Network error. Could not resend OTP.");
                    $("#btnResendOtp").removeAttr("disabled").text("Resend OTP");
                }
            });
        }
    </script>
</body>
</html>
