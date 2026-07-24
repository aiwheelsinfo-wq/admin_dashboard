<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
error_reporting(E_ALL);

include 'db_connect.php';
if (!$conn) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . mysqli_connect_error()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ob_clean();
    exit(0);
}

function validateDate($date) {
    if (empty($date)) return NULL;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return $date;
    }
    return NULL;
}

// GET: Fetch driver and vendor details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['phone_number'])) {
    $phone_number = mysqli_real_escape_string($conn, $_GET['phone_number']);
    $response = ["status" => "success", "driversdata" => [], "vendorsdata" => []];

    // Fetch driver details
    $driver_sql = "SELECT * FROM drivers WHERE phone_number = ?";
    $driver_stmt = mysqli_prepare($conn, $driver_sql);
    if ($driver_stmt) {
        mysqli_stmt_bind_param($driver_stmt, "s", $phone_number);
        mysqli_stmt_execute($driver_stmt);
        $driver_result = mysqli_stmt_get_result($driver_stmt);
        if ($driver = mysqli_fetch_assoc($driver_result)) {
            $response["driversdata"] = [$driver];
        }
        mysqli_stmt_close($driver_stmt);
    }

    // Fetch vendor details
    $vendor_sql = "SELECT * FROM vendors WHERE phone_number = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_sql);
    if ($vendor_stmt) {
        mysqli_stmt_bind_param($vendor_stmt, "s", $phone_number);
        mysqli_stmt_execute($vendor_stmt);
        $vendor_result = mysqli_stmt_get_result($vendor_stmt);
        if ($vendor = mysqli_fetch_assoc($vendor_result)) {
            $response["vendorsdata"] = [$vendor];
        }
        mysqli_stmt_close($vendor_stmt);
    }

    ob_clean();
    echo json_encode($response);
    mysqli_close($conn);
    exit;
}

// POST: Add or Update driver
$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    $data = $_POST; // Fallback to form-data
}

$phone_number = isset($data['phone_number']) ? mysqli_real_escape_string($conn, trim($data['phone_number'])) : '';
$full_name = isset($data['full_name']) ? mysqli_real_escape_string($conn, trim($data['full_name'])) : '';
$email = isset($data['email']) ? mysqli_real_escape_string($conn, trim($data['email'])) : NULL;
$date_of_birth = isset($data['date_of_birth']) ? validateDate($data['date_of_birth']) : NULL;
$driver_address = isset($data['driver_address']) ? mysqli_real_escape_string($conn, $data['driver_address']) : NULL;
$pin_code = isset($data['pin_code']) ? mysqli_real_escape_string($conn, $data['pin_code']) : NULL;
$license_no = isset($data['license_no']) ? mysqli_real_escape_string($conn, strtoupper(trim($data['license_no']))) : NULL;
$license_doe = isset($data['license_doe']) ? validateDate($data['license_doe']) : NULL;
$license_type = isset($data['license_type']) ? mysqli_real_escape_string($conn, $data['license_type']) : 'LMV';
$adhaar_card_no = isset($data['adhaar_card_no']) ? mysqli_real_escape_string($conn, $data['adhaar_card_no']) : NULL;
$pan_card_no = isset($data['pan_card_no']) ? mysqli_real_escape_string($conn, $data['pan_card_no']) : NULL;
$photo = isset($data['photo']) ? mysqli_real_escape_string($conn, $data['photo']) : 'NO';
$driver_city = isset($data['driver_city']) ? mysqli_real_escape_string($conn, $data['driver_city']) : NULL;
$agency_name = isset($data['agency_name']) ? mysqli_real_escape_string($conn, $data['agency_name']) : NULL;
$second_number = isset($data['second_number']) ? mysqli_real_escape_string($conn, $data['second_number']) : NULL;
$vendor_number = isset($data['vendor_number']) ? mysqli_real_escape_string($conn, trim($data['vendor_number'])) : NULL;
$status = isset($data['status']) ? mysqli_real_escape_string($conn, $data['status']) : 'filled';
$userType = isset($data['userType']) ? mysqli_real_escape_string($conn, $data['userType']) : 'Driver';

if (empty($phone_number)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Phone number is required"]);
    mysqli_close($conn);
    exit;
}

// Check if driver exists
$check_sql = "SELECT 1 FROM drivers WHERE phone_number = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "s", $phone_number);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$driverExists = (mysqli_num_rows($check_result) > 0);
mysqli_stmt_close($check_stmt);

if ($driverExists) {
    // UPDATE EXISTING DRIVER
    $sql = "UPDATE drivers SET 
        full_name = ?, email = ?, date_of_birth = ?, driver_address = ?, pin_code = ?, 
        license_no = ?, license_doe = ?, license_type = ?, adhaar_card_no = ?, pan_card_no = ?, 
        photo = ?, driver_city = ?, agency_name = ?, second_number = ?, status = ?, userType = ?
        WHERE phone_number = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt, "sssssssssssssssss",
        $full_name, $email, $date_of_birth, $driver_address, $pin_code,
        $license_no, $license_doe, $license_type, $adhaar_card_no, $pan_card_no,
        $photo, $driver_city, $agency_name, $second_number, $status, $userType, $phone_number
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} else {
    // INSERT NEW DRIVER
    $sql = "INSERT INTO drivers (
        phone_number, full_name, email, date_of_birth, driver_address, pin_code, 
        license_no, license_doe, license_type, adhaar_card_no, pan_card_no, 
        photo, driver_city, agency_name, second_number, status, userType
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt, "sssssssssssssssss",
        $phone_number, $full_name, $email, $date_of_birth, $driver_address, $pin_code,
        $license_no, $license_doe, $license_type, $adhaar_card_no, $pan_card_no,
        $photo, $driver_city, $agency_name, $second_number, $status, $userType
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// Link to Vendor in driver_vendor_join_Table if vendor_number is provided
if (!empty($vendor_number)) {
    // Ensure table exists
    mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `driver_vendor_join_Table` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `driver_id` VARCHAR(50) NOT NULL,
      `vendor_id` VARCHAR(50) NOT NULL,
      UNIQUE KEY `unique_join` (`driver_id`, `vendor_id`)
    )");

    $insert_sql = "INSERT INTO driver_vendor_join_Table (driver_id, vendor_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE vendor_id=VALUES(vendor_id)";
    if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
        mysqli_stmt_bind_param($insert_stmt, "ss", $phone_number, $vendor_number);
        mysqli_stmt_execute($insert_stmt);
        mysqli_stmt_close($insert_stmt);
    }
}

ob_clean();
echo json_encode([
    "status" => "success",
    "message" => "Driver registered/updated successfully",
    "phone_number" => $phone_number
]);
mysqli_close($conn);
ob_end_flush();
?>