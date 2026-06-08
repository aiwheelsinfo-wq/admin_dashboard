<?php
/**
 * partner/api/rate_limiter.php
 * Checks per-minute and per-day request limits for a partner.
 * Uses partner_api_limits table with upsert pattern.
 */

function check_rate_limit(array $partner, $conn): void {
    $partner_id = (int)$partner['id'];
    $limit_min  = (int)$partner['rate_limit_per_minute'];
    $limit_day  = (int)$partner['rate_limit_per_day'];

    $now        = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $minute_key = $now->format('YmdHi'); // e.g. 202606081435
    $day_key    = $now->format('Ymd');   // e.g. 20260608

    // Fetch current counters
    $stmt = mysqli_prepare($conn, "SELECT minute_key, day_key, minute_count, day_count FROM partner_api_limits WHERE partner_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $partner_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row    = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $current_minute_count = 0;
    $current_day_count    = 0;

    if ($row) {
        // Reset minute counter if minute has changed
        $current_minute_count = ($row['minute_key'] === $minute_key) ? (int)$row['minute_count'] : 0;
        // Reset day counter if day has changed
        $current_day_count    = ($row['day_key'] === $day_key) ? (int)$row['day_count'] : 0;
    }

    // Check limits BEFORE incrementing
    if ($current_minute_count >= $limit_min) {
        global $_API_NAME;
        require_once __DIR__ . '/logger.php';
        log_api_request($partner_id, $_API_NAME ?? 'unknown', [], ['status' => false, 'message' => 'Rate Limit Exceeded'], 'rate_limited');
        http_response_code(429);
        echo json_encode([
            'status'  => false,
            'message' => "Rate Limit Exceeded. You've reached {$limit_min} requests/minute. Retry after 60 seconds.",
        ]);
        exit();
    }

    if ($current_day_count >= $limit_day) {
        global $_API_NAME;
        require_once __DIR__ . '/logger.php';
        log_api_request($partner_id, $_API_NAME ?? 'unknown', [], ['status' => false, 'message' => 'Daily Rate Limit Exceeded'], 'rate_limited');
        http_response_code(429);
        echo json_encode([
            'status'  => false,
            'message' => "Daily Rate Limit Exceeded. You've used {$limit_day} requests today. Limit resets at midnight.",
        ]);
        exit();
    }

    // Upsert counters
    $new_minute_count = $current_minute_count + 1;
    $new_day_count    = $current_day_count    + 1;

    if ($row) {
        $upd = mysqli_prepare($conn,
            "UPDATE partner_api_limits SET minute_key=?, minute_count=?, day_key=?, day_count=?, updated_at=NOW() WHERE partner_id=?"
        );
        mysqli_stmt_bind_param($upd, 'sisii', $minute_key, $new_minute_count, $day_key, $new_day_count, $partner_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    } else {
        $ins = mysqli_prepare($conn,
            "INSERT INTO partner_api_limits (partner_id, minute_key, minute_count, day_key, day_count) VALUES (?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($ins, 'isisi', $partner_id, $minute_key, $new_minute_count, $day_key, $new_day_count);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
    }
}
