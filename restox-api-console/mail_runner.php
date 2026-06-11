<?php
// mail_runner.php - Background email sender
ignore_user_abort(true);
set_time_limit(120);

// Include mailer.php to access the sync mail sending functions
require_once __DIR__ . '/mailer.php';

$spool_dir = __DIR__ . '/mail_spool';
if (!is_dir($spool_dir)) {
    exit;
}

$files = glob($spool_dir . '/*.json');
foreach ($files as $file) {
    // Read the queue file
    $content = @file_get_contents($file);
    if (!$content) {
        continue;
    }
    
    $data = json_decode($content, true);
    if ($data && isset($data['type'])) {
        if ($data['type'] === 'otp') {
            send_otp_email_sync($data['to_email'], $data['otp'], $data['to_name']);
        } elseif ($data['type'] === 'admin_notification') {
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
    @unlink($file);
}
?>
