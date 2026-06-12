<?php
include __DIR__ . '/../db_connect.php'; // Include database connection

date_default_timezone_set("Asia/Kolkata"); // set PHP timezone

$phone_number = $_POST['phone_number'] ?? '';

if (empty($phone_number)) {
    echo json_encode(["success" => false, "message" => "Phone number required"]);
    exit;
}

$sql = "SELECT * FROM drivers WHERE phone_number = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $phone_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(["success" => true]);
    
    // 🔹 Update updated_at with IST time
    $currentTime = date("Y-m-d H:i:s");
    $updateupdated_at = $conn->prepare("UPDATE drivers SET updated_at = ? WHERE phone_number = ?");
    $updateupdated_at->bind_param("ss", $currentTime, $phone_number);
    $updateupdated_at->execute();
    $updateupdated_at->close();
} else {
    echo json_encode(["success" => false]);
}

$stmt->close();
$conn->close();
?>
