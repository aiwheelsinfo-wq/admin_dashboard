<?php
// email_config.php - Transactional Email Service Configuration
// Bypasses GoDaddy's 1.56-minute outbound mail queue delay by routing emails over HTTPS (Port 443).

// 1. Choose your provider: 'resend', 'brevo', 'sendgrid', or 'local' (to fall back to GoDaddy queue)
define('EMAIL_PROVIDER', 'resend');

// 2. Paste your API Key here:
define('EMAIL_API_KEY', 're_YOUR_API_KEY_HERE');

// 3. Configure the verified sender details (must match what you verified in your provider dashboard):
define('EMAIL_SENDER_NAME', 'Redox API Service');
define('EMAIL_SENDER_EMAIL', 'noreply@agnicarrental.com');
?>
