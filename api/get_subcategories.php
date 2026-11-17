<?php
/** API: Get subcategories by parent_id */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
header('Content-Type: application/json');
requireLogin();

$parentId = (int)($_GET['parent_id'] ?? 0);
if (!$parentId) { jsonSuccess([], 'No parent'); }

$subs = db()->fetchAll(
    "SELECT id, name FROM categories WHERE parent_id=? AND status='active' ORDER BY name",
    [$parentId]
);
jsonSuccess($subs, 'Subcategories found');
