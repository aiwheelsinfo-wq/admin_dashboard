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

// Log request details
file_put_contents('debug.log', "Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI'] . " at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

include 'db_connect.php';
if (!$conn) {
    file_put_contents('debug.log', "Database connection failed: " . mysqli_connect_error() . "\n", FILE_APPEND);
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
    file_put_contents('debug.log', "GET request for phone_number: " . $_GET['phone_number'] . "\n", FILE_APPEND);
    
    $phone_number = mysqli_real_escape_string($conn, $_GET['phone_number']);
    
    // Initialize response
    $response = ["status" => "success", "driversdata" => [], "vendorsdata" => []];

    // Fetch driver details
    $driver_sql = "SELECT * FROM drivers WHERE phone_number = ?";
    $driver_stmt = mysqli_prepare($conn, $driver_sql);
    if (!$driver_stmt) {
        file_put_contents('debug.log', "Error preparing driver query: " . mysqli_error($conn) . "\n", FILE_APPEND);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Error preparing driver query: " . mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_bind_param($driver_stmt, "s", $phone_number);
    if (!mysqli_stmt_execute($driver_stmt)) {
        file_put_contents('debug.log', "Error executing driver query: " . mysqli_stmt_error($driver_stmt) . "\n", FILE_APPEND);
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Error executing driver query: " . mysqli_stmt_error($driver_stmt)]);
        mysqli_stmt_close($driver_stmt);
        mysqli_close($conn);
        exit;
    }
    $driver_result = mysqli_stmt_get_result($driver_stmt);
    $driver = mysqli_fetch_assoc($driver_result);
    mysqli_stmt_close($driver_stmt);

    if ($driver) {
        array_walk_recursive($driver, function (&$item) {
            if (is_string($item)) {
                $item = mb_convert_encoding($item, 'UTF-8', 'auto');
            }
        });
        $response["driversdata"] = [$driver];
    }

    // Fetch vendor details (check if vendors table exists)
    $vendor_sql = "SELECT 1 FROM information_schema.tables WHERE table_name = 'vendors'";
    $table_check = mysqli_query($conn, $vendor_sql);
    if (mysqli_num_rows($table_check) > 0) {
        $vendor_sql = "SELECT * FROM vendors WHERE phone_number = ?";
        $vendor_stmt = mysqli_prepare($conn, $vendor_sql);
        if (!$vendor_stmt) {
            file_put_contents('debug.log', "Error preparing vendor query: " . mysqli_error($conn) . "\n", FILE_APPEND);
            ob_clean();
            echo json_encode(["status" => "error", "message" => "Error preparing vendor query: " . mysqli_error($conn)]);
            mysqli_close($conn);
            exit;
        }
        mysqli_stmt_bind_param($vendor_stmt, "s", $phone_number);
        if (!mysqli_stmt_execute($vendor_stmt)) {
            file_put_contents('debug.log', "Error executing vendor query: " . mysqli_stmt_error($vendor_stmt) . "\n", FILE_APPEND);
            ob_clean();
            echo json_encode(["status" => "error", "message" => "Error executing vendor query: " . mysqli_stmt_error($vendor_stmt)]);
            mysqli_stmt_close($vendor_stmt);
            mysqli_close($conn);
            exit;
        }
        $vendor_result = mysqli_stmt_get_result($vendor_stmt);
        $vendor = mysqli_fetch_assoc($vendor_result);
        mysqli_stmt_close($vendor_stmt);

        if ($vendor) {
            array_walk_recursive($vendor, function (&$item) {
                if (is_string($item)) {
                    $item = mb_convert_encoding($item, 'UTF-8', 'auto');
                }
            });
            $response["vendorsdata"] = [$vendor];
        }
    } else {
        file_put_contents('debug.log', "Vendors table does not exist\n", FILE_APPEND);
    }

    if (empty($response["driversdata"]) && empty($response["vendorsdata"])) {
        $response = ["status" => "error", "message" => "No driver or vendor found for phone number: $phone_number"];
    }

    file_put_contents('debug.log', "GET response: " . json_encode($response) . "\n", FILE_APPEND);
    ob_clean();
    echo json_encode($response);
    mysqli_close($conn);
    exit;
}

// POST: Accept JSON body OR form-encoded POST data
$rawInput = file_get_contents("php://input");
$data = null;
if (!empty($rawInput)) {
    $data = json_decode($rawInput, true);
}
// Fallback: form-encoded POST
if (!$data && !empty($_POST)) {
    $data = $_POST;
}
if (!$data) {
    file_put_contents('debug.log', "Invalid JSON input\n", FILE_APPEND);
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Invalid JSON input"]);
    mysqli_close($conn);
    exit;
}

file_put_contents('debug.log', "Received POST data: " . print_r($data, true) . "\n", FILE_APPEND);

// Extract and sanitize
$phone_number = isset($data['phone_number']) ? mysqli_real_escape_string($conn, $data['phone_number']) : '';
$full_name = isset($data['full_name']) ? mysqli_real_escape_string($conn, $data['full_name']) : NULL;
$email = isset($data['email']) ? mysqli_real_escape_string($conn, $data['email']) : NULL;
$date_of_birth = isset($data['date_of_birth']) ? validateDate($data['date_of_birth']) : NULL;
$driver_address = isset($data['driver_address']) ? mysqli_real_escape_string($conn, $data['driver_address']) : NULL;
$pin_code = isset($data['pin_code']) ? mysqli_real_escape_string($conn, $data['pin_code']) : NULL;
$license_no = isset($data['license_no']) ? mysqli_real_escape_string($conn, $data['license_no']) : NULL;
$license_doe = isset($data['license_doe']) ? validateDate($data['license_doe']) : NULL;
$license_type = isset($data['license_type']) ? mysqli_real_escape_string($conn, $data['license_type']) : NULL;
$adhaar_card_no = isset($data['adhaar_card_no']) ? mysqli_real_escape_string($conn, $data['adhaar_card_no']) : NULL;
$pan_card_no = isset($data['pan_card_no']) ? mysqli_real_escape_string($conn, $data['pan_card_no']) : NULL;
$photo = isset($data['photo']) ? mysqli_real_escape_string($conn, $data['photo']) : 'NO';
$driver_city = isset($data['driver_city']) ? mysqli_real_escape_string($conn, $data['driver_city']) : NULL;
$agency_name = isset($data['agency_name']) ? mysqli_real_escape_string($conn, $data['agency_name']) : NULL;
$second_number = isset($data['second_number']) ? mysqli_real_escape_string($conn, $data['second_number']) : NULL;
$vendor_number = isset($data['vendor_number']) ? mysqli_real_escape_string($conn, $data['vendor_number']) : NULL;
$status = isset($data['status']) ? mysqli_real_escape_string($conn, $data['status']) : NULL;
$userType = isset($data['userType']) ? mysqli_real_escape_string($conn, $data['userType']) : '';

// Override status for Vendor
if ($userType === 'Vendor') {
    $status = 'active';
}
elseif ($userType === 'Driver') {
    $status = 'filled'; // For drivers with vendor_number
}
elseif ($vendor_number) {
    $status = 'filled'; // For drivers with vendor_number
}

if (empty($phone_number)) {
    file_put_contents('debug.log', "Phone number is required\n", FILE_APPEND);
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Phone number is required"]);
    mysqli_close($conn);
    exit;
}

if (!in_array($userType, ['Driver', 'Vendor', ''])) {
    file_put_contents('debug.log', "Invalid userType: $userType\n", FILE_APPEND);
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Invalid userType"]);
    mysqli_close($conn);
    exit;
}

// INSERT new driver or UPDATE existing one
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

if ($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param(
        $stmt, "sssssssssssssssss",
        $phone_number, $full_name, $email, $date_of_birth, $driver_address, $pin_code,
        $license_no, $license_doe, $license_type, $adhaar_card_no, $pan_card_no,
        $photo, $driver_city, $agency_name, $second_number, $status, $userType
    );
    if (!mysqli_stmt_execute($stmt)) {
        ob_clean();
        echo json_encode(["status" => "error", "message" => "Error saving driver: " . mysqli_stmt_error($stmt)]);
        mysqli_stmt_close($stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($stmt);
} else {
    ob_clean();
    echo json_encode(["status" => "error", "message" => "Error preparing query: " . mysqli_error($conn)]);
    mysqli_close($conn);
    exit;
}

// Link driver to vendor (INSERT IGNORE avoids duplicate crash)
if ($status === 'filled' && !empty($vendor_number)) {
    $insert_sql = "INSERT IGNORE INTO driver_vendor_join_Table (driver_id, vendor_id) VALUES (?, ?)";
    if ($insert_stmt = mysqli_prepare($conn, $insert_sql)) {
        mysqli_stmt_bind_param($insert_stmt, "ss", $phone_number, $vendor_number);
        mysqli_stmt_execute($insert_stmt); // Ignore errors — IGNORE handles duplicates
        mysqli_stmt_close($insert_stmt);
    }
}

// Update status to 'filled' if 'join'
if ($status === 'join') {
    $updateSql = "UPDATE drivers SET status = 'filled' WHERE phone_number = ?";
    if ($updateStmt = mysqli_prepare($conn, $updateSql)) {
        mysqli_stmt_bind_param($updateStmt, "s", $phone_number);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
    }
}

mysqli_close($conn);
ob_clean();
echo json_encode(["status" => "success", "message" => "Driver saved successfully"]);
exit;