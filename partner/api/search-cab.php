<?php
/**
 * POST /partner/api/search-cab.php
 * Search for available cabs based on trip details.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Body (JSON):
 *   from_address  string  required
 *   to_address    string  required (not needed for Local-Duty)
 *   trip_type     string  One-way | Round-Trip | Local-taxi | Local-Duty
 *   date          string  YYYY-MM-DD
 *   time          string  HH:MM
 */

$_API_NAME = 'search-cab';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$from      = trim($body['from_address'] ?? '');
$to        = trim($body['to_address']   ?? '');
$trip_type = trim($body['trip_type']    ?? 'One-way');
$date      = trim($body['date']         ?? '');
$time      = trim($body['time']         ?? '');

if (empty($from)) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'from_address is required'], 'error');
    api_error('from_address is required', 400);
}

// Fetch active car types from DB
$cars_sql  = "SELECT DISTINCT vehicle_type FROM cars WHERE status = 'active' ORDER BY vehicle_type";
$cars_res  = mysqli_query($conn, $cars_sql);
$car_types = [];
while ($row = mysqli_fetch_assoc($cars_res)) {
    $car_types[] = $row['vehicle_type'];
}

$response = [
    'status'  => true,
    'message' => 'Available cab types found',
    'data'    => [
        'from_address' => $from,
        'to_address'   => $to,
        'trip_type'    => $trip_type,
        'date'         => $date,
        'time'         => $time,
        'available_car_types' => !empty($car_types)
            ? $car_types
            : ['Sedan', 'Ertiga', 'Innova', 'Crysta'],
        'note' => 'Call /get-fare with car_type to get pricing details.',
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
