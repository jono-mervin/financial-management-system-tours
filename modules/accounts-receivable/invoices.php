<?php
$page_title = 'AR Invoices';
$page_subtitle = 'Customer invoices posted to accounts receivable.';
$active_module = 'ar';
$breadcrumb = ['Accounts Receivable', 'Invoices'];
require_once __DIR__ . '/../../includes/header.php';

$status_filter = $_GET['status'] ?? '';
$sql = "SELECT i.*, c.customer_name FROM ar_invoices i JOIN customers c ON c.customer_id = i.customer_id ORDER BY i.invoice_date DESC, i.ar_invoice_id DESC";
$all = $pdo->query($sql)->fetchAll();

$rows = array_filter($all, function ($r) use ($status_filter) {
    if (!$status_filter) return true;
    return invoice_display_status($r['status'], $r['due_date']) === $status_filter;
});

$statuses = ['Unpaid', 'Partially Paid', 'Overdue', 'Paid', 'Cancelled'];
$customers = $pdo->query("SELECT customer_id, customer_name, customer_type FROM customers WHERE is_active = 1 ORDER BY customer_name")->fetchAll();
$revenue_accounts = $pdo->query("SELECT account_id, account_code, account_name FROM chart_of_accounts WHERE account_type = 'Revenue' AND is_active = 1 ORDER BY account_code")->fetchAll();
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
        <a href="collections.php" class="text-sm font-medium text-primary hover:underline">Collections →</a>
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
                    <th class="px-3 py-3 font-medium">Customer</th>
                    <th class="px-3 py-3 font-medium">Booking Ref</th>
                    <th class="px-3 py-3 font-medium">Due Date</th>
                    <th class="px-3 py-3 font-medium text-right">Amount</th>
                    <th class="px-3 py-3 font-medium text-right">Balance</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$rows): ?>
                <tr><td colspan="8" class="px-6 py-10 text-center text-ink/40">No invoices found. <button type="button" onclick="document.getElementById('create-modal').showModal()" class="text-primary font-medium hover:underline">Create the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): $disp = invoice_display_status($r['status'], $r['due_date']); $balance = $r['amount'] - $r['amount_received']; ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($r['invoice_no']) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($r['customer_name']) ?></td>
                    <td class="px-3 py-3 text-ink/50"><?= e($r['booking_ref'] ?: '—') ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= date('M j, Y', strtotime($r['due_date'])) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= money((float)$r['amount']) ?></td>
                    <td class="px-3 py-3 text-right font-mono <?= $balance > 0 ? 'font-semibold text-amber-600' : 'text-ink/40' ?>"><?= money((float)$balance) ?></td>
                    <td class="px-3 py-3 text-center"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= badge_class($disp) ?>"><?= e($disp) ?></span></td>
                    <td class="px-6 py-3 text-right"><a href="invoice-view.php?id=<?= (int)$r['ar_invoice_id'] ?>" class="text-primary hover:underline text-xs font-semibold">View</a></td>
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
                    <h3>New AR Invoice</h3>
                    <p>Creates the invoice and posts it to the General Ledger</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="this.closest('dialog').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="tf-modal-body space-y-4">
                <div>
                    <label class="tf-modal-label">Customer *</label>
                    <select required name="customer_id" class="tf-input w-full">
                        <option value="">Select customer…</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['customer_id'] ?>"><?= e($c['customer_name'] . ' — ' . $c['customer_type']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$customers): ?><p class="text-xs text-rose-500 mt-1.5">No active customers — <a href="customers.php" class="underline">add one first</a>.</p><?php endif; ?>
                </div>
                <div>
                    <label class="tf-modal-label">Booking Reference</label>
                    <input name="booking_ref" class="tf-input w-full" placeholder="e.g. TP-1044">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Invoice Date *</label>
                        <input required type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Due Date *</label>
                        <input required type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+15 days')) ?>" class="tf-input w-full">
                    </div>
                </div>
                <div>
                    <label class="tf-modal-label">Amount *</label>
                    <input required type="number" step="0.01" min="0.01" name="amount" placeholder="0.00" class="tf-input w-full font-mono">
                </div>
                <div>
                    <label class="tf-modal-label">Revenue Account *</label>
                    <select required name="revenue_account_id" class="tf-input w-full">
                        <option value="">Select revenue account…</option>
                        <?php foreach ($revenue_accounts as $a): ?>
                        <option value="<?= $a['account_id'] ?>"><?= e($a['account_code'] . ' — ' . $a['account_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tf-modal-note">Posts <strong>Debit</strong> Accounts Receivable / <strong>Credit</strong> the selected revenue account immediately.</div>
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
