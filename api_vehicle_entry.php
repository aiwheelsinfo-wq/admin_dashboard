<?php
// api_vehicle_entry.php - API Handler for Vehicle Entries (Public Submit & Admin CRUD)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once 'db_connect.php';

// Safe inputs helper
function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

$action = $_REQUEST['action'] ?? '';

// ─────────────────────────────────────────────────────────────────────────────
// 1. PUBLIC SUBMIT - Action: add (No authentication required)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $car_no = clean_input($_POST['car_no'] ?? '');
    $car_type = clean_input($_POST['car_type'] ?? '');
    $fuel_type = clean_input($_POST['fuel_type'] ?? '');
    $owner = clean_input($_POST['owner'] ?? '');
    $owner_mobile = clean_input($_POST['owner_mobile'] ?? '');
    $driver = clean_input($_POST['driver'] ?? '');
    $driver_mobile = clean_input($_POST['driver_mobile'] ?? '');
    $location = clean_input($_POST['location'] ?? '');

    // Server-side validation
    if (empty($car_no) || empty($car_type) || empty($fuel_type) || empty($owner) || empty($owner_mobile) || empty($driver) || empty($driver_mobile) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $owner_mobile) || !preg_match('/^[6-9][0-9]{9}$/', $driver_mobile)) {
        echo json_encode(['success' => false, 'message' => 'Invalid 10-digit mobile number.']);
        exit;
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO vehicle_entries (car_no, car_type, owner, owner_mobile, driver, driver_mobile, location, fuel_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssssssss", $car_no, $car_type, $owner, $owner_mobile, $driver, $driver_mobile, $location, $fuel_type);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Vehicle details submitted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Query preparation failed: ' . $conn->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// ADMIN SESSION VALIDATION FOR READ / WRITE OPERATIONS
// ─────────────────────────────────────────────────────────────────────────────
session_start();
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Session expired or missing.']);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. ADMIN READ LIST - Action: list (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $search = clean_input($_GET['search'] ?? '');
    $type = clean_input($_GET['type'] ?? '');
    $locationFilter = clean_input($_GET['location'] ?? '');
    $sort = clean_input($_GET['sort'] ?? 'desc'); // desc or asc

    $where = [];
    $params = [];
    $types = "";

    if (!empty($search)) {
        $where[] = "car_no LIKE ?";
        $params[] = "%" . $search . "%";
        $types .= "s";
    }

    if (!empty($type)) {
        $where[] = "car_type = ?";
        $params[] = $type;
        $types .= "s";
    }

    if (!empty($locationFilter)) {
        $where[] = "location LIKE ?";
        $params[] = "%" . $locationFilter . "%";
        $types .= "s";
    }

    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $order = ($sort === 'asc') ? 'ASC' : 'DESC';

    $sql = "SELECT * FROM vehicle_entries $whereClause ORDER BY created_at $order";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $records = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get aggregate details
        $totalRes = $conn->query("SELECT COUNT(*) as total FROM vehicle_entries");
        $totalCount = $totalRes ? $totalRes->fetch_assoc()['total'] : 0;

        echo json_encode([
            'success' => true,
            'total_submissions' => $totalCount,
            'data' => $records
        ]);
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'List preparation failed.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ADMIN UPDATE ENTRY - Action: edit (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $car_no = clean_input($_POST['car_no'] ?? '');
    $car_type = clean_input($_POST['car_type'] ?? '');
    $fuel_type = clean_input($_POST['fuel_type'] ?? '');
    $owner = clean_input($_POST['owner'] ?? '');
    $owner_mobile = clean_input($_POST['owner_mobile'] ?? '');
    $driver = clean_input($_POST['driver'] ?? '');
    $driver_mobile = clean_input($_POST['driver_mobile'] ?? '');
    $location = clean_input($_POST['location'] ?? '');

    if ($id <= 0 || empty($car_no) || empty($car_type) || empty($fuel_type) || empty($owner) || empty($owner_mobile) || empty($driver) || empty($driver_mobile) || empty($location)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!preg_match('/^[6-9][0-9]{9}$/', $owner_mobile) || !preg_match('/^[6-9][0-9]{9}$/', $driver_mobile)) {
        echo json_encode(['success' => false, 'message' => 'Invalid 10-digit mobile number.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE vehicle_entries SET car_no = ?, car_type = ?, owner = ?, owner_mobile = ?, driver = ?, driver_mobile = ?, location = ?, fuel_type = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ssssssssi", $car_no, $car_type, $owner, $owner_mobile, $driver, $driver_mobile, $location, $fuel_type, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Record updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Edit preparation failed.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ADMIN DELETE ENTRY - Action: delete (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM vehicle_entries WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete preparation failed.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ADMIN CSV EXPORT - Action: export (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'export') {
    // Clear out JSON header to output plain text csv
    header_remove("Content-Type");
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=Vehicle_Entries_Report_" . date('Ymd_His') . ".csv");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen("php://output", "w");
    
    // CSV Header row
    fputcsv($output, ['Car No', 'Car Type', 'Fuel Type', 'Owner', 'Owner Mobile', 'Driver', 'Driver Mobile', 'Location', 'Submitted Date']);

    $res = $conn->query("SELECT car_no, car_type, fuel_type, owner, owner_mobile, driver, driver_mobile, location, created_at FROM vehicle_entries ORDER BY created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
?>
