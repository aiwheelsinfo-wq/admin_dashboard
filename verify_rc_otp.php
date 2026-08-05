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

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$client_id = trim($inputData['client_id'] ?? $_POST['client_id'] ?? '');
$otp       = trim($inputData['otp']       ?? $_POST['otp']       ?? '');
$rawRc     = $inputData['rc_number']  ?? $inputData['rc_no'] ?? $_POST['rc_number'] ?? '';
$rc_number = strtoupper(trim(preg_replace('/[\s\-]/', '', $rawRc)));

if (empty($otp)) {
    sendJsonResponse(false, "OTP is required");
}

if (empty($rc_number)) {
    sendJsonResponse(false, "RC Number is required");
}

// 1. Verify OTP against rc_otp_sessions OR allow fallback test OTP 123456
$isValidOtp = false;
$checkStmt = mysqli_prepare($conn, "SELECT * FROM rc_otp_sessions WHERE (client_id = ? OR rc_number = ?) AND (otp = ? OR ? = '123456') ORDER BY id DESC LIMIT 1");
if ($checkStmt) {
    mysqli_stmt_bind_param($checkStmt, "ssss", $client_id, $rc_number, $otp, $otp);
    mysqli_stmt_execute($checkStmt);
    $res = mysqli_stmt_get_result($checkStmt);
    if (mysqli_fetch_assoc($res) || $otp === '123456') {
        $isValidOtp = true;
    }
    mysqli_stmt_close($checkStmt);
}

if (!$isValidOtp) {
    sendJsonResponse(false, "Incorrect OTP. Please check the SMS sent to the RC owner's mobile number.");
}

$api_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmcmVzaCI6ZmFsc2UsImlhdCI6MTc4NDA5Nzc2MCwianRpIjoiZGU2YjkxZGItZTE4MC00M2EzLWI0MmUtOWM5YTM0MWEzYWQ0IiwidHlwZSI6ImFjY2VzcyIsImlkZW50aXR5IjoiZGV2LmFnbmljYXJyZW50YWxfMTg9NDE3QHN1cmVwYXNzLmlvIiwibmJmIjoxNzg0MDk3NzYwLCJleHAiOjE3ODY2ODk3NjAsImVtYWlsIjoiYWduaWNhcnJlbnRhbF8xODk0MTdAc3VyZXBhc3MuaW8iLCJ0ZW5hbnRfaWQiOiJtYWluIiwidXNlcl9jbGFpbXMiOnsic2NvcGVzIjpbInVzZXIiXX19.9lcZoAJ98v5fv5NF9pg4QuCIrkQ7jLMuq4E4oM6ZjIQ";
$customer_id = "agnicarrental_189417";

// Fetch full RC details via Surepass Sandbox RC Full API
$fullUrl = "https://sandbox.surepass.io/api/v1/rc/rc-full";
$payload2 = json_encode(["id_number" => $rc_number]);

$ch2 = curl_init();
curl_setopt_array($ch2, [
    CURLOPT_URL => $fullUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload2,
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
$rcDetails = null;

if (($httpCode2 == 200 || $httpCode2 == 201) && isset($resData2['success']) && $resData2['success'] === true && !empty($resData2['data'])) {
    $rcDetails = $resData2['data'];
}

if (!empty($rcDetails)) {
    $rc = $rcDetails;

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

    $ver_status = 'VERIFIED';
    $today = date('Y-m-d');
    if (($insurance_upto && $insurance_upto < $today) || ($fit_up_to && $fit_up_to < $today)) {
        $ver_status = 'EXPIRED';
    }

    if (!empty($rc_number)) {
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
    }

    sendJsonResponse(true, "RC OTP Verified successfully!", [
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
    sendJsonResponse(false, "Failed to retrieve RC details from Government database.");
}
?>
