<?php
/**
 * partner/api/debug-book-cab.php
 * Temporary debug file to output PHP error stack traces directly.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the main booking API script
require_once __DIR__ . '/book-cab.php';
