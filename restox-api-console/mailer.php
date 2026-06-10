<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Returns the HTML email template for the OTP code.
 */
function get_email_body($otp, $to_name) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                background-color: #0b0f19;
                font-family: Arial, sans-serif;
                color: #f3f4f6;
                margin: 0;
                padding: 0;
            }
            .container {
                max-width: 600px;
                margin: 40px auto;
                background-color: #111827;
                border: 1px solid rgba(255, 255, 255, 0.08);
                border-radius: 16px;
                padding: 40px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            }
            .logo {
                font-size: 24px;
                font-weight: bold;
                color: #6c63ff;
                margin-bottom: 24px;
                text-align: center;
                letter-spacing: -0.5px;
            }
            .title {
                font-size: 20px;
                font-weight: bold;
                margin-bottom: 16px;
                color: #ffffff;
                text-align: center;
            }
            .message {
                font-size: 16px;
                line-height: 1.6;
                color: #9ca3af;
                margin-bottom: 30px;
                text-align: center;
            }
            .otp-box {
                font-size: 32px;
                font-weight: bold;
                color: #10b981;
                background-color: rgba(16, 185, 129, 0.1);
                border: 1px solid rgba(16, 185, 129, 0.2);
                padding: 12px 24px;
                border-radius: 8px;
                text-align: center;
                letter-spacing: 6px;
                display: inline-block;
                margin: 0 auto;
            }
            .otp-container {
                text-align: center;
                margin-bottom: 30px;
            }
            .footer {
                font-size: 12px;
                color: #6b7280;
                text-align: center;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                padding-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">REDOX API SERVICE</div>
            <div class="title">Verify Your Email Address</div>
            <div class="message">
                Hello ' . htmlspecialchars($to_name) . ',<br>
                Thank you for applying to the Redox API Service. Please use the following One-Time Password (OTP) to complete your email verification. This code is valid for 10 minutes.
            </div>
            <div class="otp-container">
                <div class="otp-box">' . $otp . '</div>
            </div>
            <div class="message">
                If you did not request this, please ignore this email.
            </div>
            <div class="footer">
                &copy; 2026 Redox. All rights reserved. Redox API Service.
            </div>
        </div>
    </body>
    </html>
    ';
}

/**
 * Sends a registration verification OTP email to a B2B partner applicant.
 *
 * @param string $to_email
 * @param string $otp
 * @param string $to_name
 * @return bool True if successfully sent, false otherwise.
 */
function send_otp_email($to_email, $otp, $to_name = 'Partner') {
    try {
        // Detect if running on local development environment
        $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
                 || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
        
        if (!$is_local) {
            // Bypass Gmail SMTP on live host to prevent the 4-second connection timeout hang
            throw new Exception("Bypassing Gmail SMTP on live server");
        }

        // Tier 1: Try direct SMTP connection (with 4-second timeout limit to prevent hanging)
        // This is ideal for local development or servers that don't block outbound port 587.
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ai.wheels.info@gmail.com';
        $mail->Password   = 'tuol rtte tllu cmtk';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->Timeout    = 4; 

        // Recipients
        $mail->setFrom('ai.wheels.info@gmail.com', 'Redox API Service');
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification OTP - Redox API Service';
        $mail->Body    = get_email_body($otp, $to_name);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Tier 2: Try GoDaddy's Localhost SMTP Relay (Port 25, no auth)
        // This is the most reliable method on GoDaddy shared hosting, utilizing the local Exim server.
        try {
            $mailLocal = new PHPMailer(true);
            $mailLocal->isSMTP();
            $mailLocal->Host       = 'localhost';
            $mailLocal->SMTPAuth   = false;
            $mailLocal->SMTPAutoTLS = false;
            $mailLocal->SMTPSecure = false;
            $mailLocal->Port       = 25;
            $mailLocal->Timeout    = 4;

            $mailLocal->setFrom('noreply@agnicarrental.com', 'Redox API Service');
            $mailLocal->addReplyTo('ai.wheels.info@gmail.com', 'Redox API Service');
            $mailLocal->addAddress($to_email, $to_name);
            $mailLocal->isHTML(true);
            $mailLocal->Subject = 'Email Verification OTP - Redox API Service';
            $mailLocal->Body    = get_email_body($otp, $to_name);
            
            $mailLocal->send();
            return true;
        } catch (Exception $eLocalhost) {
            // Tier 3: Fallback to PHP native mail() function via PHPMailer
            try {
                $mailBackup = new PHPMailer(true);
                $mailBackup->isMail(); 
                $mailBackup->setFrom('noreply@agnicarrental.com', 'Redox API Service');
                $mailBackup->addReplyTo('ai.wheels.info@gmail.com', 'Redox API Service');
                $mailBackup->addAddress($to_email, $to_name);
                $mailBackup->isHTML(true);
                $mailBackup->Subject = 'Email Verification OTP - Redox API Service';
                $mailBackup->Body    = get_email_body($otp, $to_name);
                
                $mailBackup->send();
                return true;
            } catch (Exception $eFallback) {
                error_log("PHPMailer all tiers failed. Error: " . $mailBackup->ErrorInfo);
                return false;
            }
        }
    }
}
?>
