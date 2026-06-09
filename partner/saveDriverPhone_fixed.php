<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Connect to database (assuming path relative to driver2025 directory on server)
include('../2025/db_connect.php');
header('Content-Type: application/json');

$response = ["success" => false, "message" => "Something went wrong!"];

$fcm_token = $_POST['fcm_token'] ?? '';
$phone_number = $_POST['phone_number'] ?? null;

$randomNumber = rand(1000, 9999);
date_default_timezone_set('Asia/Kolkata');
$created_at = date("Y-m-d H:i:s");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (empty($phone_number)) {
        echo json_encode(["success" => false, "message" => "Phone number is required!"]);
        exit;
    }

    // 🔍 Check if phone exists
    $stmt = $conn->prepare("SELECT phone_number FROM drivers WHERE phone_number = ?");
    $stmt->bind_param("s", $phone_number);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // ✅ Phone found → Always update token (even if empty)
        $update_stmt = $conn->prepare("UPDATE drivers SET fcm_token = ? WHERE phone_number = ?");
        $update_stmt->bind_param("ss", $fcm_token, $phone_number);

        if ($update_stmt->execute()) {
            $response = [
                "success" => true,
                "message" => "FCM token updated successfully!"
            ];
        } else {
            $response["message"] = "Failed to update token.";
        }

        $update_stmt->close();

    } else {
        // 🆕 Insert new record
        $stmt->close();
        $insert_stmt = $conn->prepare("
            INSERT INTO drivers (phone_number, fcm_token, driver_code, created_at)
            VALUES (?, ?, ?, ?)
        ");
        $insert_stmt->bind_param("ssss", $phone_number, $fcm_token, $randomNumber, $created_at);

        if ($insert_stmt->execute()) {
            $response = [
                "success" => true,
                "message" => "New driver created & token saved!"
            ];
        } else {
            $response["message"] = "Failed to insert new driver.";
        }

        $insert_stmt->close();
    }

    $stmt->close();

} else {
    $response["message"] = "Invalid request method!";
}

$conn->close();
echo json_encode($response);
exit;
?>
