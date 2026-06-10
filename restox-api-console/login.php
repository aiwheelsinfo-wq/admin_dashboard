<?php
session_start();
require_once __DIR__ . '/../db_connect.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['admin_id'])) {
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
            // Check credentials against admins table
            $stmt = mysqli_prepare($conn, "SELECT id, password FROM admins WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 's', $email);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $admin = mysqli_fetch_assoc($res);
            mysqli_stmt_close($stmt);

            if ($admin && $password === $admin['password']) {
                // Set session
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $email;
                
                header("Location: dashboard.php");
                exit();
            } else {
                $error = 'Invalid admin email address or password.';
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
    <title>Login - Redox API Service</title>
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
            max-width: 450px;
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
            justify-content: center;
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
            margin-bottom: 32px;
            text-align: center;
        }

        .header-title {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 0.88rem;
            font-weight: 500;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 6px;
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

        .alert {
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            font-size: 0.92rem;
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.4;
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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
            box-shadow: 0 4px 15px rgba(108, 99, 255, 0.35);
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 99, 255, 0.5);
            background: linear-gradient(135deg, #818cf8 0%, var(--primary-accent) 100%);
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
        <div class="logo-section">
            <i class="fa-solid fa-terminal logo-icon"></i>
            <span class="logo-text">REDOX API SERVICE</span>
        </div>

        <div class="header-section">
            <h2 class="header-title">Admin Console Login</h2>
            <p class="header-subtitle">Log in using your Agni Car Rental administrator email and password.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label" for="email"><i class="fa-solid fa-envelope"></i> Administrator Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@company.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-right-to-bracket"></i> Login to Console
            </button>
        </form>

        <div class="footer-note">
            &copy; 2026 Redox. All rights reserved. Redox API Service.
        </div>
    </div>

</body>
</html>
