<?php
/**
 * partner/toggle_status.php
 * AJAX handler to Block / Unblock a partner.
 * POST body: { partner_id, action: 'block'|'unblock' }
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
$action     = trim($body['action'] ?? '');

if (!$partner_id || !in_array($action, ['block', 'unblock'])) {
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit();
}

$new_status = $action === 'block' ? 'blocked' : 'active';

$stmt = mysqli_prepare($conn, "UPDATE partners SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'si', $new_status, $partner_id);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status'     => true,
        'message'    => 'Partner ' . ($new_status === 'blocked' ? 'blocked' : 'activated') . ' successfully.',
        'new_status' => $new_status,
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
