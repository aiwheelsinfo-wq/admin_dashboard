<?php
include 'db_connect.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_no = mysqli_real_escape_string($conn, trim($_POST['car_no'] ?? ''));
    $car_type = mysqli_real_escape_string($conn, trim($_POST['car_type'] ?? ''));
    $owner_name = mysqli_real_escape_string($conn, trim($_POST['owner_name'] ?? ''));
    $owner_mobile = mysqli_real_escape_string($conn, trim($_POST['owner_mobile'] ?? ''));
    $driver_name = mysqli_real_escape_string($conn, trim($_POST['driver_name'] ?? ''));
    $driver_mobile = mysqli_real_escape_string($conn, trim($_POST['driver_mobile'] ?? ''));
    $location = mysqli_real_escape_string($conn, trim($_POST['location'] ?? ''));

    if (empty($car_no) || empty($car_type) || empty($owner_name) || empty($owner_mobile) || empty($driver_name) || empty($driver_mobile) || empty($location)) {
        $message = "Please fill in all fields.";
    } else {
        $sql = "INSERT INTO shared_onboardings (car_no, car_type, owner_name, owner_mobile, driver_name, driver_mobile, location, status) 
                VALUES ('$car_no', '$car_type', '$owner_name', '$owner_mobile', '$driver_name', '$driver_mobile', '$location', 'Pending')";

        if (mysqli_query($conn, $sql)) {
            $success = true;
            $message = "Your details have been submitted successfully!";
        } else {
            $message = "Failed to submit details: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fleet Registration - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #1a1a1a;
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 10px;
        }
        .form-container {
            background-color: #262626;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(255, 179, 0, 0.15);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            border: 1px solid #333333;
        }
        .brand-logo {
            text-align: center;
            margin-bottom: 25px;
        }
        .brand-logo img {
            max-width: 180px;
            height: auto;
        }
        .form-title {
            text-align: center;
            font-weight: 700;
            color: #ffb300;
            margin-bottom: 30px;
            font-size: 1.6rem;
            letter-spacing: 0.5px;
        }
        .form-label {
            font-weight: 500;
            color: #cccccc;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }
        .form-control, .form-select {
            background-color: #1e1e1e;
            border: 1px solid #444444;
            color: #ffffff;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            background-color: #1e1e1e;
            border-color: #ffb300;
            color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(255, 179, 0, 0.25);
        }
        .input-group-text {
            background-color: #1e1e1e;
            border: 1px solid #444444;
            color: #ffb300;
            border-radius: 10px 0 0 10px;
        }
        .form-control-with-icon {
            border-radius: 0 10px 10px 0;
        }
        .submit-btn {
            background-color: #ffb300;
            color: #1a1a1a;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        .submit-btn:hover {
            background-color: #ffa000;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 179, 0, 0.3);
            color: #1a1a1a;
        }
        .submit-btn:active {
            transform: translateY(0);
        }
        .section-header {
            font-size: 1rem;
            font-weight: 600;
            color: #ffb300;
            border-left: 3px solid #ffb300;
            padding-left: 10px;
            margin: 25px 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .success-box {
            text-align: center;
            padding: 30px;
        }
        .success-icon {
            font-size: 4rem;
            color: #2e7d32;
            margin-bottom: 20px;
            animation: scaleUp 0.5s ease-out;
        }
        @keyframes scaleUp {
            0% { transform: scale(0); }
            100% { transform: scale(1); }
        }
        .success-title {
            font-weight: 700;
            color: #4caf50;
            margin-bottom: 15px;
        }
        .success-desc {
            color: #cccccc;
            margin-bottom: 25px;
        }
        .back-btn {
            background-color: transparent;
            border: 2px solid #ffb300;
            color: #ffb300;
            padding: 10px 25px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .back-btn:hover {
            background-color: #ffb300;
            color: #1a1a1a;
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="brand-logo">
        <img src="images/logo_rentox.png" alt="Agni Car Rental">
    </div>

    <?php if ($success): ?>
        <div class="success-box">
            <i class="fas fa-check-circle success-icon"></i>
            <h3 class="success-title">Submission Successful</h3>
            <p class="success-desc">Thank you for registering your fleet. Your details have been submitted to the administrator for review and approval.</p>
            <a href="onboard.php" class="back-btn"><i class="fas fa-redo me-2"></i>Submit Another</a>
        </div>
    <?php else: ?>
        <h2 class="form-title">Fleet Registration Form</h2>

        <?php if (!empty($message)): ?>
            <div class="alert alert-danger bg-danger text-white border-0" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="onboard.php" method="POST" class="needs-validation" novalidate>
            
            <div class="section-header">Vehicle Details</div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="car_no" class="form-label">Car Number</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-car"></i></span>
                        <input type="text" class="form-control form-control-with-icon" id="car_no" name="car_no" placeholder="e.g. MH12AB1234" required style="text-transform: uppercase;">
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="car_type" class="form-label">Car Type</label>
                    <select class="form-select" id="car_type" name="car_type" required>
                        <option value="" disabled selected>Select Category</option>
                        <option value="Hatchback">Hatchback</option>
                        <option value="Sedan">Sedan</option>
                        <option value="SUV">SUV</option>
                        <option value="Prime SUV">Prime SUV</option>
                    </select>
                </div>
            </div>

            <div class="section-header">Owner Details</div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="owner_name" class="form-label">Owner Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user-tie"></i></span>
                        <input type="text" class="form-control form-control-with-icon" id="owner_name" name="owner_name" placeholder="Full Name" required>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="owner_mobile" class="form-label">Owner Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                        <input type="tel" class="form-control form-control-with-icon" id="owner_mobile" name="owner_mobile" placeholder="10-digit number" pattern="[0-9]{10}" required>
                    </div>
                </div>
            </div>

            <div class="section-header">Driver Details</div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="driver_name" class="form-label">Driver Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                        <input type="text" class="form-control form-control-with-icon" id="driver_name" name="driver_name" placeholder="Full Name" required>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="driver_mobile" class="form-label">Driver Mobile</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
                        <input type="tel" class="form-control form-control-with-icon" id="driver_mobile" name="driver_mobile" placeholder="10-digit number" pattern="[0-9]{10}" required>
                    </div>
                </div>
            </div>

            <div class="section-header">Service Area</div>

            <div class="mb-4">
                <label for="location" class="form-label">Operating City / Location</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                    <input type="text" class="form-control form-control-with-icon" id="location" name="location" placeholder="e.g. Pune, Maharashtra" required>
                </div>
            </div>

            <button type="submit" class="submit-btn"><i class="fas fa-paper-plane me-2"></i>Submit Fleet Details</button>
        </form>
    <?php endif; ?>
</div>

<script>
    // Bootstrap validation script
    (function () {
        'use strict'
        var forms = document.querySelectorAll('.needs-validation')
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
    })()
</script>
</body>
</html>
