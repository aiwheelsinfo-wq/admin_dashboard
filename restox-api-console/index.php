<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name       = trim($_POST['company_name'] ?? '');
    $company_owner_name = trim($_POST['company_owner_name'] ?? '');
    $contact_person     = trim($_POST['contact_person'] ?? '');
    $contact_number     = trim($_POST['contact_number'] ?? '');
    $email              = trim($_POST['email'] ?? '');
    $gst_number         = trim($_POST['gst_number'] ?? '');

    // Basic validation
    if (!$company_name || !$company_owner_name || !$contact_person || !$contact_number || !$email || !$gst_number) {
        $error = 'All fields are compulsory.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid business email address.';
    } else {
        try {
            // Check if email already exists
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM partners WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, 's', $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $error = 'This email address is already registered. Please contact support.';
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                // Insert pending request
                // We map: partner_name => contact_person (or owner name), notes => 'Registered via Restox API Console'
                $partner_name = $company_name; // Default partner_name display to company name
                $notes = 'Registered via Restox API Console';
                
                $stmt = mysqli_prepare($conn, 
                    "INSERT INTO partners (partner_name, company_name, company_owner_name, contact_person, mobile_number, email, gst_number, status, notes)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
                );
                
                mysqli_stmt_bind_param($stmt, 'ssssssss', 
                    $partner_name, $company_name, $company_owner_name, $contact_person, $contact_number, $email, $gst_number, $notes
                );

                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Your API credentials request has been submitted successfully!';
                } else {
                    $error = 'Failed to submit request: ' . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            }
        } catch (Exception $e) {
            $error = 'An error occurred: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restox API Developer Console</title>
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
        .blur-1 {
            top: 10%;
            left: 10%;
            background: var(--primary-accent);
        }
        .blur-2 {
            bottom: 10%;
            right: 10%;
            background: #10b981;
        }

        .console-container {
            width: 100%;
            max-width: 650px;
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
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            animation: pulse 3s infinite alternate;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.8; }
            100% { transform: scale(1.05); opacity: 1; }
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
            margin-bottom: 32px;
        }

        .header-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 580px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .console-container {
                padding: 24px;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label span {
            color: var(--error-color);
        }

        .form-control {
            font-family: inherit;
            background-color: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 12px;
            color: #fff;
            padding: 12px 16px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .form-control:focus {
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px var(--primary-glow);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.2);
        }

        .alert {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.4;
            animation: fadeIn 0.4s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
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
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.3);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.5);
            background: linear-gradient(135deg, #818cf8 0%, var(--primary-accent) 100%);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .success-card {
            text-align: center;
            padding: 20px 0;
            animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: 24px;
            filter: drop-shadow(0 0 15px rgba(16, 185, 129, 0.3));
        }

        .success-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: #fff;
        }

        .success-text {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .success-badge {
            background-color: rgba(255, 152, 0, 0.1);
            border: 1px solid rgba(255, 152, 0, 0.25);
            color: #ffb74d;
            padding: 10px 16px;
            border-radius: 100px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .footer-note {
            text-align: center;
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.2);
            margin-top: 32px;
        }
    </style>
</head>
<body>

    <div class="ambient-blur blur-1"></div>
    <div class="ambient-blur blur-2"></div>

    <div class="console-container">
        
        <?php if ($success): ?>
            <!-- Success Display State -->
            <div class="success-card">
                <i class="fa-solid fa-circle-check success-icon"></i>
                <h3 class="success-title">Application Submitted</h3>
                <p class="success-text">
                    Your request for partner API access was successfully recorded. Our administration team is reviewing your profile and company details.
                </p>
                <div class="success-badge">
                    <i class="fa-solid fa-hourglass-half"></i> Status: Pending Admin Approval
                </div>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin-top: 24px;">
                    An API Key & Secret will be generated upon approval. Please check back later or verify with your account manager.
                </p>
            </div>
        <?php else: ?>
            <!-- Registration Form State -->
            <div class="logo-section">
                <i class="fa-solid fa-terminal logo-icon"></i>
                <span class="logo-text">RESTOX DEVELOPER PORTAL</span>
            </div>

            <div class="header-section">
                <h2 class="header-title">Request API Credentials</h2>
                <p class="header-subtitle">Submit your company information to request a REST API Key and Secret. Access is subject to manual verification and approval.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="company_name"><i class="fa-solid fa-building"></i> Company Name <span>*</span></label>
                        <input type="text" id="company_name" name="company_name" class="form-control" placeholder="e.g. Akbar Travels" required value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="company_owner_name"><i class="fa-solid fa-user-tie"></i> Company Owner Name <span>*</span></label>
                        <input type="text" id="company_owner_name" name="company_owner_name" class="form-control" placeholder="Full name of owner" required value="<?= htmlspecialchars($_POST['company_owner_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="contact_person"><i class="fa-solid fa-user"></i> Contact Person <span>*</span></label>
                        <input type="text" id="contact_person" name="contact_person" class="form-control" placeholder="Primary contact name" required value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="contact_number"><i class="fa-solid fa-phone"></i> Contact Number <span>*</span></label>
                        <input type="tel" id="contact_number" name="contact_number" class="form-control" placeholder="+91 XXXXXXXXXX" required value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="email"><i class="fa-solid fa-envelope"></i> Business Email <span>*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="api@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label" for="gst_number"><i class="fa-solid fa-file-invoice"></i> GST Number <span>*</span></label>
                        <input type="text" id="gst_number" name="gst_number" class="form-control" placeholder="15-digit GSTIN (e.g. 07AAAAA1111A1Z1)" required value="<?= htmlspecialchars($_POST['gst_number'] ?? '') ?>">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-paper-plane"></i> Submit Request
                </button>
            </form>
        <?php endif; ?>

        <div class="footer-note">
            &copy; 2026 Restox. All rights reserved. Restox API Console.
        </div>
    </div>

</body>
</html>
