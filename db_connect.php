<?php

// Set PHP default timezone
date_default_timezone_set("Asia/Kolkata");
// Database credentials
$host = "localhost"; // Your server
$dbname = "agnicar2025"; // Your database name
$username = "agnicar"; // Your database username
$password = "dGwW(W8b237~"; // Your database password

// Create connection
$conn = mysqli_connect($host, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
// Set MySQL session timezone to IST
mysqli_query($conn, "SET time_zone = '+05:30'");
?>
