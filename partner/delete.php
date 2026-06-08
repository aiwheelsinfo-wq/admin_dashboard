<?php
/**
 * partner/delete.php
 * AJAX handler — Delete a partner and associated logs.
 * POST body: { partner_id }
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$partner_id = (int)($body['partner_id'] ?? 0);

if (!$partner_id) {
    echo json_encode(['status' => false, 'message' => 'partner_id is required']);
    exit();
}

// Delete related records first (logs, limits)
mysqli_query($conn, "DELETE FROM partner_api_logs   WHERE partner_id = $partner_id");
mysqli_query($conn, "DELETE FROM partner_api_limits WHERE partner_id = $partner_id");
// Keep partner_bookings for records but update status
mysqli_query($conn, "UPDATE partner_bookings SET status = 'partner_deleted' WHERE partner_id = $partner_id");

// Delete partner
$stmt = mysqli_prepare($conn, "DELETE FROM partners WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $partner_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['status' => true, 'message' => 'Partner deleted successfully']);
} else {
    echo json_encode(['status' => false, 'message' => 'Delete failed: ' . mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
