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

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$rawRc = $inputData['rc_number'] ?? $inputData['rc_no'] ?? $inputData['id_number'] ?? $_POST['rc_number'] ?? $_POST['rc_no'] ?? $_GET['rc_number'] ?? '';
$rc_number = strtoupper(trim(preg_replace('/[\s\-]/', '', $rawRc)));

if (empty($rc_number)) {
    sendJsonResponse(false, "RC Number is required");
}

// 1. Check DB Cache First (₹0 Cost Optimization)
$cacheStmt = mysqli_prepare($conn, "SELECT * FROM driver_rc_verifications WHERE rc_number = ? AND verification_status IN ('VERIFIED', 'MANUAL_APPROVED')");
if ($cacheStmt) {
    mysqli_stmt_bind_param($cacheStmt, "s", $rc_number);
    mysqli_stmt_execute($cacheStmt);
    $cacheResult = mysqli_stmt_get_result($cacheStmt);

    if ($cachedRow = mysqli_fetch_assoc($cacheResult)) {
        sendJsonResponse(true, "Vehicle already verified in Database Cache", [
            "source" => "cache",
            "already_verified" => true,
            "data" => [
                "rc_number" => $cachedRow['rc_number'],
                "owner_name" => $cachedRow['owner_name'],
                "maker_model" => $cachedRow['maker_model'],
                "verification_status" => $cachedRow['verification_status']
            ]
        ]);
    }
    mysqli_stmt_close($cacheStmt);
}

// 2. Call Surepass Sandbox RC-to-Mobile API to trigger OTP
$surepass_url = "https://sandbox.surepass.app/api/v1/rc/rc-to-mobile-number";
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

if (($httpCode == 200 || $httpCode == 201) && isset($resData['success']) && $resData['success'] === true) {
    $data = $resData['data'] ?? [];
    $client_id = $data['client_id'] ?? $data['request_id'] ?? ('rc_req_' . time());
    $masked_mobile = $data['mobile_number'] ?? $data['masked_mobile'] ?? $data['phone'] ?? 'RTO Registered Mobile';

    sendJsonResponse(true, "OTP sent successfully to vehicle registered mobile number", [
        "client_id" => $client_id,
        "rc_number" => $rc_number,
        "mobile_number" => $masked_mobile,
        "otp_sent" => true,
        "raw_surepass_response" => $data
    ]);
} else {
    // Fallback: If sandbox environment requires rc-full endpoint or returns standard error
    $errorMsg = $resData['message'] ?? "Failed to send OTP to registered RC mobile number. Please check RC number.";
    sendJsonResponse(false, $errorMsg, [
        "http_code" => $httpCode,
        "raw_response" => $resData
    ]);
}
?>
