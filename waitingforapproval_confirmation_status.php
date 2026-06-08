<?php
header('Content-Type: application/json');
include 'db_connect.php';

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $car_id = $_POST['id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if ($car_id && in_array($new_status, ['active', 'Notified'])) {
        $stmt = $conn->prepare("UPDATE cars SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $car_id);

        if ($stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => "car status changed to $new_status.",
                'id' => $car_id
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
            'message' => 'Missing car_id or invalid status.'
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
