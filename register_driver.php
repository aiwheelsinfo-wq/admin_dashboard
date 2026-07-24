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

// Support JSON, POST, and GET parameters
$rawInput = file_get_contents("php://input");
$data = json_decode($rawInput, true) ?? [];

$rawPhone = $data['phone_number'] ?? $_POST['phone_number'] ?? $_GET['phone_number'] ?? '';
$phone_number = mysqli_real_escape_string($conn, trim($rawPhone));

$rawName = $data['full_name'] ?? $_POST['full_name'] ?? $_GET['full_name'] ?? '';
$full_name = mysqli_real_escape_string($conn, trim($rawName));

$rawEmail = $data['email'] ?? $_POST['email'] ?? $_GET['email'] ?? NULL;
$email = $rawEmail ? mysqli_real_escape_string($conn, trim($rawEmail)) : NULL;

$rawDob = $data['date_of_birth'] ?? $_POST['date_of_birth'] ?? $_GET['date_of_birth'] ?? '';
$date_of_birth = validateDate($rawDob);

$rawAddr = $data['driver_address'] ?? $_POST['driver_address'] ?? $_GET['driver_address'] ?? NULL;
$driver_address = $rawAddr ? mysqli_real_escape_string($conn, $rawAddr) : NULL;

$rawPin = $data['pin_code'] ?? $_POST['pin_code'] ?? $_GET['pin_code'] ?? NULL;
$pin_code = $rawPin ? mysqli_real_escape_string($conn, $rawPin) : NULL;

$rawDl = $data['license_no'] ?? $_POST['license_no'] ?? $_GET['license_no'] ?? NULL;
$license_no = $rawDl ? mysqli_real_escape_string($conn, strtoupper(trim(preg_replace('/[\s\-]/', '', $rawDl)))) : NULL;

$rawDoe = $data['license_doe'] ?? $_POST['license_doe'] ?? $_GET['license_doe'] ?? '';
$license_doe = validateDate($rawDoe);

$rawType = $data['license_type'] ?? $_POST['license_type'] ?? $_GET['license_type'] ?? 'LMV';
$license_type = mysqli_real_escape_string($conn, $rawType);

$rawAadhaar = $data['adhaar_card_no'] ?? $_POST['adhaar_card_no'] ?? $_GET['adhaar_card_no'] ?? NULL;
$adhaar_card_no = $rawAadhaar ? mysqli_real_escape_string($conn, $rawAadhaar) : NULL;

$rawPan = $data['pan_card_no'] ?? $_POST['pan_card_no'] ?? $_GET['pan_card_no'] ?? NULL;
$pan_card_no = $rawPan ? mysqli_real_escape_string($conn, $rawPan) : NULL;

$photo = $data['photo'] ?? $_POST['photo'] ?? $_GET['photo'] ?? 'NO';
$driver_city = $data['driver_city'] ?? $_POST['driver_city'] ?? $_GET['driver_city'] ?? NULL;
$agency_name = $data['agency_name'] ?? $_POST['agency_name'] ?? $_GET['agency_name'] ?? NULL;
$second_number = $data['second_number'] ?? $_POST['second_number'] ?? $_GET['second_number'] ?? NULL;

$rawVendor = $data['vendor_number'] ?? $_POST['vendor_number'] ?? $_GET['vendor_number'] ?? '';
$vendor_number = mysqli_real_escape_string($conn, trim($rawVendor));

$rawStatus = $data['status'] ?? $_POST['status'] ?? $_GET['status'] ?? 'filled';
$status = mysqli_real_escape_string($conn, $rawStatus);

$rawUserType = $data['userType'] ?? $_POST['userType'] ?? $_GET['userType'] ?? 'Driver';
$userType = mysqli_real_escape_string($conn, $rawUserType);

$isCheckOnly = isset($data['check_only']) || isset($_GET['check_only']);

if (empty($phone_number)) {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Phone number is required"]);
    mysqli_close($conn);
    exit;
}

// 1. DUPLICATE PHONE NUMBER VALIDATION
$check_sql = "SELECT driver_id, full_name, phone_number FROM drivers WHERE phone_number = ? AND full_name IS NOT NULL AND full_name != ''";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "s", $phone_number);
mysqli_stmt_execute($check_stmt);
$check_result = mysqli_stmt_get_result($check_stmt);
$existingDriver = mysqli_fetch_assoc($check_result);
mysqli_stmt_close($check_stmt);

// If check_only parameter is passed, just return check result
if ($isCheckOnly) {
    ob_clean();
    if ($existingDriver) {
        echo json_encode([
            "status" => "duplicate",
            "exists" => true,
            "message" => "⚠️ Driver with phone number {$phone_number} already exists! (" . ($existingDriver['full_name'] ?: 'Registered') . ")"
        ]);
    } else {
        echo json_encode(["status" => "success", "exists" => false, "message" => "Phone number available"]);
    }
    mysqli_close($conn);
    exit;
}

// 2. DUPLICATE DRIVING LICENSE VALIDATION
if (!empty($license_no)) {
    $dl_check_sql = "SELECT full_name, phone_number FROM drivers WHERE UPPER(REPLACE(REPLACE(license_no, ' ', ''), '-', '')) = ? AND phone_number != ?";
    $dl_stmt = mysqli_prepare($conn, $dl_check_sql);
    if ($dl_stmt) {
        mysqli_stmt_bind_param($dl_stmt, "ss", $license_no, $phone_number);
        mysqli_stmt_execute($dl_stmt);
        $dl_result = mysqli_stmt_get_result($dl_stmt);
        if ($dlDriver = mysqli_fetch_assoc($dl_result)) {
            ob_clean();
            echo json_encode([
                "status" => "duplicate",
                "message" => "⚠️ Driving License {$license_no} is already registered to {$dlDriver['phone_number']} (" . ($dlDriver['full_name'] ?: 'Registered Driver') . ")"
            ]);
            mysqli_stmt_close($dl_stmt);
            mysqli_close($conn);
            exit;
        }
        mysqli_stmt_close($dl_stmt);
    }
}

if ($existingDriver) {
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
    $del_stmt = mysqli_prepare($conn, "DELETE FROM driver_vendor_join_Table WHERE driver_id = ?");
    if ($del_stmt) {
        mysqli_stmt_bind_param($del_stmt, "s", $phone_number);
        mysqli_stmt_execute($del_stmt);
        mysqli_stmt_close($del_stmt);
    }

    $ins_stmt = mysqli_prepare($conn, "INSERT INTO driver_vendor_join_Table (driver_id, vendor_id) VALUES (?, ?)");
    if ($ins_stmt) {
        mysqli_stmt_bind_param($ins_stmt, "ss", $phone_number, $vendor_number);
        mysqli_stmt_execute($ins_stmt);
        mysqli_stmt_close($ins_stmt);
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