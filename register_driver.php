<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

include 'db_connect.php';

function sendJsonResponse($status, $message, $extra = []) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(array_merge(["status" => $status, "message" => $message], $extra));
    exit;
}

if (!$conn) {
    sendJsonResponse("error", "Database connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) ob_clean();
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
        if (mysqli_stmt_execute($driver_stmt)) {
            $driver_result = mysqli_stmt_get_result($driver_stmt);
            $driver = mysqli_fetch_assoc($driver_result);
            if ($driver) {
                array_walk_recursive($driver, function (&$item) {
                    if (is_string($item)) {
                        $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                    }
                });
                $response["driversdata"] = [$driver];
            }
        }
        mysqli_stmt_close($driver_stmt);
    }

    // Fetch vendor details
    $vendor_sql = "SELECT * FROM vendors WHERE phone_number = ?";
    $vendor_stmt = mysqli_prepare($conn, $vendor_sql);
    if ($vendor_stmt) {
        mysqli_stmt_bind_param($vendor_stmt, "s", $phone_number);
        if (mysqli_stmt_execute($vendor_stmt)) {
            $vendor_result = mysqli_stmt_get_result($vendor_stmt);
            $vendor = mysqli_fetch_assoc($vendor_result);
            if ($vendor) {
                array_walk_recursive($vendor, function (&$item) {
                    if (is_string($item)) {
                        $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                    }
                });
                $response["vendorsdata"] = [$vendor];
            }
        }
        mysqli_stmt_close($vendor_stmt);
    }

    sendJsonResponse("success", "Fetched details", ["driversdata" => $response["driversdata"], "vendorsdata" => $response["vendorsdata"]]);
}

// POST: Register / Update driver
$raw_input = file_get_contents("php://input");
$data = json_decode($raw_input, true);
if (!is_array($data) || empty($data)) {
    if (!empty($_POST)) {
        $data = $_POST;
    }
}

if (!is_array($data) || empty($data)) {
    sendJsonResponse("error", "Invalid or empty input");
}

// Extract and sanitize
$phone_number  = isset($data['phone_number']) ? trim(mysqli_real_escape_string($conn, $data['phone_number'])) : '';
$full_name     = isset($data['full_name']) ? trim(mysqli_real_escape_string($conn, $data['full_name'])) : NULL;
$email         = isset($data['email']) ? trim(mysqli_real_escape_string($conn, $data['email'])) : NULL;
$date_of_birth = isset($data['date_of_birth']) ? validateDate($data['date_of_birth']) : NULL;
$driver_address= isset($data['driver_address']) ? trim(mysqli_real_escape_string($conn, $data['driver_address'])) : NULL;
$pin_code      = isset($data['pin_code']) ? trim(mysqli_real_escape_string($conn, $data['pin_code'])) : NULL;
$license_no    = isset($data['license_no']) ? trim(mysqli_real_escape_string($conn, $data['license_no'])) : NULL;
$license_doe   = isset($data['license_doe']) ? validateDate($data['license_doe']) : NULL;
$license_type  = isset($data['license_type']) ? trim(mysqli_real_escape_string($conn, $data['license_type'])) : NULL;
$adhaar_card_no= isset($data['adhaar_card_no']) ? trim(mysqli_real_escape_string($conn, $data['adhaar_card_no'])) : NULL;
$pan_card_no   = isset($data['pan_card_no']) ? trim(mysqli_real_escape_string($conn, $data['pan_card_no'])) : NULL;
$photo         = isset($data['photo']) ? trim(mysqli_real_escape_string($conn, $data['photo'])) : 'NO';
$driver_city   = isset($data['driver_city']) ? trim(mysqli_real_escape_string($conn, $data['driver_city'])) : NULL;
$agency_name   = isset($data['agency_name']) ? trim(mysqli_real_escape_string($conn, $data['agency_name'])) : NULL;
$second_number = isset($data['second_number']) ? trim(mysqli_real_escape_string($conn, $data['second_number'])) : NULL;
$vendor_number = isset($data['vendor_number']) ? trim(mysqli_real_escape_string($conn, $data['vendor_number'])) : NULL;
$status        = isset($data['status']) ? trim(mysqli_real_escape_string($conn, $data['status'])) : 'filled';
$userType      = isset($data['userType']) ? trim(mysqli_real_escape_string($conn, $data['userType'])) : 'Driver';

if (empty($phone_number)) {
    sendJsonResponse("error", "Phone number is required");
}

// Override status logic
if ($userType === 'Vendor') {
    $status = 'active';
} elseif ($vendor_number) {
    $status = 'filled';
}

// 1. UPSERT Driver into `drivers` table
$sql = "INSERT INTO drivers 
    (phone_number, full_name, email, date_of_birth, driver_address, pin_code,
     license_no, license_doe, license_type, adhaar_card_no, pan_card_no,
     photo, driver_city, agency_name, second_number, status, userType)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    email = VALUES(email),
    date_of_birth = VALUES(date_of_birth),
    driver_address = VALUES(driver_address),
    pin_code = VALUES(pin_code),
    license_no = VALUES(license_no),
    license_doe = VALUES(license_doe),
    license_type = VALUES(license_type),
    adhaar_card_no = VALUES(adhaar_card_no),
    pan_card_no = VALUES(pan_card_no),
    photo = VALUES(photo),
    driver_city = VALUES(driver_city),
    agency_name = VALUES(agency_name),
    second_number = VALUES(second_number),
    status = VALUES(status),
    userType = VALUES(userType)";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    sendJsonResponse("error", "Error preparing query: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt, "sssssssssssssssss",
    $phone_number, $full_name, $email, $date_of_birth, $driver_address, $pin_code,
    $license_no, $license_doe, $license_type, $adhaar_card_no, $pan_card_no,
    $photo, $driver_city, $agency_name, $second_number, $status, $userType
);

if (!mysqli_stmt_execute($stmt)) {
    sendJsonResponse("error", "Failed to save driver details: " . mysqli_stmt_error($stmt));
}
mysqli_stmt_close($stmt);

// 2. Link driver to vendor if vendor_number is provided (using INSERT IGNORE to prevent duplicate key crashes)
if (!empty($vendor_number)) {
    $join_sql = "INSERT IGNORE INTO driver_vendor_join_Table (driver_id, vendor_id) VALUES (?, ?)";
    $join_stmt = mysqli_prepare($conn, $join_sql);
    if ($join_stmt) {
        mysqli_stmt_bind_param($join_stmt, "ss", $phone_number, $vendor_number);
        mysqli_stmt_execute($join_stmt);
        mysqli_stmt_close($join_stmt);
    }
}

sendJsonResponse("success", "Driver registered and updated successfully");
?>