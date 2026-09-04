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

$action = $params['action'] ?? 'get_vendors';

// 1. GET VENDOR SUMMARY STATS
if ($action === 'get_vendor_stats') {
    $statsSql = "SELECT 
                    COUNT(DISTINCT v.vendor_phone) AS total_vendors,
                    (SELECT COUNT(*) FROM cars WHERE owner_id IS NOT NULL AND owner_id != '') AS total_vehicles,
                    (SELECT COUNT(DISTINCT driver_id) FROM driver_vendor_join_Table WHERE vendor_id IS NOT NULL AND vendor_id != '') AS total_drivers
                 FROM (
                    SELECT phone_number AS vendor_phone FROM vendors
                    UNION
                    SELECT phone_number FROM drivers WHERE userType = 'vendor'
                    UNION
                    SELECT DISTINCT vendor_id FROM driver_vendor_join_Table WHERE vendor_id IS NOT NULL AND vendor_id != ''
                    UNION
                    SELECT DISTINCT owner_id FROM cars WHERE owner_id IS NOT NULL AND owner_id != ''
                 ) v";
    $res = $conn->query($statsSql);
    $stats = $res ? $res->fetch_assoc() : [];

    echo json_encode([
        "status" => "success",
        "stats" => [
            "total_vendors" => (int)($stats['total_vendors'] ?? 0),
            "total_vehicles" => (int)($stats['total_vehicles'] ?? 0),
            "total_drivers" => (int)($stats['total_drivers'] ?? 0)
        ]
    ]);
    exit;
}

// 2. GET ALL VENDORS WITH DRIVER & VEHICLE COUNTS
if ($action === 'get_vendors') {
    $limit = isset($params['limit']) ? intval($params['limit']) : 500;
    if ($limit <= 0 || $limit > 1000) $limit = 500;

    $sql = "SELECT 
                v.vendor_phone,
                COALESCE(NULLIF(d.full_name, ''), NULLIF(vnd.full_name, ''), 'Transport Vendor') AS vendor_name,
                COALESCE(NULLIF(d.agency_name, ''), NULLIF(vnd.agency_name, ''), '') AS agency_name,
                COALESCE(NULLIF(d.driver_city, ''), NULLIF(vnd.driver_city, ''), '') AS city,
                COALESCE(NULLIF(d.email, ''), NULLIF(vnd.email, ''), '') AS email,
                COALESCE(NULLIF(d.status, ''), NULLIF(vnd.status, ''), 'active') AS status,
                COALESCE(d.created_at, vnd.created_at) AS created_at,
                (SELECT COUNT(DISTINCT j.driver_id) FROM driver_vendor_join_Table j WHERE j.vendor_id = v.vendor_phone) AS driver_count,
                (SELECT COUNT(*) FROM cars c WHERE c.owner_id = v.vendor_phone) AS vehicle_count
            FROM (
                SELECT phone_number AS vendor_phone FROM vendors
                UNION
                SELECT phone_number FROM drivers WHERE userType = 'vendor'
                UNION
                SELECT DISTINCT vendor_id FROM driver_vendor_join_Table WHERE vendor_id IS NOT NULL AND vendor_id != ''
                UNION
                SELECT DISTINCT owner_id FROM cars WHERE owner_id IS NOT NULL AND owner_id != ''
            ) v
            LEFT JOIN drivers d ON v.vendor_phone = d.phone_number
            LEFT JOIN vendors vnd ON v.vendor_phone = vnd.phone_number
            ORDER BY (driver_count + vehicle_count) DESC, v.vendor_phone ASC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Query prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $vendors = [];
    while ($row = $result->fetch_assoc()) {
        $vendors[] = [
            'vendor_phone' => $row['vendor_phone'],
            'vendor_name' => trim($row['vendor_name']),
            'agency_name' => trim($row['agency_name']),
            'city' => trim($row['city']),
            'email' => trim($row['email']),
            'status' => $row['status'] ?: 'active',
            'created_at' => $row['created_at'],
            'driver_count' => (int)$row['driver_count'],
            'vehicle_count' => (int)$row['vehicle_count']
        ];
    }
    $stmt->close();

    echo json_encode([
        "status" => "success",
        "total" => count($vendors),
        "vendors" => $vendors
    ]);
    exit;
}

// 3. GET SPECIFIC VENDOR DETAILS (Full profile + Attached Drivers + Attached Vehicles)
if ($action === 'get_vendor_details') {
    $vendor_phone = trim($params['vendor_phone'] ?? '');

    if (empty($vendor_phone)) {
        echo json_encode(["status" => "error", "message" => "Vendor phone number is required."]);
        exit;
    }

    // A. Vendor Profile
    $vendorSql = "SELECT 
                    COALESCE(NULLIF(d.full_name, ''), NULLIF(vnd.full_name, ''), 'Transport Vendor') AS vendor_name,
                    COALESCE(NULLIF(d.agency_name, ''), NULLIF(vnd.agency_name, ''), '') AS agency_name,
                    COALESCE(NULLIF(d.driver_city, ''), NULLIF(vnd.driver_city, ''), '') AS city,
                    COALESCE(NULLIF(d.email, ''), NULLIF(vnd.email, ''), '') AS email,
                    COALESCE(NULLIF(d.driver_address, ''), NULLIF(vnd.driver_address, ''), '') AS address,
                    COALESCE(NULLIF(d.status, ''), NULLIF(vnd.status, ''), 'active') AS status,
                    COALESCE(d.created_at, vnd.created_at) AS created_at
                  FROM (SELECT ? AS vendor_phone) v
                  LEFT JOIN drivers d ON v.vendor_phone = d.phone_number
                  LEFT JOIN vendors vnd ON v.vendor_phone = vnd.phone_number
                  LIMIT 1";
    $vStmt = $conn->prepare($vendorSql);
    $vStmt->bind_param("s", $vendor_phone);
    $vStmt->execute();
    $vendorProfile = $vStmt->get_result()->fetch_assoc() ?? [];
    $vStmt->close();

    $vendorProfile['vendor_phone'] = $vendor_phone;

    // B. Attached Drivers
    $driversSql = "SELECT 
                    d.driver_id,
                    d.full_name,
                    d.phone_number,
                    d.driver_city,
                    d.status,
                    d.license_no,
                    d.license_type,
                    d.rc_no,
                    d.created_at,
                    d.is_online
                   FROM driver_vendor_join_Table j
                   JOIN drivers d ON (j.driver_id = d.phone_number OR j.driver_id = d.driver_id)
                   WHERE j.vendor_id = ?
                   ORDER BY d.driver_id DESC";
    $dStmt = $conn->prepare($driversSql);
    $dStmt->bind_param("s", $vendor_phone);
    $dStmt->execute();
    $dResult = $dStmt->get_result();

    $drivers = [];
    $seenDrivers = [];
    while ($dRow = $dResult->fetch_assoc()) {
        $driverId = $dRow['driver_id'];
        if (!isset($seenDrivers[$driverId])) {
            $seenDrivers[$driverId] = true;
            $drivers[] = [
                'driver_id' => (int)$dRow['driver_id'],
                'full_name' => trim($dRow['full_name'] ?: 'Driver'),
                'phone_number' => $dRow['phone_number'],
                'driver_city' => trim($dRow['driver_city'] ?: ''),
                'status' => $dRow['status'] ?: 'active',
                'license_no' => $dRow['license_no'] ?: '—',
                'license_type' => $dRow['license_type'] ?: '',
                'created_at' => $dRow['created_at'],
                'is_online' => (bool)$dRow['is_online']
            ];
        }
    }
    $dStmt->close();

    // C. Attached Vehicles (Cars)
    $carsSql = "SELECT 
                    id,
                    vehicle_number,
                    vehicle_name,
                    vehicle_type,
                    fuel_type,
                    status,
                    rc_no,
                    rc_name,
                    insurance_number,
                    insurance_doe,
                    fitness_certificate_doe
                FROM cars
                WHERE owner_id = ?
                ORDER BY id DESC";
    $cStmt = $conn->prepare($carsSql);
    $cStmt->bind_param("s", $vendor_phone);
    $cStmt->execute();
    $cResult = $cStmt->get_result();

    $vehicles = [];
    while ($cRow = $cResult->fetch_assoc()) {
        $vehicles[] = [
            'id' => (int)$cRow['id'],
            'vehicle_number' => strtoupper(trim($cRow['vehicle_number'])),
            'vehicle_name' => trim($cRow['vehicle_name'] ?: 'Vehicle'),
            'vehicle_type' => strtoupper(trim($cRow['vehicle_type'] ?: 'Sedan')),
            'fuel_type' => trim($cRow['fuel_type'] ?: 'Petrol/Diesel'),
            'status' => $cRow['status'] ?: 'active',
            'rc_no' => $cRow['rc_no'] ?: '—',
            'rc_name' => trim($cRow['rc_name'] ?: ''),
            'insurance_number' => $cRow['insurance_number'] ?: '—',
            'insurance_doe' => $cRow['insurance_doe'] ?: '—',
            'fitness_certificate_doe' => $cRow['fitness_certificate_doe'] ?: '—'
        ];
    }
    $cStmt->close();

    echo json_encode([
        "status" => "success",
        "vendor" => $vendorProfile,
        "driver_count" => count($drivers),
        "vehicle_count" => count($vehicles),
        "drivers" => $drivers,
        "vehicles" => $vehicles
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action specified."]);
?>
