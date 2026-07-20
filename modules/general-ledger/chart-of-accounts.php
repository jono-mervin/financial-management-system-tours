<?php
$page_title = 'Chart of Accounts';
$page_subtitle = 'Maintain the account structure used across every financial module.';
$active_module = 'gl';
$breadcrumb = ['General Ledger', 'Chart of Accounts'];
require_once __DIR__ . '/../../includes/header.php';

$type_filter = $_GET['type'] ?? '';
$sql = "SELECT * FROM chart_of_accounts";
$params = [];
if ($type_filter) {
    $sql .= " WHERE account_type = ?";
    $params[] = $type_filter;
}
$sql .= " ORDER BY account_code ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll();

$types = ['Asset', 'Liability', 'Equity', 'Revenue', 'Expense'];
$type_colors = [
    'Asset'     => 'bg-sky-50 text-sky-700 ring-sky-600/20',
    'Liability' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
    'Equity'    => 'bg-violet-50 text-violet-700 ring-violet-600/20',
    'Revenue'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'Expense'   => 'bg-rose-50 text-rose-700 ring-rose-600/20',
];
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="chart-of-accounts.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($types as $t): ?>
        <a href="?type=<?= urlencode($t) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === $t ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
    </div>
    <button onclick="openAccountModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20 hover:scale-[1.02] shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Add Account
    </button>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Code</th>
                    <th class="px-3 py-3 font-medium">Account Name</th>
                    <th class="px-3 py-3 font-medium">Type</th>
                    <th class="px-3 py-3 font-medium">Subtype</th>
                    <th class="px-3 py-3 font-medium">Normal Balance</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$accounts): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-ink/40">No accounts found for this filter.</td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <tr class="hover:bg-canvas/60 <?= !$a['is_active'] ? 'opacity-40' : '' ?>">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($a['account_code']) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($a['account_name']) ?></td>
                    <td class="px-3 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= $type_colors[$a['account_type']] ?>"><?= e($a['account_type']) ?></span></td>
                    <td class="px-3 py-3 text-ink/50"><?= e($a['account_subtype'] ?? '—') ?></td>
                    <td class="px-3 py-3 text-ink/50"><?= e($a['normal_balance']) ?></td>
                    <td class="px-3 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $a['is_active'] ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/20' ?>"><?= $a['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button onclick='openAccountModal(<?= json_encode($a) ?>)' class="text-primary hover:underline text-xs font-semibold">Edit</button>
                            <form method="post" action="chart-of-accounts-save.php" class="inline" data-confirm="Delete this account? This cannot be undone.">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="account_id" value="<?= (int)$a['account_id'] ?>">
                                <button type="submit" class="text-rose-500 hover:underline text-xs font-semibold">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="account-modal" class="tf-modal">
    <div class="tf-modal-panel">
        <form method="post" action="chart-of-accounts-save.php">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="account_id" id="modal-account-id">

            <div class="tf-modal-header">
                <div>
                    <h3 id="modal-title">Add Account</h3>
                    <p id="modal-subtitle">Create a new chart of accounts entry</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="document.getElementById('account-modal').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="tf-modal-body space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Account Code *</label>
                        <input required name="account_code" id="modal-code" class="tf-input w-full font-mono" placeholder="e.g. 1030">
                    </div>
                    <div>
                        <label class="tf-modal-label">Normal Balance *</label>
                        <select required name="normal_balance" id="modal-normal" class="tf-input w-full">
                            <option value="Debit">Debit</option>
                            <option value="Credit">Credit</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="tf-modal-label">Account Name *</label>
                    <input required name="account_name" id="modal-name" class="tf-input w-full" placeholder="e.g. Cash in Bank - Operating">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Account Type *</label>
                        <select required name="account_type" id="modal-type" class="tf-input w-full">
                            <?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="tf-modal-label">Subtype</label>
                        <input name="account_subtype" id="modal-subtype" class="tf-input w-full" placeholder="e.g. Current Asset">
                    </div>
                </div>
                <div>
                    <label class="tf-modal-label">Description</label>
                    <textarea name="description" id="modal-description" rows="2" class="tf-input w-full"></textarea>
                </div>
                <label class="flex items-center gap-2.5 text-sm text-ink/70 cursor-pointer select-none">
                    <input type="checkbox" name="is_active" id="modal-active" checked class="rounded border-black/20 text-primary focus:ring-primary/30 w-4 h-4">
                    Active account
                </label>
            </div>

            <div class="tf-modal-footer">
                <button type="button" onclick="document.getElementById('account-modal').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-all hover:shadow-lg hover:shadow-primary/20">Save Account</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openAccountModal(data) {
    const modal = document.getElementById('account-modal');
    const isEdit = !!data;
    document.getElementById('modal-title').textContent = isEdit ? 'Edit Account' : 'Add Account';
    document.getElementById('modal-subtitle').textContent = isEdit ? 'Update account details' : 'Create a new chart of accounts entry';
    document.getElementById('modal-action').value = isEdit ? 'update' : 'create';
    document.getElementById('modal-account-id').value = isEdit ? data.account_id : '';
    document.getElementById('modal-code').value = isEdit ? data.account_code : '';
    document.getElementById('modal-name').value = isEdit ? data.account_name : '';
    document.getElementById('modal-type').value = isEdit ? data.account_type : 'Asset';
    document.getElementById('modal-subtype').value = isEdit ? (data.account_subtype || '') : '';
    document.getElementById('modal-normal').value = isEdit ? data.normal_balance : 'Debit';
    document.getElementById('modal-description').value = isEdit ? (data.description || '') : '';
    document.getElementById('modal-active').checked = isEdit ? !!Number(data.is_active) : true;
    modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
