<?php
/**
 * Setup Script — Unnatti Enterprises Mini ERP
 * Run this once to create tables and seed data.
 * REMOVE or RESTRICT this file after setup.
 */

// Security: only run from browser with correct key or from CLI
$setupKey = $_GET['key'] ?? '';
$validKey = 'unnatti_setup_2025'; // Change this before deploying

if (PHP_SAPI !== 'cli' && $setupKey !== $validKey) {
    http_response_code(403);
    die('<h2>403 Forbidden</h2><p>Append <code>?key=' . $validKey . '</code> to the URL to run setup.</p>');
}

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'mini_erp');

$errors  = [];
$success = [];

try {
    // Connect without DB first to create it
    $pdo = new PDO("mysql:host=" . DB_HOST . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $success[] = "✅ Database '" . DB_NAME . "' created/verified.";

    // Run SQL schema
    $sqlFile = dirname(__DIR__) . '/database/mini_erp.sql';
    if (!file_exists($sqlFile)) {
        $errors[] = "❌ SQL file not found: $sqlFile";
    } else {
        $sql = file_get_contents($sqlFile);
        // Split by semicolons and run each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        $count = 0;
        foreach ($statements as $stmt) {
            if (empty($stmt) || substr($stmt, 0, 2) === '--') continue;
            try {
                $pdo->exec($stmt);
                $count++;
            } catch (PDOException $e) {
                // Ignore duplicate table errors during re-runs
                if (strpos($e->getMessage(), 'already exists') === false) {
                    $errors[] = "⚠️ SQL Warning: " . $e->getMessage();
                }
            }
        }
        $success[] = "✅ Executed $count SQL statements from mini_erp.sql";
    }

    // Create uploads directories
    $dirs = [
        dirname(__DIR__) . '/uploads',
        dirname(__DIR__) . '/uploads/products',
        dirname(__DIR__) . '/uploads/logos',
        dirname(__DIR__) . '/exports',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            $success[] = "✅ Created directory: $dir";
        } else {
            $success[] = "✔ Directory exists: " . basename($dir);
        }
    }

    // Verify admin exists
    $adminCheck = $pdo->query("SELECT COUNT(*) AS c FROM users WHERE role='admin'")->fetch();
    if ($adminCheck['c'] > 0) {
        $success[] = "✅ Admin user exists. <strong>Login: admin / admin123</strong>";
    } else {
        $pdo->exec("INSERT INTO users (full_name, username, password, role, status) VALUES ('Administrator','admin','" . password_hash('admin123', PASSWORD_DEFAULT) . "','admin','active')");
        $success[] = "✅ Created admin user. <strong>Login: admin / admin123</strong>";
    }

    $success[] = "✅ Setup complete! <a href='../login.php'><strong>→ Go to Login</strong></a>";

} catch (PDOException $e) {
    $errors[] = "❌ Database connection failed: " . $e->getMessage();
    $errors[] = "Make sure XAMPP MySQL is running and credentials match.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Setup — Unnatti ERP</title>
<style>
  body { font-family: 'Segoe UI', sans-serif; background: #0f172a; color: #f1f5f9; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
  .setup-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; padding: 36px; max-width: 640px; width: 100%; margin: 20px; }
  h1 { font-size: 1.5rem; margin-bottom: 6px; }
  h1 span { background: linear-gradient(135deg, #2563eb, #7c3aed); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
  .subtitle { color: #94a3b8; font-size: 0.88rem; margin-bottom: 24px; }
  .msg { padding: 10px 14px; border-radius: 8px; margin: 6px 0; font-size: 0.87rem; }
  .msg.ok  { background: rgba(5,150,105,0.15); border: 1px solid rgba(5,150,105,0.3); }
  .msg.err { background: rgba(220,38,38,0.15); border: 1px solid rgba(220,38,38,0.3); color: #fca5a5; }
  .warning { background: #312e81; border: 1px solid #4338ca; border-radius: 10px; padding: 14px; margin-top: 20px; font-size: 0.83rem; color: #c7d2fe; }
  a { color: #60a5fa; }
</style>
</head>
<body>
<div class="setup-card">
  <h1><i>⚙️</i> <span>Unnatti ERP</span> Setup</h1>
  <p class="subtitle">Database initialization and directory setup</p>

  <?php foreach ($success as $msg): ?>
  <div class="msg ok"><?= $msg ?></div>
  <?php endforeach; ?>

  <?php foreach ($errors as $err): ?>
  <div class="msg err"><?= $err ?></div>
  <?php endforeach; ?>

  <?php if (empty($errors)): ?>
  <div class="warning">
    <strong>⚠️ Security Notice:</strong><br>
    Please delete or restrict access to <code>setup/index.php</code> after setup is complete.<br>
    Change the admin password immediately after first login.
  </div>
  <?php endif; ?>
</div>
</body>
</html>
