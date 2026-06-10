<?php
session_start();

if (isset($_SESSION['partner_id'])) {
    header("Location: dashboard.php");
    exit();
} else {
    header("Location: login.php");
    exit();
}
?>
