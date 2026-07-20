<?php
$page_title = 'Audit Logs';
$page_subtitle = 'Complete trail of transactions and system actions across every module.';
$active_module = 'audit';
$breadcrumb = ['Audit Logs'];
require_once __DIR__ . '/../../includes/header.php';

$module_filter = $_GET['module'] ?? '';
$action_filter = $_GET['action'] ?? '';
$q = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM audit_logs WHERE 1=1";
$params = [];

if ($module_filter !== '') {
    $sql .= " AND module = ?";
    $params[] = $module_filter;
}
if ($action_filter !== '') {
    $sql .= " AND action = ?";
    $params[] = $action_filter;
}
if ($q !== '') {
    $sql .= " AND (description LIKE ? OR entity_no LIKE ? OR username LIKE ?)";
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY created_at DESC LIMIT 200";

$logs = [];
$modules = [];
$actions = [];
$table_ready = true;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    $modules = $pdo->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetchAll(PDO::FETCH_COLUMN);
    $actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $table_ready = false;
}

$action_colors = [
    'create'       => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'update'       => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'delete'       => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'post'         => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'void'         => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'cancel'       => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'approve'      => 'bg-violet-50 text-violet-700 ring-violet-600/20',
    'close'        => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    'login'        => 'bg-primary/10 text-primary ring-primary/20',
    'logout'       => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    'login_failed' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
];
?>

<?php if (!$table_ready): ?>
<div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-8 text-center">
    <p class="font-display font-semibold text-amber-900 mb-2">Audit log table not found</p>
    <p class="text-sm text-amber-800/70 mb-4">Import <code class="font-mono text-xs bg-white/60 px-1.5 py-0.5 rounded">database/migrations/001_audit_logs.sql</code> via phpMyAdmin to enable logging.</p>
</div>
<?php else: ?>

<form method="get" class="flex flex-wrap items-end gap-3 mb-5">
    <div class="flex-1 min-w-[180px]">
        <label class="tf-modal-label">Search</label>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Description, doc no, user…" class="tf-input w-full">
    </div>
    <div class="w-44">
        <label class="tf-modal-label">Module</label>
        <select name="module" class="tf-input w-full">
            <option value="">All modules</option>
            <?php foreach ($modules as $m): ?>
            <option value="<?= e($m) ?>" <?= $module_filter === $m ? 'selected' : '' ?>><?= e($m) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="w-40">
        <label class="tf-modal-label">Action</label>
        <select name="action" class="tf-input w-full">
            <option value="">All actions</option>
            <?php foreach ($actions as $a): ?>
            <option value="<?= e($a) ?>" <?= $action_filter === $a ? 'selected' : '' ?>><?= e($a) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Filter</button>
    <?php if ($module_filter || $action_filter || $q): ?>
    <a href="index.php" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/50 hover:bg-white ring-1 ring-black/5">Clear</a>
    <?php endif; ?>
</form>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">When</th>
                    <th class="px-3 py-3 font-medium">User</th>
                    <th class="px-3 py-3 font-medium">Action</th>
                    <th class="px-3 py-3 font-medium">Module</th>
                    <th class="px-3 py-3 font-medium">Entity</th>
                    <th class="px-6 py-3 font-medium">Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$logs): ?>
                <tr><td colspan="6" class="px-6 py-12 text-center text-ink/40">No audit entries yet. Actions across the system will appear here.</td></tr>
                <?php endif; ?>
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 whitespace-nowrap">
                        <span class="font-mono text-xs text-ink/70"><?= date('M j, Y', strtotime($log['created_at'])) ?></span>
                        <span class="block text-[11px] text-ink/35 font-mono"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                    </td>
                    <td class="px-3 py-3">
                        <span class="text-sm font-medium text-ink"><?= e($log['username'] ?? '—') ?></span>
                        <?php if ($log['ip_address']): ?>
                        <span class="block text-[10px] font-mono text-ink/30"><?= e($log['ip_address']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 <?= $action_colors[$log['action']] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20' ?>">
                            <?= e($log['action']) ?>
                        </span>
                    </td>
                    <td class="px-3 py-3 text-ink/60 text-xs"><?= e($log['module']) ?></td>
                    <td class="px-3 py-3">
                        <span class="text-xs text-ink/40"><?= e($log['entity_type']) ?></span>
                        <?php if ($log['entity_no']): ?>
                        <span class="block font-mono text-xs text-ink font-medium"><?= e($log['entity_no']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-ink/80 max-w-md"><?= e($log['description']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($logs): ?>
    <div class="px-6 py-3 border-t border-black/5 text-xs text-ink/35">Showing up to 200 most recent entries</div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
