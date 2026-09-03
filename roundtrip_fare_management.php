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

    $action = $_REQUEST['action'] ?? 'get_fares';

    // 1. Fetch Round-Trip Vehicle Rates and Driver Allowance
    if ($action === 'get_fares') {
        $sql = "SELECT id, carType, kmRate, driver_allowance, daily_limit AS kmPerDay, gstPercent, agni_share 
                FROM `tripCostTable` 
                WHERE `tripType` = 'Round-trip' 
                ORDER BY `id` ASC";
        $result = $conn->query($sql);

        $vehicles = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $vehicles[] = [
                    'id' => (int)$row['id'],
                    'carType' => $row['carType'],
                    'kmRate' => (float)$row['kmRate'],
                    'driver_allowance' => (float)($row['driver_allowance'] ?? 400),
                    'kmPerDay' => (float)($row['kmPerDay'] ?? 300),
                    'gstPercent' => (float)($row['gstPercent'] ?? 5.0),
                    'agni_share' => (float)($row['agni_share'] ?? 2.0)
                ];
            }
        }

        echo json_encode([
            'status' => 'success',
            'tripType' => 'Round-trip',
            'vehicles' => $vehicles
        ]);
        exit;
    }

    // 2. Update Single Vehicle Rate and Driver Allowance
    if ($action === 'update_rate') {
        $carType = trim($_POST['carType'] ?? '');
        $kmRate = floatval($_POST['kmRate'] ?? 0);
        $driverAllowance = floatval($_POST['driver_allowance'] ?? 0);

        if (empty($carType)) {
            echo json_encode(['status' => 'error', 'message' => 'Vehicle category is required.']);
            exit;
        }

        if ($kmRate <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Vehicle rate per KM must be greater than 0.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE `tripCostTable` SET `kmRate` = ?, `driver_allowance` = ? WHERE `tripType` = 'Round-trip' AND `carType` = ?");
        $stmt->bind_param("dds", $kmRate, $driverAllowance, $carType);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "Successfully updated {$carType} (Rate: ₹{$kmRate}/KM, Allowance: ₹{$driverAllowance})",
                'updated' => [
                    'carType' => $carType,
                    'kmRate' => $kmRate,
                    'driver_allowance' => $driverAllowance
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed: ' . $conn->error]);
        }
        $stmt->close();
        exit;
    }

    // 3. Bulk Update All Round-Trip Vehicle Rates
    if ($action === 'bulk_update') {
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);
        $vehicles = $data['vehicles'] ?? [];

        if (empty($vehicles) && !empty($_POST['vehicles'])) {
            $vehicles = is_array($_POST['vehicles']) ? $_POST['vehicles'] : json_decode($_POST['vehicles'], true);
        }

        if (empty($vehicles)) {
            echo json_encode(['status' => 'error', 'message' => 'No vehicles data provided for update.']);
            exit;
        }

        $updatedCount = 0;
        $stmt = $conn->prepare("UPDATE `tripCostTable` SET `kmRate` = ?, `driver_allowance` = ? WHERE `tripType` = 'Round-trip' AND `carType` = ?");

        foreach ($vehicles as $v) {
            $cType = trim($v['carType'] ?? '');
            $kRate = floatval($v['kmRate'] ?? 0);
            $dAllow = floatval($v['driver_allowance'] ?? 0);

            if (!empty($cType) && $kRate > 0) {
                $stmt->bind_param("dds", $kRate, $dAllow, $cType);
                if ($stmt->execute()) {
                    $updatedCount++;
                }
            }
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'message' => "Updated {$updatedCount} Round-Trip vehicle configurations successfully."
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid API action.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Round-Trip Fare Management | Rentox Admin</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 40px; background: #f8fafc; color: #0f172a; }
        .card { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h1 { font-size: 1.5rem; margin-top: 0; color: #f59e0b; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Rentox Round-Trip Fare Management API</h1>
        <p>This endpoint manages <strong>Vehicle Rates</strong> and <strong>Driver Allowance</strong> for Round-Trip journeys.</p>
        <p>Use the React Admin Dashboard at <code>/roundtrip-fare</code> or call <code>?api=1</code> for JSON data.</p>
    </div>
</body>
</html>
