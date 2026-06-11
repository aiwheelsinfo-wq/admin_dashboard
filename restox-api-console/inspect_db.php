<?php
require_once __DIR__ . '/../db_connect.php';

$result = mysqli_query($conn, "DESCRIBE partners");
if ($result) {
    echo "Columns in partners table:\n";
    while ($row = mysqli_fetch_assoc($result)) {
        echo "- {$row['Field']} ({$row['Type']}) - Null: {$row['Null']} - Key: {$row['Key']}\n";
    }
} else {
    echo "Error describing partners: " . mysqli_error($conn) . "\n";
}
?>
