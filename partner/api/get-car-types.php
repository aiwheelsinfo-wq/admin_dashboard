<?php
/**
 * GET /partner/api/get-car-types.php
 * Returns list of active car types available in the system.
 *
 * Headers: X-API-Key, X-Secret-Key
 */

$_API_NAME = 'get-car-types';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/logger.php';

// Fetch unique car types from tripCostTable
$sql = "SELECT DISTINCT carType FROM tripCostTable WHERE carType IS NOT NULL AND carType != '' ORDER BY carType ASC";
$result = mysqli_query($conn, $sql);

$car_types = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $car_types[] = $row['carType'];
    }
}

// Fallback to defaults if database is empty or has error
if (empty($car_types)) {
    $car_types = ['Sedan', 'Ertiga', 'Innova', 'Crysta'];
}

$response = [
    'status'    => true,
    'message'   => 'Car types retrieved successfully',
    'car_types' => $car_types
];

log_api_request($partner['id'], $_API_NAME, $_GET, $response, 'success');
echo json_encode($response);
