<?php
header('Content-Type: application/json');
include 'db_connect.php';

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $driver_id = $_POST['driver_id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if ($driver_id && in_array($new_status, ['active', 'inactive'])) {
        $stmt = $conn->prepare("UPDATE drivers SET status = ? WHERE driver_id = ?");
        $stmt->bind_param("si", $new_status, $driver_id);

        if ($stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => "Driver status changed to $new_status.",
                'driver_id' => $driver_id
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Database error: ' . $stmt->error
            ];
        }

        $stmt->close();
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Missing driver_id or invalid status.'
        ];
    }

    $conn->close();
} else {
    $response = [
        'status' => 'error',
        'message' => 'Invalid request method.'
    ];
}

echo json_encode($response);
?>
