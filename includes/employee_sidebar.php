<?php
/**
 * Employee Sidebar Navigation
 */
$currentPath = $_SERVER['PHP_SELF'] ?? '';
$appUrl = APP_URL;
$businessName = getSetting('business_name', 'Unnatti Enterprises');

function isEmpNavActive(string $path, string $current): string {
    return (strpos($current, $path) !== false) ? ' active' : '';
}
?>
<nav class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-store"></i></div>
        <div class="brand-info">
            <h4 class="brand-name"><?= e($businessName) ?></h4>
            <span class="brand-tagline">Employee Portal</span>
        </div>
    </div>
    <div class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="nav-item<?= isEmpNavActive('/employee/dashboard', $currentPath) ?>">
                <a href="<?= $appUrl ?>/employee/dashboard.php">
                    <span class="nav-icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-section-label"><span>Billing</span></li>
            <li class="nav-item<?= isEmpNavActive('/employee/create_bill', $currentPath) ?>">
                <a href="<?= $appUrl ?>/employee/create_bill.php">
                    <span class="nav-icon"><i class="fas fa-file-invoice"></i></span>
                    <span class="nav-text">Create Bill</span>
                </a>
            </li>
            <li class="nav-item<?= isEmpNavActive('/employee/my_bills', $currentPath) ?>">
                <a href="<?= $appUrl ?>/employee/my_bills.php">
                    <span class="nav-icon"><i class="fas fa-receipt"></i></span>
                    <span class="nav-text">My Bills</span>
                </a>
            </li>
            <li class="nav-item<?= isEmpNavActive('/employee/sales_history', $currentPath) ?>">
                <a href="<?= $appUrl ?>/employee/sales_history.php">
                    <span class="nav-icon"><i class="fas fa-chart-line"></i></span>
                    <span class="nav-text">My Sales</span>
                </a>
            </li>
            <?php if (canEnterStock()): ?>
            <li class="nav-section-label"><span>Stock</span></li>
            <li class="nav-item<?= isEmpNavActive('/employee/stock_entry', $currentPath) ?>">
                <a href="<?= $appUrl ?>/employee/stock_entry.php">
                    <span class="nav-icon"><i class="fas fa-boxes"></i></span>
                    <span class="nav-text">Stock Entry</span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-section-label"><span>Account</span></li>
            <li class="nav-item">
                <a href="<?= $appUrl ?>/change_password.php">
                    <span class="nav-icon"><i class="fas fa-key"></i></span>
                    <span class="nav-text">Change Password</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="<?= $appUrl ?>/logout.php">
                    <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar-sm"><?= strtoupper(substr($user['name'] ?? 'E', 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name-sm"><?= e($user['name'] ?? 'Employee') ?></span>
                <span class="user-role-sm">Employee</span>
            </div>
        </div>
    </div>
</nav>
