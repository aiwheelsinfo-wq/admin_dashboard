<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agni Car Rental - Admin Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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

    .login-page {
      display: flex;
      width: 820px;
      min-height: 540px;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 30px 80px rgba(0,0,0,0.5);
    }

    /* ── LEFT PANEL ── */
    .login-left {
      flex: 1;
      background: #0f1520;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 3rem 2.5rem;
      position: relative;
      overflow: hidden;
    }

    .left-bg-circle {
      position: absolute;
      border-radius: 50%;
      background: #e8a020;
      opacity: 0.07;
    }
    .left-bg-circle.c1 { width: 420px; height: 420px; top: -130px; left: -130px; }
    .left-bg-circle.c2 { width: 260px; height: 260px; bottom: -80px; right: -80px; }

    .brand-block {
      text-align: center;
      z-index: 1;
    }

    .flame-icon {
      font-size: 52px;
      display: block;
      margin-bottom: 14px;
      filter: drop-shadow(0 0 20px rgba(232,160,32,0.55));
      animation: pulse 2.5s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { filter: drop-shadow(0 0 14px rgba(232,160,32,0.4)); }
      50%       { filter: drop-shadow(0 0 28px rgba(232,160,32,0.75)); }
    }

    .brand-name {
      font-family: 'Playfair Display', serif;
      font-size: 2.5rem;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: 1px;
      line-height: 1.1;
    }

    .brand-sub {
      font-size: 0.72rem;
      letter-spacing: 5px;
      text-transform: uppercase;
      color: #e8a020;
      margin-top: 10px;
      font-weight: 400;
    }

    .divider-line {
      width: 60px;
      height: 1.5px;
      background: #e8a020;
      margin: 22px auto;
      opacity: 0.7;
    }

    .left-tagline {
      color: #7a8599;
      font-size: 0.82rem;
      max-width: 200px;
      line-height: 1.9;
      text-align: center;
      z-index: 1;
    }

    .car-svg {
      margin-top: 2.2rem;
      z-index: 1;
      opacity: 0.65;
    }

    /* ── RIGHT PANEL ── */
    .login-right {
      width: 380px;
      background: #ffffff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3rem 2.5rem;
      position: relative;
    }

    .right-top-accent {
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, #e8a020, #f4c46a);
    }

    .login-heading {
      font-family: 'Playfair Display', serif;
      font-size: 1.8rem;
      font-weight: 700;
      color: #0f1520;
      margin-bottom: 4px;
      animation: fadeUp 0.5s ease both 0.05s;
    }

    .login-subhead {
      font-size: 0.82rem;
      color: #8892a4;
      margin-bottom: 2rem;
      font-weight: 400;
      animation: fadeUp 0.5s ease both 0.1s;
    }

    .form-field {
      margin-bottom: 1.2rem;
      animation: fadeUp 0.5s ease both 0.15s;
    }

    .form-field label {
      display: block;
      font-size: 0.72rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      color: #555f70;
      margin-bottom: 7px;
    }

    .input-wrap { position: relative; }

    .input-wrap input {
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

    .input-wrap input:focus {
      border-color: #e8a020;
      background: #fff;
      box-shadow: 0 0 0 3px rgba(232,160,32,0.12);
    }

    .input-wrap .eye-icon {
      position: absolute;
      right: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #aab0be;
      cursor: pointer;
      font-size: 15px;
      transition: color 0.2s;
    }
    .input-wrap .eye-icon:hover { color: #e8a020; }

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
      transition: width 0.25s;
    }

    .login-btn:hover {
      background: #e8a020;
    }
    .login-btn:hover::before { width: 100%; z-index: 0; }
    .login-btn:hover span { position: relative; z-index: 1; }
    .login-btn:active { transform: scale(0.99); }

    .forgot-link {
      display: block;
      text-align: right;
      font-size: 0.76rem;
      color: #e8a020;
      margin-top: 14px;
      text-decoration: none;
      font-weight: 500;
      cursor: pointer;
      transition: opacity 0.2s;
      animation: fadeUp 0.5s ease both 0.3s;
    }
    .forgot-link:hover { opacity: 0.75; }

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
      .login-page { flex-direction: column; width: 95vw; min-height: auto; }
      .login-left  { padding: 2.5rem 2rem; }
      .login-right { width: 100%; padding: 2.5rem 2rem; }
      .brand-name  { font-size: 2rem; }
    }
  </style>
</head>
<body>

<div class="login-page">

  <!-- LEFT PANEL -->
  <div class="login-left">
    <div class="left-bg-circle c1"></div>
    <div class="left-bg-circle c2"></div>

    <div class="brand-block">
      <span class="flame-icon">🔥</span>
      <p class="brand-name">Agni<br>Car Rental</p>
      <p class="brand-sub">Admin Portal</p>
      <div class="divider-line"></div>
      <p class="left-tagline">Manage your fleet.<br>Drive your business forward.</p>
    </div>

    <!-- Car SVG Illustration -->
    <svg class="car-svg" viewBox="0 0 230 75" width="230" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect x="25" y="30" width="180" height="28" rx="9" fill="#e8a020" opacity="0.18"/>
      <rect x="48" y="16" width="124" height="32" rx="11" fill="#e8a020" opacity="0.26"/>
      <rect x="58" y="20" width="44" height="22" rx="5" fill="#e8a020" opacity="0.38"/>
      <rect x="112" y="20" width="46" height="22" rx="5" fill="#e8a020" opacity="0.38"/>
      <circle cx="74"  cy="59" r="13" fill="#1a2030" stroke="#e8a020" stroke-width="2.5"/>
      <circle cx="74"  cy="59" r="5"  fill="#e8a020" opacity="0.65"/>
      <circle cx="156" cy="59" r="13" fill="#1a2030" stroke="#e8a020" stroke-width="2.5"/>
      <circle cx="156" cy="59" r="5"  fill="#e8a020" opacity="0.65"/>
      <rect x="25"  y="38" width="7"  height="12" rx="2" fill="#e8a020" opacity="0.5"/>
      <rect x="198" y="38" width="7"  height="12" rx="2" fill="#e8a020" opacity="0.5"/>
    </svg>
  </div>

  <!-- RIGHT PANEL -->
  <div class="login-right">
    <div class="right-top-accent"></div>

    <h2 class="login-heading">Welcome back</h2>
    <p class="login-subhead">Sign in to the admin dashboard</p>

    <form id="loginForm" action="your-auth-handler.php" method="POST">

      <div class="form-field">
        <label for="email">Email Address</label>
        <div class="input-wrap">
          <input
            type="email"
            id="email"
            name="email"
            value="agnicarrental@gmail.com"
            placeholder="Enter your email"
            required
          >
        </div>
      </div>

      <div class="form-field">
        <label for="password">Password</label>
        <div class="input-wrap">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          >
          <span class="eye-icon" id="toggleIcon" onclick="togglePassword()">
            <i class="fas fa-eye" id="eyeIconClass"></i>
          </span>
        </div>
      </div>

      <p class="error-msg" id="error-message"></p>

      <button type="submit" class="login-btn" onclick="return validateForm()">
        <span>Login</span>
      </button>

    </form>

    <a class="forgot-link" href="forgot-password.php">Forgot password?</a>

    <p class="footer-badge">© 2025 Agni Car Rental. All rights reserved.</p>
  </div>

</div>

<script>
  function togglePassword() {
    const pwd     = document.getElementById('password');
    const icon    = document.getElementById('eyeIconClass');
    if (pwd.type === 'password') {
      pwd.type      = 'text';
      icon.className = 'fas fa-eye-slash';
    } else {
      pwd.type      = 'password';
      icon.className = 'fas fa-eye';
    }
  }

  function validateForm() {
    const email = document.getElementById('email').value.trim();
    const pwd   = document.getElementById('password').value.trim();
    const err   = document.getElementById('error-message');

    if (!email || !pwd) {
      err.textContent = '⚠ Please enter both email and password.';
      return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      err.textContent = '⚠ Please enter a valid email address.';
      return false;
    }

    if (pwd.length < 6) {
      err.textContent = '⚠ Password must be at least 6 characters.';
      return false;
    }

    err.textContent = '';
    return true; // allows form to submit to PHP
  }
</script>

</body>
</html>