<?php
/**
 * ============================================================
 *  partner_db_setup.php  — Automated Database Migration Runner
 * ============================================================
 *
 * HOW IT WORKS:
 *   1. Connects to the GoDaddy MySQL database via db_connect.php.
 *   2. Creates the `schema_migrations` tracking table if it doesn't exist.
 *   3. Scans the /migrations folder for *.sql files (sorted numerically).
 *   4. Skips files that have already been applied (tracked by name).
 *   5. Applies each new migration in order and records it in the table.
 *
 * HOW TO ADD A NEW FIELD / TABLE IN THE FUTURE:
 *   - Create a new file in /migrations/ named like:
 *       007_add_column_to_bookings.sql
 *   - Write your ALTER TABLE or CREATE TABLE statement inside.
 *   - Visit this page once — it will auto-apply only the new migration.
 *   - Never needs manual phpMyAdmin work again!
 *
 * SECURITY:
 *   - Protect this file from public access (see instructions below).
 *   - Add your IP restriction or a secret token check in production.
 */

// ── Security: only allow access with a secret token ──────────────────────────
// Change this token to something only you know.
define('MIGRATION_SECRET', 'agni2025_migrate_secret');

$provided_token = $_GET['token'] ?? '';
if ($provided_token !== MIGRATION_SECRET) {
    http_response_code(403);
    die('<h2 style="color:red;font-family:sans-serif;">403 Forbidden — Access Denied.</h2>
         <p style="font-family:sans-serif;">Add <code>?token=agni2025_migrate_secret</code> to the URL to run migrations.</p>');
}

// ── Start output buffering & set content type ─────────────────────────────────
ob_start();
header('Content-Type: text/html; charset=UTF-8');

// ── Load DB connection ────────────────────────────────────────────────────────
require_once __DIR__ . '/db_connect.php';

if (!$conn) {
    die('<p style="color:red;">❌ Database connection failed: ' . htmlspecialchars(mysqli_connect_error()) . '</p>');
}

// ── Helper: print styled log lines ───────────────────────────────────────────
function log_line(string $msg, string $type = 'info'): void {
    $colors = [
        'info'    => '#2196F3',
        'success' => '#4CAF50',
        'warning' => '#FF9800',
        'error'   => '#f44336',
        'skip'    => '#9E9E9E',
    ];
    $icons = [
        'info'    => 'ℹ️',
        'success' => '✅',
        'warning' => '⚠️',
        'error'   => '❌',
        'skip'    => '⏭️',
    ];
    $color = $colors[$type] ?? '#000';
    $icon  = $icons[$type]  ?? '•';
    echo '<p style="margin:4px 0;font-family:monospace;font-size:14px;color:' . $color . ';">'
       . $icon . ' ' . htmlspecialchars($msg)
       . '</p>';
    ob_flush();
    flush();
}

// ── Step 1: Ensure schema_migrations table exists ─────────────────────────────
$create_tracking = "CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (!mysqli_query($conn, $create_tracking)) {
    die('<p style="color:red;">❌ Could not create schema_migrations table: ' . htmlspecialchars(mysqli_error($conn)) . '</p>');
}
log_line('schema_migrations tracking table ready.', 'info');

// ── Step 2: Load already-applied migrations ───────────────────────────────────
$applied = [];
$result = mysqli_query($conn, "SELECT migration_name FROM schema_migrations");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $applied[$row['migration_name']] = true;
    }
}
log_line('Already applied: ' . count($applied) . ' migration(s).', 'info');

// ── Step 3: Scan /migrations folder for .sql files ───────────────────────────
$migrations_dir = __DIR__ . '/migrations';
if (!is_dir($migrations_dir)) {
    log_line('No /migrations folder found. Creating it...', 'warning');
    mkdir($migrations_dir, 0755, true);
    log_line('/migrations folder created. Add your .sql files there.', 'info');
    mysqli_close($conn);
    exit;
}

$sql_files = glob($migrations_dir . '/*.sql');
if (empty($sql_files)) {
    log_line('No migration files found in /migrations/.', 'warning');
    mysqli_close($conn);
    exit;
}

// Sort by filename (ensures 001_, 002_, 003_ order)
sort($sql_files);

$applied_count = 0;
$skipped_count = 0;
$error_count   = 0;

// ── Step 4: Apply each migration ─────────────────────────────────────────────
foreach ($sql_files as $filepath) {
    $filename = basename($filepath);

    // Skip already-applied migrations
    if (isset($applied[$filename])) {
        log_line("SKIPPED (already applied): $filename", 'skip');
        $skipped_count++;
        continue;
    }

    log_line("Applying: $filename ...", 'info');

    $sql_content = file_get_contents($filepath);
    if ($sql_content === false) {
        log_line("Could not read file: $filename", 'error');
        $error_count++;
        continue;
    }

    // Remove SQL comments (-- style) and split on semicolons
    $sql_content = preg_replace('/--[^\n]*\n/', "\n", $sql_content);
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        fn($s) => !empty($s)
    );

    $file_success = true;

    foreach ($statements as $statement) {
        if (!mysqli_query($conn, $statement)) {
            $err = mysqli_error($conn);

            // MySQL 1060 = Duplicate column — that's fine, skip gracefully
            if (mysqli_errno($conn) === 1060) {
                log_line("  Column already exists (skipped): " . substr($statement, 0, 80) . "...", 'skip');
                continue;
            }

            log_line("  SQL Error in $filename: $err", 'error');
            log_line("  Statement: " . substr($statement, 0, 120), 'error');
            $file_success = false;
            break;
        }
    }

    if ($file_success) {
        // Record migration as applied
        $record_stmt = mysqli_prepare($conn, "INSERT INTO schema_migrations (migration_name) VALUES (?)");
        if ($record_stmt) {
            mysqli_stmt_bind_param($record_stmt, 's', $filename);
            mysqli_stmt_execute($record_stmt);
            mysqli_stmt_close($record_stmt);
        }
        log_line("Applied successfully: $filename", 'success');
        $applied_count++;
    } else {
        $error_count++;
    }
}

// ── Step 5: Summary ───────────────────────────────────────────────────────────
echo '<hr style="margin:20px 0;">';
log_line("━━━ Migration Complete ━━━", 'info');
log_line("✔ Applied:  $applied_count migration(s)", $applied_count > 0 ? 'success' : 'info');
log_line("⏭ Skipped:  $skipped_count migration(s) (already done)", 'skip');
if ($error_count > 0) {
    log_line("✖ Errors:   $error_count migration(s) failed — check above", 'error');
} else {
    log_line("✖ Errors:   0", 'success');
}

echo '<hr style="margin:20px 0;">';
echo '<p style="font-family:sans-serif;font-size:13px;color:#555;">
  <strong>How to add a new field in future:</strong><br>
  Create a file like <code>migrations/007_add_new_column.sql</code> with your <code>ALTER TABLE</code> statement,
  then visit this page once to auto-apply it.
</p>';

mysqli_close($conn);
ob_end_flush();
?>
