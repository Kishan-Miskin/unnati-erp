<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
if (isLoggedIn()) {
    logActivity('User logged out', 'auth');
    logoutUser();
}
header('Location: ' . APP_URL . '/login.php?msg=logged_out');
exit;
