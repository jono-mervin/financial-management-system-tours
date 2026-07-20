<?php
$page_title = 'Invoice Detail';
$page_subtitle = 'Invoice amounts, status, and linked journal entry.';
$active_module = 'ar';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT i.*, c.customer_name, c.customer_type, c.email, c.phone FROM ar_invoices i JOIN customers c ON c.customer_id = i.customer_id WHERE i.ar_invoice_id = ?");
$stmt->execute([$id]);
$inv = $stmt->fetch();

if (!$inv) { flash('error', 'Invoice not found.'); header('Location: invoices.php'); exit; }

$breadcrumb = ['Accounts Receivable', 'Invoices', $inv['invoice_no']];
require_once __DIR__ . '/../../includes/header.php';

$disp = invoice_display_status($inv['status'], $inv['due_date']);
$balance = $inv['amount'] - $inv['amount_received'];

$collections = $pdo->prepare("SELECT * FROM collections WHERE ar_invoice_id = ? ORDER BY collection_date");
$collections->execute([$id]);
$collections = $collections->fetchAll();
?>

<div class="flex items-center justify-between mb-5 -mt-2">
    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= badge_class($disp) ?>"><?= e($disp) ?></span>
    <div class="flex items-center gap-3 no-print">
        <button onclick="window.print()" class="text-sm font-medium text-ink/60 hover:text-ink flex items-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
        <?php if ($balance > 0 && $inv['status'] !== 'Cancelled'): ?>
        <a href="collections.php?ar_invoice_id=<?= $id ?>" class="bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2 rounded-xl">Record Collection</a>
        <?php endif; ?>
        <?php if ($inv['status'] === 'Unpaid' && $inv['amount_received'] == 0): ?>
        <form method="post" action="invoice-save.php" data-confirm="Cancel this invoice? Its journal entry will be voided.">
            <input type="hidden" name="action" value="cancel">
            <input type="hidden" name="ar_invoice_id" value="<?= $id ?>">
            <button type="submit" class="text-rose-500 hover:underline text-sm font-medium">Cancel Invoice</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 p-8 mb-6">
    <div class="flex items-start justify-between pb-6 mb-6 border-b border-black/5">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">AR Invoice</p>
            <h2 class="font-display font-bold text-2xl text-ink font-mono"><?= e($inv['invoice_no']) ?></h2>
        </div>
        <div class="text-right text-sm">
            <p class="text-ink/40">Due Date</p>
            <p class="font-medium text-ink"><?= date('F j, Y', strtotime($inv['due_date'])) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-8 text-sm">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Customer</p>
            <p class="text-ink font-medium"><?= e($inv['customer_name']) ?></p>
            <p class="text-ink/40 text-xs mt-0.5"><?= e($inv['customer_type']) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Booking Reference</p>
            <p class="text-ink"><?= e($inv['booking_ref'] ?: '—') ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Invoice Date</p>
            <p class="text-ink"><?= date('F j, Y', strtotime($inv['invoice_date'])) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-2">
        <div class="pass-card p-4">
            <p class="text-xs text-ink/40 uppercase tracking-wide">Invoice Amount</p>
            <p class="font-mono text-xl font-bold text-ink mt-1"><?= money((float)$inv['amount']) ?></p>
        </div>
        <div class="pass-card p-4">
            <p class="text-xs text-ink/40 uppercase tracking-wide">Received</p>
            <p class="font-mono text-xl font-bold text-emerald-600 mt-1"><?= money((float)$inv['amount_received']) ?></p>
        </div>
        <div class="pass-card p-4">
            <p class="text-xs text-ink/40 uppercase tracking-wide">Balance Due</p>
            <p class="font-mono text-xl font-bold <?= $balance > 0 ? 'text-amber-600' : 'text-ink' ?> mt-1"><?= money((float)$balance) ?></p>
        </div>
    </div>

    <?php if ($inv['linked_entry_id']): ?>
    <p class="text-xs text-ink/40 mt-6">Posted to General Ledger: <a href="../general-ledger/journal-entry-view.php?id=<?= (int)$inv['linked_entry_id'] ?>" class="text-primary hover:underline font-medium">View journal entry →</a></p>
    <?php endif; ?>
</div>

<?php if ($collections): ?>
<div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
    <div class="px-6 py-4 border-b border-black/5"><h3 class="font-display font-semibold text-ink">Collection History</h3></div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                <th class="px-6 py-3 font-medium">Collection No.</th>
                <th class="px-3 py-3 font-medium">Date</th>
                <th class="px-3 py-3 font-medium">Method</th>
                <th class="px-6 py-3 font-medium text-right">Amount</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-black/5">
            <?php foreach ($collections as $c): ?>
            <tr class="hover:bg-canvas/60 cursor-pointer" onclick="window.location='collection-view.php?id=<?= (int)$c['collection_id'] ?>'">
                <td class="px-6 py-3 font-mono text-xs text-primary font-semibold hover:underline"><?= e($c['collection_no']) ?></td>
                <td class="px-3 py-3 text-ink/60"><?= date('M j, Y', strtotime($c['collection_date'])) ?></td>
                <td class="px-3 py-3 text-ink/60"><?= e($c['payment_method']) ?></td>
                <td class="px-6 py-3 text-right font-mono"><?= money((float)$c['amount']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
