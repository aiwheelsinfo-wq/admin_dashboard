<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <title>Login Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0a0d14;
            font-family: 'DM Sans', sans-serif;
        }

        nav { display: none; }

        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem;
        }

        .login-card {
            display: flex;
            width: 820px;
            min-height: 540px;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(0,0,0,0.55);
        }

        /* ── LEFT PANEL ── */
        .login-card::before {
            content: '';
            display: block;
        }

        .left-panel {
            flex: 1;
            background: #0f1520;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem 2.5rem;
            position: relative;
            overflow: hidden;
            text-align: center;
        }

        .left-panel::before,
        .left-panel::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: #e8a020;
            opacity: 0.07;
        }
        .left-panel::before { width: 420px; height: 420px; top: -130px; left: -130px; }
        .left-panel::after  { width: 260px; height: 260px; bottom: -80px; right: -80px; }

        .flame-icon {
            font-size: 52px;
            display: block;
            margin-bottom: 14px;
            filter: drop-shadow(0 0 20px rgba(232,160,32,0.55));
            animation: flamePulse 2.5s ease-in-out infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes flamePulse {
            0%, 100% { filter: drop-shadow(0 0 14px rgba(232,160,32,0.4)); }
            50%       { filter: drop-shadow(0 0 28px rgba(232,160,32,0.78)); }
        }

        .brand-name {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 1px;
            line-height: 1.1;
            position: relative;
            z-index: 1;
        }

        .brand-sub {
            font-size: 0.72rem;
            letter-spacing: 5px;
            text-transform: uppercase;
            color: #e8a020;
            margin-top: 10px;
            font-weight: 400;
            position: relative;
            z-index: 1;
        }

        .divider-line {
            width: 60px;
            height: 1.5px;
            background: #e8a020;
            margin: 22px auto;
            opacity: 0.7;
            position: relative;
            z-index: 1;
        }

        .left-tagline {
            color: #7a8599;
            font-size: 0.82rem;
            max-width: 200px;
            line-height: 1.9;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .car-svg {
            margin-top: 2.2rem;
            position: relative;
            z-index: 1;
            opacity: 0.65;
        }

        /* ── RIGHT PANEL ── */
        .right-panel {
            width: 380px;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 3rem 2.5rem;
            position: relative;
        }

        .right-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #e8a020, #f4c46a);
        }

        .right-panel h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f1520;
            margin-bottom: 4px;
            animation: fadeUp 0.5s ease both 0.05s;
        }

        .right-subhead {
            font-size: 0.82rem;
            color: #8892a4;
            margin-bottom: 2rem;
            font-weight: 400;
            animation: fadeUp 0.5s ease both 0.1s;
        }

        .form-group {
            margin-bottom: 1.2rem;
            animation: fadeUp 0.5s ease both 0.15s;
        }

        .form-group label {
            display: block;
            font-size: 0.72rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1.3px;
            color: #555f70;
            margin-bottom: 7px;
        }

        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            border: 1.5px solid #e0e4ed;
            border-radius: 8px;
            padding: 11px 42px 11px 14px;
            font-size: 0.9rem;
            font-family: 'DM Sans', sans-serif;
            color: #0f1520;
            background: #f8f9fc;
            outline: none;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #e8a020;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(232,160,32,0.12);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-icon {
            position: absolute;
            right: 13px;
            bottom: 13px;
            color: #aab0be;
            cursor: pointer;
            font-size: 15px;
            transition: color 0.2s;
        }
        .toggle-icon:hover { color: #e8a020; }

        .error-msg {
            font-size: 0.77rem;
            color: #d85a30;
            min-height: 18px;
            margin: -6px 0 10px;
        }

        .login-btn {
            width: 100%;
            padding: 13px;
            background: #0f1520;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            font-family: 'DM Sans', sans-serif;
            letter-spacing: 2px;
            text-transform: uppercase;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: background 0.25s, transform 0.15s;
            animation: fadeUp 0.5s ease both 0.25s;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 4px;
            background: #e8a020;
            transition: width 0.3s ease;
            z-index: 0;
        }

        .login-btn span {
            position: relative;
            z-index: 1;
        }

        .login-btn:hover { background: #1a2030; }
        .login-btn:hover::before { width: 100%; }
        .login-btn:active { transform: scale(0.99); }

        .footer-badge {
            position: absolute;
            bottom: 1.4rem;
            left: 0; right: 0;
            text-align: center;
            font-size: 0.68rem;
            color: #c0c6d0;
            letter-spacing: 0.4px;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 700px) {
            .login-card   { flex-direction: column; width: 95vw; min-height: auto; }
            .left-panel   { padding: 2.5rem 2rem; }
            .right-panel  { width: 100%; padding: 2.5rem 2rem; }
            .brand-name   { font-size: 2rem; }
            .car-svg      { display: none; }
        }
    </style>
</head>
<body>

    <nav>
        <img src="images/logo.png" alt="Company Logo" class="logo">
    </nav>

    <div class="container">
        <div class="login-card">

            <!-- LEFT PANEL -->
            <div class="left-panel">
                <span class="flame-icon">🔥</span>
                <p class="brand-name">Agni<br>Car Rental</p>
                <p class="brand-sub">Admin Portal</p>
                <div class="divider-line"></div>
                <p class="left-tagline">Manage your fleet.<br>Drive your business forward.</p>

                <svg class="car-svg" viewBox="0 0 230 75" width="220" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="25" y="30" width="180" height="28" rx="9" fill="#e8a020" opacity="0.18"/>
                    <rect x="48" y="16" width="124" height="32" rx="11" fill="#e8a020" opacity="0.26"/>
                    <rect x="58" y="20" width="44" height="22" rx="5" fill="#e8a020" opacity="0.38"/>
                    <rect x="112" y="20" width="46" height="22" rx="5" fill="#e8a020" opacity="0.38"/>
                    <circle cx="74"  cy="59" r="13" fill="#1a2030" stroke="#e8a020" stroke-width="2.5"/>
                    <circle cx="74"  cy="59" r="5"  fill="#e8a020" opacity="0.65"/>
                    <circle cx="156" cy="59" r="13" fill="#1a2030" stroke="#e8a020" stroke-width="2.5"/>
                    <circle cx="156" cy="59" r="5"  fill="#e8a020" opacity="0.65"/>
                    <rect x="25"  y="38" width="7" height="12" rx="2" fill="#e8a020" opacity="0.5"/>
                    <rect x="198" y="38" width="7" height="12" rx="2" fill="#e8a020" opacity="0.5"/>
                </svg>
            </div>

            <!-- RIGHT PANEL -->
            <div class="right-panel">
                <h2>Welcome back</h2>
                <p class="right-subhead">Sign in to the admin dashboard</p>

                <form id="loginForm">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" value="agnicarrental@gmail.com" placeholder="Enter your email" required>
                    </div>
                    <div class="form-group password-wrapper">
                        <label for="password">Password</label>
                        <input type="password" id="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye toggle-icon" id="toggleIcon"></i>
                    </div>
                    <p id="error-message" class="error-msg"></p>
                    <button type="submit" class="login-btn"><span>Login</span></button>
                </form>

                <p class="footer-badge">© 2025 Agni Car Rental. All rights reserved.</p>
            </div>

        </div>
    </div>

    <script src="javascripts/script.js"></script>

</body>
</html>
