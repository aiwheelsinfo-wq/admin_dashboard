<?php
include 'db_connect.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$stored_number = trim($_POST['stored_number'] ?? $_GET['stored_number'] ?? '');

if (!empty($stored_number)) {
    // 1. Check if vendor exists in vendors table
    $vSql = "SELECT * FROM vendors WHERE phone_number = ?";
    $vStmt = $conn->prepare($vSql);
    if ($vStmt) {
        $vStmt->bind_param("s", $stored_number);
        $vStmt->execute();
        $vRes = $vStmt->get_result();
        if ($vRes && $vRes->num_rows > 0) {
            echo json_encode([
                "success" => true,
                "message" => "Vendor account active",
                "current_status" => "active"
            ]);
            exit;
        }
    }

    // 2. Fetch driver details
    $sql = "SELECT * FROM drivers WHERE phone_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $stored_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $driver = $result->fetch_assoc();
        $currStatus = strtolower(trim($driver['status'] ?? ''));

        // If status is already filled, active, not car, or notified, keep it active!
        if (in_array($currStatus, ['filled', 'active', 'not car', 'notified'])) {
            echo json_encode([
                "success" => true,
                "message" => "Account active",
                "current_status" => $currStatus
            ]);
            exit;
        }

        // Check required fields
        $requiredFields = ['agency_name', 'full_name', 'driver_address', 'email', 'driver_city', 'pin_code'];
        $hasEmpty = false;
        foreach ($requiredFields as $field) {
            if (empty($driver[$field])) {
                $hasEmpty = true;
                break;
            }
        }

        $newStatus = $hasEmpty ? 'not filled' : 'active';
        if ($currStatus !== $newStatus) {
            $updateStmt = $conn->prepare("UPDATE drivers SET status = ? WHERE phone_number = ?");
            $updateStmt->bind_param("ss", $newStatus, $stored_number);
            $updateStmt->execute();
        }

        echo json_encode([
            "success" => true,
            "message" => "Status processed",
            "current_status" => $newStatus
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Driver not found"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Phone number required"]);
}
?>
