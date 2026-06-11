<?php
// mail_runner.php - Background email sender
ignore_user_abort(true);
set_time_limit(120);

// Include mailer.php to access the sync mail sending functions
require_once __DIR__ . '/mailer.php';

log_mail_debug("mail_runner.php: Starting background mail processing");

$spool_dir = __DIR__ . '/mail_spool';
if (!is_dir($spool_dir)) {
    log_mail_debug("mail_runner.php: Spool directory does not exist: $spool_dir");
    exit;
}

$files = glob($spool_dir . '/*.json');
$count = count($files);
log_mail_debug("mail_runner.php: Found $count queued mail files");

foreach ($files as $file) {
    log_mail_debug("mail_runner.php: Processing file " . basename($file));
    // Read the queue file
    $content = @file_get_contents($file);
    if (!$content) {
        log_mail_debug("mail_runner.php: Could not read file content of " . basename($file));
        continue;
    }
    
    $data = json_decode($content, true);
    if ($data && isset($data['type'])) {
        if ($data['type'] === 'otp') {
            log_mail_debug("mail_runner.php: Sending OTP email for " . $data['to_email']);
            send_otp_email_sync($data['to_email'], $data['otp'], $data['to_name']);
        } elseif ($data['type'] === 'admin_notification') {
            log_mail_debug("mail_runner.php: Sending admin notification email for " . $data['company_name']);
            send_admin_notification_email_sync(
                $data['company_name'],
                $data['partner_name'],
                $data['company_owner'],
                $data['contact_person'],
                $data['contact_mobile'],
                $data['business_email'],
                $data['gst_number']
            );
        }
    }
    // Delete the spool file after processing
    if (@unlink($file)) {
        log_mail_debug("mail_runner.php: Successfully deleted spool file " . basename($file));
    } else {
        log_mail_debug("mail_runner.php: Failed to delete spool file " . basename($file));
    }
}
log_mail_debug("mail_runner.php: Background mail processing completed");
?>
