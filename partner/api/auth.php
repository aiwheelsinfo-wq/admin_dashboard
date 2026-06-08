<?php
/**
 * partner/api/auth.php — Partner API Authentication Middleware
 * Include this at the TOP of every partner API endpoint.
 *
 * Reads headers:  X-API-Key, X-Secret-Key
 * Sets globals:   $partner (array with partner DB row)
 * On failure:     echoes JSON error and exits
 */

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Secret-Key");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Load DB ────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../db_connect.php';

// ── Helper: send JSON response and exit ────────────────────────────────────
function api_error(string $message, int $http_code = 401, string $log_status = 'error'): void {
    http_response_code($http_code);
    $response = json_encode(['status' => false, 'message' => $message]);
    // Log the failed request if partner_id is available globally
    global $partner, $conn, $_API_NAME;
    if (!empty($partner['id'])) {
        require_once __DIR__ . '/logger.php';
        log_api_request($partner['id'], $_API_NAME ?? 'unknown', [], ['status' => false, 'message' => $message], $log_status);
    }
    echo $response;
    exit();
}

// ── Read API credentials from headers ─────────────────────────────────────
$api_key    = trim($_SERVER['HTTP_X_API_KEY']    ?? '');
$secret_key = trim($_SERVER['HTTP_X_SECRET_KEY'] ?? '');

if (empty($api_key) || empty($secret_key)) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Missing API credentials. Provide X-API-Key and X-Secret-Key headers.']);
    exit();
}

// ── Query partner from DB ──────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT * FROM partners WHERE api_key = ? AND secret_key = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Internal server error (DB prepare failed).']);
    exit();
}
mysqli_stmt_bind_param($stmt, 'ss', $api_key, $secret_key);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$partner = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$partner) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Invalid API Key or Secret Key.']);
    exit();
}

// ── Check if partner is blocked ────────────────────────────────────────────
if ($partner['status'] === 'blocked') {
    http_response_code(403);
    require_once __DIR__ . '/logger.php';
    log_api_request($partner['id'], $_API_NAME ?? 'unknown', [], ['status' => false, 'message' => 'API Access Blocked'], 'blocked');
    echo json_encode(['status' => false, 'message' => 'API Access Blocked. Please contact support.']);
    exit();
}

// ── Run rate limiter ───────────────────────────────────────────────────────
require_once __DIR__ . '/rate_limiter.php';
check_rate_limit($partner, $conn);

// Auth passed — $partner is now available in the calling file.
