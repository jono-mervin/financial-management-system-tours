<?php
$page_title = 'Collections';
$page_subtitle = 'Incoming receipts against AR invoices.';
$active_module = 'ar';
$breadcrumb = ['Accounts Receivable', 'Collections'];
require_once __DIR__ . '/../../includes/header.php';

$rows = $pdo->query("
    SELECT c.*, i.invoice_no, cu.customer_name
    FROM collections c
    JOIN ar_invoices i ON i.ar_invoice_id = c.ar_invoice_id
    JOIN customers cu ON cu.customer_id = i.customer_id
    ORDER BY c.collection_date DESC, c.collection_id DESC
")->fetchAll();

$total_this_month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE collection_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

$preselect_id = (int)($_GET['ar_invoice_id'] ?? 0);
$invoices = $pdo->query("
    SELECT i.ar_invoice_id, i.invoice_no, i.amount, i.amount_received, c.customer_name
    FROM ar_invoices i JOIN customers c ON c.customer_id = i.customer_id
    WHERE i.status IN ('Unpaid','Partially Paid')
    ORDER BY i.due_date ASC
")->fetchAll();
$auto_open = isset($_GET['new']) || $preselect_id > 0;
$methods = ['Bank Transfer', 'Cheque', 'Cash', 'Credit Card', 'Online Wallet'];
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="pass-card px-5 py-3 inline-flex items-center gap-3">
        <span class="text-xs text-ink/40 uppercase tracking-wide font-semibold">Collected this month</span>
        <span class="font-mono font-bold text-ink"><?= money($total_this_month) ?></span>
    </div>
    <button type="button" onclick="document.getElementById('create-modal').showModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
        Record Collection
    </button>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Collection No.</th>
                    <th class="px-3 py-3 font-medium">Date</th>
                    <th class="px-3 py-3 font-medium">Customer</th>
                    <th class="px-3 py-3 font-medium">Invoice</th>
                    <th class="px-3 py-3 font-medium">Method</th>
                    <th class="px-6 py-3 font-medium text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$rows): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-ink/40">No collections recorded yet. <button type="button" onclick="document.getElementById('create-modal').showModal()" class="text-primary font-medium hover:underline">Record one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-canvas/60 cursor-pointer" onclick="window.location='collection-view.php?id=<?= (int)$r['collection_id'] ?>'">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($r['collection_no']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= date('M j, Y', strtotime($r['collection_date'])) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($r['customer_name']) ?></td>
                    <td class="px-3 py-3 font-mono text-xs text-ink/50"><?= e($r['invoice_no']) ?></td>
                    <td class="px-3 py-3 text-ink/60"><?= e($r['payment_method']) ?></td>
                    <td class="px-6 py-3 text-right font-mono font-semibold"><?= money((float)$r['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="create-modal" class="tf-modal">
    <div class="tf-modal-panel">
        <form method="post" action="collection-save.php">
            <input type="hidden" name="action" value="create">
            <div class="tf-modal-header">
                <div>
                    <h3>Record Collection</h3>
                    <p>Receive payment against an outstanding AR invoice</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="this.closest('dialog').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <div class="tf-modal-body space-y-4">
                <div>
                    <label class="tf-modal-label">AR Invoice *</label>
                    <select required name="ar_invoice_id" id="invoice-select" data-balance-select data-balance-amount="#amount-input" data-balance-hint="#balance-hint" class="tf-input w-full">
                        <option value="">Select an outstanding invoice…</option>
                        <?php foreach ($invoices as $i): $balance = $i['amount'] - $i['amount_received']; ?>
                        <option value="<?= $i['ar_invoice_id'] ?>" data-balance="<?= $balance ?>" <?= $preselect_id == $i['ar_invoice_id'] ? 'selected' : '' ?>>
                            <?= e($i['customer_name'] . ' — ' . $i['invoice_no'] . ' (Balance: ' . number_format($balance, 2) . ')') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$invoices): ?><p class="text-xs text-rose-500 mt-1.5">No outstanding AR invoices — <a href="invoices.php?new=1" class="underline">create one first</a>.</p><?php endif; ?>
                    <p id="balance-hint" class="text-xs text-ink/40 mt-1.5"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Collection Date *</label>
                        <input required type="date" name="collection_date" value="<?= date('Y-m-d') ?>" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Payment Method *</label>
                        <select required name="payment_method" class="tf-input w-full">
                            <?php foreach ($methods as $m): ?><option value="<?= $m ?>"><?= $m ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="tf-modal-label">Bank Account</label>
                    <input name="bank_account" class="tf-input w-full" placeholder="e.g. BDO Operating - 1234">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Amount *</label>
                        <input required type="number" step="0.01" min="0.01" name="amount" id="amount-input" placeholder="0.00" class="tf-input w-full font-mono">
                    </div>
                    <div>
                        <label class="tf-modal-label">Reference No.</label>
                        <input name="reference_no" class="tf-input w-full" placeholder="OR / transfer ref">
                    </div>
                </div>
                <div class="tf-modal-note">Posts <strong>Debit</strong> Cash / <strong>Credit</strong> AR and updates the invoice balance.</div>
            </div>
            <div class="tf-modal-footer">
                <button type="button" onclick="this.closest('dialog').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-colors">Record &amp; Post</button>
            </div>
        </form>
    </div>
</dialog>

<?php if ($auto_open): ?>
<script>document.addEventListener('DOMContentLoaded', () => document.getElementById('create-modal')?.showModal());</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
