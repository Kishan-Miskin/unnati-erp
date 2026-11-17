<?php
/**
 * Application Configuration — Unnatti Enterprises Mini ERP
 */

// Check PHP version FIRST - Critical security requirement
require_once __DIR__ . '/version_check.php';

// Application
define('APP_NAME', 'Unnatti ERP');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/unnatti');
define('APP_ROOT', dirname(__DIR__));

// Paths
define('UPLOAD_PATH', APP_ROOT . '/uploads/');
define('EXPORT_PATH', APP_ROOT . '/exports/');
define('VENDOR_PATH', APP_ROOT . '/vendor/');

// Session
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds
define('SESSION_NAME', 'unnatti_erp_session');

// Security
define('CSRF_TOKEN_LENGTH', 32);

// Pagination
define('DEFAULT_PAGE_SIZE', 25);

// File upload limits
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_IMPORT_TYPES', ['text/csv', 'application/vnd.ms-excel', 
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);

// Load database
require_once __DIR__ . '/database.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/logs/error.log');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Helper: get setting from DB (cached in session)
function getSetting(string $key, string $default = ''): string {
    if (!isset($_SESSION['_settings'])) {
        try {
            $rows = db()->fetchAll("SELECT setting_key, setting_value FROM settings");
            $_SESSION['_settings'] = array_column($rows, 'setting_value', 'setting_key');
        } catch (Exception $e) {
            return $default;
        }
    }
    return $_SESSION['_settings'][$key] ?? $default;
}

// Helper: clear settings cache (call after updating settings)
function clearSettingsCache(): void {
    unset($_SESSION['_settings']);
}
