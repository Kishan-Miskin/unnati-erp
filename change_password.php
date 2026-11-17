<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$user = currentUser();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current)) $errors[] = 'Current password is required.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm) $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        $dbUser = db()->fetchOne("SELECT password FROM users WHERE id = ?", [$user['id']]);
        if (!$dbUser || !password_verify($current, $dbUser['password'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT, ['cost' => 12]);
            db()->execute("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?", [$hashed, $user['id']]);
            logActivity('Changed password', 'auth', $user['id'], 'user');
            $success = 'Password changed successfully.';
        }
    }
}

$pageTitle  = 'Change Password';
$activePage = 'change_password';
include __DIR__ . '/includes/header.php';
?>
<div class="page-header">
    <div class="page-header-left">
        <h1 class="page-title"><i class="fas fa-key text-primary" style="margin-right:10px"></i>Change Password</h1>
        <p class="page-subtitle">Update your login password</p>
    </div>
</div>

<div style="max-width:520px">
<div class="card">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-shield-alt"></i> Security</span>
    </div>
    <div class="card-body">
        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= e($success) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= e($err) ?></div>
        <?php endforeach; ?>

        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Current Password <span class="required">*</span></label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password <span class="required">*</span></label>
                <input type="password" name="new_password" class="form-control" required minlength="8">
                <div class="form-hint">Minimum 8 characters</div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-save"></i> Change Password
            </button>
        </form>
    </div>
</div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
