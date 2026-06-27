<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(["status" => false, "message" => "Unauthorized access."]);
    exit();
}

include 'db_connect.php';

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$status_filter = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

$sql = "SELECT id, car_no, car_type, owner_name, owner_mobile, driver_name, driver_mobile, location, status, created_at 
        FROM shared_onboardings WHERE 1=1";

if (!empty($search)) {
    $sql .= " AND (car_no LIKE '%$search%' 
                OR owner_name LIKE '%$search%' 
                OR owner_mobile LIKE '%$search%' 
                OR driver_name LIKE '%$search%' 
                OR driver_mobile LIKE '%$search%'
                OR location LIKE '%$search%')";
}

if (!empty($status_filter)) {
    $sql .= " AND status = '$status_filter'";
}

$sql .= " ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

if ($result) {
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode(["status" => true, "data" => $data]);
} else {
    echo json_encode(["status" => false, "message" => "Database query failed: " . mysqli_error($conn)]);
}

mysqli_close($conn);
?>
