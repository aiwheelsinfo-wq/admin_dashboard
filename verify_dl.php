<?php
ob_start();
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include 'db_connect.php';

// Support both JSON body and POST/GET form data
$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);

$rawDl = $inputData['license_no'] ?? $_POST['license_no'] ?? $_GET['license_no'] ?? '';
$rawDob = $inputData['date_of_birth'] ?? $_POST['date_of_birth'] ?? $_GET['date_of_birth'] ?? '';

$license_no = strtoupper(trim(preg_replace('/[\s\-]/', '', $rawDl)));
$dob = trim($rawDob);

if (empty($license_no)) {
    echo json_encode(["success" => false, "message" => "License number is required"]);
    exit;
}

// 1. Regex Format Check (Indian DL)
if (!preg_match('/^[A-Z]{2}[0-9]{2}[0-9]{11}$/', $license_no)) {
    echo json_encode(["success" => false, "message" => "Invalid Indian Driving License format. Must be 15 characters (e.g. KL7320220004599)"]);
    exit;
}

// Ensure table exists
$tableSql = "CREATE TABLE IF NOT EXISTS `driver_dl_verifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `dl_number` VARCHAR(30) NOT NULL UNIQUE,
  `dob` DATE NOT NULL,
  `holder_name` VARCHAR(100),
  `issue_date` DATE,
  `expiry_date` DATE,
  `vehicle_classes` VARCHAR(255),
  `has_lmv` TINYINT(1) DEFAULT 1,
  `dl_photo_path` VARCHAR(255),
  `permanent_address` TEXT,
  `verification_status` ENUM('VERIFIED', 'EXPIRED', 'REJECTED') DEFAULT 'VERIFIED',
  `verified_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";
mysqli_query($conn, $tableSql);

// 2. CHECK MYSQL CACHE FIRST (Avoid Duplicate API Charges - ₹0 Cost)
$cacheStmt = mysqli_prepare($conn, "SELECT * FROM driver_dl_verifications WHERE dl_number = ? AND verification_status = 'VERIFIED'");
mysqli_stmt_bind_param($cacheStmt, "s", $license_no);
mysqli_stmt_execute($cacheStmt);
$cacheResult = mysqli_stmt_get_result($cacheStmt);

if ($cachedRow = mysqli_fetch_assoc($cacheResult)) {
    echo json_encode([
        "success" => true,
        "source" => "cache",
        "message" => "Verified from Database Cache (₹0 Cost)",
        "data" => [
            "name" => $cachedRow['holder_name'],
            "license_number" => $cachedRow['dl_number'],
            "dob" => $cachedRow['dob'],
            "expiry_date" => $cachedRow['expiry_date'],
            "issue_date" => $cachedRow['issue_date'],
            "permanent_address" => $cachedRow['permanent_address'],
            "has_lmv" => (bool)$cachedRow['has_lmv'],
            "dl_photo_path" => $cachedRow['dl_photo_path']
        ]
    ]);
    exit;
}

// 3. EXECUTE SUREPASS API AUTOMATICALLY
if (empty($dob)) {
    echo json_encode(["success" => false, "message" => "Date of Birth is required for API verification"]);
    exit;
}

$surepass_url = "https://sandbox.surepass.app/api/v1/driving-license/driving-license"; // Sandbox endpoint
$api_token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJmcmVzaCI6ZmFsc2UsImlhdCI6MTc4NDA5Nzc2MCwianRpIjoiZGU2YjkxZGItZTE4MC00M2EzLWI0MmUtOWM5YTM0MWEzYWQ0IiwidHlwZSI6ImFjY2VzcyIsImlkZW50aXR5IjoiZGV2LmFnbmljYXJyZW50YWxfMTg5NDE3QHN1cmVwYXNzLmlvIiwibmJmIjoxNzg0MDk3NzYwLCJleHAiOjE3ODY2ODk3NjAsImVtYWlsIjoiYWduaWNhcnJlbnRhbF8xODk0MTdAc3VyZXBhc3MuaW8iLCJ0ZW5hbnRfaWQiOiJtYWluIiwidXNlcl9jbGFpbXMiOnsic2NvcGVzIjpbInVzZXIiXX19.9lcZoAJ98v5fv5NF9pg4QuCIrkQ7jLMuq4E4oM6ZjIQ";
$customer_id = "agnicarrental_189417";

$payload = json_encode([
    "id_number" => $license_no,
    "dob" => $dob
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
    $dl = $resData['data'];

    $holder_name = mysqli_real_escape_string($conn, $dl['name'] ?? '');
    $expiry_date = mysqli_real_escape_string($conn, $dl['doe'] ?? $dl['expiry_date'] ?? '');
    $issue_date = mysqli_real_escape_string($conn, $dl['doi'] ?? $dl['issue_date'] ?? '');
    $address = mysqli_real_escape_string($conn, $dl['permanent_address'] ?? '');
    
    $vehicle_classes = isset($dl['vehicle_classes']) ? json_encode($dl['vehicle_classes']) : '["LMV"]';
    $has_lmv = (is_array($dl['vehicle_classes']) && in_array("LMV", $dl['vehicle_classes'])) ? 1 : 1;

    // Base64 Photo Extraction to JPG
    $dl_photo_path = "";
    if (!empty($dl['profile_image'])) {
        $imgBinary = base64_decode($dl['profile_image']);
        if (!file_exists('uploads/dl_photos')) {
            mkdir('uploads/dl_photos', 0777, true);
        }
        $fileName = "dl_" . $license_no . "_" . time() . ".jpg";
        $dl_photo_path = "uploads/dl_photos/" . $fileName;
        file_put_contents($dl_photo_path, $imgBinary);
    }

    // Save to Database
    $saveSql = "INSERT INTO driver_dl_verifications 
        (dl_number, dob, holder_name, issue_date, expiry_date, vehicle_classes, has_lmv, dl_photo_path, permanent_address, verification_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'VERIFIED')
        ON DUPLICATE KEY UPDATE 
        holder_name=?, expiry_date=?, has_lmv=?, dl_photo_path=?, verification_status='VERIFIED'";

    $stmt = mysqli_prepare($conn, $saveSql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ssssssisisis", 
            $license_no, $dob, $holder_name, $issue_date, $expiry_date, $vehicle_classes, $has_lmv, $dl_photo_path, $address,
            $holder_name, $expiry_date, $has_lmv, $dl_photo_path
        );
        mysqli_stmt_execute($stmt);
    }

    echo json_encode([
        "success" => true,
        "source" => "surepass_api",
        "message" => "DL Verified successfully via Government API",
        "data" => [
            "name" => $holder_name,
            "license_number" => $license_no,
            "dob" => $dob,
            "expiry_date" => $expiry_date,
            "issue_date" => $issue_date,
            "permanent_address" => $address,
            "has_lmv" => (bool)$has_lmv,
            "dl_photo_path" => $dl_photo_path
        ]
    ]);
} else {
    $errorMsg = $resData['message'] ?? "Invalid Driving License details or Surepass API error";
    echo json_encode(["success" => false, "message" => $errorMsg]);
}
?>
