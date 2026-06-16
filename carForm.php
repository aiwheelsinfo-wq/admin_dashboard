<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="images/favicon.png">
    <title>Update Car Details</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            font-size: 24px;
            font-weight: 600;
        }

        .close-btn {
            color: white;
            font-size: 28px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            line-height: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .close-btn:hover {
            color: #f3f4f6;
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.1);
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

        button:hover {
            background: #1e40af;
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
            <h1 id="formTitle">Car Registration Form</h1>
            <a href="dashboard.php" class="close-btn" id="closeBtn" title="Back to Dashboard">&times;</a>
        </header>
        <div class="form-content">
            <form id="carForm">
                <section class="section">
                    <h2>Vehicle Information</h2>
                    <div class="form-group">
                        <label for="vehicle_number">Vehicle Number</label>
                        <input type="text" id="vehicle_number" name="vehicle_number" readonly>
                    </div>
                    <div class="form-group">
                        <label for="owner_id">Owner ID (Phone Number)</label>
                        <input type="text" id="owner_id" name="owner_id" readonly>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" required>
                            <option value="">Select Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="Notified">Notified</option>
                            
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle Type</label>
                        <input type="text" id="vehicle_type" name="vehicle_type" placeholder="Enter vehicle type" required>
                    </div>
                    <div class="form-group">
                        <label for="vehicle_name">Vehicle Name</label>
                        <input type="text" id="vehicle_name" name="vehicle_name" placeholder="Enter vehicle name" required>
                    </div>
                    <div class="form-group">
                        <label for="fuel_type">Fuel Type</label>
                        <select id="fuel_type" name="fuel_type" required>
                            <option value="">Select Fuel Type</option>
                            <option value="petrol">Petrol</option>
                            <option value="Diesel">Diesel</option>
                            <option value="CNG">CNG</option>
                            <option value="Petrol & CNG">Petrol & CNG</option>
                            <option value="EV">EV</option>
                        </select>

                    </div>
                </section>

                <section class="section">
                    <h2>Registration Details</h2>
                    <div class="form-group">
                        <label for="rc_no">RC Number</label>
                        <input type="text" id="rc_no" name="rc_no" placeholder="Enter RC number" required>
                    </div>
                    <div class="form-group">
                        <label for="rc_name">RC Name</label>
                        <input type="text" id="rc_name" name="rc_name" placeholder="Enter RC name" required>
                    </div>
                    <div class="form-group">
                        <label for="rc_manufecture_date">RC Manufacture Date</label>
                        <input type="date" id="rc_manufecture_date" name="rc_manufecture_date" required>
                    </div>
                </section>

                <section class="section">
                    <h2>Insurance & Permits</h2>
                    <div class="form-group">
                        <label for="insurance_number">Insurance Number</label>
                        <input type="text" id="insurance_number" name="insurance_number" placeholder="Enter insurance number" required>
                    </div>
                    <div class="form-group">
                        <label for="insurance_doe">Insurance DOE</label>
                        <input type="date" id="insurance_doe" name="insurance_doe" required>
                    </div>
                    <div class="form-group">
                        <label for="puc_doi">PUC DOI</label>
                        <input type="date" id="puc_doi" name="puc_doi">
                    </div>
                    <div class="form-group">
                        <label for="puc_doe">PUC DOE</label>
                        <input type="date" id="puc_doe" name="puc_doe">
                    </div>
                    <div class="form-group">
                        <label for="texi_permit_no">Taxi Permit Number</label>
                        <input type="text" id="texi_permit_no" name="texi_permit_no" placeholder="Enter permit number" required>
                    </div>
                    <div class="form-group">
                        <label for="texi_permit_doi">Taxi Permit DOI</label>
                        <input type="date" id="texi_permit_doi" name="texi_permit_doi" required>
                    </div>
                    <div class="form-group">
                        <label for="texi_permit_doe">Taxi Permit DOE</label>
                        <input type="date" id="texi_permit_doe" name="texi_permit_doe" required>
                    </div>
                </section>

                <section class="section">
                    <h2>Fitness Certificate</h2>
                    <div class="form-group">
                        <label for="fitness_certificate_no">Certificate Number</label>
                        <input type="text" id="fitness_certificate_no" name="fitness_certificate_no" placeholder="Enter certificate number">
                    </div>
                    <div class="form-group">
                        <label for="fitness_certificate_doi">DOI</label>
                        <input type="date" id="fitness_certificate_doi" name="fitness_certificate_doi">
                    </div>
                    <div class="form-group">
                        <label for="fitness_certificate_doe">DOE</label>
                        <input type="date" id="fitness_certificate_doe" name="fitness_certificate_doe">
                    </div>
                </section>

                <button type="submit" id="submitBtn">Update Car Details</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const closeBtn = document.getElementById('closeBtn');
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                window.close();
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 100);
            });
        }

        const urlParams = new URLSearchParams(window.location.search);
        const vehicleNumber = urlParams.get('vehicle_number');

        if (vehicleNumber) {
            document.getElementById('vehicle_number').value = vehicleNumber;
            document.getElementById('formTitle').innerText = `Car Registration Form - ${vehicleNumber}`;
            fetchCarDetails(vehicleNumber);
        }

        const form = document.getElementById('carForm');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (confirm('Are you sure you want to update this car\'s information?')) {
                await submitForm();
            }
        });
    });

    async function fetchCarDetails(vehicleNumber) {
        try {
            const response = await fetch(`https://agnicarrental.com/admin2025/register_car.php?vehicle_number=${vehicleNumber}`);
            const data = await response.json();
            if (data && data.status === 'success' && data.cardata) {
                const car = data.cardata[0];
                document.getElementById('vehicle_number').value = car.vehicle_number ?? '';
                document.getElementById('owner_id').value = car.owner_id ?? '';
                document.getElementById('status').value = car.status ?? '';
                document.getElementById('vehicle_type').value = car.vehicle_type ?? '';
                document.getElementById('vehicle_name').value = car.vehicle_name ?? '';
                document.getElementById('fuel_type').value = car.fuel_type ?? '';
                document.getElementById('rc_no').value = car.rc_no ?? '';
                document.getElementById('rc_name').value = car.rc_name ?? '';
                document.getElementById('rc_manufecture_date').value = car.rc_manufecture_date ?? '';
                document.getElementById('insurance_number').value = car.insurance_number ?? '';
                document.getElementById('insurance_doe').value = car.insurance_doe ?? '';
                document.getElementById('puc_doi').value = car.puc_doi ?? '';
                document.getElementById('puc_doe').value = car.puc_doe ?? '';
                document.getElementById('texi_permit_no').value = car.texi_permit_no ?? '';
                document.getElementById('texi_permit_doi').value = car.texi_permit_doi ?? '';
                document.getElementById('texi_permit_doe').value = car.texi_permit_doe ?? '';
                document.getElementById('fitness_certificate_no').value = car.fitness_certificate_no ?? '';
                document.getElementById('fitness_certificate_doi').value = car.fitness_certificate_doi ?? '';
                document.getElementById('fitness_certificate_doe').value = car.fitness_certificate_doe ?? '';
            } else {
                alert(data.message || 'No car found');
            }
        } catch (error) {
            console.error('Fetch error:', error);
            alert('Failed to fetch car details: ' + error.message);
        }
    }

    async function submitForm() {
        const form = document.getElementById('carForm');
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('https://agnicarrental.com/admin2025/register_car.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            const result = await response.json();
            if (result.status === 'success') {
                alert(result.message || 'Car updated successfully');
                window.location.reload();
            } else if (result.status === 'warning') {
                alert(result.message || 'No changes were detected');
            } else {
                alert(result.message || 'Failed to update car');
            }
        } catch (error) {
            console.error('Submit error:', error);
            alert('Error: ' + error.message);
        }
    }
    </script>
</body>
</html>