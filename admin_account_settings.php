<?php
// Enable CORS for React Admin Dashboard
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

// Parse JSON input or Form POST
$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true) ?? [];
$params = array_merge($_GET, $_POST, $inputData);

$action = $params['action'] ?? 'get_profile';

// 1. GET PROFILE (id = 1 default superadmin)
if ($action === 'get_profile') {
    $stmt = $conn->prepare("SELECT id, userName, email, created_at FROM admins ORDER BY id ASC LIMIT 1");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database query failed: " . $conn->error]);
        exit;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            "status" => "success",
            "data" => [
                "id" => (int)$row['id'],
                "userName" => $row['userName'] ?: "Agni Car Rental",
                "email" => $row['email'],
                "role" => "SuperAdmin",
                "created_at" => $row['created_at']
            ]
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Admin account not found."]);
    }
    $stmt->close();
    exit;
}

// 2. UPDATE EMAIL OR PASSWORD
if ($action === 'update_credentials' || $action === 'update_email' || $action === 'update_password') {
    $current_password = trim($params['current_password'] ?? '');
    $new_email = isset($params['new_email']) ? trim($params['new_email']) : null;
    $new_password = isset($params['new_password']) ? trim($params['new_password']) : null;

    if (empty($current_password)) {
        echo json_encode(["status" => "error", "message" => "Current password is required to verify your identity."]);
        exit;
    }

    // Retrieve stored password
    $stmt = $conn->prepare("SELECT id, email, password FROM admins ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        echo json_encode(["status" => "error", "message" => "Admin account record not found."]);
        exit;
    }

    $admin_id = (int)$admin['id'];
    $stored_password = $admin['password'];

    // Verify current password
    if ($current_password !== $stored_password) {
        echo json_encode(["status" => "error", "message" => "Incorrect current password. Please try again."]);
        exit;
    }

    $updates = [];
    $types = "";
    $bindValues = [];

    // Validate and prepare email update
    if ($new_email !== null && $new_email !== '') {
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["status" => "error", "message" => "Invalid email address format."]);
            exit;
        }
        $updates[] = "`email` = ?";
        $types .= "s";
        $bindValues[] = $new_email;
    }

    // Validate and prepare password update
    if ($new_password !== null && $new_password !== '') {
        if (strlen($new_password) < 6) {
            echo json_encode(["status" => "error", "message" => "New password must be at least 6 characters long."]);
            exit;
        }
        $updates[] = "`password` = ?";
        $types .= "s";
        $bindValues[] = $new_password;
    }

    if (empty($updates)) {
        echo json_encode(["status" => "error", "message" => "No new email or password provided to update."]);
        exit;
    }

    // Bind admin id
    $types .= "i";
    $bindValues[] = $admin_id;

    $sql = "UPDATE admins SET " . implode(", ", $updates) . " WHERE id = ?";
    $updateStmt = $conn->prepare($sql);
    if (!$updateStmt) {
        echo json_encode(["status" => "error", "message" => "Database update failed: " . $conn->error]);
        exit;
    }

    $updateStmt->bind_param($types, ...$bindValues);
    if ($updateStmt->execute()) {
        $updateStmt->close();
        echo json_encode([
            "status" => "success",
            "message" => "Account settings updated successfully.",
            "data" => [
                "email" => $new_email !== null ? $new_email : $admin['email']
            ]
        ]);
    } else {
        $errorMsg = $updateStmt->error;
        $updateStmt->close();
        echo json_encode(["status" => "error", "message" => "Failed to update settings: " . $errorMsg]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
?>
