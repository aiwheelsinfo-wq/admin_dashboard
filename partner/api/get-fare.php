<?php
/**
 * POST /partner/api/get-fare.php
 * Returns estimated fare for a trip.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Body (JSON):
 *   from_address  string  required
 *   to_address    string  required
 *   trip_type     string  One-way | Round-Trip | Local-taxi | Local-Duty
 *   car_type      string  Sedan | Ertiga | Innova | Crysta
 *   distance_km   float   required (client must provide Google Maps distance)
 */

$_API_NAME = 'get-fare';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$from        = trim($body['from_address'] ?? '');
$to          = trim($body['to_address']   ?? '');
$trip_type   = trim($body['trip_type']    ?? 'One-way');
$car_type    = trim($body['car_type']     ?? '');
$distance_km = (float)($body['distance_km'] ?? 0);

if (empty($from) || empty($car_type) || $distance_km <= 0) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'from_address, car_type, and distance_km are required'], 'error');
    api_error('from_address, car_type (e.g. Sedan), and distance_km are required', 400);
}

// Fare calculation logic (matching the dashboard's modal price formula)
// Base rates per km per car type (approximate defaults)
$base_rates = [
    'sedan'   => 13.0,
    'ertiga'  => 16.0,
    'innova'  => 19.0,
    'crysta'  => 24.0,
];

$car_key  = strtolower(str_replace(' ', '', $car_type));
$km_rate  = $base_rates[$car_key] ?? 13.0;

// Driver allowance
$driver_allowance = $distance_km > 200 ? 400 : 300;

// Toll (estimate)
$toll = 0;

// GST 5%
$base    = $distance_km * $km_rate;
$gst     = $base * 0.05;
$total   = $base + ($distance_km * 2.25) + $driver_allowance + $gst + $toll;

$response = [
    'status'  => true,
    'message' => 'Fare calculated successfully',
    'data'    => [
        'from_address'     => $from,
        'to_address'       => $to,
        'trip_type'        => $trip_type,
        'car_type'         => $car_type,
        'distance_km'      => round($distance_km, 2),
        'km_rate'          => $km_rate,
        'base_charge'      => round($base, 2),
        'driver_allowance' => $driver_allowance,
        'toll_charge'      => $toll,
        'gst_5_percent'    => round($gst, 2),
        'estimated_total'  => round($total, 2),
        'currency'         => 'INR',
        'note'             => 'Final fare may vary. Toll charges subject to actual road tolls.',
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
