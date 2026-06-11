<?php
if (function_exists('fastcgi_finish_request')) {
    echo "FPM_SUPPORTED";
} else {
    echo "FPM_NOT_SUPPORTED";
}
?>
