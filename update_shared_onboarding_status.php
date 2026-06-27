<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(["status" => false, "message" => "Unauthorized access."]);
    exit();
}

include 'db_connect.php';

$id = intval($_POST['id'] ?? 0);
$status = mysqli_real_escape_string($conn, $_POST['status'] ?? ''); // 'Approved' or 'Rejected'

if ($id <= 0 || !in_array($status, ['Approved', 'Rejected'])) {
    echo json_encode(["status" => false, "message" => "Invalid parameters."]);
    exit();
}

// 1. Fetch onboarding record details
$sqlFetch = "SELECT * FROM shared_onboardings WHERE id = $id";
$resFetch = mysqli_query($conn, $sqlFetch);
$record = mysqli_fetch_assoc($resFetch);

if (!$record) {
    echo json_encode(["status" => false, "message" => "Onboarding record not found."]);
    exit();
}

if ($record['status'] !== 'Pending') {
    echo json_encode(["status" => false, "message" => "This onboarding request has already been processed."]);
    exit();
}

// Start Transaction
mysqli_begin_transaction($conn);

try {
    // Update the shared onboarding request status
    $sqlUpdate = "UPDATE shared_onboardings SET status = '$status' WHERE id = $id";
    if (!mysqli_query($conn, $sqlUpdate)) {
        throw new Exception("Failed to update onboarding status: " . mysqli_error($conn));
    }

    if ($status === 'Approved') {
        $car_no = $record['car_no'];
        $car_type = $record['car_type'];
        $owner_name = $record['owner_name'];
        $owner_mobile = $record['owner_mobile'];
        $driver_name = $record['driver_name'];
        $driver_mobile = $record['driver_mobile'];
        $location = $record['location'];

        // Normalize vehicle number for insertion
        $normalized_car_no = preg_replace('/[^A-Za-z0-9]/', '', $car_no);
        $normalized_car_no = strtoupper($normalized_car_no);

        // --- Step A: Register / Update Car ---
        $checkCar = "SELECT id FROM cars WHERE vehicle_number = '$normalized_car_no'";
        $resCheckCar = mysqli_query($conn, $checkCar);
        if (mysqli_num_rows($resCheckCar) > 0) {
            // Update existing car
            $sqlCar = "UPDATE cars SET owner_id = '$owner_mobile', vehicle_type = '$car_type', vehicle_name = '$car_type', status = 'active' WHERE vehicle_number = '$normalized_car_no'";
        } else {
            // Insert new car
            $sqlCar = "INSERT INTO cars (vehicle_number, vehicle_type, vehicle_name, fuel_type, plate_color, owner_id, status) 
                       VALUES ('$normalized_car_no', '$car_type', '$car_type', 'Petrol', 'Yellow', '$owner_mobile', 'active')";
        }
        if (!mysqli_query($conn, $sqlCar)) {
            throw new Exception("Failed to save vehicle details: " . mysqli_error($conn));
        }

        // --- Step B: Register / Update Driver ---
        $checkDriver = "SELECT driver_id FROM drivers WHERE phone_number = '$driver_mobile'";
        $resCheckDriver = mysqli_query($conn, $checkDriver);
        if (mysqli_num_rows($resCheckDriver) > 0) {
            // Update existing driver
            $sqlDriver = "UPDATE drivers SET full_name = '$driver_name', status = 'active' WHERE phone_number = '$driver_mobile'";
        } else {
            // Insert new driver
            $sqlDriver = "INSERT INTO drivers (phone_number, full_name, status, userType, driver_type, agency_name, second_number, driver_city) 
                          VALUES ('$driver_mobile', '$driver_name', 'active', 'driver', 'Self', 'Agni Fleet', '', '$location')";
        }
        if (!mysqli_query($conn, $sqlDriver)) {
            throw new Exception("Failed to save driver details: " . mysqli_error($conn));
        }

        // --- Step C: Link Driver to Owner/Vendor ---
        if ($owner_mobile !== $driver_mobile) {
            $checkLink = "SELECT id FROM driver_vendor_join_Table WHERE driver_id = '$driver_mobile' AND vendor_id = '$owner_mobile'";
            $resCheckLink = mysqli_query($conn, $checkLink);
            if (mysqli_num_rows($resCheckLink) == 0) {
                $sqlLink = "INSERT INTO driver_vendor_join_Table (driver_id, vendor_id) VALUES ('$driver_mobile', '$owner_mobile')";
                if (!mysqli_query($conn, $sqlLink)) {
                    throw new Exception("Failed to link driver to owner: " . mysqli_error($conn));
                }
            }
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    echo json_encode(["status" => true, "message" => "Request successfully $status."]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}

mysqli_close($conn);
?>
