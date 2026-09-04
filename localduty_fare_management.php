<?php
// Enable CORS for React Admin Dashboard
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/db_connect.php';

// Ensure JSON response for API calls
if (isset($_REQUEST['api']) || isset($_GET['api']) || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Parse JSON payload or form post
    $rawPayload = file_get_contents('php://input');
    $jsonPayload = json_decode($rawPayload, true) ?? [];
    $params = array_merge($_GET, $_POST, $jsonPayload);

    $action = $params['action'] ?? 'get_fares';

    // 1. Fetch Local-Duty Vehicle Fares and Driver Allowances
    if ($action === 'get_fares') {
        $sql = "SELECT id, carType, baseAmount, extraKMAmount, extraHoursAmount, packageKm, packageHours, 
                       driverRate, extraKMAmountFroDriver, extraHoursAmountForDriver, driver_allowance, 
                       agni_share, gstPercent 
                FROM `tripCostTable` 
                WHERE `tripType` = 'Local-Duty' 
                ORDER BY `id` ASC";
        $result = $conn->query($sql);

        $vehicles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $vehicles[] = [
                    'id' => (int)$row['id'],
                    'carType' => $row['carType'],
                    'baseAmount' => (float)$row['baseAmount'],
                    'extraKMAmount' => (float)$row['extraKMAmount'],
                    'extraHoursAmount' => (float)$row['extraHoursAmount'],
                    'packageKm' => (float)($row['packageKm'] ?? 80),
                    'packageHours' => (float)($row['packageHours'] ?? 8),
                    'driverRate' => (float)($row['driverRate'] ?? 2000),
                    'extraKMAmountFroDriver' => (float)($row['extraKMAmountFroDriver'] ?? 15),
                    'extraHoursAmountForDriver' => (float)($row['extraHoursAmountForDriver'] ?? 100),
                    'driver_allowance' => (float)($row['driver_allowance'] ?? 300),
                    'agni_share' => (float)($row['agni_share'] ?? 300),
                    'gstPercent' => (float)($row['gstPercent'] ?? 5.0)
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'tripType' => 'Local-Duty',
            'packageSummary' => '80 KM / 8 Hours Standard Duty Package',
            'vehicles' => $vehicles
        ]);
        exit;
    }

    // 2. Update Single Vehicle Local-Duty Rate & Allowance
    if ($action === 'update_rate') {
        $carType = trim($params['carType'] ?? '');
        $baseAmount = floatval($params['baseAmount'] ?? 0);
        $extraKMAmount = floatval($params['extraKMAmount'] ?? 0);
        $extraHoursAmount = floatval($params['extraHoursAmount'] ?? 0);
        $driverAllowance = floatval($params['driver_allowance'] ?? 0);
        $driverRate = floatval($params['driverRate'] ?? 0);

        if (empty($carType)) {
            echo json_encode(['status' => 'error', 'message' => 'Vehicle category is required.']);
            exit;
        }

        if ($baseAmount <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Base package fare must be greater than 0.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE `tripCostTable` 
                                SET `baseAmount` = ?, 
                                    `extraKMAmount` = ?, 
                                    `extraHoursAmount` = ?, 
                                    `driver_allowance` = ?, 
                                    `driverRate` = ? 
                                WHERE `tripType` = 'Local-Duty' AND `carType` = ?");
        $stmt->bind_param("ddddds", $baseAmount, $extraKMAmount, $extraHoursAmount, $driverAllowance, $driverRate, $carType);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "Successfully updated {$carType} (Base: ₹{$baseAmount}, Extra KM: ₹{$extraKMAmount}, Allowance: ₹{$driverAllowance})",
                'updated' => [
                    'carType' => $carType,
                    'baseAmount' => $baseAmount,
                    'extraKMAmount' => $extraKMAmount,
                    'extraHoursAmount' => $extraHoursAmount,
                    'driver_allowance' => $driverAllowance,
                    'driverRate' => $driverRate
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    // 3. Bulk Update All Local-Duty Rates & Allowances
    if ($action === 'bulk_update') {
        $vehicles = $params['vehicles'] ?? [];

        if (empty($vehicles)) {
            echo json_encode(['status' => 'error', 'message' => 'No vehicle data provided for bulk update.']);
            exit;
        }

        $successCount = 0;
        $stmt = $conn->prepare("UPDATE `tripCostTable` 
                                SET `baseAmount` = ?, 
                                    `extraKMAmount` = ?, 
                                    `extraHoursAmount` = ?, 
                                    `driver_allowance` = ?, 
                                    `driverRate` = ? 
                                WHERE `tripType` = 'Local-Duty' AND `carType` = ?");

        foreach ($vehicles as $v) {
            $cType = trim($v['carType'] ?? '');
            $bAmount = floatval($v['baseAmount'] ?? 0);
            $eKM = floatval($v['extraKMAmount'] ?? 0);
            $eHr = floatval($v['extraHoursAmount'] ?? 0);
            $dAllow = floatval($v['driver_allowance'] ?? 0);
            $dRate = floatval($v['driverRate'] ?? 0);

            if (!empty($cType) && $bAmount > 0) {
                $stmt->bind_param("ddddds", $bAmount, $eKM, $eHr, $dAllow, $dRate, $cType);
                if ($stmt->execute()) {
                    $successCount++;
                }
            }
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'message' => "Successfully updated {$successCount} Local-Duty vehicle rates and driver allowances.",
            'count' => $successCount
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    exit;
}

// Fallback HTML if opened in browser directly
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rentox Local Duty Fare Management API</title>
    <style>body { font-family: sans-serif; padding: 30px; line-height: 1.6; } pre { background: #f4f4f4; padding: 15px; border-radius: 8px; }</style>
</head>
<body>
    <h2>Rentox Local Duty Fare Management API</h2>
    <p>Use <code>?action=get_fares&api=1</code> to retrieve fares in JSON format.</p>
</body>
</html>
