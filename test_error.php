<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h3>Diagnostic Script</h3>";
echo "Current PHP Version: " . phpversion() . "<br>";

echo "Connecting to DB...<br>";
try {
    require_once __DIR__ . '/db_connect.php';
    echo "db_connect.php loaded successfully. Connection: " . ($conn ? "Active" : "Null") . "<br>";
} catch (Throwable $t) {
    echo "Error loading db_connect.php: " . $t->getMessage() . "<br>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
}

echo "Including MigrationRunner.php...<br>";
try {
    require_once __DIR__ . '/../2025/MigrationRunner.php';
    echo "MigrationRunner.php loaded successfully.<br>";
} catch (Throwable $t) {
    echo "Error loading MigrationRunner.php: " . $t->getMessage() . "<br>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
}

echo "Running MigrationRunner::run...<br>";
try {
    MigrationRunner::run($conn);
    echo "MigrationRunner::run completed successfully.<br>";
} catch (Throwable $t) {
    echo "Error running MigrationRunner: " . $t->getMessage() . "<br>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
}

echo "Running query SELECT * FROM discounts...<br>";
try {
    $result = mysqli_query($conn, "SELECT * FROM discounts ORDER BY id DESC");
    if ($result) {
        echo "Query successful. Rows: " . mysqli_num_rows($result) . "<br>";
    } else {
        echo "Query returned false. Error: " . mysqli_error($conn) . "<br>";
    }
} catch (Throwable $t) {
    echo "Error running query: " . $t->getMessage() . "<br>";
    echo "<pre>" . $t->getTraceAsString() . "</pre>";
}
