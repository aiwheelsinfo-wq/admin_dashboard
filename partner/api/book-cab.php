<?php
/**
 * POST /partner/api/book-cab.php
 * Creates a booking via partner API.
 * Booking is stored in main bookings table with source='partner'.
 *
 * Headers: X-API-Key, X-Secret-Key
 * Body (JSON):
 *   from_address         string  required
 *   to_address           string  required
 *   trip_type            string  One-way | Round-Trip | Local-taxi | Local-Duty
 *   car_type             string  required
 *   date                 string  YYYY-MM-DD required
 *   time                 string  HH:MM required
 *   user_name            string  required
 *   user_mobile          string  required
 *   user_email           string  optional
 *   distance_km          float   optional
 *   total_amount         float   optional
 *   return_date          string  YYYY-MM-DD (for Round-Trip)
 *   return_time          string  HH:MM (for Round-Trip)
 *   partner_booking_ref  string  Partner's own reference ID
 */

$_API_NAME = 'book-cab';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Required fields
$required = ['from_address','to_address','trip_type','car_type','date','time','user_name','user_mobile'];
foreach ($required as $field) {
    if (empty(trim($body[$field] ?? ''))) {
        $msg = "Field '{$field}' is required.";
        log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$msg], 'error');
        api_error($msg, 400);
    }
}

// Sanitize
$from        = mysqli_real_escape_string($conn, trim($body['from_address']));
$to          = mysqli_real_escape_string($conn, trim($body['to_address']));
$trip_type   = mysqli_real_escape_string($conn, trim($body['trip_type']));
$car_type    = mysqli_real_escape_string($conn, trim($body['car_type']));
$date        = mysqli_real_escape_string($conn, trim($body['date']));
$time        = mysqli_real_escape_string($conn, trim($body['time']));
$user_name   = mysqli_real_escape_string($conn, trim($body['user_name']));
$user_mobile = mysqli_real_escape_string($conn, trim($body['user_mobile']));
$user_email  = mysqli_real_escape_string($conn, trim($body['user_email']  ?? ''));
$distance    = (float)($body['distance_km']   ?? 0);
$amount      = (float)($body['total_amount']  ?? 0);
$ret_date    = mysqli_real_escape_string($conn, trim($body['return_date'] ?? ''));
$ret_time    = mysqli_real_escape_string($conn, trim($body['return_time'] ?? ''));
$partner_ref = mysqli_real_escape_string($conn, trim($body['partner_booking_ref'] ?? ''));

// Generate booking ID
$booking_id = 'PB' . strtoupper(substr(md5(uniqid($partner['api_key'], true)), 0, 10));

// ── Ensure user exists in users table so they show up in admin joins ──────
$user_check = mysqli_prepare($conn, "SELECT phone_number FROM users WHERE phone_number = ? LIMIT 1");
if ($user_check) {
    mysqli_stmt_bind_param($user_check, 's', $user_mobile);
    mysqli_stmt_execute($user_check);
    mysqli_stmt_store_result($user_check);
    $user_exists = mysqli_stmt_num_rows($user_check) > 0;
    mysqli_stmt_close($user_check);

    if (!$user_exists) {
        $user_inst = mysqli_prepare($conn, "INSERT INTO users (phone_number, name, email, created_at) VALUES (?, ?, ?, NOW())");
        if ($user_inst) {
            mysqli_stmt_bind_param($user_inst, 'sss', $user_mobile, $user_name, $user_email);
            mysqli_stmt_execute($user_inst);
            mysqli_stmt_close($user_inst);
        }
    } else {
        $user_upd = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ? WHERE phone_number = ?");
        if ($user_upd) {
            mysqli_stmt_bind_param($user_upd, 'sss', $user_name, $user_email, $user_mobile);
            mysqli_stmt_execute($user_upd);
            mysqli_stmt_close($user_upd);
        }
    }
}

// Generate OTP
$otp = (string)rand(1000, 9999);

// Check if bookings table has the columns we need; use safe INSERT
// We add 'source' as 'partner' and track booker_id as partner mobile
$insert_sql = "INSERT INTO bookings
    (booking_id, from_address, to_address, trip_type, car_type,
     date, time, booker_id, mobile, otp,
     distance, total_amount, return_date, return_time,
     vendor_amount, agni_amount,
     booking_status, booked_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())";

$stmt = mysqli_prepare($conn, $insert_sql);

if (!$stmt) {
    $err = mysqli_error($conn);
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$err], 'error');
    api_error('Booking creation failed: ' . $err, 500);
}

// ── Helper: Geocode Address using Google API ────────────────────────────────
if (!function_exists('get_geocode_coords')) {
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
}

// ── Helper: Check Coordinate Bounding Box ──────────────────────────────────
if (!function_exists('is_within_bounds')) {
    function is_within_bounds($lat, $lng, $minLat, $maxLat, $minLng, $maxLng) {
        if ($lat === null || $lng === null || $minLat === null || $maxLat === null || $minLng === null || $maxLng === null) {
            return false;
        }
        return ($lat >= $minLat && $lat <= $maxLat && $lng >= $minLng && $lng <= $maxLng);
    }
}

// ── Calculate dynamic revenue splits ───────────────────────────────────────
$vendor_amount = $amount;
$agni_amount = 0.0;

if ($trip_type === 'One-way') {
    $from_coords = get_geocode_coords($from);
    $to_coords   = get_geocode_coords($to);

    $fromLat = $from_coords ? $from_coords['lat'] : null;
    $fromLon = $from_coords ? $from_coords['lng'] : null;
    $toLat   = $to_coords ? $to_coords['lat'] : null;
    $toLon   = $to_coords ? $to_coords['lng'] : null;

    // Query tripCostTable with bounding box check
    $sql_cost = "SELECT * FROM tripCostTable WHERE tripType = 'One-way' AND carType = ?";
    $stmt_cost = mysqli_prepare($conn, $sql_cost);
    $final_row = null;
    if ($stmt_cost) {
        mysqli_stmt_bind_param($stmt_cost, 's', $car_type);
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
        // Fallback default base rates
        $base_rates = [
            'sedan'  => 13.0,
            'ertiga' => 16.0,
            'innova' => 19.0,
            'crysta' => 24.0,
        ];
        $car_key = strtolower(str_replace(' ', '', $car_type));
        $km_rate = $base_rates[$car_key] ?? 13.0;
    }

    $agni_amount = $km_rate * $distance * 0.20;
    $vendor_amount = $amount - $agni_amount;

} elseif ($trip_type === 'Local-Duty') {
    $sql_cost = "SELECT * FROM tripCostTable WHERE tripType = 'Local-Duty' AND carType = ? LIMIT 1";
    $stmt_cost = mysqli_prepare($conn, $sql_cost);
    if ($stmt_cost) {
        mysqli_stmt_bind_param($stmt_cost, 's', $car_type);
        mysqli_stmt_execute($stmt_cost);
        $res_cost = mysqli_stmt_get_result($stmt_cost);
        if ($row = mysqli_fetch_assoc($res_cost)) {
            $agni_amount = (float)$row['agni_share'];
            $vendor_amount = (float)$row['driverRate'];
        }
        mysqli_stmt_close($stmt_cost);
    }
}

mysqli_stmt_bind_param($stmt, 'ssssssssssddssdd',
    $booking_id, $from, $to, $trip_type, $car_type,
    $date, $time, $user_mobile, $user_mobile, $otp,
    $distance, $amount, $ret_date, $ret_time,
    $vendor_amount, $agni_amount
);

if (!mysqli_stmt_execute($stmt)) {
    $err = mysqli_stmt_error($stmt);
    log_api_request($partner['id'], $_API_NAME, $body, ['status'=>false,'message'=>$err], 'error');
    api_error('Booking failed: ' . $err, 500);
}
mysqli_stmt_close($stmt);

// Record in partner_bookings cross-reference
$pb = mysqli_prepare($conn,
    "INSERT INTO partner_bookings (partner_id, booking_id, partner_booking_ref, trip_type, status)
     VALUES (?, ?, ?, ?, 'pending')"
);
if ($pb) {
    mysqli_stmt_bind_param($pb, 'isss', $partner['id'], $booking_id, $partner_ref, $trip_type);
    mysqli_stmt_execute($pb);
    mysqli_stmt_close($pb);
}

$response = [
    'status'  => true,
    'message' => 'Booking created successfully',
    'data'    => [
        'booking_id'         => $booking_id,
        'partner_booking_ref'=> $partner_ref,
        'booking_status'     => 'Pending',
        'from_address'       => $from,
        'to_address'         => $to,
        'trip_type'          => $trip_type,
        'car_type'           => $car_type,
        'date'               => $date,
        'time'               => $time,
        'passenger_name'     => $user_name,
        'passenger_mobile'   => $user_mobile,
        'note'               => 'Booking is pending vendor acceptance. Track status using /booking-status?booking_id=' . $booking_id,
    ],
];

log_api_request($partner['id'], $_API_NAME, $body, $response, 'success');
echo json_encode($response);
