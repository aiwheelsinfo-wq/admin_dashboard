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

// Normalize car type for matching in database
$car_key = strtolower(str_replace([' ', '-'], '', $car_type));
$car_type_normalized = 'Sedan';
if (strpos($car_key, 'sedan') !== false || strpos($car_key, 'dzire') !== false) {
    $car_type_normalized = 'Sedan';
} elseif (strpos($car_key, 'ertiga') !== false) {
    $car_type_normalized = 'Ertiga';
} elseif (strpos($car_key, 'crysta') !== false) {
    $car_type_normalized = 'Crysta';
} elseif (strpos($car_key, 'innova') !== false) {
    $car_type_normalized = 'Innova';
}

if (empty($from) || empty($car_type) || ($trip_type !== 'Local-Duty' && $distance_km <= 0)) {
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'from_address, car_type, and distance_km are required'], 'error');
    api_error('from_address, car_type (e.g. Sedan), and distance_km are required', 400);
}

// ── Helper: Geocode Address using Google API ────────────────────────────────
function get_geocode_coords($address) {
    $apiKey = 'AIzaSyBz4vqQWuT-s_3UEWk6pnSMxSIt7QOZEqk';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($address) . "&key=" . $apiKey;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!$response) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (isset($data['status']) && $data['status'] === 'OK' && isset($data['results'][0]['geometry']['location'])) {
        return [
            'lat' => (float)$data['results'][0]['geometry']['location']['lat'],
            'lng' => (float)$data['results'][0]['geometry']['location']['lng']
        ];
    }
    return null;
}

// ── Helper: Check Coordinate Bounding Box ──────────────────────────────────
function is_within_bounds($lat, $lng, $minLat, $maxLat, $minLng, $maxLng) {
    if ($lat === null || $lng === null || $minLat === null || $maxLat === null || $minLng === null || $maxLng === null) {
        return false;
    }
    return ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng);
}

$effective_km = $distance_km;
$trip_note    = '';
$is_final_fare   = true;
$pending_charges = [];
$km_rate = null;
$base = 0.0;
$driver_allowance = 0.0;
$toll_charge = 0.0;
$gst = 0.0;
$total = 0.0;

switch ($trip_type) {
    case 'One-way':
        $from_coords = get_geocode_coords($from);
        $to_coords   = get_geocode_coords($to);

        $fromLat = $from_coords ? $from_coords['lat'] : null;
        $fromLon = $from_coords ? $from_coords['lng'] : null;
        $toLat   = $to_coords ? $to_coords['lat'] : null;
        $toLon   = $to_coords ? $to_coords['lng'] : null;

        // Check if this is a special route
        $is_special = false;
        if ($fromLat !== null && $fromLon !== null && $toLat !== null && $toLon !== null) {
            $sql_spec = "SELECT tripType FROM special_routes
                    WHERE ? BETWEEN minLat_from AND maxLat_from
                      AND ? BETWEEN minLon_from AND maxLon_from
                      AND ? BETWEEN minLat_to AND maxLat_to
                      AND ? BETWEEN minLon_to AND maxLon_to
                    LIMIT 1";
            $stmt_spec = mysqli_prepare($conn, $sql_spec);
            if ($stmt_spec) {
                mysqli_stmt_bind_param($stmt_spec, "dddd", $fromLat, $fromLon, $toLat, $toLon);
                mysqli_stmt_execute($stmt_spec);
                mysqli_stmt_store_result($stmt_spec);
                if (mysqli_stmt_num_rows($stmt_spec) > 0) {
                    $is_special = true;
                }
                mysqli_stmt_close($stmt_spec);
            }
        }

        if (!$is_special && $distance_km <= 75) {
            log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Distance is less than 75km. Please choose Local Taxi or Local Duty.'], 'error');
            api_error('Distance is less than 75km. Please choose Local Taxi or Local Duty.', 400);
        }

        // Query tripCostTable with bounding box check
        $sql = "SELECT * FROM tripCostTable WHERE tripType = 'One-way' AND carType = ?";
        $stmt_cost = mysqli_prepare($conn, $sql);
        $final_row = null;
        if ($stmt_cost) {
            mysqli_stmt_bind_param($stmt_cost, 's', $car_type_normalized);
            mysqli_stmt_execute($stmt_cost);
            $res_cost = mysqli_stmt_get_result($stmt_cost);
            
            $fallback_row = null;
            while ($row = mysqli_fetch_assoc($res_cost)) {
                $hasCoords = ($row['minLat'] !== null && $row['maxLat'] !== null && $row['minLon'] !== null && $row['maxLon'] !== null);
                $match = false;
                if ($hasCoords) {
                    if (($fromLat !== null && $fromLon !== null && is_within_bounds($fromLat, $fromLon, $row['minLat'], $row['maxLat'], $row['minLon'], $row['maxLon']))
                        ||
                        ($toLat !== null && $toLon !== null && is_within_bounds($toLat, $toLon, $row['minLat'], $row['maxLat'], $row['minLon'], $row['maxLon']))) {
                        $match = true;
                    }
                }
                if ($match) {
                    $final_row = $row;
                    break;
                }
                if (!$hasCoords && !$fallback_row) {
                    $fallback_row = $row;
                }
            }
            mysqli_stmt_close($stmt_cost);
            if (!$final_row) {
                $final_row = $fallback_row;
            }
        }

        if ($final_row) {
            $km_rate = (float)$final_row['kmRate'];
        } else {
            // Default static base rates
            $base_rates = [
                'sedan'  => 13.0,
                'ertiga' => 16.0,
                'innova' => 19.0,
                'crysta' => 24.0,
            ];
            $car_key = strtolower(str_replace(' ', '', $car_type_normalized));
            $km_rate = $base_rates[$car_key] ?? 13.0;
        }

        $driver_allowance = ($distance_km < 200) ? 300 : 400;
        $toll_charge = $distance_km * 2.25;
        $base = $km_rate * $distance_km;
        $gst = $base * 0.05;
        $total = ($km_rate * $distance_km * 1.05) + $driver_allowance + $toll_charge;
        $trip_note = "One-way: dynamically calculated from trip cost table.";
        break;

    case 'Round-Trip':
        $sql = "SELECT * FROM tripCostTable WHERE tripType = 'Round-Trip' AND carType = ? LIMIT 1";
        $stmt_cost = mysqli_prepare($conn, $sql);
        $km_rate = null;
        $driver_allowance_db = null;
        if ($stmt_cost) {
            mysqli_stmt_bind_param($stmt_cost, 's', $car_type_normalized);
            mysqli_stmt_execute($stmt_cost);
            $res_cost = mysqli_stmt_get_result($stmt_cost);
            if ($row = mysqli_fetch_assoc($res_cost)) {
                $km_rate = (float)$row['kmRate'];
                $driver_allowance_db = (float)$row['driver_allowance'];
            }
            mysqli_stmt_close($stmt_cost);
        }

        if ($km_rate === null) {
            $base_rates = [
                'sedan'  => 13.0,
                'ertiga' => 16.0,
                'innova' => 19.0,
                'crysta' => 24.0,
            ];
            $car_key = strtolower(str_replace(' ', '', $car_type_normalized));
            $km_rate = $base_rates[$car_key] ?? 13.0;
        }

        $effective_km = $distance_km * 2;
        $min_km = 250;
        if ($effective_km < $min_km) $effective_km = $min_km;

        if ($driver_allowance_db !== null && $driver_allowance_db > 0) {
            $driver_allowance = $driver_allowance_db;
        } else {
            $driver_allowance = $effective_km > 300 ? 500 : ($effective_km > 200 ? 400 : 300);
        }

        $base = $effective_km * $km_rate;
        $is_final_fare = false;
        $pending_charges = [
            'parking_charge' => 'Decided by driver after trip',
            'toll_charge'    => 'Decided by driver after trip',
            'permit_charge'  => 'Decided by driver after trip',
            'driver_allowance' => 'Decided by driver after trip',
            'other_charges'  => 'Any additional trip expenses',
        ];
        $trip_note = "Round-Trip base fare (" . round($effective_km, 1) . " km). FINAL amount will be set by admin after trip completion based on actual charges.";
        break;

    case 'Local-taxi':
        if ($distance_km > 80) {
            log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Distance exceeds 80km. Please use our One-Way service for long trips.'], 'error');
            api_error('Distance exceeds 80km. Please use our One-Way service for long trips.', 400);
        }

        $sql = "SELECT * FROM local_texi_fare_chart ORDER BY km ASC";
        $res_fares = mysqli_query($conn, $sql);
        $selected_fare = null;
        if ($res_fares && mysqli_num_rows($res_fares) > 0) {
            while ($row = mysqli_fetch_assoc($res_fares)) {
                $kmLimit = (float)$row['km'];
                if ($distance_km <= $kmLimit) {
                    $selected_fare = $row;
                    break;
                }
            }
            if (!$selected_fare) {
                mysqli_data_seek($res_fares, mysqli_num_rows($res_fares) - 1);
                $selected_fare = mysqli_fetch_assoc($res_fares);
            }
        }

        if ($selected_fare) {
            $car_key = strtolower(str_replace(' ', '', $car_type_normalized));
            $col_name = 'Sedan';
            if (strpos($car_key, 'hatchback') !== false) {
                $col_name = 'Hatchback';
            } elseif (strpos($car_key, 'ertiga') !== false) {
                $col_name = 'Ertiga';
            } else {
                $col_name = 'Sedan';
            }
            $total = (float)$selected_fare[$col_name];
            $km_limit = $selected_fare['km'];
        } else {
            $total = 1200.0;
            $km_limit = 80;
        }

        $km_rate = 0.0;
        $base = $total;
        $driver_allowance = 0.0;
        $toll_charge = 0.0;
        $gst = 0.0;
        $is_final_fare = true;
        $trip_note = "Local Taxi: slab-based package for up to " . $km_limit . " km.";
        break;

    case 'Local-Duty':
        $sql = "SELECT * FROM tripCostTable WHERE tripType = 'Local-Duty' AND carType = ? LIMIT 1";
        $stmt_cost = mysqli_prepare($conn, $sql);
        $baseAmount = null;
        $packageKm = 100;
        $packageHours = 10;
        $extraKMAmount = 0;
        $extraHoursAmount = 0;
        if ($stmt_cost) {
            mysqli_stmt_bind_param($stmt_cost, 's', $car_type_normalized);
            mysqli_stmt_execute($stmt_cost);
            $res_cost = mysqli_stmt_get_result($stmt_cost);
            if ($row = mysqli_fetch_assoc($res_cost)) {
                $baseAmount = (float)$row['baseAmount'];
                $packageKm = (float)$row['packageKm'];
                $packageHours = (float)$row['packageHours'];
                $extraKMAmount = (float)$row['extraKMAmount'];
                $extraHoursAmount = (float)$row['extraHoursAmount'];
            }
            mysqli_stmt_close($stmt_cost);
        }

        if ($baseAmount === null) {
            $base_amounts = [
                'sedan'  => 1800.0,
                'ertiga' => 2200.0,
                'innova' => 2800.0,
                'crysta' => 3500.0,
            ];
            $car_key = strtolower(str_replace(' ', '', $car_type_normalized));
            $baseAmount = $base_amounts[$car_key] ?? 1800.0;
        }

        $km_rate = 0.0;
        $base = $baseAmount;
        $driver_allowance = 0.0;
        $toll_charge = 0.0;
        $gst = 0.0;
        $total = $baseAmount;
        $is_final_fare = true;
        $trip_note = "Local Duty: " . $packageHours . " hours / " . $packageKm . " km package. Extra KM: ₹" . $extraKMAmount . "/km, Extra Hr: ₹" . $extraHoursAmount . "/hr.";
        break;

    default:
        log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>'Invalid trip type.'], 'error');
        api_error('Invalid trip type.', 400);
}

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
        'driver_allowance'   => $is_final_fare ? $driver_allowance : null,
        'toll_charge'        => $is_final_fare ? $toll_charge : null,
        'gst_5_percent'      => $is_final_fare ? round($gst, 2) : null,
        'estimated_total'    => $is_final_fare ? round($total, 2) : null,
        'is_final_fare'      => $is_final_fare,
        'pending_charges'    => empty($pending_charges) ? null : $pending_charges,
        'currency'           => 'INR',
        'note'               => $trip_note,
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
