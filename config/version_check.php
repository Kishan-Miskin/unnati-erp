<?php
/**
 * PHP Version Check — Unnatti Enterprises Mini ERP
 * Ensures application runs only on secure PHP versions
 * 
 * SECURITY REQUIREMENT: PHP 8.4.27+ or PHP 8.3.36+
 * Critical vulnerabilities CVE-2026-109618 and CVE-2026-109619 patched
 */

// Minimum required PHP version
define('REQUIRED_PHP_VERSION', '8.4.27');
define('MIN_SECURE_PHP_VERSION', '8.3.36');

/**
 * Check if current PHP version meets security requirements
 */
function checkPHPVersion(): void {
    $currentVersion = PHP_VERSION;
    
    // Check if running minimum secure version
    if (version_compare($currentVersion, MIN_SECURE_PHP_VERSION, '<')) {
        displayVersionError(
            "CRITICAL SECURITY WARNING",
            "Your PHP version ({$currentVersion}) has critical security vulnerabilities!",
            "CVE-2026-109618 and CVE-2026-109619 allow remote code execution.",
            "Required: PHP 8.4.27+ or PHP 8.3.36+"
        );
        exit(1);
    }
    
    // Warn if not using recommended version
    if (version_compare($currentVersion, REQUIRED_PHP_VERSION, '<')) {
        // Log warning but allow execution for 8.3.36+
        error_log("WARNING: PHP {$currentVersion} detected. Recommended: " . REQUIRED_PHP_VERSION . "+");
    }
}

/**
 * Display version error page
 */
function displayVersionError(string $title, string $message, string $details, string $requirement): void {
    http_response_code(500);
    
    if (PHP_SAPI === 'cli') {
        // Command line output
        echo "\n" . str_repeat("=", 70) . "\n";
        echo "ERROR: {$title}\n";
        echo str_repeat("=", 70) . "\n";
        echo "{$message}\n\n";
        echo "{$details}\n\n";
        echo "REQUIRED: {$requirement}\n";
        echo "CURRENT:  PHP " . PHP_VERSION . "\n";
        echo str_repeat("=", 70) . "\n\n";
    } else {
        // Web output
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 16px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    max-width: 600px;
                    width: 100%;
                    padding: 48px;
                    text-align: center;
                }
                .error-icon {
                    width: 80px;
                    height: 80px;
                    background: linear-gradient(135deg, #dc2626, #991b1b);
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    margin: 0 auto 24px;
                    font-size: 48px;
                    color: white;
                    animation: shake 0.5s;
                }
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-10px); }
                    75% { transform: translateX(10px); }
                }
                h1 {
                    color: #dc2626;
                    font-size: 28px;
                    font-weight: 700;
                    margin-bottom: 16px;
                }
                .message {
                    color: #1f2937;
                    font-size: 18px;
                    font-weight: 600;
                    margin-bottom: 12px;
                    line-height: 1.6;
                }
                .details {
                    color: #4b5563;
                    font-size: 15px;
                    margin-bottom: 24px;
                    line-height: 1.6;
                    background: #fef2f2;
                    padding: 16px;
                    border-radius: 8px;
                    border-left: 4px solid #dc2626;
                }
                .version-info {
                    background: #f9fafb;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 24px 0;
                    border: 2px solid #e5e7eb;
                }
                .version-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 8px 0;
                    border-bottom: 1px solid #e5e7eb;
                }
                .version-row:last-child { border-bottom: none; }
                .version-label {
                    color: #6b7280;
                    font-size: 14px;
                    font-weight: 500;
                }
                .version-value {
                    color: #1f2937;
                    font-size: 16px;
                    font-weight: 700;
                }
                .version-value.current { color: #dc2626; }
                .version-value.required { color: #059669; }
                .instructions {
                    background: #eff6ff;
                    padding: 20px;
                    border-radius: 8px;
                    border-left: 4px solid #2563eb;
                    text-align: left;
                    margin-top: 24px;
                }
                .instructions h3 {
                    color: #1e40af;
                    font-size: 16px;
                    margin-bottom: 12px;
                    font-weight: 600;
                }
                .instructions ol {
                    margin-left: 20px;
                    color: #1e3a8a;
                }
                .instructions li {
                    margin: 8px 0;
                    line-height: 1.6;
                }
                .instructions code {
                    background: #dbeafe;
                    padding: 2px 6px;
                    border-radius: 4px;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    color: #1e40af;
                }
                .footer {
                    margin-top: 24px;
                    padding-top: 24px;
                    border-top: 2px solid #e5e7eb;
                    color: #6b7280;
                    font-size: 13px;
                }
                .footer strong {
                    color: #1f2937;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                <h1><?= htmlspecialchars($title) ?></h1>
                <p class="message"><?= htmlspecialchars($message) ?></p>
                <p class="details"><?= htmlspecialchars($details) ?></p>
                
                <div class="version-info">
                    <div class="version-row">
                        <span class="version-label">Current PHP Version:</span>
                        <span class="version-value current"><?= PHP_VERSION ?></span>
                    </div>
                    <div class="version-row">
                        <span class="version-label">Minimum Required:</span>
                        <span class="version-value required"><?= htmlspecialchars($requirement) ?></span>
                    </div>
                </div>
                
                <div class="instructions">
                    <h3>🔧 How to Fix This:</h3>
                    <ol>
                        <li>Download <strong>XAMPP 8.4.27</strong> from <code>apachefriends.org</code></li>
                        <li>Install to <code>H:\xampp</code></li>
                        <li>Copy your project to <code>H:\xampp\htdocs\unnatti</code></li>
                        <li>Start Apache and MySQL from XAMPP Control Panel</li>
                        <li>Refresh this page</li>
                    </ol>
                </div>
                
                <div class="footer">
                    <strong>Unnatti Enterprises ERP</strong><br>
                    Protecting your business with secure software
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Run the version check
checkPHPVersion();
