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

// ── Per-KM rates by car type ───────────────────────────────────────────────
$base_rates = [
    'sedan'  => 13.0,
    'ertiga' => 16.0,
    'innova' => 19.0,
    'crysta' => 24.0,
];

$car_key = strtolower(str_replace(' ', '', $car_type));
$km_rate = $base_rates[$car_key] ?? 13.0;

// ── Effective distance based on trip type ─────────────────────────────────
// Round-Trip  → both ways, minimum 250 km
// One-way     → one direction, minimum 130 km
// Local-taxi  → 8hr/80km package, minimum 80 km
// Local-Duty  → 10hr/100km package, minimum 100 km

$effective_km = $distance_km;
$trip_note    = '';

switch ($trip_type) {
    case 'Round-Trip':
        $effective_km = $distance_km * 2;          // charge both ways
        $min_km       = 250;
        if ($effective_km < $min_km) $effective_km = $min_km;
        $trip_note = "Round-Trip: charged for " . round($effective_km, 1) . " km (both ways, min 250 km).";
        break;

    case 'Local-taxi':
        $effective_km = max($distance_km, 80);
        $trip_note = "Local Taxi: 8hr/80km package. Charged for " . round($effective_km, 1) . " km.";
        break;

    case 'Local-Duty':
        $effective_km = max($distance_km, 100);
        $trip_note = "Local Duty: 10hr/100km package. Charged for " . round($effective_km, 1) . " km.";
        break;

    default: // One-way
        $min_km       = 130;
        if ($effective_km < $min_km) $effective_km = $min_km;
        $trip_note = "One-way: charged for " . round($effective_km, 1) . " km (min 130 km).";
        break;
}

// ── Charges ────────────────────────────────────────────────────────────────
$base             = $effective_km * $km_rate;
$driver_allowance = $effective_km > 300 ? 500 : ($effective_km > 200 ? 400 : 300);
$toll             = 0;  // actual toll decided by driver — shown as 0 estimate
$gst              = $base * 0.05;
$total            = $base + ($effective_km * 2.25) + $driver_allowance + $gst + $toll;

$response = [
    'status'  => true,
    'message' => 'Fare calculated successfully',
    'data'    => [
        'from_address'       => $from,
        'to_address'         => $to,
        'trip_type'          => $trip_type,
        'car_type'           => $car_type,
        'distance_km'        => round($distance_km, 2),
        'effective_km'       => round($effective_km, 2),
        'km_rate'            => $km_rate,
        'base_charge'        => round($base, 2),
        'driver_allowance'   => $driver_allowance,
        'toll_charge'        => $toll,
        'gst_5_percent'      => round($gst, 2),
        'estimated_total'    => round($total, 2),
        'currency'           => 'INR',
        'note'               => $trip_note . ' Toll charges subject to actual road tolls.',
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
