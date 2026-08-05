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
    if (ob_get_length()) ob_clean();
    echo json_encode(array_merge([
        "success" => $success,
        "message" => $message
    ], $extra));
    exit;
}

if (!$conn) {
    sendJsonResponse(false, "Database connection failed: " . mysqli_connect_error());
}

// Ensure driver_rc_verifications table exists
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

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$rawRc = $inputData['rc_number'] ?? $inputData['rc_no'] ?? $_POST['rc_number'] ?? $_POST['rc_no'] ?? $_GET['rc_number'] ?? '';
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
        sendJsonResponse(true, "Verified from Cache", [
            "otp_required" => false,
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

// 2. Call Surepass Sandbox RC OTP Initiate API
$surepass_url = "https://sandbox.surepass.app/api/v1/rc/rc-to-mobile-number";
$api_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmcmVzaCI6ZmFsc2UsImlhdCI6MTc4NDA5Nzc2MCwianRpIjoiZGU2YjkxZGItZTE4MC00M2EzLWI0MmUtOWM5YTM0MWEzYWQ0IiwidHlwZSI6ImFjY2VzcyIsImlkZW50aXR5IjoiZGV2LmFnbmljYXJyZW50YWxfMTg9NDE3QHN1cmVwYXNzLmlvIiwibmJmIjoxNzg0MDk3NzYwLCJleHAiOjE3ODY2ODk3NjAsImVtYWlsIjoiYWduaWNhcnJlbnRhbF8xODk0MTdAc3VyZXBhc3MuaW8iLCJ0ZW5hbnRfaWQiOiJtYWluIiwidXNlcl9jbGFpbXMiOnsic2NvcGVzIjpbInVzZXIiXX19.9lcZoAJ98v5fv5NF9pg4QuCIrkQ7jLMuq4E4oM6ZjIQ";
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
    $data = $resData['data'];
    $client_id = $data['client_id'] ?? '';
    $mobile_number = $data['mobile_number'] ?? 'registered mobile';

    sendJsonResponse(true, "OTP sent successfully to RC owner mobile number", [
        "otp_required" => true,
        "client_id" => $client_id,
        "mobile_number" => $mobile_number,
        "rc_number" => $rc_number
    ]);
} else {
    // Fallback: Direct full RC fetch if Sandbox initiate API isn't enabled or bypasses OTP
    $fallbackUrl = "https://sandbox.surepass.app/api/v1/rc/rc-full";
    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $fallbackUrl,
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
    $apiResponse2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $resData2 = json_decode($apiResponse2, true);
    if (($httpCode2 == 200 || $httpCode2 == 201) && isset($resData2['success']) && $resData2['success'] === true && !empty($resData2['data'])) {
        $rc = $resData2['data'];
        sendJsonResponse(true, "RC Verified successfully", [
            "otp_required" => false,
            "data" => [
                "rc_number" => $rc_number,
                "owner_name" => trim($rc['owner_name'] ?? ''),
                "maker_model" => trim($rc['maker_model'] ?? ''),
                "maker_description" => trim($rc['maker_description'] ?? ''),
                "registration_date" => !empty($rc['registration_date']) ? $rc['registration_date'] : NULL,
                "fit_up_to" => !empty($rc['fit_up_to']) ? $rc['fit_up_to'] : NULL,
                "insurance_policy_number" => trim($rc['insurance_policy_number'] ?? ''),
                "insurance_upto" => !empty($rc['insurance_upto']) ? $rc['insurance_upto'] : NULL,
                "insurance_company" => trim($rc['insurance_company'] ?? ''),
                "fuel_type" => trim($rc['fuel_type'] ?? ''),
                "color" => trim($rc['color'] ?? ''),
                "seat_capacity" => trim((string)($rc['seat_capacity'] ?? '')),
                "permit_number" => trim($rc['permit_number'] ?? ''),
                "permit_valid_upto" => !empty($rc['permit_valid_upto']) ? $rc['permit_valid_upto'] : NULL,
                "rc_status" => trim($rc['rc_status'] ?? 'ACTIVE'),
                "permanent_address" => trim($rc['permanent_address'] ?? $rc['present_address'] ?? ''),
                "verification_status" => "VERIFIED"
            ]
        ]);
    } else {
        $errorMsg = $resData['message'] ?? $resData2['message'] ?? "Failed to initiate RC OTP verification";
        sendJsonResponse(false, $errorMsg);
    }
}
?>
