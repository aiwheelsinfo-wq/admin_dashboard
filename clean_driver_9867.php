<?php
include 'db_connect.php';

$phone = '9867177044';

// 1. Fetch license_no before deletion if any
$licRes = mysqli_query($conn, "SELECT license_no, rc_no FROM drivers WHERE phone_number='$phone'");
$licNos = [];
$rcNos = [];
while ($row = mysqli_fetch_assoc($licRes)) {
    if (!empty($row['license_no'])) $licNos[] = mysqli_real_escape_string($conn, $row['license_no']);
    if (!empty($row['rc_no'])) $rcNos[] = mysqli_real_escape_string($conn, $row['rc_no']);
}

// 2. Delete from drivers table
$delDrivers = mysqli_query($conn, "DELETE FROM drivers WHERE phone_number='$phone'");
$deletedDriversCount = mysqli_affected_rows($conn);

// 3. Delete from driver_vendor_join_Table
$delJoin = mysqli_query($conn, "DELETE FROM driver_vendor_join_Table WHERE driver_id='$phone'");
$deletedJoinCount = mysqli_affected_rows($conn);

// 4. Delete from driver_dl_verifications if license_no matches
$deletedDlCount = 0;
if (!empty($licNos)) {
    $licList = "'" . implode("','", $licNos) . "'";
    mysqli_query($conn, "DELETE FROM driver_dl_verifications WHERE dl_number IN ($licList)");
    $deletedDlCount = mysqli_affected_rows($conn);
}

// 5. Delete from driver_rc_verifications if rc_no matches
$deletedRcCount = 0;
if (!empty($rcNos)) {
    $rcList = "'" . implode("','", $rcNos) . "'";
    mysqli_query($conn, "DELETE FROM driver_rc_verifications WHERE rc_number IN ($rcList)");
    $deletedRcCount = mysqli_affected_rows($conn);
}

echo json_encode([
    "success" => true,
    "phone_number" => $phone,
    "drivers_deleted" => $deletedDriversCount,
    "join_deleted" => $deletedJoinCount,
    "dl_verifications_deleted" => $deletedDlCount,
    "rc_verifications_deleted" => $deletedRcCount,
    "message" => "All driver data for $phone successfully deleted from MySQL database."
]);
?>
