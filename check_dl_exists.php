<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

include 'db_connect.php';

$rawDl = $_POST['license_no'] ?? $_GET['license_no'] ?? '';
$license_no = strtoupper(trim(preg_replace('/[\s\-]/', '', $rawDl)));

if (empty($license_no)) {
    echo json_encode(["exists" => false, "message" => "License number empty"]);
    exit;
}

// 1. Check in drivers table
$stmt1 = $conn->prepare("SELECT driver_id, full_name FROM drivers WHERE REPLACE(REPLACE(UPPER(license_no), ' ', ''), '-', '') = ?");
if ($stmt1) {
    $stmt1->bind_param("s", $license_no);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    if ($res1 && $res1->num_rows > 0) {
        $row = $res1->fetch_assoc();
        echo json_encode([
            "exists" => true,
            "message" => "This driving license number already exists. Please enter a different license number.",
            "driver_name" => $row['full_name'] ?? ''
        ]);
        exit;
    }
}

// 2. Check in driver_dl_verifications table
$stmt2 = $conn->prepare("SELECT id, holder_name FROM driver_dl_verifications WHERE REPLACE(REPLACE(UPPER(dl_number), ' ', ''), '-', '') = ?");
if ($stmt2) {
    $stmt2->bind_param("s", $license_no);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    if ($res2 && $res2->num_rows > 0) {
        $row = $res2->fetch_assoc();
        echo json_encode([
            "exists" => true,
            "message" => "This driving license number already exists. Please enter a different license number.",
            "driver_name" => $row['holder_name'] ?? ''
        ]);
        exit;
    }
}

echo json_encode([
    "exists" => false,
    "message" => "License number is unique and available."
]);
?>
