<?php
include 'db_connect.php';

$vendor_phone = '9847267465';

// 1. Delete links from driver_vendor_join_Table
mysqli_query($conn, "DELETE FROM driver_vendor_join_Table WHERE vendor_id='$vendor_phone'");

// 2. Delete drivers associated with this vendor agency except the vendor's own row
mysqli_query($conn, "DELETE FROM drivers WHERE agency_name='AGNI CAR RENTALS' AND phone_number!='$vendor_phone'");
mysqli_query($conn, "DELETE FROM drivers WHERE phone_number='9526362442'");

// 3. Keep vendor row active and filled
mysqli_query($conn, "UPDATE drivers SET status='filled' WHERE phone_number='$vendor_phone'");

echo json_encode(["status" => "success", "message" => "All drivers deleted for vendor 9847267465"]);
?>
