<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access.']);
    exit();
}

require_once __DIR__ . '/../db_connect.php';

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$partner_id = (int)($data['partner_id'] ?? 0);

if (!$partner_id) {
    echo json_encode(['status' => false, 'message' => 'Invalid Partner ID.']);
    exit();
}

// Fetch partner to check status and get name for prefix
$stmt = mysqli_prepare($conn, "SELECT partner_name, status FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $partner_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$partner = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$partner) {
    echo json_encode(['status' => false, 'message' => 'Partner not found.']);
    exit();
}

if ($partner['status'] !== 'pending') {
    echo json_encode(['status' => false, 'message' => 'Partner is not in pending status.']);
    exit();
}

// Generate unique API key matching add.php logic
$partner_name = $partner['partner_name'];
$name_prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $partner_name), 0, 8));
if (empty($name_prefix)) {
    $name_prefix = 'PARTNER';
}

do {
    $api_key = $name_prefix . '_' . strtoupper(bin2hex(random_bytes(20)));
    $ck = mysqli_query($conn, "SELECT id FROM partners WHERE api_key = '" . mysqli_real_escape_string($conn, $api_key) . "' LIMIT 1");
} while (mysqli_num_rows($ck) > 0);

$secret_key = bin2hex(random_bytes(24));

// Update partner in database
try {
    $upd_stmt = mysqli_prepare($conn, "UPDATE partners SET api_key = ?, secret_key = ?, status = 'active' WHERE id = ?");
    mysqli_stmt_bind_param($upd_stmt, 'ssi', $api_key, $secret_key, $partner_id);
    
    if (mysqli_stmt_execute($upd_stmt)) {
        echo json_encode([
            'status' => true,
            'message' => 'Partner approved and keys generated successfully!',
            'api_key' => $api_key,
            'secret_key' => $secret_key
        ]);
    } else {
        echo json_encode(['status' => false, 'message' => 'Failed to update partner: ' . mysqli_error($conn)]);
    }
    mysqli_stmt_close($upd_stmt);
} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

mysqli_close($conn);
exit();
