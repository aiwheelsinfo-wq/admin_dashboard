<?php
header('Content-Type: application/json'); // Set response format to JSON

include 'db_connect.php';

$response = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get raw POST data
    $input = json_decode(file_get_contents('php://input'), true);

    // Extract values
    $id = $input['id'] ?? null;
    $status = $input['status'] ?? null;

    if ($id && in_array($status, ['Pending', 'Not Confirmed'])) { // Restrict to valid statuses
        $stmt = $conn->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);

        if ($stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => "Booking status changed to $status."
            ];
        } else {
            $response = [
                'status' => 'error',
                'message' => 'Database error while updating.'
            ];
        }
        $stmt->close();
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Missing or invalid booking ID/status.'
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