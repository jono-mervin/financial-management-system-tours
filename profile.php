<?php
$page_title = 'Profile Settings';
$page_subtitle = 'Your account details and session information.';
$active_module = 'dashboard';
$breadcrumb = ['Profile'];
require_once __DIR__ . '/includes/header.php';

$user = null;
if (current_user_id()) {
    $stmt = $pdo->prepare("SELECT user_id, username, full_name, email, role, is_active, created_at FROM users WHERE user_id = ?");
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($full_name === '') {
            $error = 'Full name is required.';
        } else {
            $pdo->prepare("UPDATE users SET full_name=?, email=? WHERE user_id=?")->execute([$full_name, $email ?: null, $user['user_id']]);
            $_SESSION['full_name'] = $full_name;
            audit_log($pdo, [
                'action' => 'update', 'module' => 'Auth', 'entity_type' => 'user',
                'entity_id' => $user['user_id'], 'entity_no' => $user['username'],
                'description' => 'Updated profile settings',
            ]);
            $message = 'Profile updated.';
            $user['full_name'] = $full_name;
            $user['email'] = $email;
        }
    }
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $hash = $pdo->prepare("SELECT password_hash FROM users WHERE user_id=?");
        $hash->execute([$user['user_id']]);
        $stored = $hash->fetchColumn();
        if (!password_verify($current, $stored)) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $pdo->prepare("UPDATE users SET password_hash=? WHERE user_id=?")->execute([password_hash($new, PASSWORD_BCRYPT), $user['user_id']]);
            audit_log($pdo, [
                'action' => 'update', 'module' => 'Auth', 'entity_type' => 'user',
                'entity_id' => $user['user_id'], 'entity_no' => $user['username'],
                'description' => 'Changed account password',
            ]);
            $message = 'Password changed successfully.';
        }
    }
}
?>

<?php if ($message): ?>
<div class="mb-4 rounded-xl px-4 py-3 text-sm font-medium bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="mb-4 rounded-xl px-4 py-3 text-sm font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-600/20"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$user): ?>
<p class="text-ink/50">User not found.</p>
<?php else: ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl border border-black/5 p-6 tf-card">
        <h2 class="font-display font-semibold text-ink mb-5">Account details</h2>
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="update_profile">
            <div>
                <label class="tf-modal-label">Username</label>
                <input class="tf-input w-full bg-canvas/60" value="<?= e($user['username']) ?>" disabled>
            </div>
            <div>
                <label class="tf-modal-label">Full name</label>
                <input name="full_name" required class="tf-input w-full" value="<?= e($user['full_name']) ?>">
            </div>
            <div>
                <label class="tf-modal-label">Email</label>
                <input type="email" name="email" class="tf-input w-full" value="<?= e($user['email'] ?? '') ?>">
            </div>
            <div>
                <label class="tf-modal-label">Role</label>
                <input class="tf-input w-full bg-canvas/60" value="<?= e(role_label($user['role'])) ?>" disabled>
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Save changes</button>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-black/5 p-6 tf-card">
        <h2 class="font-display font-semibold text-ink mb-5">Change password</h2>
        <form method="post" class="space-y-4">
            <input type="hidden" name="action" value="change_password">
            <div>
                <label class="tf-modal-label">Current password</label>
                <input type="password" name="current_password" required class="tf-input w-full">
            </div>
            <div>
                <label class="tf-modal-label">New password</label>
                <input type="password" name="new_password" required minlength="6" class="tf-input w-full">
            </div>
            <div>
                <label class="tf-modal-label">Confirm new password</label>
                <input type="password" name="confirm_password" required minlength="6" class="tf-input w-full">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Update password</button>
        </form>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
