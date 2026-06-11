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
 * Sends a registration verification OTP email to a B2B partner applicant (Synchronous).
 *
 * @param string $to_email
 * @param string $otp
 * @param string $to_name
 * @return bool True if successfully sent, false otherwise.
 */
function send_otp_email_sync($to_email, $otp, $to_name = 'Partner') {
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
        // Tier 2: Try PHP native mail() function via PHPMailer (extremely fast, no network socket blocking)
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
        } catch (Exception $eMail) {
            // Tier 3: Try GoDaddy's Localhost SMTP Relay (Port 25, no auth)
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
            } catch (Exception $eFallback) {
                error_log("PHPMailer all tiers failed. Error: " . $mailLocal->ErrorInfo);
                return false;
            }
        }
    }
}

/**
 * Returns the HTML email template for the admin API access notification.
 */
function get_admin_notification_body($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number) {
    $partner_name_val   = !empty($partner_name) ? htmlspecialchars($partner_name) : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $company_name_val   = !empty($company_name) ? htmlspecialchars($company_name) : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $company_owner_val  = !empty($company_owner) ? htmlspecialchars($company_owner) : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $contact_person_val = !empty($contact_person) ? htmlspecialchars($contact_person) : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $contact_mobile_val = !empty($contact_mobile) ? htmlspecialchars($contact_mobile) : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $business_email_val = !empty($business_email) ? '<a href="mailto:' . htmlspecialchars($business_email) . '">' . htmlspecialchars($business_email) . '</a>' : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';
    $gst_number_val     = !empty($gst_number) ? '<span style="font-family:monospace; color:#4f46e5;">' . htmlspecialchars($gst_number) . '</span>' : '<span style="color:#9ca3af;font-style:italic;">Not provided yet (Pending profile details)</span>';

    return '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 20px auto; padding: 25px; border: 1px solid #e5e7eb; border-radius: 12px; background-color: #ffffff; color: #1f2937;">
        <h2 style="color: #4f46e5; margin-top: 0; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px;">New Partner API Request</h2>
        <p style="font-size: 15px; line-height: 1.5;">Dear Rentox Admin,</p>
        <p style="font-size: 15px; line-height: 1.5;">A new company has submitted a request for API access to the Rentox Partner API Platform.</p>
        
        <h3 style="color: #374151; margin-top: 20px; font-size: 16px;">Company Information:</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 14px;">
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; width: 150px; border-bottom: 1px solid #f3f4f6;">Partner Name:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $partner_name_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">Company Name:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $company_name_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">Company Owner:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $company_owner_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">Contact Person:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $contact_person_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">Contact Mobile:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $contact_mobile_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">Business Email:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $business_email_val . '</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; font-weight: bold; color: #4b5563; border-bottom: 1px solid #f3f4f6;">GST Number:</td>
                <td style="padding: 8px 0; color: #1f2937; border-bottom: 1px solid #f3f4f6;">' . $gst_number_val . '</td>
            </tr>
        </table>
        
        <p style="font-size: 15px; line-height: 1.5; margin-top: 25px; font-weight: 500; color: #b45309; background-color: #fffbeb; padding: 10px; border-radius: 6px; border-left: 4px solid #f59e0b;">
            The request is currently marked as Pending Review.
        </p>
        
        <p style="font-size: 14px; margin-top: 25px;">You can review and approve this request in the admin panel under the Partner API section.</p>
        <div style="margin-top: 20px; text-align: center;">
            <a href="https://agnicarrental.com/admin2025/partner/index.php" style="background-color: #4f46e5; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600; display: inline-block;">Go to Admin Panel</a>
        </div>
    </div>
    ';
}

function send_admin_notification_email_sync($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number) {
    $mail = new PHPMailer(true);
    try {
        // Try direct SMTP connection if running locally
        $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
                 || (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost');
        
        if (!$is_local) {
            throw new Exception("Bypassing Gmail SMTP on live server");
        }

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
        $mail->addAddress('ai.wheels.info@gmail.com', 'Rentox Admin');
        if (!empty($business_email)) {
            $mail->addAddress($business_email, $contact_person);
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'New Partner API Access Request - Action Required';
        $mail->Body    = get_admin_notification_body($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Fallback Tier 2: PHP native mail() function (extremely fast, no network socket blocking)
        try {
            $mailBackup = new PHPMailer(true);
            $mailBackup->isMail(); 
            $mailBackup->setFrom('noreply@agnicarrental.com', 'Redox API Service');
            $mailBackup->addReplyTo($business_email, $contact_person);
            $mailBackup->addAddress('ai.wheels.info@gmail.com', 'Rentox Admin');
            if (!empty($business_email)) {
                $mailBackup->addAddress($business_email, $contact_person);
            }
            $mailBackup->isHTML(true);
            $mailBackup->Subject = 'New Partner API Access Request - Action Required';
            $mailBackup->Body    = get_admin_notification_body($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number);
            
            $mailBackup->send();
            return true;
        } catch (Exception $eMail) {
            // Fallback Tier 3: Try GoDaddy's Localhost SMTP Relay (Port 25, no auth)
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
                $mailLocal->addReplyTo($business_email, $contact_person);
                $mailLocal->addAddress('ai.wheels.info@gmail.com', 'Rentox Admin');
                if (!empty($business_email)) {
                    $mailLocal->addAddress($business_email, $contact_person);
                }
                $mailLocal->isHTML(true);
                $mailLocal->Subject = 'New Partner API Access Request - Action Required';
                $mailLocal->Body    = get_admin_notification_body($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number);
                
                $mailLocal->send();
                return true;
            } catch (Exception $eFallback) {
                error_log("Admin notification mailing failed. Error: " . $mailLocal->ErrorInfo);
                return false;
            }
        }
    }
}

/**
 * Triggers the background CLI or HTTP mail script to process queued emails immediately.
 */
function trigger_background_mailer() {
    // Disable server-side self-loopbacks on GoDaddy shared hosting to prevent 1.56-minute hangs.
    // Mailing is triggered asynchronously from client-side JavaScript instead.
    return;
}

/**
 * Asynchronous wrapper to send registration verification OTP email.
 */
function send_otp_email($to_email, $otp, $to_name = 'Partner') {
    $spool_dir = __DIR__ . '/mail_spool';
    if (!is_dir($spool_dir)) {
        @mkdir($spool_dir, 0755, true);
    }
    
    $payload = [
        'type' => 'otp',
        'to_email' => $to_email,
        'otp' => $otp,
        'to_name' => $to_name
    ];
    
    $file = $spool_dir . '/mail_otp_' . microtime(true) . '_' . rand(1000, 9999) . '.json';
    @file_put_contents($file, json_encode($payload));
    
    trigger_background_mailer();
    return true;
}

/**
 * Asynchronous wrapper to send admin API access request notification email.
 */
function send_admin_notification_email($company_name, $partner_name, $company_owner, $contact_person, $contact_mobile, $business_email, $gst_number) {
    $spool_dir = __DIR__ . '/mail_spool';
    if (!is_dir($spool_dir)) {
        @mkdir($spool_dir, 0755, true);
    }
    
    $payload = [
        'type' => 'admin_notification',
        'company_name' => $company_name,
        'partner_name' => $partner_name,
        'company_owner' => $company_owner,
        'contact_person' => $contact_person,
        'contact_mobile' => $contact_mobile,
        'business_email' => $business_email,
        'gst_number' => $gst_number
    ];
    
    $file = $spool_dir . '/mail_admin_' . microtime(true) . '_' . rand(1000, 9999) . '.json';
    @file_put_contents($file, json_encode($payload));
    
    trigger_background_mailer();
    return true;
}
?>
