<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/driver2.png">
    <title>Update Driver Details</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        header {
            background: linear-gradient(135deg, #2b6cb0, #1e40af);
            padding: 20px 30px;
            color: white;
        }

        header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .form-content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fafafa;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .section:hover {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        h2 {
            color: #2b6cb0;
            font-size: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: 500;
            margin-bottom: 6px;
            color: #4b5563;
        }

        input, select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2b6cb0;
            box-shadow: 0 0 0 3px rgba(43, 108, 176, 0.2);
        }

        .counter-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .counter {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
            background: #f3f4f6;
            padding: 2px 8px;
            border-radius: 4px;
        }

        select {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%236b7280" height="20" viewBox="0 0 20 20" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M5 7l5 5 5-5H5z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 10px center;
            padding-right: 36px;
        }

        button {
            background: #2b6cb0;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.3s ease;
            display: block;
            margin: 20px auto 0;
        }

        button:hover:not(:disabled) {
            background: #1e40af;
        }

        button:disabled {
            background: #a0aec0;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #333;
            display: block;
            margin-bottom: 10px;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-item:hover {
            background: #e2e8f0;
            border-color: #2b6cb0;
        }

        .checkbox-item input[type="checkbox"] {
            appearance: none;
            width: 18px;
            height: 18px;
            border: 2px solid #2b6cb0;
            border-radius: 4px;
            position: relative;
            cursor: pointer;
            outline: none;
            transition: all 0.3s ease;
        }

        .checkbox-item input[type="checkbox"]:checked {
            background-color: #2b6cb0;
            border-color: #2b6cb0;
        }

        .checkbox-item input[type="checkbox"]:checked::before {
            content: '✔';
            font-size: 12px;
            color: white;
            position: absolute;
            left: 4px;
            top: 2px;
        }

        .checkbox-item label {
            font-size: 15px;
            color: #333;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            .checkbox-group {
                flex-direction: column;
                gap: 12px;
            }
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px;
            }
            .form-content {
                padding: 20px;
            }
            .section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1 id="formTitle">Driver Registration Form</h1>
        </header>
        <div class="form-content">
            <form id="driverForm">
                <section class="section">
                    <h2>Personal Information</h2>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="text" id="phone_number" name="phone_number" readonly>
                    </div>
                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <input type="text" id="full_name" name="full_name" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Enter email" required>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_address">Address</label>
                        <input type="text" id="driver_address" name="driver_address" placeholder="Enter address" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_city">City</label>
                        <input type="text" id="driver_city" name="driver_city" placeholder="Enter City" required>
                    </div>
                    <div class="form-group">
                        <label for="pin_code">Pin Code</label>
                        <input type="text" id="pin_code" name="pin_code" placeholder="Enter pin code" required>
                    </div>
                </section>
                
                <section class="section">
                    <h2>License Details</h2>
                    <div class="form-group">
                        <label for="license_no">License Number</label>
                        <input type="text" id="license_no" name="license_no" placeholder="Enter license number" required>
                    </div>
                    <div class="form-group">
                        <label for="license_doe">License DOE</label>
                        <input type="date" id="license_doe" name="license_doe" required>
                    </div>
                    <div class="form-group">
                        <label for="license_type">License Type</label>
                        <input type="text" id="license_type" name="license_type" placeholder="Enter license type" required>
                    </div>
                </section>

                <section class="section">
                    <h2>Identification</h2>
                    <div class="form-group counter-wrapper">
                        <label for="adhaar_card_no">Aadhar Number</label>
                        <input type="text" id="adhaar_card_no" name="adhaar_card_no" maxlength="12" placeholder="Enter Aadhar number" required>
                        <span id="aadhaar_counter" class="counter">0/12</span>
                    </div>
                    <div class="form-group counter-wrapper">
                        <label for="pan_card_no">PAN Card Number</label>
                        <input type="text" id="pan_card_no" name="pan_card_no" maxlength="10" placeholder="Enter PAN number" required>
                        <span id="pan_counter" class="counter">0/10</span>
                    </div>
                    <div class="form-group">
                        <label for="photo">Photo (YES/NO)</label>
                        <input type="text" id="photo" name="photo" value="NO" required>
                    </div>
                </section>

                <section class="section">
                    <h2>User Type</h2>
                    <div class="checkbox-group">
                        <div class="checkbox-item">
                            <input type="checkbox" id="driverCheckbox" name="userType" value="Driver">
                            <label for="driverCheckbox">Please confirm by checking the box if you are a driver.</label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" id="vendorCheckbox" name="userType" value="Vendor">
                            <label for="vendorCheckbox">Please confirm by checking the box if you are a vendor.</label>
                        </div>
                    </div>
                </section>

                <button type="submit" id="submitBtn" disabled>Update Profile</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const phoneNumber = urlParams.get('phone_number');
        
        const aadhaarInput = document.getElementById('adhaar_card_no');
        const panInput = document.getElementById('pan_card_no');
        const aadhaarCounter = document.getElementById('aadhaar_counter');
        const panCounter = document.getElementById('pan_counter');
        const driverCheckbox = document.getElementById('driverCheckbox');
        const vendorCheckbox = document.getElementById('vendorCheckbox');
        const submitBtn = document.getElementById('submitBtn');

        // Function to toggle submit button state
        function toggleSubmitButton() {
            submitBtn.disabled = !driverCheckbox.checked && !vendorCheckbox.checked;
        }

        // Event listeners
        aadhaarInput.addEventListener('input', updateAadhaarCounter);
        panInput.addEventListener('input', updatePanCounter);
        driverCheckbox.addEventListener('change', () => {
            if (driverCheckbox.checked) vendorCheckbox.checked = false;
            toggleSubmitButton();
        });
        vendorCheckbox.addEventListener('change', () => {
            if (vendorCheckbox.checked) driverCheckbox.checked = false;
            toggleSubmitButton();
        });

        updateAadhaarCounter();
        updatePanCounter();

        if (phoneNumber) {
            document.getElementById('phone_number').value = phoneNumber;
            document.getElementById('formTitle').innerText = `Driver Registration Form - ${phoneNumber}`;
            fetchDriverDetails(phoneNumber);
        }

        const form = document.getElementById('driverForm');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const aadhaar = aadhaarInput.value;
            const pan = panInput.value;
            if (!/^\d{12}$/.test(aadhaar)) {
                alert('Aadhar number must be 12 digits');
                return;
            }
            if (!/^[A-Z0-9]{10}$/.test(pan)) {
                alert('PAN number must be 10 alphanumeric characters');
                return;
            }
            if (!driverCheckbox.checked && !vendorCheckbox.checked) {
                alert('Please select a user type (Driver or Vendor).');
                return;
            }
            if (confirm('Are you sure you want to update this driver\'s information?')) {
                await submitForm();
            }
        });
    });

    function updateAadhaarCounter() {
        const aadhaarInput = document.getElementById('adhaar_card_no');
        const aadhaarCounter = document.getElementById('aadhaar_counter');
        const length = aadhaarInput.value.length;
        aadhaarCounter.textContent = `${length}/12`;
    }

    function updatePanCounter() {
        const panInput = document.getElementById('pan_card_no');
        const panCounter = document.getElementById('pan_counter');
        const length = panInput.value.length;
        panCounter.textContent = `${length}/10`;
    }

    async function fetchDriverDetails(phoneNumber) {
        try {
            const response = await fetch(`https://agnicarrental.com/admin2025/register_driver.php?phone_number=${phoneNumber}`);
            console.log('HTTP Status:', response.status, 'OK:', response.ok);
            console.log('Headers:', [...response.headers.entries()]);
            const text = await response.text();
            console.log('Raw response:', text);
            if (!text) {
                throw new Error('Empty response from server');
            }
            const data = JSON.parse(text);
            if (data.status === 'success' && data.driversdata && data.driversdata[0]) {
                const driver = data.driversdata[0];
                document.getElementById('full_name').value = driver.full_name ?? '';
                document.getElementById('email').value = driver.email ?? '';
                document.getElementById('date_of_birth').value = driver.date_of_birth ?? '';
                document.getElementById('driver_address').value = driver.driver_address ?? '';
                document.getElementById('driver_city').value = driver.driver_city ?? '';
                document.getElementById('pin_code').value = driver.pin_code ?? '';
                document.getElementById('license_no').value = driver.license_no ?? '';
                document.getElementById('license_doe').value = driver.license_doe ?? '';
                document.getElementById('license_type').value = driver.license_type ?? '';
                document.getElementById('adhaar_card_no').value = driver.adhaar_card_no ?? '';
                document.getElementById('pan_card_no').value = driver.pan_card_no ?? '';
                document.getElementById('photo').value = driver.photo ?? 'NO';
                const userType = (driver.userType || '').toLowerCase();
                document.getElementById('driverCheckbox').checked = userType === 'driver';
                document.getElementById('vendorCheckbox').checked = userType === 'vendor';

                updateAadhaarCounter();
                updatePanCounter();
                // Enable button if a checkbox is checked
                document.getElementById('submitBtn').disabled = !driverCheckbox.checked && !vendorCheckbox.checked;
            } else {
                alert(data.message || 'No driver data found');
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Failed to fetch driver details: ' + error.message);
        }
    }

    async function submitForm() {
        const form = document.getElementById('driverForm');
        const driverCheckbox = document.getElementById('driverCheckbox');
        const vendorCheckbox = document.getElementById('vendorCheckbox');
        const formData = new FormData(form);
        formData.set('userType', driverCheckbox.checked ? 'Driver' : vendorCheckbox.checked ? 'Vendor' : '');
        const data = Object.fromEntries(formData.entries());
        data.phone_number = document.getElementById('phone_number').value;

        try {
            const response = await fetch('https://agnicarrental.com/admin2025/register_driver.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            console.log('POST HTTP Status:', response.status, 'OK:', response.ok);
            const text = await response.text();
            console.log('POST Raw response:', text);
            if (!text) {
                throw new Error('Empty response from server');
            }
            const result = JSON.parse(text);
            if (result.status === 'success') {
                alert(result.message || 'Driver updated successfully');
                window.location.reload();
            } else if (result.status === 'warning') {
                alert(result.message || 'No changes were detected');
            } else {
                alert(result.message || 'Failed to update driver');
            }
        } catch (error) {
            console.error('Submit error:', error);
            alert('Error: ' + error.message);
        }
    }
    </script>
</body>
</html>