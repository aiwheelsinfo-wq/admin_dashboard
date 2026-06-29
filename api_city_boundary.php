<?php
// api_city_boundary.php - API Controller for managing City Geofence Boundaries
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
// 1. PUBLIC GET ACTIVE BOUNDARIES - Action: get_active_boundaries
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'get_active_boundaries') {
    $sql = "SELECT city_name as name, min_lat as minLat, max_lat as maxLat, min_lng as minLng, max_lng as maxLng 
            FROM city_boundaries 
            WHERE status = 'active' 
            ORDER BY city_name ASC";
    
    $result = $conn->query($sql);
    if ($result) {
        $boundaries = [];
        while ($row = $result->fetch_assoc()) {
            // Convert to double coordinates to prevent string formatting type issues in Flutter
            $boundaries[] = [
                'name' => $row['name'],
                'minLat' => floatval($row['minLat']),
                'maxLat' => floatval($row['maxLat']),
                'minLng' => floatval($row['minLng']),
                'maxLng' => floatval($row['maxLng'])
            ];
        }
        echo json_encode([
            'success' => true,
            'cities' => $boundaries
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to retrieve active boundaries: ' . $conn->error
        ]);
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
// 2. ADMIN LIST ALL - Action: list (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $sql = "SELECT * FROM city_boundaries ORDER BY city_name ASC";
    $result = $conn->query($sql);
    
    if ($result) {
        $records = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode([
            'success' => true,
            'data' => $records
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'List retrieval failed.']);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. ADMIN ADD CITY - Action: add (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'add') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $city_name = clean_input($_POST['city_name'] ?? '');
    $min_lat = floatval($_POST['min_lat'] ?? 0);
    $max_lat = floatval($_POST['max_lat'] ?? 0);
    $min_lng = floatval($_POST['min_lng'] ?? 0);
    $max_lng = floatval($_POST['max_lng'] ?? 0);
    $status = clean_input($_POST['status'] ?? 'active');

    if (empty($city_name) || $min_lat == 0 || $max_lat == 0 || $min_lng == 0 || $max_lng == 0) {
        echo json_encode(['success' => false, 'message' => 'All boundary coordinates and city name are required.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO city_boundaries (city_name, min_lat, max_lat, min_lng, max_lng, status) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sdddds", $city_name, $min_lat, $max_lat, $min_lng, $max_lng, $status);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'City boundary geofence added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Preparation failed: ' . $conn->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. ADMIN EDIT CITY - Action: edit (Requires admin session)
// ─────────────────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }

    $id = intval($_POST['id'] ?? 0);
    $city_name = clean_input($_POST['city_name'] ?? '');
    $min_lat = floatval($_POST['min_lat'] ?? 0);
    $max_lat = floatval($_POST['max_lat'] ?? 0);
    $min_lng = floatval($_POST['min_lng'] ?? 0);
    $max_lng = floatval($_POST['max_lng'] ?? 0);
    $status = clean_input($_POST['status'] ?? 'active');

    if ($id <= 0 || empty($city_name) || $min_lat == 0 || $max_lat == 0 || $min_lng == 0 || $max_lng == 0) {
        echo json_encode(['success' => false, 'message' => 'All boundary coordinates and city name are required.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE city_boundaries SET city_name = ?, min_lat = ?, max_lat = ?, min_lng = ?, max_lng = ?, status = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sddddsi", $city_name, $min_lat, $max_lat, $min_lng, $max_lng, $status, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'City boundary geofence updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Preparation failed: ' . $conn->error]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. ADMIN DELETE CITY - Action: delete (Requires admin session)
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

    $stmt = $conn->prepare("DELETE FROM city_boundaries WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'City boundary geofence deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Delete preparation failed.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action parameter.']);
?>
