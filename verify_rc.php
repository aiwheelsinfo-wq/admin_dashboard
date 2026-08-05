<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ob_get_length()) ob_clean();
    exit(0);
}

include 'db_connect.php';

function sendJsonResponse($success, $message, $extra = []) {
    if (ob_get_length()) {
        ob_clean();
    }
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

if (!$conn) {
    sendJsonResponse(false, "Database connection failed: " . mysqli_connect_error());
}

// Ensure table exists
$tableSql = "CREATE TABLE IF NOT EXISTS `driver_rc_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `rc_number` VARCHAR(30) NOT NULL UNIQUE,
  `owner_name` VARCHAR(100),
  `maker_model` VARCHAR(150),
  `maker_description` VARCHAR(100),
  `registration_date` DATE,
  `fit_up_to` DATE,
  `insurance_policy_number` VARCHAR(100),
  `insurance_upto` DATE,
  `insurance_company` VARCHAR(150),
  `fuel_type` VARCHAR(50),
  `color` VARCHAR(50),
  `seat_capacity` VARCHAR(10),
  `permit_number` VARCHAR(100),
  `permit_valid_upto` DATE,
  `rc_status` VARCHAR(50) DEFAULT 'ACTIVE',
  `permanent_address` TEXT,
  `verification_status` ENUM('VERIFIED', 'EXPIRED', 'REJECTED', 'MANUAL_APPROVED') DEFAULT 'VERIFIED',
  `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $tableSql);

// Support both JSON body and POST/GET form data
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$rawRc = $inputData['rc_number'] ?? $inputData['rc_no'] ?? $inputData['id_number'] ?? $_POST['rc_number'] ?? $_POST['rc_no'] ?? $_GET['rc_number'] ?? '';
$rc_number = strtoupper(trim(preg_replace('/[\s\-]/', '', $rawRc)));

if (empty($rc_number)) {
    sendJsonResponse(false, "RC Number is required");
}

// 1. Check Database Cache First (₹0 Cost)
$cacheStmt = mysqli_prepare($conn, "SELECT * FROM driver_rc_verifications WHERE rc_number = ? AND verification_status IN ('VERIFIED', 'MANUAL_APPROVED')");
if ($cacheStmt) {
    mysqli_stmt_bind_param($cacheStmt, "s", $rc_number);
    mysqli_stmt_execute($cacheStmt);
    $cacheResult = mysqli_stmt_get_result($cacheStmt);

    if ($cachedRow = mysqli_fetch_assoc($cacheResult)) {
        sendJsonResponse(true, "Verified from Database Cache (₹0 Cost)", [
            "source" => "cache",
            "data" => [
                "rc_number" => $cachedRow['rc_number'],
                "owner_name" => $cachedRow['owner_name'],
                "maker_model" => $cachedRow['maker_model'],
                "maker_description" => $cachedRow['maker_description'],
                "registration_date" => $cachedRow['registration_date'],
                "fit_up_to" => $cachedRow['fit_up_to'],
                "insurance_policy_number" => $cachedRow['insurance_policy_number'],
                "insurance_upto" => $cachedRow['insurance_upto'],
                "insurance_company" => $cachedRow['insurance_company'],
                "fuel_type" => $cachedRow['fuel_type'],
                "color" => $cachedRow['color'],
                "seat_capacity" => $cachedRow['seat_capacity'],
                "permit_number" => $cachedRow['permit_number'],
                "permit_valid_upto" => $cachedRow['permit_valid_upto'],
                "rc_status" => $cachedRow['rc_status'],
                "permanent_address" => $cachedRow['permanent_address'],
                "verification_status" => $cachedRow['verification_status']
            ]
        ]);
    }
    mysqli_stmt_close($cacheStmt);
}

// 2. Call Surepass Sandbox RC API
$surepass_url = "https://sandbox.surepass.app/api/v1/rc/rc-full";
$api_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmcmVzaCI6ZmFsc2UsImlhdCI6MTc4NDA5Nzc2MCwianRpIjoiZGU2YjkxZGItZTE4MC00M2EzLWI0MmUtOWM5YTM0MWEzYWQ0IiwidHlwZSI6ImFjY2VzcyIsImlkZW50aXR5IjoiZGV2LmFnbmljYXJyZW50YWxfMTg5NDE3QHN1cmVwYXNzLmlvIiwibmJmIjoxNzg0MDk3NzYwLCJleHAiOjE3ODY2ODk3NjAsImVtYWlsIjoiYWduaWNhcnJlbnRhbF8xODk0MTdAc3VyZXBhc3MuaW8iLCJ0ZW5hbnRfaWQiOiJtYWluIiwidXNlcl9jbGFpbXMiOnsic2NvcGVzIjpbInVzZXIiXX19.9lcZoAJ98v5fv5NF9pg4QuCIrkQ7jLMuq4E4oM6ZjIQ";
$customer_id = "agnicarrental_189417";

$payload = json_encode([
    "id_number" => $rc_number
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $surepass_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $api_token,
        "X-Customer-Id: " . $customer_id,
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 15
]);

$apiResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$resData = json_decode($apiResponse, true);

if (($httpCode == 200 || $httpCode == 201) && isset($resData['success']) && $resData['success'] === true && !empty($resData['data'])) {
    $rc = $resData['data'];

    $owner_name         = trim($rc['owner_name'] ?? '');
    $maker_model        = trim($rc['maker_model'] ?? '');
    $maker_description  = trim($rc['maker_description'] ?? '');
    $registration_date  = !empty($rc['registration_date']) ? $rc['registration_date'] : NULL;
    $fit_up_to          = !empty($rc['fit_up_to']) ? $rc['fit_up_to'] : NULL;
    $insurance_policy   = trim($rc['insurance_policy_number'] ?? '');
    $insurance_upto     = !empty($rc['insurance_upto']) ? $rc['insurance_upto'] : NULL;
    $insurance_company  = trim($rc['insurance_company'] ?? '');
    $fuel_type          = trim($rc['fuel_type'] ?? '');
    $color              = trim($rc['color'] ?? '');
    $seat_capacity      = trim((string)($rc['seat_capacity'] ?? ''));
    $permit_number      = trim($rc['permit_number'] ?? '');
    $permit_valid_upto  = !empty($rc['permit_valid_upto']) ? $rc['permit_valid_upto'] : NULL;
    $rc_status          = trim($rc['rc_status'] ?? 'ACTIVE');
    $permanent_address  = trim($rc['permanent_address'] ?? $rc['present_address'] ?? '');

    // Determine verification status
    $ver_status = 'VERIFIED';
    $today = date('Y-m-d');
    if (($insurance_upto && $insurance_upto < $today) || ($fit_up_to && $fit_up_to < $today)) {
        $ver_status = 'EXPIRED';
    }

    // Upsert into driver_rc_verifications
    $saveSql = "INSERT INTO driver_rc_verifications 
        (rc_number, owner_name, maker_model, maker_description, registration_date, fit_up_to, 
         insurance_policy_number, insurance_upto, insurance_company, fuel_type, color, 
         seat_capacity, permit_number, permit_valid_upto, rc_status, permanent_address, verification_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        owner_name=VALUES(owner_name), 
        maker_model=VALUES(maker_model), 
        maker_description=VALUES(maker_description),
        registration_date=VALUES(registration_date),
        fit_up_to=VALUES(fit_up_to), 
        insurance_policy_number=VALUES(insurance_policy_number),
        insurance_upto=VALUES(insurance_upto), 
        insurance_company=VALUES(insurance_company),
        fuel_type=VALUES(fuel_type), 
        color=VALUES(color), 
        seat_capacity=VALUES(seat_capacity),
        permit_number=VALUES(permit_number), 
        permit_valid_upto=VALUES(permit_valid_upto), 
        rc_status=VALUES(rc_status),
        permanent_address=VALUES(permanent_address), 
        verification_status=VALUES(verification_status)";

    $stmt = mysqli_prepare($conn, $saveSql);
    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt, "sssssssssssssssss",
            $rc_number, $owner_name, $maker_model, $maker_description, $registration_date, $fit_up_to,
            $insurance_policy, $insurance_upto, $insurance_company, $fuel_type, $color,
            $seat_capacity, $permit_number, $permit_valid_upto, $rc_status, $permanent_address, $ver_status
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Auto-update drivers table if matching rc_no exists
    $updateDriverSql = "UPDATE drivers SET 
        rc_name = ?, 
        vehicle_name = IF(vehicle_name IS NULL OR vehicle_name = '', ?, vehicle_name),
        fuel_type = IF(fuel_type IS NULL OR fuel_type = '', ?, fuel_type),
        insurnce_number = ?,
        insurnce_doe = ?,
        texi_permit_no = ?,
        texi_permit_doe = ?,
        fitness_certificate_doe = ?
        WHERE REPLACE(REPLACE(UPPER(rc_no), ' ', ''), '-', '') = ?";
    $dStmt = mysqli_prepare($conn, $updateDriverSql);
    if ($dStmt) {
        $fullVehicleName = trim("$maker_description $maker_model");
        mysqli_stmt_bind_param(
            $dStmt, "sssssssss",
            $owner_name, $fullVehicleName, $fuel_type, $insurance_policy, $insurance_upto,
            $permit_number, $permit_valid_upto, $fit_up_to, $rc_number
        );
        mysqli_stmt_execute($dStmt);
        mysqli_stmt_close($dStmt);
    }

    sendJsonResponse(true, "Vehicle RC Verified successfully via Government API", [
        "source" => "surepass_api",
        "data" => [
            "rc_number" => $rc_number,
            "owner_name" => $owner_name,
            "maker_model" => $maker_model,
            "maker_description" => $maker_description,
            "registration_date" => $registration_date,
            "fit_up_to" => $fit_up_to,
            "insurance_policy_number" => $insurance_policy,
            "insurance_upto" => $insurance_upto,
            "insurance_company" => $insurance_company,
            "fuel_type" => $fuel_type,
            "color" => $color,
            "seat_capacity" => $seat_capacity,
            "permit_number" => $permit_number,
            "permit_valid_upto" => $permit_valid_upto,
            "rc_status" => $rc_status,
            "permanent_address" => $permanent_address,
            "verification_status" => $ver_status
        ]
    ]);
} else {
    $errorMsg = $resData['message'] ?? "Invalid Vehicle RC details or Surepass API error";
    sendJsonResponse(false, $errorMsg);
}
?>
