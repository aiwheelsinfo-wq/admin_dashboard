<?php
// vehicle_onboard_success.php - Dedicated success landing page for vehicle onboard form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-amber: #FFB300;
            --accent-yellow: #FFD54F;
            --dark-charcoal: #121212;
            --surface-dark: #1E1E1E;
            --text-light: #F5F5F5;
            --text-grey: #B0B0B0;
            --success-green: #66BB6A;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f0f12 0%, #1e1b15 100%);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 500px;
            background: rgba(30, 30, 30, 0.75);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 179, 0, 0.15);
            border-radius: 24px;
            padding: 45px 35px;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 0 25px rgba(255, 179, 0, 0.05);
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

        .success-icon-container {
            width: 100px;
            height: 100px;
            background: rgba(102, 187, 106, 0.1);
            border: 2px dashed var(--success-green);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px auto;
            position: relative;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(102, 187, 106, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(102, 187, 106, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(102, 187, 106, 0);
            }
        }

        .success-icon {
            font-size: 50px;
            color: var(--success-green);
        }

        .title {
            font-size: 26px;
            font-weight: 800;
            color: white;
            margin-bottom: 12px;
        }

        .desc {
            font-size: 15px;
            color: var(--text-grey);
            line-height: 1.6;
            margin-bottom: 35px;
        }

        .btn-row {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border-radius: 12px;
            border: none;
            font-family: inherit;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-amber);
            color: var(--dark-charcoal);
        }

        .btn-primary:hover {
            background: var(--accent-yellow);
            box-shadow: 0 5px 15px rgba(255, 179, 0, 0.3);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-grey);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .footer-logo {
            max-height: 35px;
            margin-top: 40px;
            opacity: 0.6;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="success-icon-container">
            <i class="fa-solid fa-circle-check success-icon"></i>
        </div>

        <h2 class="title">Submission Successful!</h2>
        <p class="desc">Vehicle details submitted successfully. Thank you for registering your vehicle with Agni Car Rental. Our team will review the details shortly.</p>

        <div class="btn-row">
            <a href="vehicle_onboard.php" class="btn btn-primary">
                <i class="fa-solid fa-plus-circle"></i> Register Another Vehicle
            </a>
            <a href="https://agnicarrental.com" class="btn btn-secondary">
                <i class="fa-solid fa-globe"></i> Go to Homepage
            </a>
        </div>

        <img src="images/logo_rentox.png" alt="Agni Logo" class="footer-logo">
    </div>

</body>
</html>
