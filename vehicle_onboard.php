<?php
// vehicle_onboard.php - Public shareable form for vehicle data collection
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Fleet Registration - Agni Car Rental</title>
    <link rel="icon" type="image/png" href="images/pnglogoagni.png">
    <!-- Google Fonts & FontAwesome Icons -->
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
            --error-red: #EF5350;
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
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 550px;
            background: rgba(30, 30, 30, 0.75);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 179, 0, 0.15);
            border-radius: 24px;
            padding: 35px 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5), 0 0 25px rgba(255, 179, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            max-height: 60px;
            margin-bottom: 15px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-light);
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 14px;
            color: var(--text-grey);
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--primary-amber);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            color: var(--text-grey);
            font-size: 16px;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .form-input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            background: rgba(18, 18, 18, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            font-family: inherit;
            font-size: 15px;
            color: white;
            outline: none;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        .form-input:focus {
            border-color: var(--primary-amber);
            box-shadow: 0 0 10px rgba(255, 179, 0, 0.15);
        }

        .form-input:focus + .input-icon {
            color: var(--primary-amber);
        }

        select.form-input {
            appearance: none;
            cursor: pointer;
        }

        .select-arrow {
            position: absolute;
            right: 15px;
            color: var(--text-grey);
            pointer-events: none;
        }

        .location-container {
            display: flex;
            gap: 10px;
        }

        .location-container .form-input {
            flex: 1;
        }

        .gps-btn {
            padding: 0 18px;
            background: rgba(255, 179, 0, 0.1);
            border: 1px solid rgba(255, 179, 0, 0.3);
            border-radius: 12px;
            color: var(--primary-amber);
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .gps-btn:hover {
            background: var(--primary-amber);
            color: var(--dark-charcoal);
            border-color: var(--primary-amber);
        }

        .btn-row {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
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
        }

        .btn-submit {
            background: var(--primary-amber);
            color: var(--dark-charcoal);
        }

        .btn-submit:hover {
            background: var(--accent-yellow);
            box-shadow: 0 5px 15px rgba(255, 179, 0, 0.3);
            transform: translateY(-2px);
        }

        .btn-reset {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-grey);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-reset:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }

        /* Success & Error State Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background: var(--surface-dark);
            border: 1px solid rgba(255, 179, 0, 0.2);
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            transform: scale(0.9);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .modal-overlay.active .modal-card {
            transform: scale(1);
        }

        .modal-icon {
            font-size: 50px;
            margin-bottom: 20px;
        }

        .modal-icon.success {
            color: var(--success-green);
        }

        .modal-icon.error {
            color: var(--error-red);
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .modal-desc {
            font-size: 14px;
            color: var(--text-grey);
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .modal-btn {
            padding: 12px 30px;
            background: var(--primary-amber);
            color: var(--dark-charcoal);
            border: none;
            border-radius: 10px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .modal-btn:hover {
            background: var(--accent-yellow);
        }

        .error-message {
            color: var(--error-red);
            font-size: 12px;
            margin-top: 5px;
            display: none;
            padding-left: 5px;
        }

        /* Subtle micro-animations */
        @keyframes spinner {
            to {transform: rotate(360deg);}
        }
         
        .spinner:before {
            content: '';
            box-sizing: border-box;
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--dark-charcoal);
            border-top-color: transparent;
            animation: spinner .6s linear infinite;
        }

        .loading-text {
            visibility: hidden;
        }

        .btn-submit.loading .loading-text {
            visibility: hidden;
        }

        .btn-submit.loading:after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid var(--dark-charcoal);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spinner 0.6s linear infinite;
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }
            .btn-row {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <img src="images/logo_rentox.png" alt="Agni Logo" class="logo">
            <h2 class="title">Vehicle Fleet Registration</h2>
            <p class="subtitle">Complete the fields below to add your vehicle to the fleet</p>
        </div>

        <form id="vehicleForm" novalidate>
            <!-- Car No -->
            <div class="form-group">
                <label class="form-label" for="carNo">Car No</label>
                <div class="input-wrapper">
                    <input type="text" id="carNo" name="car_no" class="form-input" placeholder="e.g. MH-12-PQ-1234" required>
                    <i class="fa-solid fa-car input-icon"></i>
                </div>
                <div class="error-message" id="carNoError">Car number is required.</div>
            </div>

            <!-- Car Type -->
            <div class="form-group">
                <label class="form-label" for="carType">Car Type</label>
                <div class="input-wrapper">
                    <select id="carType" name="car_type" class="form-input" required>
                        <option value="" disabled selected>Select Car Type</option>
                        <option value="Sedan">Sedan</option>
                        <option value="SUV">SUV</option>
                        <option value="Hatchback">Hatchback</option>
                        <option value="Truck">Truck</option>
                        <option value="Other">Other</option>
                    </select>
                    <i class="fa-solid fa-list input-icon"></i>
                    <i class="fa-solid fa-chevron-down select-arrow"></i>
                </div>
                <div class="error-message" id="carTypeError">Please select a car type.</div>
            </div>

            <!-- Owner Name -->
            <div class="form-group">
                <label class="form-label" for="ownerName">Owner Name</label>
                <div class="input-wrapper">
                    <input type="text" id="ownerName" name="owner" class="form-input" placeholder="e.g. Rahul Sharma" required>
                    <i class="fa-solid fa-user-tie input-icon"></i>
                </div>
                <div class="error-message" id="ownerNameError">Owner name is required.</div>
            </div>

            <!-- Owner Mobile -->
            <div class="form-group">
                <label class="form-label" for="ownerMobile">Owner Mobile</label>
                <div class="input-wrapper">
                    <input type="tel" id="ownerMobile" name="owner_mobile" class="form-input" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" required>
                    <i class="fa-solid fa-phone input-icon"></i>
                </div>
                <div class="error-message" id="ownerMobileError">Please enter a valid 10-digit mobile number.</div>
            </div>

            <!-- Driver Name -->
            <div class="form-group">
                <label class="form-label" for="driverName">Driver Name</label>
                <div class="input-wrapper">
                    <input type="text" id="driverName" name="driver" class="form-input" placeholder="e.g. Amit Kumar" required>
                    <i class="fa-solid fa-user input-icon"></i>
                </div>
                <div class="error-message" id="driverNameError">Driver name is required.</div>
            </div>

            <!-- Driver Mobile -->
            <div class="form-group">
                <label class="form-label" for="driverMobile">Driver Mobile</label>
                <div class="input-wrapper">
                    <input type="tel" id="driverMobile" name="driver_mobile" class="form-input" placeholder="e.g. 9876543210" pattern="[6-9][0-9]{9}" required>
                    <i class="fa-solid fa-phone-volume input-icon"></i>
                </div>
                <div class="error-message" id="driverMobileError">Please enter a valid 10-digit mobile number.</div>
            </div>

            <!-- Location -->
            <div class="form-group">
                <label class="form-label" for="location">Location</label>
                <div class="location-container">
                    <div class="input-wrapper" style="flex: 1;">
                        <input type="text" id="location" name="location" class="form-input" placeholder="e.g. Pune, Maharashtra" required>
                        <i class="fa-solid fa-location-dot input-icon"></i>
                    </div>
                    <button type="button" class="gps-btn" id="gpsBtn" title="Detect GPS Location">
                        <i class="fa-solid fa-crosshairs"></i>
                    </button>
                </div>
                <div class="error-message" id="locationError">Location is required.</div>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <button type="button" class="btn btn-reset" id="resetBtn">
                    <i class="fa-solid fa-rotate-right"></i> Reset
                </button>
                <button type="submit" class="btn btn-submit" id="submitBtn">
                    <span class="loading-text"><i class="fa-solid fa-paper-plane"></i> Submit Details</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Status Modals -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-card">
            <div class="modal-icon" id="modalIcon"></div>
            <h3 class="modal-title" id="modalTitle"></h3>
            <p class="modal-desc" id="modalDesc"></p>
            <button class="modal-btn" id="modalCloseBtn">OK</button>
        </div>
    </div>

    <script>
        const form = document.getElementById('vehicleForm');
        const gpsBtn = document.getElementById('gpsBtn');
        const resetBtn = document.getElementById('resetBtn');
        const submitBtn = document.getElementById('submitBtn');
        const locationInput = document.getElementById('location');
        
        // Modal elements
        const statusModal = document.getElementById('statusModal');
        const modalIcon = document.getElementById('modalIcon');
        const modalTitle = document.getElementById('modalTitle');
        const modalDesc = document.getElementById('modalDesc');
        const modalCloseBtn = document.getElementById('modalCloseBtn');

        // Phone number pattern validator
        function validatePhone(phone) {
            const re = /^[6-9][0-9]{9}$/;
            return re.test(phone);
        }

        // Detect GPS Location
        gpsBtn.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }
            gpsBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude.toFixed(6);
                    const lng = position.coords.longitude.toFixed(6);
                    locationInput.value = `${lat}, ${lng}`;
                    gpsBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
                    document.getElementById('locationError').style.display = 'none';
                    setTimeout(() => {
                        gpsBtn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                    }, 2000);
                },
                (error) => {
                    gpsBtn.innerHTML = '<i class="fa-solid fa-crosshairs"></i>';
                    alert('Unable to retrieve GPS location. Please type your location manually.');
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        });

        // Reset fields
        resetBtn.addEventListener('click', () => {
            form.reset();
            // Clear all errors
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
        });

        // Form Submit
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            let isValid = true;

            // Car No Check
            const carNo = document.getElementById('carNo');
            if (carNo.value.trim() === '') {
                document.getElementById('carNoError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('carNoError').style.display = 'none';
            }

            // Car Type Check
            const carType = document.getElementById('carType');
            if (carType.value === '') {
                document.getElementById('carTypeError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('carTypeError').style.display = 'none';
            }

            // Owner Name Check
            const ownerName = document.getElementById('ownerName');
            if (ownerName.value.trim() === '') {
                document.getElementById('ownerNameError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('ownerNameError').style.display = 'none';
            }

            // Owner Mobile Check
            const ownerMobile = document.getElementById('ownerMobile');
            if (!validatePhone(ownerMobile.value)) {
                document.getElementById('ownerMobileError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('ownerMobileError').style.display = 'none';
            }

            // Driver Name Check
            const driverName = document.getElementById('driverName');
            if (driverName.value.trim() === '') {
                document.getElementById('driverNameError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('driverNameError').style.display = 'none';
            }

            // Driver Mobile Check
            const driverMobile = document.getElementById('driverMobile');
            if (!validatePhone(driverMobile.value)) {
                document.getElementById('driverMobileError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('driverMobileError').style.display = 'none';
            }

            // Location Check
            if (locationInput.value.trim() === '') {
                document.getElementById('locationError').style.display = 'block';
                isValid = false;
            } else {
                document.getElementById('locationError').style.display = 'none';
            }

            if (!isValid) return;

            // Submit Form via AJAX
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            const formData = new FormData(form);
            formData.append('action', 'add');

            fetch('api_vehicle_entry.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                
                if (data.success) {
                    showModal('success', 'Success!', 'Vehicle details submitted successfully');
                    form.reset();
                } else {
                    showModal('error', 'Submission Failed', data.message || 'Error occurred while saving data.');
                }
            })
            .catch(err => {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                showModal('error', 'Network Error', 'Could not connect to the server. Please check your internet connection.');
            });
        });

        function showModal(type, title, desc) {
            modalTitle.innerText = title;
            modalDesc.innerText = desc;
            
            if (type === 'success') {
                modalIcon.className = 'modal-icon success';
                modalIcon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
            } else {
                modalIcon.className = 'modal-icon error';
                modalIcon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
            }
            
            statusModal.classList.add('active');
        }

        modalCloseBtn.addEventListener('click', () => {
            statusModal.classList.remove('active');
        });
    </script>
</body>
</html>
