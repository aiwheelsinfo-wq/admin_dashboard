<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

echo "<h2>PHPMailer Debugger</h2>";

$to = isset($_GET['email']) ? $_GET['email'] : 'ai.wheels.info@gmail.com';
echo "<p>Sending test email to: <strong>" . htmlspecialchars($to) . "</strong> (use <code>?email=your-email@domain.com</code> to change this)</p>";

// ── Test 1: Direct SMTP via Gmail ─────────────────────────────────────────────
echo "<h3>Test 1: Direct SMTP via Gmail (Port 587)</h3>";
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ai.wheels.info@gmail.com';
    $mail->Password   = 'tuol rtte tllu cmtk';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->Timeout    = 8; // 8 seconds timeout
    
    // Enable verbose debug output
    $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        echo "<pre style='background:#f4f4f4;padding:5px;border:1px solid #ddd;font-size:12px;margin:2px 0;'>" . htmlspecialchars($str) . "</pre>";
    };

    $mail->setFrom('ai.wheels.info@gmail.com', 'Redox Test SMTP');
    $mail->addAddress($to);
    $mail->Subject = 'Test SMTP - Redox API Service';
    $mail->Body    = 'This is a test email sent using direct Gmail SMTP from Redox API Service.';

    $mail->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 1 SMTP Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 1 SMTP Failed: " . htmlspecialchars($mail->ErrorInfo) . "</p>";
}

// ── Test 2: PHP mail() function via PHPMailer ────────────────────────────────
echo "<h3>Test 2: PHP native mail() via PHPMailer (From: ai.wheels.info@gmail.com)</h3>";
$mail2 = new PHPMailer(true);
try {
    $mail2->isMail();
    $mail2->setFrom('ai.wheels.info@gmail.com', 'Redox Test Mail');
    $mail2->addAddress($to);
    $mail2->Subject = 'Test Mail - Redox API Service';
    $mail2->Body    = 'This is a test email sent using PHP native mail() with a Gmail from address.';
    
    $mail2->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 2 native mail() Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 2 native mail() Failed: " . htmlspecialchars($mail2->ErrorInfo) . "</p>";
}

// ── Test 3: PHP mail() function (From: local domain noreply@agnicarrental.com) ──
echo "<h3>Test 3: PHP native mail() via PHPMailer (From: noreply@agnicarrental.com)</h3>";
$mail3 = new PHPMailer(true);
try {
    $mail3->isMail();
    $mail3->setFrom('noreply@agnicarrental.com', 'Redox Test Local');
    $mail3->addReplyTo('ai.wheels.info@gmail.com', 'Redox support');
    $mail3->addAddress($to);
    $mail3->Subject = 'Test Local Mail - Redox API Service';
    $mail3->Body    = 'This is a test email sent using PHP native mail() with a local domain noreply@agnicarrental.com address.';
    
    $mail3->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 3 local mail() Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 3 local mail() Failed: " . htmlspecialchars($mail3->ErrorInfo) . "</p>";
}
?>
