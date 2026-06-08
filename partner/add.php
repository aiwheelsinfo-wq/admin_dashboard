<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../adminlogin.php");
    exit();
}
require_once __DIR__ . '/../db_connect.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $partner_name  = trim($_POST['partner_name']  ?? '');
    $company_name  = trim($_POST['company_name']  ?? '');
    $contact_person= trim($_POST['contact_person']?? '');
    $mobile        = trim($_POST['mobile_number'] ?? '');
    $email         = trim($_POST['email']         ?? '');
    $rate_min      = (int)($_POST['rate_limit_per_minute'] ?? 60);
    $rate_day      = (int)($_POST['rate_limit_per_day']    ?? 10000);
    $notes         = trim($_POST['notes']         ?? '');

    // Validate required
    if (!$partner_name || !$company_name || !$contact_person || !$mobile || !$email) {
        $error = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Generate unique API key
        $name_prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $partner_name), 0, 8));
        do {
            $api_key = $name_prefix . '_' . strtoupper(bin2hex(random_bytes(20)));
            $ck = mysqli_query($conn, "SELECT id FROM partners WHERE api_key = '" . mysqli_real_escape_string($conn, $api_key) . "' LIMIT 1");
        } while (mysqli_num_rows($ck) > 0);

        $secret_key = bin2hex(random_bytes(24));

        try {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO partners (partner_name, company_name, contact_person, mobile_number, email, api_key, secret_key, rate_limit_per_minute, rate_limit_per_day, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')"
            );
            mysqli_stmt_bind_param($stmt, 'sssssssiis',
                $partner_name, $company_name, $contact_person, $mobile, $email,
                $api_key, $secret_key, $rate_min, $rate_day, $notes
            );

            if (mysqli_stmt_execute($stmt)) {
                $new_id  = mysqli_insert_id($conn);
                $success = "Partner added successfully!";
                // Show keys in session for one-time display
                $_SESSION['new_keys'] = ['api_key' => $api_key, 'secret_key' => $secret_key, 'id' => $new_id];
            } else {
                $error = 'Error: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $error = 'Error: A partner with this email address already exists.';
            } else {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$new_keys = $_SESSION['new_keys'] ?? null;
if ($new_keys && $success) {
    unset($_SESSION['new_keys']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Partner — Agni Car Rental</title>
    <link rel="icon" type="image/png" href="../images/pnglogoagni.png">
    <link rel="stylesheet" href="../css/Dashboard_styles.css">
    <link rel="stylesheet" href="../css/partner_styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body style="background: linear-gradient(135deg,#0f0f23 0%,#1a1a2e 50%,#16213e 100%); min-height:100vh;">
<nav class="top-nav">
    <div class="logo-container"><img src="../images/logo.png" alt="Logo" class="logo"></div>
    <h1 class="dashboard-heading">Add Partner</h1>
    <div class="right-nav">
        <a href="index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-arrow-left me-1"></i> Back</a>
    </div>
</nav>

<div class="partner-page">
    <div style="max-width: 760px; margin: 0 auto;">

        <div class="partner-header">
            <div>
                <h2><i class="fas fa-user-plus me-2"></i>Add New Partner</h2>
                <div class="breadcrumb-text">API keys are auto-generated upon saving</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" style="border-radius:12px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success && $new_keys): ?>
            <!-- Keys Generated Modal -->
            <div style="background:linear-gradient(135deg,#1a1a2e,#16213e);border:2px solid #00D68F;border-radius:16px;padding:28px;margin-bottom:24px;">
                <h4 style="color:#00D68F;margin-bottom:16px;"><i class="fas fa-key me-2"></i>✅ Partner Added — API Keys Generated</h4>
                <p style="color:#ff9800;font-size:0.85rem;margin-bottom:16px;">⚠️ Copy these keys now! The secret key will not be shown again in full.</p>
                <label style="color:#888;font-size:0.75rem;text-transform:uppercase;">API Key</label>
                <div class="api-key-display">
                    <code id="display-api-key"><?= htmlspecialchars($new_keys['api_key']) ?></code>
                    <button class="copy-btn" onclick="copyText('display-api-key', this)"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <label style="color:#888;font-size:0.75rem;text-transform:uppercase;margin-top:12px;">Secret Key</label>
                <div class="api-key-display">
                    <code id="display-secret-key"><?= htmlspecialchars($new_keys['secret_key']) ?></code>
                    <button class="copy-btn" onclick="copyText('display-secret-key', this)"><i class="fas fa-copy"></i> Copy</button>
                </div>
                <div class="mt-3 d-flex gap-2">
                    <a href="view.php?id=<?= $new_keys['id'] ?>" class="btn-partner-primary"><i class="fas fa-eye me-1"></i>View Partner</a>
                    <a href="index.php" class="btn-partner-info"><i class="fas fa-list me-1"></i>All Partners</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="partner-card">
            <form method="POST" class="partner-form">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label>Partner Name <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="partner_name" class="form-control" placeholder="e.g. Akbar Travels" required value="<?= htmlspecialchars($_POST['partner_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Company Name <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="company_name" class="form-control" placeholder="Legal company name" required value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Contact Person <span style="color:#FF3D71">*</span></label>
                        <input type="text" name="contact_person" class="form-control" placeholder="Primary contact name" required value="<?= htmlspecialchars($_POST['contact_person'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Mobile Number <span style="color:#FF3D71">*</span></label>
                        <input type="tel" name="mobile_number" class="form-control" placeholder="+91 XXXXXXXXXX" required value="<?= htmlspecialchars($_POST['mobile_number'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label>Business Email <span style="color:#FF3D71">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="api@akbartravels.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="col-12"><hr style="border-color:rgba(108,99,255,0.2);">
                        <h6 style="color:#6C63FF;margin-bottom:16px;"><i class="fas fa-tachometer-alt me-1"></i>Rate Limits</h6>
                    </div>

                    <div class="col-md-6">
                        <label>Requests per Minute</label>
                        <input type="number" name="rate_limit_per_minute" class="form-control" value="<?= htmlspecialchars($_POST['rate_limit_per_minute'] ?? '60') ?>" min="1" max="1000">
                    </div>
                    <div class="col-md-6">
                        <label>Requests per Day</label>
                        <input type="number" name="rate_limit_per_day" class="form-control" value="<?= htmlspecialchars($_POST['rate_limit_per_day'] ?? '10000') ?>" min="1" max="1000000">
                    </div>

                    <div class="col-12">
                        <label>Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Internal notes about this partner..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 mt-2">
                        <div style="background:rgba(108,99,255,0.08);border:1px solid rgba(108,99,255,0.2);border-radius:10px;padding:14px;font-size:0.84rem;color:#888;margin-bottom:16px;">
                            <i class="fas fa-info-circle me-1" style="color:#6C63FF"></i>
                            API Key and Secret Key will be auto-generated and shown once after saving.
                        </div>
                        <button type="submit" class="btn-partner-primary" style="width:100%;justify-content:center;">
                            <i class="fas fa-save me-2"></i>Save Partner & Generate Keys
                        </button>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<script>
function copyText(id, btn) {
    const text = document.getElementById(id).textContent;
    navigator.clipboard.writeText(text).then(() => {
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy"></i> Copy'; btn.classList.remove('copied'); }, 2000);
    });
}
</script>
</body>
</html>
