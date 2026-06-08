<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <title>Agni Car Rental - Admin Login</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts: Inter and Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Stylesheet override -->
    <link rel="stylesheet" type="text/css" href="css/styles.css">
    <style>
        /* Modern Reset & Base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background-color: #f1f5f9;
            font-family: 'Inter', sans-serif;
            overflow-x: hidden;
        }

        nav {
            display: none !important; /* hide default navigation if any */
        }

        /* Layout Container */
        .container {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: 100vw !important;
            background: #f8fafc;
        }

        .login-card {
            display: flex !important;
            width: 100vw !important;
            max-width: 100vw !important;
            height: 100vh !important;
            min-height: 100vh !important;
            padding: 0 !important;
            margin: 0 !important;
            border-radius: 0 !important;
            overflow: hidden !important;
            box-shadow: none !important;
            background-color: transparent !important;
        }

        /* ── LEFT PANEL (Branding & Features) ── */
        .left-panel {
            background: radial-gradient(circle at 10% 20%, #1a2236 0%, #0d1220 90%);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ffffff;
            z-index: 1;
        }

        /* Left Panel background glow grids */
        .left-panel::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(232, 160, 32, 0.08) 0%, rgba(232, 160, 32, 0) 70%);
            top: -150px;
            left: -150px;
            z-index: -1;
            pointer-events: none;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(232, 160, 32, 0.05) 0%, rgba(232, 160, 32, 0) 70%);
            bottom: -100px;
            right: -100px;
            z-index: -1;
            pointer-events: none;
        }

        .brand-container {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .brand-logo-img {
            height: 90px;
            object-fit: contain;
            margin-bottom: 1.25rem;
            filter: drop-shadow(0 0 15px rgba(232, 160, 32, 0.35));
            animation: pulseGlow 3s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% {
                transform: scale(1);
                filter: drop-shadow(0 0 15px rgba(232, 160, 32, 0.35));
            }
            50% {
                transform: scale(1.03);
                filter: drop-shadow(0 0 25px rgba(232, 160, 32, 0.55));
            }
        }

        .brand-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 800;
            font-size: 2.75rem;
            color: #ffffff;
            line-height: 1;
            letter-spacing: -0.5px;
            margin: 0;
        }

        .brand-subtitle {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            letter-spacing: 6px;
            color: #e8a020;
            margin: 6px 0 0 6px;
            text-transform: uppercase;
        }

        .title-underline {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #e8a020, #f5b027);
            margin: 1.5rem auto;
            border-radius: 2px;
        }

        .brand-tagline {
            font-size: 0.95rem;
            color: #94a3b8;
            max-width: 320px;
            line-height: 1.6;
            font-weight: 400;
        }

        /* Features Highlights */
        .feature-list {
            list-style: none;
            margin: 2rem 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem 2rem;
            text-align: left;
            max-width: 480px;
            width: 100%;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.95rem;
            color: #e2e8f0;
            font-weight: 500;
        }

        .feature-icon {
            color: #e8a020;
            font-size: 1.1rem;
            filter: drop-shadow(0 0 4px rgba(232, 160, 32, 0.4));
        }

        /* SVG Route Graphic */
        .left-graphics-container {
            width: 100%;
            max-width: 450px;
            margin-top: 1.5rem;
            position: relative;
        }

        .dashboard-svg {
            width: 100%;
            height: auto;
            overflow: visible;
        }

        .route-line-anim-1 {
            animation: routeAnim1 20s linear infinite;
        }

        .route-line-anim-2 {
            animation: routeAnim2 15s linear infinite;
        }

        @keyframes routeAnim1 {
            0% { stroke-dashoffset: 200; }
            100% { stroke-dashoffset: 0; }
        }

        @keyframes routeAnim2 {
            0% { stroke-dashoffset: 0; }
            100% { stroke-dashoffset: 200; }
        }

        .ping-anim {
            transform-origin: center;
            animation: ping 2.5s cubic-bezier(0, 0, 0.2, 1) infinite;
        }

        @keyframes ping {
            0% { transform: scale(0.6); opacity: 0.8; }
            100% { transform: scale(2.2); opacity: 0; }
        }

        /* ── RIGHT PANEL (Login Card) ── */
        .right-panel {
            background: radial-gradient(circle at 80% 20%, #f8fafc 0%, #e2e8f0 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .login-card-inner {
            width: 100%;
            max-width: 450px;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            box-shadow: 
                0 4px 6px -1px rgba(0, 0, 0, 0.02), 
                0 10px 15px -3px rgba(0, 0, 0, 0.03),
                0 20px 25px -5px rgba(0, 0, 0, 0.05),
                0 0 40px 0 rgba(232, 160, 32, 0.02);
            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .login-card-inner:hover {
            transform: translateY(-4px);
            box-shadow: 
                0 10px 15px -3px rgba(0, 0, 0, 0.03),
                0 25px 35px -10px rgba(0, 0, 0, 0.06),
                0 0 50px 0 rgba(232, 160, 32, 0.04);
        }

        .right-panel-header {
            margin-bottom: 2rem;
            text-align: left;
        }

        .right-panel-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .right-subhead {
            font-size: 0.9rem;
            color: #64748b;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #475569;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-prefix-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            font-size: 1rem;
            transition: color 0.2s;
            pointer-events: none;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 46px !important;
            background: rgba(248, 250, 252, 0.8) !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 12px !important;
            font-size: 0.95rem !important;
            font-family: 'Inter', sans-serif !important;
            color: #0f172a !important;
            outline: none !important;
            transition: all 0.2s ease-in-out !important;
            box-shadow: none !important;
        }

        .form-group input:focus {
            background: #ffffff !important;
            border-color: #e8a020 !important;
            box-shadow: 0 0 0 4px rgba(232, 160, 32, 0.15) !important;
        }

        .form-group input:focus + .input-prefix-icon {
            color: #e8a020;
        }

        .password-wrapper input {
            padding-right: 46px !important;
        }

        .toggle-icon {
            position: absolute;
            right: 16px;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1.1rem;
            transition: color 0.2s;
            z-index: 10;
        }

        .toggle-icon:hover {
            color: #e8a020;
        }

        .error-msg {
            font-size: 0.85rem;
            color: #dc2626;
            min-height: 22px;
            margin: -8px 0 16px;
            text-align: center;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        /* Gold/Amber Premium Gradient Button */
        .login-btn {
            width: 100% !important;
            padding: 14px !important;
            background: linear-gradient(135deg, #e8a020 0%, #f5b027 100%) !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 12px !important;
            font-size: 1rem !important;
            font-weight: 600 !important;
            font-family: 'Inter', sans-serif !important;
            letter-spacing: 0.5px !important;
            text-transform: none !important;
            cursor: pointer !important;
            position: relative !important;
            overflow: hidden !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            box-shadow: 0 4px 12px rgba(232, 160, 32, 0.25) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(232, 160, 32, 0.4) !important;
            background: linear-gradient(135deg, #f5b027 0%, #e8a020 100%) !important;
        }

        .login-btn:active {
            transform: translateY(0) !important;
            box-shadow: 0 4px 10px rgba(232, 160, 32, 0.2) !important;
        }

        /* ── ANIMATIONS ── */
        .animate-fade-in {
            opacity: 0;
            animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
        .delay-500 { animation-delay: 0.5s; }
        .delay-600 { animation-delay: 0.6s; }
        .delay-700 { animation-delay: 0.7s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Footer badge */
        .footer-badge {
            position: relative !important;
            bottom: auto !important;
            left: auto !important;
            right: auto !important;
            margin-top: 2rem !important;
            text-align: center !important;
            font-size: 0.8rem !important;
            color: #64748b !important;
            font-weight: 500 !important;
            letter-spacing: 0px !important;
        }

        /* ── RESPONSIVE MEDIA QUERIES ── */
        @media (min-width: 1024px) {
            .left-panel {
                width: 45%;
                height: 100vh;
                padding: 4rem;
            }
            .right-panel {
                width: 55%;
                height: 100vh;
                padding: 4rem;
            }
        }

        @media (min-width: 768px) and (max-width: 1023px) {
            .login-card {
                flex-direction: row !important;
            }
            .left-panel {
                width: 45%;
                height: 100vh;
                padding: 2.5rem 1.5rem;
            }
            .right-panel {
                width: 55%;
                height: 100vh;
                padding: 2.5rem 1.5rem;
            }
            .brand-title {
                font-size: 2.25rem;
            }
            .feature-list {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
        }

        @media (max-width: 767px) {
            .container {
                height: auto !important;
                min-height: 100vh !important;
                overflow-y: auto !important;
            }
            .login-card {
                flex-direction: column !important;
                height: auto !important;
                min-height: 100vh !important;
            }
            .left-panel {
                width: 100%;
                padding: 3rem 1.5rem 2.5rem;
                min-height: 35vh;
            }
            .right-panel {
                width: 100%;
                padding: 2.5rem 1.5rem 4rem;
                min-height: 65vh;
                justify-content: flex-start;
            }
            .feature-list, .left-graphics-container {
                display: none !important;
            }
            .login-card-inner {
                padding: 2rem 1.5rem;
                border-radius: 20px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="login-card">

            <!-- LEFT PANEL: BRANDING & FEATURES -->
            <div class="left-panel">
                <div class="brand-container animate-fade-in">
                    <img src="images/pnglogoagni.png" alt="Agni Logo" class="brand-logo-img">
                    <h1 class="brand-title">Agni</h1>
                    <p class="brand-subtitle">CAR RENTAL</p>
                    <div class="title-underline"></div>
                    <p class="brand-tagline">Enterprise Fleet & Mobility Management System</p>
                </div>

                <!-- Styled feature highlight list -->
                <ul class="feature-list">
                    <li class="feature-item animate-fade-in delay-200">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>One Way Booking</span>
                    </li>
                    <li class="feature-item animate-fade-in delay-300">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>Round Trip Booking</span>
                    </li>
                    <li class="feature-item animate-fade-in delay-400">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>Local Rentals</span>
                    </li>
                    <li class="feature-item animate-fade-in delay-500">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>Driver Management</span>
                    </li>
                    <li class="feature-item animate-fade-in delay-600">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>Vendor Management</span>
                    </li>
                    <li class="feature-item animate-fade-in delay-700">
                        <i class="fa-solid fa-circle-check feature-icon"></i>
                        <span>Partner API System</span>
                    </li>
                </ul>

                <!-- Premium Route & Fleet Visual Graphics -->
                <div class="left-graphics-container animate-fade-in delay-500">
                    <svg class="dashboard-svg" viewBox="0 0 500 250" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Dashboard grid background -->
                        <path d="M 0,200 L 500,200 M 0,150 L 500,150 M 0,100 L 500,100 M 0,50 L 500,50" stroke="rgba(255,255,255,0.03)" stroke-width="1"/>
                        <path d="M 100,0 L 100,250 M 200,0 L 200,250 M 300,0 L 300,250 M 400,0 L 400,250" stroke="rgba(255,255,255,0.03)" stroke-width="1"/>
                        
                        <!-- Glowing wave connection paths -->
                        <path d="M-20,180 C 100,220 180,60 300,80 C 400,95 450,180 520,170" stroke="url(#gradient-line-1)" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="12 8" class="route-line-anim-1"/>
                        <path d="M-20,120 C 120,60 220,220 340,160 C 420,120 460,70 520,60" stroke="url(#gradient-line-2)" stroke-width="2" stroke-linecap="round" stroke-dasharray="8 6" class="route-line-anim-2"/>
                        
                        <!-- Location points & pings -->
                        <circle cx="150" cy="140" r="5" fill="#e8a020"/>
                        <circle cx="150" cy="140" r="10" stroke="#e8a020" stroke-width="1.5" fill="none" class="ping-anim" style="animation-delay: 0s;"/>
                        
                        <circle cx="320" cy="115" r="5" fill="#f5b027"/>
                        <circle cx="320" cy="115" r="10" stroke="#f5b027" stroke-width="1.5" fill="none" class="ping-anim" style="animation-delay: 0.7s;"/>
                        
                        <!-- Premium Minimalist Car Outline -->
                        <g transform="translate(190, 85) scale(0.65)">
                            <ellipse cx="90" cy="40" rx="75" ry="10" fill="rgba(232, 160, 32, 0.15)" filter="blur(5px)" />
                            <path d="M 15 32 C 15 32 30 25 50 15 C 65 8 95 8 110 12 C 120 13 145 20 160 30 C 170 34 175 42 170 45 C 160 48 10 48 2 45 C -3 42 5 32 15 32 Z" fill="#1e293b" stroke="#e8a020" stroke-width="3" stroke-linejoin="round"/>
                            <path d="M 52 15 C 52 15 75 11 92 11 C 108 11 125 14 125 14 L 140 25 L 42 25 Z" fill="#0f172a" stroke="#e8a020" stroke-width="2"/>
                            <circle cx="45" cy="45" r="14" fill="#0b0f17" stroke="#e8a020" stroke-width="2.5"/>
                            <circle cx="45" cy="45" r="5" fill="#e8a020"/>
                            <circle cx="130" cy="45" r="14" fill="#0b0f17" stroke="#e8a020" stroke-width="2.5"/>
                            <circle cx="130" cy="45" r="5" fill="#e8a020"/>
                            <path d="M 172 38 L 195 35 L 195 45 Z" fill="url(#lightGlow)" opacity="0.6"/>
                        </g>
                        
                        <defs>
                            <linearGradient id="gradient-line-1" x1="0" y1="0" x2="500" y2="0" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#e8a020" stop-opacity="0.1"/>
                                <stop offset="50%" stop-color="#e8a020" stop-opacity="0.9"/>
                                <stop offset="100%" stop-color="#f5b027" stop-opacity="0.1"/>
                            </linearGradient>
                            <linearGradient id="gradient-line-2" x1="0" y1="0" x2="500" y2="0" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#f5b027" stop-opacity="0.1"/>
                                <stop offset="40%" stop-color="#f5b027" stop-opacity="0.8"/>
                                <stop offset="100%" stop-color="#e8a020" stop-opacity="0.1"/>
                            </linearGradient>
                            <linearGradient id="lightGlow" x1="172" y1="40" x2="195" y2="40" gradientUnits="userSpaceOnUse">
                                <stop offset="0%" stop-color="#e8a020" stop-opacity="1"/>
                                <stop offset="100%" stop-color="#e8a020" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                    </svg>
                </div>
            </div>

            <!-- RIGHT PANEL: CENTERED LOGIN CARD -->
            <div class="right-panel">
                <div class="login-card-inner animate-fade-in delay-200">
                    <div class="right-panel-header">
                        <h2>Welcome Back 👋</h2>
                        <p class="right-subhead">Sign in to continue to your dashboard</p>
                    </div>

                    <form id="loginForm">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <div class="input-wrapper">
                                <input type="email" id="email" value="agnicarrental@gmail.com" placeholder="name@company.com" required>
                                <i class="fa-regular fa-envelope input-prefix-icon"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper password-wrapper">
                                <input type="password" id="password" placeholder="••••••••" required>
                                <i class="fa-solid fa-lock input-prefix-icon"></i>
                                <i class="fas fa-eye toggle-icon" id="toggleIcon"></i>
                            </div>
                        </div>
                        
                        <p id="error-message" class="error-msg"></p>
                        
                        <button type="submit" class="login-btn">
                            <span>Sign In</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>

                    <footer class="footer-badge">
                        <p>© 2025 Agni Car Rental. All Rights Reserved.</p>
                    </footer>
                </div>
            </div>

        </div>
    </div>

    <!-- JS Form Submit & Visibility Handlers -->
    <script src="javascripts/script.js"></script>

</body>
</html>
