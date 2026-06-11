<?php
// check_version.php - Checks if the live mailer.php is updated
$content = file_get_contents(__DIR__ . '/mailer.php');
if (strpos($content, 'extremely fast, no network socket blocking') !== false) {
    echo "UPDATED";
} else {
    echo "OLD";
}
?>
