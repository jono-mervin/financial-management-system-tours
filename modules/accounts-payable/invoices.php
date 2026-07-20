<?php
$page_title = 'AP Invoices';
$page_subtitle = 'Vendor bills posted to accounts payable.';
$active_module = 'ap';
$breadcrumb = ['Accounts Payable', 'Invoices'];
require_once __DIR__ . '/../../includes/header.php';

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT i.*, v.vendor_name FROM ap_invoices i JOIN vendors v ON v.vendor_id = i.vendor_id ORDER BY i.invoice_date DESC, i.ap_invoice_id DESC";
$all = $pdo->query($sql)->fetchAll();

$rows = array_filter($all, function ($r) use ($status_filter) {
    if (!$status_filter) return true;
    return invoice_display_status($r['status'], $r['due_date']) === $status_filter;
});

$statuses = ['Unpaid', 'Partially Paid', 'Overdue', 'Paid', 'Cancelled'];
$vendors = $pdo->query("SELECT vendor_id, vendor_name, vendor_type FROM vendors WHERE is_active = 1 ORDER BY vendor_name")->fetchAll();
$expense_accounts = $pdo->query("SELECT account_id, account_code, account_name FROM chart_of_accounts WHERE account_type = 'Expense' AND is_active = 1 ORDER BY account_code")->fetchAll();
$auto_open = isset($_GET['new']);
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="invoices.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($statuses as $s): ?>
        <a href="?status=<?= urlencode($s) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $status_filter === $s ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3">
        <a href="aging-report.php" class="text-sm font-medium text-primary hover:underline">Aging Report →</a>
        <button type="button" onclick="document.getElementById('create-modal').showModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            New Invoice
        </button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Invoice No.</th>
                    <th class="px-3 py-3 font-medium">Vendor</th>
                    <th class="px-3 py-3 font-medium">Due Date</th>
                    <th class="px-3 py-3 font-medium text-right">Amount</th>
                    <th class="px-3 py-3 font-medium text-right">Balance</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$rows): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-ink/40">No invoices found. <button type="button" onclick="document.getElementById('create-modal').showModal()" class="text-primary font-medium hover:underline">Create the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): $disp = invoice_display_status($r['status'], $r['due_date']); $balance = $r['amount'] - $r['amount_paid']; ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($r['invoice_no']) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($r['vendor_name']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= date('M j, Y', strtotime($r['due_date'])) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= money((float)$r['amount']) ?></td>
                    <td class="px-3 py-3 text-right font-mono <?= $balance > 0 ? 'font-semibold text-amber-600' : 'text-ink/40' ?>"><?= money((float)$balance) ?></td>
                    <td class="px-3 py-3 text-center"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= badge_class($disp) ?>"><?= e($disp) ?></span></td>
                    <td class="px-6 py-3 text-right"><a href="invoice-view.php?id=<?= (int)$r['ap_invoice_id'] ?>" class="text-primary hover:underline text-xs font-semibold">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="create-modal" class="tf-modal">
    <div class="tf-modal-panel">
        <form method="post" action="invoice-save.php">
            <input type="hidden" name="action" value="create">
            <div class="tf-modal-header">
                <div>
                    <h3>New AP Invoice</h3>
                    <p>Creates the bill and posts it to the General Ledger</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="this.closest('dialog').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="tf-modal-body space-y-4">
                <div>
                    <label class="tf-modal-label">Vendor *</label>
                    <select required name="vendor_id" class="tf-input w-full">
                        <option value="">Select vendor…</option>
                        <?php foreach ($vendors as $v): ?>
                        <option value="<?= $v['vendor_id'] ?>"><?= e($v['vendor_name'] . ' — ' . $v['vendor_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$vendors): ?><p class="text-xs text-rose-500 mt-1.5">No active vendors — <a href="vendors.php" class="underline">add one first</a>.</p><?php endif; ?>
                </div>
                <div>
                    <label class="tf-modal-label">Vendor Invoice / Reference No.</label>
                    <input name="vendor_invoice_no" class="tf-input w-full" placeholder="Their invoice number">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Invoice Date *</label>
                        <input required type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Due Date *</label>
                        <input required type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="tf-input w-full">
                    </div>
                </div>
                <div>
                    <label class="tf-modal-label">Amount *</label>
                    <input required type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" class="tf-input w-full font-mono">
                </div>
                <div>
                    <label class="tf-modal-label">Expense / Cost Account *</label>
                    <select required name="expense_account_id" class="tf-input w-full">
                        <option value="">Select account…</option>
                        <?php foreach ($expense_accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= e($a['account_code'] . ' — ' . $a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tf-modal-note">Posts <strong>Debit</strong> the selected expense / <strong>Credit</strong> Accounts Payable immediately.</div>
            </div>
            <div class="tf-modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Create &amp; Post</button>
            </div>
        </form>
    </div>
</dialog>

<?php if ($auto_open): ?>
<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('create-modal')?.showModal());</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
