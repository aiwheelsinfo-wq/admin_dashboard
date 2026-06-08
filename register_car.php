<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Enable error reporting for debugging (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');

include 'db_connect.php';

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Normalize vehicle number (remove non-alphanumeric, convert to uppercase)
function normalizeVehicleNumber($vehicleNumber) {
    $vehicleNumber = preg_replace('/[^A-Za-z0-9]/', '', $vehicleNumber);
    return strtoupper($vehicleNumber);
}

// Validate date format (YYYY-MM-DD)
function validateDate($date) {
    if (empty($date)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date)) {
        return $date;
    }
    file_put_contents('debug.log', "Invalid Date: $date\n", FILE_APPEND);
    return null;
}

// Handle GET request to fetch car details
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vehicle_number'])) {
    $vehicle_number = mysqli_real_escape_string($conn, $_GET['vehicle_number']);
    $normalized_vehicle_number = normalizeVehicleNumber($vehicle_number);
    file_put_contents('debug.log', "GET Request: vehicle_number = $normalized_vehicle_number\n", FILE_APPEND);

    $sql = "SELECT * FROM cars WHERE vehicle_number = ?";
    file_put_contents('debug.log', "SQL: $sql\n", FILE_APPEND);

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = "Prepare failed: " . mysqli_error($conn);
        file_put_contents('debug.log', "Prepare Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "s", $normalized_vehicle_number);
    if (!mysqli_stmt_execute($stmt)) {
        $error = "Execute failed: " . mysqli_stmt_error($stmt);
        file_put_contents('debug.log', "Execute Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        $error = "Result failed: " . mysqli_stmt_error($stmt);
        file_put_contents('debug.log', "Result Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }

    $car = mysqli_fetch_assoc($result);
    file_put_contents('debug.log', "Fetched Data: " . print_r($car, true) . "\n", FILE_APPEND);

    if ($car) {
        echo json_encode([
            "status" => "success",
            "cardata" => [$car]
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "No car found with vehicle number: $normalized_vehicle_number"
        ]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

// Handle POST request to update car data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents("php://input");
    file_put_contents('debug.log', "POST Raw Input: $raw_input\n", FILE_APPEND);

    $data = json_decode($raw_input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error = "Invalid JSON data: " . json_last_error_msg();
        file_put_contents('debug.log', "JSON Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }
    file_put_contents('debug.log', "POST Parsed Data: " . print_r($data, true) . "\n", FILE_APPEND);

    // Validate required fields
    if (empty($data['vehicle_number']) || empty($data['owner_id']) || empty($data['status'])) {
        $error = "Missing required fields: vehicle_number, owner_id, or status";
        file_put_contents('debug.log', "Validation Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        exit;
    }

    // Extract and sanitize data
    $vehicle_number = mysqli_real_escape_string($conn, $data['vehicle_number']);
    $normalized_vehicle_number = normalizeVehicleNumber($vehicle_number);
    $owner_id = mysqli_real_escape_string($conn, $data['owner_id']);
    $vehicle_type = isset($data['vehicle_type']) ? mysqli_real_escape_string($conn, $data['vehicle_type']) : null;
    $vehicle_name = isset($data['vehicle_name']) ? mysqli_real_escape_string($conn, $data['vehicle_name']) : null;
    $fuel_type = isset($data['fuel_type']) ? mysqli_real_escape_string($conn, $data['fuel_type']) : null;
    $status = mysqli_real_escape_string($conn, $data['status']);
    $rc_no = isset($data['rc_no']) ? mysqli_real_escape_string($conn, $data['rc_no']) : null;
    $rc_name = isset($data['rc_name']) ? mysqli_real_escape_string($conn, $data['rc_name']) : null;
    $rc_manufecture_date = isset($data['rc_manufecture_date']) ? validateDate($data['rc_manufecture_date']) : null;
    $insurance_number = isset($data['insurance_number']) ? mysqli_real_escape_string($conn, $data['insurance_number']) : null;
    $insurance_doe = isset($data['insurance_doe']) ? validateDate($data['insurance_doe']) : null;
    $puc_doi = isset($data['puc_doi']) ? validateDate($data['puc_doi']) : null;
    $puc_doe = isset($data['puc_doe']) ? validateDate($data['puc_doe']) : null;
    $texi_permit_no = isset($data['texi_permit_no']) ? mysqli_real_escape_string($conn, $data['texi_permit_no']) : null;
    $texi_permit_doi = isset($data['texi_permit_doi']) ? validateDate($data['texi_permit_doi']) : null;
    $texi_permit_doe = isset($data['texi_permit_doe']) ? validateDate($data['texi_permit_doe']) : null;
    $fitness_certificate_no = isset($data['fitness_certificate_no']) ? mysqli_real_escape_string($conn, $data['fitness_certificate_no']) : null;
    $fitness_certificate_doi = isset($data['fitness_certificate_doi']) ? validateDate($data['fitness_certificate_doi']) : null;
    $fitness_certificate_doe = isset($data['fitness_certificate_doe']) ? validateDate($data['fitness_certificate_doe']) : null;

    // Log sanitized data for debugging
    file_put_contents('debug.log', "Normalized Vehicle Number: $normalized_vehicle_number\n", FILE_APPEND);
    file_put_contents('debug.log', "Sanitized Data: " . print_r([
        'owner_id' => $owner_id,
        'status' => $status,
        'vehicle_type' => $vehicle_type,
        'rc_manufecture_date' => $rc_manufecture_date,
        'insurance_doe' => $insurance_doe,
    ], true) . "\n", FILE_APPEND);

    // Check if vehicle exists
    $check_sql = "SELECT id FROM cars WHERE vehicle_number = ?";
    $check_stmt = mysqli_prepare($conn, $check_sql);
    if (!$check_stmt) {
        $error = "Check Prepare failed: " . mysqli_error($conn);
        file_put_contents('debug.log', "Check Prepare Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_bind_param($check_stmt, "s", $normalized_vehicle_number);
    mysqli_stmt_execute($check_stmt);
    mysqli_stmt_store_result($check_stmt);
    if (mysqli_stmt_num_rows($check_stmt) == 0) {
        file_put_contents('debug.log', "No vehicle found for: $normalized_vehicle_number\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => "Vehicle number not found: $normalized_vehicle_number"]);
        mysqli_stmt_close($check_stmt);
        mysqli_close($conn);
        exit;
    }
    mysqli_stmt_close($check_stmt);

    // Update query
    $sql = "UPDATE cars SET 
        owner_id = ?, vehicle_type = ?, vehicle_name = ?, fuel_type = ?, status = ?,
        rc_no = ?, rc_name = ?, rc_manufecture_date = ?,
        insurance_number = ?, insurance_doe = ?, puc_doi = ?, puc_doe = ?,
        texi_permit_no = ?, texi_permit_doi = ?, texi_permit_doe = ?,
        fitness_certificate_no = ?, fitness_certificate_doi = ?, fitness_certificate_doe = ?
        WHERE vehicle_number = ?";
    file_put_contents('debug.log', "Update SQL: $sql\n", FILE_APPEND);

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = "Prepare failed: " . mysqli_error($conn);
        file_put_contents('debug.log', "Update Prepare Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
        mysqli_close($conn);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "sssssssssssssssssss",
        $owner_id, $vehicle_type, $vehicle_name, $fuel_type, $status,
        $rc_no, $rc_name, $rc_manufecture_date,
        $insurance_number, $insurance_doe, $puc_doi, $puc_doe,
        $texi_permit_no, $texi_permit_doi, $texi_permit_doe,
        $fitness_certificate_no, $fitness_certificate_doi, $fitness_certificate_doe,
        $normalized_vehicle_number
    );

    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        file_put_contents('debug.log', "Update Rows Affected: $affected_rows\n", FILE_APPEND);
        if ($affected_rows > 0) {
            echo json_encode([
                "status" => "success",
                "message" => "Car updated successfully",
                "rows_affected" => $affected_rows
            ]);
        } else {
            echo json_encode([
                "status" => "warning",
                "message" => "No changes were made. Data may match existing record or vehicle number not found."
            ]);
        }
    } else {
        $error = "Execute failed: " . mysqli_stmt_error($stmt);
        file_put_contents('debug.log', "Update Execute Error: $error\n", FILE_APPEND);
        echo json_encode(["status" => "error", "message" => $error]);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

// Invalid request method
echo json_encode(["status" => "error", "message" => "Invalid request method"]);
exit;
?>