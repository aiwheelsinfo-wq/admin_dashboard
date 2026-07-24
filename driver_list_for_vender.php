<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

include 'db_connect.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    /* =====================================================
       GET → Get Vendor Drivers + Duty Details
    ===================================================== */
   case 'GET':

    if (!isset($_GET['vendor_id'])) {
        echo json_encode(["status" => false, "message" => "vendor_id required"]);
        exit;
    }

    $vendor_id = trim($_GET['vendor_id']);

    $sql = "
    SELECT 
        d.driver_id,
        d.phone_number,
        COALESCE(NULLIF(dl.holder_name, ''), NULLIF(d.full_name, ''), d.phone_number) AS full_name,
        d.status,
        d.latitude,
        d.longitude,
        d.insurnce_doe,
        d.puc_doe,
        d.license_doe,
        d.texi_permit_doe,
        d.fitness_certificate_doe,
        b.id AS booking_id,
        b.trip_type,
        b.date,
        b.time,
        b.booking_status,
        b.from_address,
        b.to_address
    FROM driver_vendor_join_Table dv
    JOIN drivers d ON dv.driver_id = d.phone_number
    LEFT JOIN driver_dl_verifications dl ON UPPER(REPLACE(REPLACE(d.license_no, ' ', ''), '-', '')) = dl.dl_number
    LEFT JOIN bookings b ON b.driver_id = d.phone_number AND b.date >= CURDATE()
    WHERE dv.vendor_id = ?
    ORDER BY d.driver_id DESC;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $vendor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $drivers = [];

    while ($row = $result->fetch_assoc()) {
        $driver_id = $row['driver_id'];

        if (!isset($drivers[$driver_id])) {
            $drivers[$driver_id] = $row;
            unset(
                $drivers[$driver_id]['booking_id'],
                $drivers[$driver_id]['trip_type'],
                $drivers[$driver_id]['date'],
                $drivers[$driver_id]['time'],
                $drivers[$driver_id]['booking_status'],
                $drivers[$driver_id]['from_address'],
                $drivers[$driver_id]['to_address']
            );
            $drivers[$driver_id]['bookings'] = [];
        }

        if (!empty($row['booking_id'])) {
            $drivers[$driver_id]['bookings'][] = [
                "booking_id" => $row['booking_id'],
                "trip_type" => $row['trip_type'],
                "date" => $row['date'],
                "time" => $row['time'],
                "booking_status" => $row['booking_status'],
                "from_address" => $row['from_address'],
                "to_address" => $row['to_address']
            ];
        }
    }

    echo json_encode([
        "status" => true,
        "total_drivers" => count($drivers),
        "data" => array_values($drivers)
    ]);

    $stmt->close();
    break;

    /* =====================================================
       PUT → Update Driver
    ===================================================== */
   case 'PUT':
        $putData = json_decode(file_get_contents("php://input"), true);
        if (!isset($putData['driver_id'])) {
            echo json_encode(["status" => false, "message" => "driver_id required"]);
            exit;
        }

        $driver_id = intval($putData['driver_id']);
        $full_name = $putData['full_name'] ?? null;
        $email = $putData['email'] ?? null;
        $vehicle_name = $putData['vehicle_name'] ?? null;
        $status = $putData['status'] ?? null;

        $sql = "UPDATE drivers SET full_name = ?, email = ?, vehicle_name = ?, status = ? WHERE driver_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssi", $full_name, $email, $vehicle_name, $status, $driver_id);

        if ($stmt->execute()) {
            echo json_encode(["status" => true, "message" => "Driver updated"]);
        } else {
            echo json_encode(["status" => false, "message" => "Update failed"]);
        }
        $stmt->close();
        break;

    /* =====================================================
       DELETE → Delete Driver (Removes join link & sub-driver)
    ===================================================== */
   case 'DELETE':
        $deleteData = json_decode(file_get_contents("php://input"), true);
        if (!isset($deleteData['driver_id'])) {
            echo json_encode(["status" => false, "message" => "driver_id required"]);
            exit;
        }

        $driver_id = intval($deleteData['driver_id']);
        $vendor_id = isset($deleteData['vendor_id']) ? trim($deleteData['vendor_id']) : '';

        $conn->begin_transaction();

        try {
            $stmtGet = $conn->prepare("SELECT phone_number, userType FROM drivers WHERE driver_id = ?");
            $stmtGet->bind_param("i", $driver_id);
            $stmtGet->execute();
            $result = $stmtGet->get_result();
            $driver = $result->fetch_assoc();
            $stmtGet->close();

            if (!$driver) {
                throw new Exception("Driver not found");
            }

            $phone = $driver['phone_number'];
            $userType = strtolower(trim($driver['userType'] ?? ''));

            // Remove link from driver_vendor_join_Table
            if (!empty($vendor_id)) {
                $stmt1 = $conn->prepare("DELETE FROM driver_vendor_join_Table WHERE driver_id = ? AND vendor_id = ?");
                $stmt1->bind_param("ss", $phone, $vendor_id);
            } else {
                $stmt1 = $conn->prepare("DELETE FROM driver_vendor_join_Table WHERE driver_id = ?");
                $stmt1->bind_param("s", $phone);
            }
            $stmt1->execute();
            $stmt1->close();

            // Delete sub-driver record if NOT a primary vendor
            if ($userType !== 'vendor' && $phone !== $vendor_id) {
                $stmt2 = $conn->prepare("DELETE FROM drivers WHERE driver_id = ?");
                $stmt2->bind_param("i", $driver_id);
                $stmt2->execute();
                $stmt2->close();
            }

            $conn->commit();
            echo json_encode(["status" => true, "message" => "Driver removed from fleet successfully"]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(["status" => false, "message" => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(["status" => false, "message" => "Invalid request method"]);
        break;
}

$conn->close();
?>
