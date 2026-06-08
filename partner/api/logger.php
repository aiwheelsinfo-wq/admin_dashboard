<?php
/**
 * partner/api/logger.php
 * Logs every partner API request to partner_api_logs table.
 */

function log_api_request(
    int    $partner_id,
    string $api_name,
    array  $request_data,
    array  $response_data,
    string $status = 'success'
): void {
    global $conn;

    if (!$conn) return;

    $ip           = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? 'unknown';
    $ip           = substr($ip, 0, 45);
    $method       = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $request_json = json_encode($request_data,  JSON_UNESCAPED_UNICODE);
    $response_json= json_encode($response_data, JSON_UNESCAPED_UNICODE);

    $stmt = mysqli_prepare($conn,
        "INSERT INTO partner_api_logs (partner_id, api_name, method, request_data, response_data, ip_address, status)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) return;

    mysqli_stmt_bind_param($stmt, 'issssss',
        $partner_id, $api_name, $method,
        $request_json, $response_json,
        $ip, $status
    );
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
