<?php
$page_title = 'Disbursement Management';
$page_subtitle = 'Outgoing payments against vendor invoices.';
$active_module = 'disb';
$breadcrumb = ['Disbursement Management'];
require_once __DIR__ . '/../../includes/header.php';

$total_this_month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$ap_outstanding = (float)$pdo->query("SELECT COALESCE(SUM(amount - amount_paid),0) FROM ap_invoices WHERE status IN ('Unpaid','Partially Paid')")->fetchColumn();
$disb_count = (int)$pdo->query("SELECT COUNT(*) FROM disbursements")->fetchColumn();

$cards = [
    [
        'title' => 'Disbursements',
        'desc'  => 'Full history of outgoing payments made against vendor bills.',
        'href'  => 'disbursements.php',
        'stat'  => "$disb_count recorded",
        'icon'  => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
    ],
    [
        'title' => 'Record Disbursement',
        'desc'  => 'Pay down an outstanding AP invoice — posts straight to the General Ledger and updates its balance.',
        'href'  => 'disbursements.php?new=1',
        'stat'  => money($ap_outstanding) . ' AP outstanding',
        'icon'  => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    ],
    [
        'title' => 'AP Aging Report',
        'desc'  => "Decide what to pay next — see what's overdue by vendor.",
        'href'  => '../accounts-payable/aging-report.php',
        'stat'  => "This month: " . money($total_this_month),
        'icon'  => '<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/><path d="M9 15v-2a2 2 0 0 1 2-2h2"/><path d="m22 3-8 8"/><path d="M17 3h5v5"/>',
    ],
];
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php foreach ($cards as $card): ?>
    <a href="<?= $card['href'] ?>" class="hub-card group bg-white rounded-2xl border border-black/5 p-6 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5 transition-all">
        <div class="flex items-start justify-between mb-4">
            <div class="w-11 h-11 rounded-xl bg-primary/5 group-hover:bg-primary flex items-center justify-center transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="stroke-primary group-hover:stroke-white transition-colors"><?= $card['icon'] ?></svg>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink/20 group-hover:text-primary group-hover:translate-x-0.5 transition-all"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </div>
        <h3 class="font-display font-semibold text-ink mb-1.5"><?= e($card['title']) ?></h3>
        <p class="text-sm text-ink/50 leading-relaxed mb-4"><?= e($card['desc']) ?></p>
        <span class="text-xs font-mono font-medium text-accent-dark bg-accent/10 rounded-full px-2.5 py-1"><?= e($card['stat']) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
