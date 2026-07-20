<?php
$page_title = 'Disbursement Detail';
$page_subtitle = 'Payment detail and linked ledger entry.';
$active_module = 'disb';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT d.*, i.invoice_no, i.amount AS invoice_amount, v.vendor_name
    FROM disbursements d
    JOIN ap_invoices i ON i.ap_invoice_id = d.ap_invoice_id
    JOIN vendors v ON v.vendor_id = i.vendor_id
    WHERE d.disbursement_id = ?
");
$stmt->execute([$id]);
$d = $stmt->fetch();

if (!$d) { flash('error', 'Disbursement not found.'); header('Location: disbursements.php'); exit; }

$breadcrumb = ['Disbursement Management', $d['disbursement_no']];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex items-center justify-between mb-5 -mt-2">
    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 bg-emerald-50 text-emerald-700 ring-emerald-600/20">Posted</span>
    <div class="flex items-center gap-3 no-print">
        <button onclick="window.print()" class="text-sm font-medium text-ink/60 hover:text-ink flex items-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
        <a href="../accounts-payable/invoice-view.php?id=<?= (int)$d['ap_invoice_id'] ?>" class="text-sm font-medium text-primary hover:underline">View Invoice →</a>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 p-8">
    <div class="flex items-start justify-between pb-6 mb-6 border-b border-black/5">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Disbursement</p>
            <h2 class="font-display font-bold text-2xl text-ink font-mono"><?= e($d['disbursement_no']) ?></h2>
        </div>
        <div class="text-right text-sm">
            <p class="text-ink/40">Payment Date</p>
            <p class="font-medium text-ink"><?= date('F j, Y', strtotime($d['payment_date'])) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-8 text-sm">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Vendor</p>
            <p class="text-ink font-medium"><?= e($d['vendor_name']) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Against Invoice</p>
            <p class="text-ink font-mono text-xs"><?= e($d['invoice_no']) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Payment Method</p>
            <p class="text-ink"><?= e($d['payment_method']) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Bank Account</p>
            <p class="text-ink"><?= e($d['bank_account'] ?: '—') ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Reference No.</p>
            <p class="text-ink"><?= e($d['reference_no'] ?: '—') ?></p>
        </div>
    </div>

    <div class="pass-card p-5 max-w-xs">
        <p class="text-xs text-ink/40 uppercase tracking-wide">Amount Paid</p>
        <p class="font-mono text-2xl font-bold text-ink mt-1"><?= money((float)$d['amount']) ?></p>
    </div>

    <?php if ($d['linked_entry_id']): ?>
    <p class="text-xs text-ink/40 mt-6">Posted to General Ledger: <a href="../general-ledger/journal-entry-view.php?id=<?= (int)$d['linked_entry_id'] ?>" class="text-primary hover:underline font-medium">View journal entry →</a></p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
