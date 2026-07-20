<?php
$page_title = 'Journal Entries';
$page_subtitle = 'Draft, posted, and voided double-entry transactions.';
$active_module = 'gl';
$breadcrumb = ['General Ledger', 'Journal Entries'];
require_once __DIR__ . '/../../includes/header.php';

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT je.*, (SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines WHERE entry_id = je.entry_id) AS total
        FROM journal_entries je";
$params = [];
if ($status_filter) {
    $sql .= " WHERE je.status = ?";
    $params[] = $status_filter;
}
$sql .= " ORDER BY je.entry_date DESC, je.entry_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll();

$statuses = ['Draft', 'Posted', 'Void'];
$accounts = $pdo->query("SELECT account_id, account_code, account_name FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();

$entry = null;
$lines = [
    ['account_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''],
    ['account_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''],
];
$edit_id = (int)($_GET['edit'] ?? 0);
$auto_open = isset($_GET['new']) || $edit_id > 0;

if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
    $stmt->execute([$edit_id]);
    $entry = $stmt->fetch();
    if (!$entry) {
        flash('error', 'Journal entry not found.');
        header('Location: journal-entries.php');
        exit;
    }
    if ($entry['status'] !== 'Draft') {
        flash('error', 'Only draft entries can be edited.');
        header('Location: journal-entry-view.php?id=' . $edit_id);
        exit;
    }
    $lstmt = $pdo->prepare("SELECT * FROM journal_entry_lines WHERE entry_id = ? ORDER BY line_order");
    $lstmt->execute([$edit_id]);
    $loaded = $lstmt->fetchAll();
    if ($loaded) $lines = $loaded;
}
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="journal-entries.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($statuses as $s): ?>
        <a href="?status=<?= urlencode($s) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === $s ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
    </div>
    <button type="button" onclick="openJeModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        New Journal Entry
    </button>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Entry No.</th>
                    <th class="px-3 py-3 font-medium">Date</th>
                    <th class="px-3 py-3 font-medium">Description</th>
                    <th class="px-3 py-3 font-medium">Source</th>
                    <th class="px-3 py-3 font-medium text-right">Amount</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$entries): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-ink/40">No journal entries found. <button type="button" onclick="openJeModal()" class="text-primary font-medium hover:underline">Create the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $row): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($row['entry_no']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= date('M j, Y', strtotime($row['entry_date'])) ?></td>
                    <td class="px-3 py-3 text-ink truncate max-w-[240px]"><?= e($row['description']) ?></td>
                    <td class="px-3 py-3 text-ink/50 text-xs"><?= e($row['source_module']) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= money((float)$row['total']) ?></td>
                    <td class="px-3 py-3 text-center"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= badge_class($row['status']) ?>"><?= e($row['status']) ?></span></td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="journal-entry-view.php?id=<?= (int)$row['entry_id'] ?>" class="text-primary hover:underline text-xs font-semibold">View</a>
                            <?php if ($row['status'] === 'Draft'): ?>
                                <a href="?edit=<?= (int)$row['entry_id'] ?>" class="text-primary hover:underline text-xs font-semibold">Edit</a>
                                <form method="post" action="journal-entry-save.php" class="inline" data-confirm="Post this entry? It will affect account balances.">
                                    <input type="hidden" name="action" value="post">
                                    <input type="hidden" name="entry_id" value="<?= (int)$row['entry_id'] ?>">
                                    <button type="submit" class="text-emerald-600 hover:underline text-xs font-semibold">Post</button>
                                </form>
                            <?php elseif ($row['status'] === 'Posted'): ?>
                                <form method="post" action="journal-entry-save.php" class="inline" data-confirm="Void this posted entry? This will reverse it from all reports.">
                                    <input type="hidden" name="action" value="void">
                                    <input type="hidden" name="entry_id" value="<?= (int)$row['entry_id'] ?>">
                                    <button type="submit" class="text-rose-500 hover:underline text-xs font-semibold">Void</button>
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

<dialog id="je-modal" class="tf-modal tf-modal-xl">
    <div class="tf-modal-panel">
        <form method="post" action="journal-entry-save.php">
            <input type="hidden" name="action" id="je-action" value="<?= $entry ? 'update' : 'create' ?>">
            <?php if ($entry): ?><input type="hidden" name="entry_id" value="<?= (int)$entry['entry_id'] ?>"><?php endif; ?>
            <div class="tf-modal-header">
                <div>
                    <h3 id="je-modal-title"><?= $entry ? 'Edit Journal Entry' : 'New Journal Entry' ?></h3>
                    <p id="je-modal-sub"><?= $entry ? e($entry['entry_no']) . ' · Draft' : 'Balanced debit and credit lines saved as draft' ?></p>
                </div>
                <button type="button" class="tf-modal-close" onclick="this.closest('dialog').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="tf-modal-body space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="tf-modal-label">Entry Date *</label>
                        <input required type="date" name="entry_date" value="<?= e($entry['entry_date'] ?? date('Y-m-d')) ?>" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Reference</label>
                        <input name="reference" value="<?= e($entry['reference'] ?? '') ?>" placeholder="Booking ref, PO#, invoice#" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Source Module</label>
                        <select name="source_module" class="tf-input w-full">
                            <?php foreach (['Manual','General Ledger','Accounts Payable','Accounts Receivable','Disbursement','Collection','Budget'] as $sm): ?>
                            <option value="<?= $sm ?>" <?= ($entry['source_module'] ?? 'Manual') === $sm ? 'selected' : '' ?>><?= $sm ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="tf-modal-label">Description *</label>
                        <input required name="description" value="<?= e($entry['description'] ?? '') ?>" placeholder="e.g. Record deposit for tour package" class="tf-input w-full">
                    </div>
                </div>

                <div class="rounded-xl border border-black/5 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-black/5 bg-canvas/40">
                        <span class="text-sm font-semibold text-ink">Debit &amp; Credit Lines</span>
                        <button type="button" id="je-add-line" class="text-xs font-semibold text-primary hover:underline flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            Add Line
                        </button>
                    </div>
                    <div class="overflow-x-auto thin-scroll">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                                    <th class="px-4 py-2.5 font-medium w-2/5">Account</th>
                                    <th class="px-2 py-2.5 font-medium">Memo</th>
                                    <th class="px-2 py-2.5 font-medium text-right w-28">Debit</th>
                                    <th class="px-2 py-2.5 font-medium text-right w-28">Credit</th>
                                    <th class="px-3 py-2.5 font-medium w-8"></th>
                                </tr>
                            </thead>
                            <tbody id="je-lines-body" class="divide-y divide-black/5">
                                <?php foreach ($lines as $line): ?>
                                <tr>
                                    <td class="px-4 py-2">
                                        <select name="account_id[]" required class="tf-input w-full py-1.5">
                                            <option value="">Select…</option>
                                            <?php foreach ($accounts as $acc): ?>
                                            <option value="<?= $acc['account_id'] ?>" <?= ($line['account_id'] ?? '') == $acc['account_id'] ? 'selected' : '' ?>><?= e($acc['account_code'] . ' — ' . $acc['account_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2"><input name="memo[]" value="<?= e($line['memo'] ?? '') ?>" class="tf-input w-full py-1.5" placeholder="optional"></td>
                                    <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="debit[]" value="<?= isset($line['debit']) && $line['debit'] !== '' && $line['debit'] != 0 ? e($line['debit']) : '' ?>" class="je-debit tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
                                    <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="credit[]" value="<?= isset($line['credit']) && $line['credit'] !== '' && $line['credit'] != 0 ? e($line['credit']) : '' ?>" class="je-credit tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
                                    <td class="px-3 py-2 text-center"><button type="button" class="je-remove-line text-ink/30 hover:text-rose-500"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between px-4 py-3 bg-canvas/50 border-t border-black/5">
                        <span id="je-balance-status" class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 text-slate-500 ring-1 ring-slate-500/20 px-3 py-1 text-xs font-semibold">Enter amounts</span>
                        <div class="flex items-center gap-5 text-sm font-mono">
                            <span>Debit: <strong id="je-total-debit">0.00</strong></span>
                            <span>Credit: <strong id="je-total-credit">0.00</strong></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tf-modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" id="je-submit-btn" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light disabled:opacity-40 disabled:cursor-not-allowed transition-colors">Save as Draft</button>
            </div>
        </form>
    </div>
</dialog>

<template id="je-line-template">
    <tr>
        <td class="px-4 py-2">
            <select name="account_id[]" required class="tf-input w-full py-1.5">
                <option value="">Select…</option>
                <?php foreach ($accounts as $acc): ?>
                <option value="<?= $acc['account_id'] ?>"><?= e($acc['account_code'] . ' — ' . $acc['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td class="px-2 py-2"><input name="memo[]" class="tf-input w-full py-1.5" placeholder="optional"></td>
        <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="debit[]" class="je-debit tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
        <td class="px-2 py-2"><input type="number" step="0.01" min="0" name="credit[]" class="je-credit tf-input w-full py-1.5 text-right font-mono" placeholder="0.00"></td>
        <td class="px-3 py-2 text-center"><button type="button" class="je-remove-line text-ink/30 hover:text-rose-500"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg></button></td>
    </tr>
</template>

<script>
function openJeModal() {
    // Fresh create: if currently editing, go to clean create URL
    <?php if ($entry): ?>
    window.location = 'journal-entries.php?new=1';
    <?php else: ?>
    document.getElementById('je-modal').showModal();
    <?php endif; ?>
}
<?php if ($auto_open): ?>
document.addEventListener('DOMContentLoaded', () => document.getElementById('je-modal')?.showModal());
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
