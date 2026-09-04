<?php
// Enable CORS for React Admin Dashboard
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

// Parse JSON input or Form POST
$inputJSON = file_get_contents('php://input');
$inputData = json_decode($inputJSON, true) ?? [];
$params = array_merge($_GET, $_POST, $inputData);

$action = $params['action'] ?? 'get_all_bookings';

// 1. GET ALL BOOKINGS (With customer, agent, and driver details)
if ($action === 'get_all_bookings') {
    $limit = isset($params['limit']) ? intval($params['limit']) : 500;
    if ($limit <= 0 || $limit > 1000) $limit = 500;

    $sql = "SELECT 
                b.id,
                b.booking_id,
                b.invoice_no,
                b.trip_type,
                b.car_type,
                b.from_address,
                b.to_address,
                b.distance,
                b.date,
                b.time,
                b.return_date,
                b.return_time,
                b.booked_at,
                b.starting_km,
                b.closing_km,
                b.running_km,
                b.starting_time,
                b.closing_time,
                b.starting_date,
                b.closing_date,
                b.mobile,
                b.customer_number,
                b.base_charge,
                b.driver_ta,
                b.driver_allowance,
                b.toll_charge,
                b.parking_charge,
                b.permit_charge,
                b.total_amount,
                b.vendor_amount,
                b.agni_amount,
                b.paid_amount,
                b.remaining_balance,
                b.collection_status,
                b.driver_id,
                b.booking_status,
                b.agent_commission,
                b.vehicle_id,
                b.vender_id,
                b.payment_status,
                b.payment_type,
                b.booker_id,
                b.gst,
                u.name AS customer_name,
                u.email AS customer_email,
                u.agency_name,
                u.accountType AS user_account_type,
                d.full_name AS driver_name,
                d.phone_number AS driver_phone,
                d.rc_no AS vehicle_rc
            FROM bookings b
            LEFT JOIN users u ON (b.mobile = u.phone_number OR (b.customer_number != '' AND b.customer_number = u.phone_number))
            LEFT JOIN drivers d ON (b.driver_id = d.phone_number OR b.driver_id = d.driver_id)
            ORDER BY b.id DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Query prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = [
            'id' => (int)$row['id'],
            'booking_id' => $row['booking_id'],
            'invoice_no' => $row['invoice_no'] ?: (string)$row['id'],
            'trip_type' => $row['trip_type'] ?: 'One-way',
            'car_type' => $row['car_type'] ?: 'Sedan',
            'from_address' => $row['from_address'] ?: '',
            'to_address' => $row['to_address'] ?: '',
            'distance' => floatval($row['distance'] ?? 0),
            'date' => $row['date'] ?: '',
            'time' => $row['time'] ?: '',
            'return_date' => $row['return_date'] ?: '',
            'return_time' => $row['return_time'] ?: '',
            'booked_at' => $row['booked_at'] ?: '',
            'starting_km' => $row['starting_km'] !== null ? (int)$row['starting_km'] : null,
            'closing_km' => $row['closing_km'] !== null ? (int)$row['closing_km'] : null,
            'running_km' => $row['running_km'] !== null ? (int)$row['running_km'] : null,
            'starting_time' => $row['starting_time'] ?: '',
            'closing_time' => $row['closing_time'] ?: '',
            'starting_date' => $row['starting_date'] ?: '',
            'closing_date' => $row['closing_date'] ?: '',
            'customer_name' => $row['customer_name'] ?: 'Customer',
            'customer_phone' => $row['customer_number'] ?: $row['mobile'] ?: '',
            'customer_email' => $row['customer_email'] ?: '',
            'agency_name' => $row['agency_name'] ?: '',
            'user_account_type' => $row['user_account_type'] ?: 'customer',
            'driver_id' => $row['driver_id'] ?: '',
            'driver_name' => $row['driver_name'] ?: ($row['driver_id'] ? 'Driver ' . $row['driver_id'] : 'Unassigned'),
            'driver_phone' => $row['driver_phone'] ?: '',
            'vehicle_id' => $row['vehicle_id'] ?: $row['vehicle_rc'] ?: '',
            'base_charge' => floatval($row['base_charge'] ?? 0),
            'driver_ta' => floatval($row['driver_ta'] ?? 0),
            'driver_allowance' => floatval($row['driver_allowance'] ?? 0),
            'toll_charge' => floatval($row['toll_charge'] ?? 0),
            'parking_charge' => floatval($row['parking_charge'] ?? 0),
            'permit_charge' => floatval($row['permit_charge'] ?? 0),
            'total_amount' => floatval($row['total_amount'] ?? 0),
            'vendor_amount' => floatval($row['vendor_amount'] ?? 0),
            'agni_amount' => floatval($row['agni_amount'] ?? 0),
            'paid_amount' => floatval($row['paid_amount'] ?? 0),
            'remaining_balance' => floatval($row['remaining_balance'] ?? 0),
            'booking_status' => $row['booking_status'] ?: 'Pending',
            'payment_status' => $row['payment_status'] ?: 'Pending',
            'payment_type' => $row['payment_type'] ?: '',
            'agent_commission' => floatval($row['agent_commission'] ?? 0),
            'gst' => $row['gst'] === 'true' || $row['gst'] === '1'
        ];
    }
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "total" => count($bookings),
        "bookings" => $bookings
    ]);
    exit;
}

// 2. GET BOOKING SUMMARY STATS
if ($action === 'get_booking_stats') {
    $statsSql = "SELECT 
                    COUNT(*) AS total_bookings,
                    SUM(CASE WHEN LOWER(booking_status) = 'completed' THEN 1 ELSE 0 END) AS completed_count,
                    SUM(CASE WHEN LOWER(booking_status) IN ('pending', 'confirmed', 'in-transit', 'driver assigned') THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN LOWER(booking_status) = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count,
                    COALESCE(SUM(total_amount), 0) AS total_revenue
                 FROM bookings";
    $res = $conn->query($statsSql);
    $stats = $res ? $res->fetch_assoc() : [];

    echo json_encode([
        "status" => "success",
        "stats" => [
            "total_bookings" => (int)($stats['total_bookings'] ?? 0),
            "completed_count" => (int)($stats['completed_count'] ?? 0),
            "active_count" => (int)($stats['active_count'] ?? 0),
            "cancelled_count" => (int)($stats['cancelled_count'] ?? 0),
            "total_revenue" => (float)($stats['total_revenue'] ?? 0)
        ]
    ]);
    exit;
}

// 3. DELETE SINGLE BOOKING
if ($action === 'delete_single') {
    $bookingId = isset($params['id']) ? intval($params['id']) : 0;
    if ($bookingId <= 0) {
        echo json_encode(["status" => "error", "message" => "Valid booking ID is required."]);
        exit;
    }

    $delStmt = $conn->prepare("DELETE FROM bookings WHERE id = ?");
    $delStmt->bind_param("i", $bookingId);
    if ($delStmt->execute()) {
        $affected = $delStmt->affected_rows;
        $delStmt->close();
        if ($affected > 0) {
            echo json_encode(["status" => "success", "message" => "Booking #{$bookingId} deleted successfully."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Booking #{$bookingId} not found."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete booking: " . $conn->error]);
    }
    exit;
}

// 4. SAFE DELETE ALL BOOKINGS (With Automated Timestamped Backup)
if ($action === 'delete_all_bookings') {
    // 1. Create a timestamped backup table first
    $timestamp = date('Ymd_His');
    $backupTableName = "bookings_backup_" . $timestamp;

    $backupSql = "CREATE TABLE `{$backupTableName}` AS SELECT * FROM `bookings`";
    if (!$conn->query($backupSql)) {
        echo json_encode([
            "status" => "error",
            "message" => "Safety backup creation failed before deletion: " . $conn->error
        ]);
        exit;
    }

    // Check backup count
    $countRes = $conn->query("SELECT COUNT(*) as cnt FROM `{$backupTableName}`");
    $backedUpCount = 0;
    if ($countRes && $row = $countRes->fetch_assoc()) {
        $backedUpCount = (int)$row['cnt'];
    }

    // 2. Truncate the bookings table and reset auto-increment
    $truncateSql = "TRUNCATE TABLE `bookings`";
    if ($conn->query($truncateSql)) {
        echo json_encode([
            "status" => "success",
            "message" => "All {$backedUpCount} bookings successfully removed from active database.",
            "backed_up_count" => $backedUpCount,
            "backup_table" => $backupTableName
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Failed to clear bookings table: " . $conn->error,
            "backup_table" => $backupTableName
        ]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
?>
