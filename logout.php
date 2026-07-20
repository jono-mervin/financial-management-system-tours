<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    audit_log($pdo, [
        'action'      => 'logout',
        'module'      => 'Auth',
        'entity_type' => 'user',
        'entity_id'   => current_user_id(),
        'entity_no'   => $_SESSION['username'] ?? null,
        'description' => current_user_name() . ' signed out',
    ]);
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();
flash('success', 'You have been signed out.');
header('Location: login.php');
exit;
