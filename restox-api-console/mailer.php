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
    <div style="font-family: Arial, sans-serif; max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #e5e7eb; border-radius: 12px; background-color: #ffffff; color: #1f2937;">
        <h2 style="color: #6c63ff; margin-top: 0;">Redox API Service</h2>
        <p style="font-size: 15px; line-height: 1.5;">Hello ' . htmlspecialchars($to_name) . ',</p>
        <p style="font-size: 15px; line-height: 1.5;">Thank you for registering. Please use the following One-Time Password (OTP) to verify your email address:</p>
        <div style="font-size: 30px; font-weight: bold; color: #10b981; background-color: #f3f4f6; border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; text-align: center; letter-spacing: 5px; margin: 24px 0;">
            ' . $otp . '
        </div>
        <p style="font-size: 14px; color: #6b7280;">This verification code is valid for 10 minutes.</p>
        <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 20px 0;">
        <p style="font-size: 12px; color: #9ca3af; text-align: center;">&copy; 2026 Redox API Service. All rights reserved.</p>
    </div>
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
