<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Handle CORS preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

require_once __DIR__ . '/db_connect.php';

$response = ["status" => "error", "categories" => []];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = mysqli_query($conn, "SELECT car_type FROM car_categories WHERE status = 'active' ORDER BY car_type ASC");
    if ($result) {
        $categories = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row['car_type'];
        }
        $response = [
            "status" => "success",
            "categories" => $categories
        ];
    } else {
        $response["message"] = "Database error: " . mysqli_error($conn);
    }
} else {
    $response["message"] = "Invalid request method";
}

echo json_encode($response);
mysqli_close($conn);
exit;
?>
