<?php
$page_title = 'Budgets';
$page_subtitle = 'Department budgets by fiscal period.';
$active_module = 'budget';
$breadcrumb = ['Budget Management'];
require_once __DIR__ . '/../../includes/header.php';

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT b.*, p.period_name,
        (SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_lines WHERE budget_id = b.budget_id) AS total_budgeted
        FROM budgets b JOIN fiscal_periods p ON p.period_id = b.period_id";
$params = [];
if ($status_filter) {
    $sql .= " WHERE b.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$budgets = $stmt->fetchAll();

$statuses = ['Draft', 'Approved', 'Closed'];
$periods = $pdo->query("SELECT period_id, period_name FROM fiscal_periods ORDER BY start_date DESC")->fetchAll();
$accounts = $pdo->query("SELECT account_id, account_code, account_name, account_type FROM chart_of_accounts WHERE account_type IN ('Revenue','Expense') AND is_active = 1 ORDER BY account_code")->fetchAll();
$auto_open = isset($_GET['new']);
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="budgets.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($statuses as $s): ?>
        <a href="?status=<?= urlencode($s) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === $s ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="document.getElementById('create-modal').showModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        New Budget
    </button>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Budget Name</th>
                    <th class="px-3 py-3 font-medium">Period</th>
                    <th class="px-3 py-3 font-medium">Department</th>
                    <th class="px-3 py-3 font-medium text-right">Total Budgeted</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$budgets): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-ink/40">No budgets found. <button type="button" onclick="document.getElementById('create-modal').showModal()" class="text-primary font-medium hover:underline">Create the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($budgets as $b): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-medium text-ink"><?= e($b['budget_name']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= e($b['period_name']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= e($b['department'] ?: '—') ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= money((float)$b['total_budgeted']) ?></td>
                    <td class="px-3 py-3 text-center"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= badge_class($b['status']) ?>"><?= e($b['status']) ?></span></td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="budget-vs-actual.php?id=<?= (int)$b['budget_id'] ?>" class="text-primary hover:underline text-xs font-semibold">View</a>
                            <?php if ($b['status'] === 'Draft'): ?>
                            <form method="post" action="budget-save.php" class="inline" data-confirm="Delete this draft budget?">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="budget_id" value="<?= (int)$b['budget_id'] ?>">
                                <button type="submit" class="text-rose-500 hover:underline text-xs font-semibold">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="create-modal" class="tf-modal tf-modal-xl">
    <div class="tf-modal-panel">
        <form method="post" action="budget-save.php">
            <input type="hidden" name="action" value="create">
            <div class="tf-modal-header">
                <div>
                    <h3>New Budget</h3>
                    <p>Multi-line budget for a fiscal period — saved as Draft</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="this.closest('dialog').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="tf-modal-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="tf-modal-label">Budget Name *</label>
                        <input required name="budget_name" placeholder="e.g. FY2026 Tour Operations Budget" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Fiscal Period *</label>
                        <select required name="period_id" class="tf-input w-full">
                            <option value="">Select period…</option>
                            <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['period_id'] ?>"><?= e($p['period_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="tf-modal-label">Department</label>
                        <input name="department" placeholder="e.g. Tour Operations, Marketing" class="tf-input w-full">
                    </div>
                </div>

                <div class="rounded-xl border border-black/5 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 bg-canvas/40">
                        <span class="text-sm font-semibold text-ink">Budget Lines</span>
                        <button type="button" id="budget-add-line" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            Add Line
                        </button>
                    </div>
                    <div class="overflow-x-auto thin-scroll">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                                    <th class="px-4 py-2.5 font-medium w-2/5">Account</th>
                                    <th class="px-2 py-2.5 font-medium">Notes</th>
                                    <th class="px-2 py-2.5 font-medium text-right w-36">Budgeted Amount</th>
                                    <th class="px-3 py-2.5 font-medium w-8"></th>
                                </tr>
                            </thead>
                            <tbody id="budget-lines-body" class="divide-y divide-black/5">
                                <tr>
                                    <td class="px-4 py-2">
                                        <select name="account_id[]" required class="tf-input w-full py-1.5">
                                            <option value="">Select…</option>
                                            <?php foreach ($accounts as $a): ?>
                                            <option value="<?= $a['account_id'] ?>"><?= e($a['account_code'] . ' — ' . $a['account_name'] . ' (' . $a['account_type'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2"><input name="notes[]" class="tf-input w-full py-1.5" placeholder="optional"></td>
                                    <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="budgeted_amount[]" class="budget-amount tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
                                    <td class="px-3 py-2 text-center"><button type="button" class="budget-remove-line text-ink/30 hover:text-rose-500"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-end px-4 py-3 bg-canvas/50 border-t border-black/5 text-sm font-mono">
                        <span>Total: <strong id="budget-total">₱0.00</strong></span>
                    </div>
                </div>
            </div>
            <div class="tf-modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Save as Draft</button>
            </div>
        </form>
    </div>
</dialog>

<template id="budget-line-template">
    <tr>
        <td class="px-4 py-2">
            <select name="account_id[]" required class="tf-input w-full py-1.5">
                <option value="">Select…</option>
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['account_id'] ?>"><?= e($a['account_code'] . ' — ' . $a['account_name'] . ' (' . $a['account_type'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-2 py-2"><input name="notes[]" class="tf-input w-full py-1.5" placeholder="optional"></td>
        <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="budgeted_amount[]" class="budget-amount tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
        <td class="px-3 py-2 text-center"><button type="button" class="budget-remove-line text-ink/30 hover:text-rose-500"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></td>
    </tr>
</template>

<?php if ($auto_open): ?>
<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('create-modal')?.showModal());</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
