<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

echo "<h2>PHPMailer Performance Debugger</h2>";

$to = isset($_GET['email']) ? $_GET['email'] : 'ai.wheels.info@gmail.com';
echo "<p>Sending test email to: <strong>" . htmlspecialchars($to) . "</strong> (use <code>?email=your-email@domain.com</code> to change this)</p>";

// ── Test 1: Direct SMTP via Gmail ─────────────────────────────────────────────
echo "<h3>Test 1: Direct SMTP via Gmail (Port 587)</h3>";
$start = microtime(true);
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
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";

// ── Test 2: PHP mail() function via PHPMailer ────────────────────────────────
echo "<h3>Test 2: PHP native mail() via PHPMailer (From: ai.wheels.info@gmail.com)</h3>";
$start = microtime(true);
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
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";

// ── Test 3: PHP mail() function (From: local domain noreply@agnicarrental.com) ──
echo "<h3>Test 3: PHP native mail() via PHPMailer (From: noreply@agnicarrental.com)</h3>";
$start = microtime(true);
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
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";

// ── Test 4: Gmail SMTP via Port 465 (SSL) ──────────────────────────────────────
echo "<h3>Test 4: Gmail SMTP via Port 465 (SSL)</h3>";
$start = microtime(true);
$mail4 = new PHPMailer(true);
try {
    $mail4->isSMTP();
    $mail4->Host       = 'smtp.gmail.com';
    $mail4->SMTPAuth   = true;
    $mail4->Username   = 'ai.wheels.info@gmail.com';
    $mail4->Password   = 'tuol rtte tllu cmtk';
    $mail4->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
    $mail4->Port       = 465;
    $mail4->Timeout    = 8;
    
    $mail4->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail4->Debugoutput = function($str, $level) {
        echo "<pre style='background:#f4f4f4;padding:5px;border:1px solid #ddd;font-size:12px;margin:2px 0;'>" . htmlspecialchars($str) . "</pre>";
    };

    $mail4->setFrom('ai.wheels.info@gmail.com', 'Redox SMTP SSL');
    $mail4->addAddress($to);
    $mail4->Subject = 'Test SSL SMTP - Redox API Service';
    $mail4->Body    = 'This is a test email sent using Gmail SMTP over SSL (Port 465).';

    $mail4->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 4 SMTP Port 465 SSL Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 4 SMTP Port 465 SSL Failed: " . htmlspecialchars($mail4->ErrorInfo) . "</p>";
}
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";

// ── Test 5: GoDaddy SMTP Relay (relay-hosting.secureserver.net) ─────────────────
echo "<h3>Test 5: GoDaddy SMTP Relay (relay-hosting.secureserver.net:25)</h3>";
$start = microtime(true);
$mail5 = new PHPMailer(true);
try {
    $mail5->isSMTP();
    $mail5->Host       = 'relay-hosting.secureserver.net';
    $mail5->SMTPAuth   = false;
    $mail5->SMTPAutoTLS = false;
    $mail5->SMTPSecure = false;
    $mail5->Port       = 25;
    $mail5->Timeout    = 8;
    
    $mail5->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail5->Debugoutput = function($str, $level) {
        echo "<pre style='background:#f4f4f4;padding:5px;border:1px solid #ddd;font-size:12px;margin:2px 0;'>" . htmlspecialchars($str) . "</pre>";
    };

    $mail5->setFrom('noreply@agnicarrental.com', 'Redox Relay');
    $mail5->addReplyTo('ai.wheels.info@gmail.com', 'Redox API Service');
    $mail5->addAddress($to);
    $mail5->Subject = 'Test GoDaddy Relay - Redox API Service';
    $mail5->Body    = 'This is a test email sent using GoDaddy SMTP Relay (relay-hosting.secureserver.net) on port 25.';

    $mail5->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 5 GoDaddy SMTP Relay Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 5 GoDaddy SMTP Relay Failed: " . htmlspecialchars($mail5->ErrorInfo) . "</p>";
}
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";

// ── Test 6: GoDaddy Localhost SMTP Relay (localhost:25) ─────────────────────────
echo "<h3>Test 6: GoDaddy Localhost SMTP Relay (localhost:25)</h3>";
$start = microtime(true);
$mail6 = new PHPMailer(true);
try {
    $mail6->isSMTP();
    $mail6->Host       = 'localhost';
    $mail6->SMTPAuth   = false;
    $mail6->SMTPAutoTLS = false;
    $mail6->SMTPSecure = false;
    $mail6->Port       = 25;
    $mail6->Timeout    = 8;
    
    $mail6->SMTPDebug  = SMTP::DEBUG_SERVER;
    $mail6->Debugoutput = function($str, $level) {
        echo "<pre style='background:#f4f4f4;padding:5px;border:1px solid #ddd;font-size:12px;margin:2px 0;'>" . htmlspecialchars($str) . "</pre>";
    };

    $mail6->setFrom('noreply@agnicarrental.com', 'Redox Localhost');
    $mail6->addReplyTo('ai.wheels.info@gmail.com', 'Redox API Service');
    $mail6->addAddress($to);
    $mail6->Subject = 'Test Localhost Relay - Redox API Service';
    $mail6->Body    = 'This is a test email sent using GoDaddy Localhost SMTP Relay (localhost) on port 25.';

    $mail6->send();
    echo "<p style='color:green;font-weight:bold;'>✅ Test 6 GoDaddy Localhost SMTP Relay Sent Successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ Test 6 GoDaddy Localhost SMTP Relay Failed: " . htmlspecialchars($mail6->ErrorInfo) . "</p>";
}
$end = microtime(true);
echo "<p>Time taken: <strong>" . round($end - $start, 4) . " seconds</strong></p>";
?>
