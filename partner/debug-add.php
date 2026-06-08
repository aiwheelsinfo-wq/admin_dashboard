<?php
/**
 * partner/debug-add.php
 * Debug script to output PHP errors inside partner/add.php.
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/add.php';
