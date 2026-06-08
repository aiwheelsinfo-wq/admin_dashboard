<?php
/**
 * partner/generate_keys.php
 * AJAX handler — generate or regenerate API Key / Secret Key.
 * POST body: { partner_id, type: 'api_key'|'secret_key'|'both' }
 */
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

// ── Helper: generate unique API key ───────────────────────────────────────
function generate_api_key(string $prefix = 'AGNI'): string {
    return strtoupper($prefix) . '_' . strtoupper(bin2hex(random_bytes(20)));
}

function generate_secret_key(): string {
    return bin2hex(random_bytes(24));
}

$body       = json_decode(file_get_contents('php://input'), true) ?? [];
$partner_id = (int)($body['partner_id'] ?? 0);
$type       = trim($body['type'] ?? 'both');
$prefix     = trim($body['prefix'] ?? 'AGNI');

if (!$partner_id) {
    echo json_encode(['status' => false, 'message' => 'partner_id is required']);
    exit();
}

// Get partner name for prefix
$ps = mysqli_prepare($conn, "SELECT partner_name FROM partners WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($ps, 'i', $partner_id);
mysqli_stmt_execute($ps);
$pr = mysqli_stmt_get_result($ps);
$p  = mysqli_fetch_assoc($pr);
mysqli_stmt_close($ps);

if (!$p) {
    echo json_encode(['status' => false, 'message' => 'Partner not found']);
    exit();
}

// Create prefix from partner name: "Akbar Travels" → "AKBAR"
$name_prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $p['partner_name']), 0, 8));

$new_api_key    = null;
$new_secret_key = null;

if ($type === 'api_key' || $type === 'both') {
    // Ensure uniqueness
    do {
        $new_api_key = generate_api_key($name_prefix);
        $ck = mysqli_query($conn, "SELECT id FROM partners WHERE api_key = '" . mysqli_real_escape_string($conn, $new_api_key) . "' LIMIT 1");
    } while (mysqli_num_rows($ck) > 0);
}

if ($type === 'secret_key' || $type === 'both') {
    $new_secret_key = generate_secret_key();
}

// Build update SQL
if ($type === 'both') {
    $stmt = mysqli_prepare($conn, "UPDATE partners SET api_key = ?, secret_key = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'ssi', $new_api_key, $new_secret_key, $partner_id);
} elseif ($type === 'api_key') {
    $stmt = mysqli_prepare($conn, "UPDATE partners SET api_key = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_api_key, $partner_id);
} else {
    $stmt = mysqli_prepare($conn, "UPDATE partners SET secret_key = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $new_secret_key, $partner_id);
}

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'status'     => true,
        'message'    => 'Keys generated successfully',
        'api_key'    => $new_api_key,
        'secret_key' => $new_secret_key,
    ]);
} else {
    echo json_encode(['status' => false, 'message' => 'Failed to update keys: ' . mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
