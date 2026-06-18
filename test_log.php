<?php
header('Content-Type: text/plain');
$logPath = __DIR__ . '/../2025/migrations.log';
if (file_exists($logPath)) {
    echo "=== migrations.log ===\n";
    echo file_get_contents($logPath);
} else {
    echo "migrations.log does not exist at $logPath\n";
}
