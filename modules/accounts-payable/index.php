<?php
$page_title = 'Accounts Payable';
$page_subtitle = 'Vendors, bills, and aging for amounts you owe.';
$active_module = 'ap';
$breadcrumb = ['Accounts Payable'];
require_once __DIR__ . '/../../includes/header.php';

$vendor_count = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE is_active = 1")->fetchColumn();
$outstanding = (float)$pdo->query("SELECT COALESCE(SUM(amount - amount_paid),0) FROM ap_invoices WHERE status IN ('Unpaid','Partially Paid')")->fetchColumn();
$overdue_count = (int)$pdo->query("SELECT COUNT(*) FROM ap_invoices WHERE status IN ('Unpaid','Partially Paid') AND due_date < CURDATE()")->fetchColumn();

$cards = [
    [
        'title' => 'Vendors',
        'desc'  => 'Manage hotel, airline, transport, and DMC partner records.',
        'href'  => 'vendors.php',
        'stat'  => "$vendor_count active vendors",
        'icon'  => '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/>',
    ],
    [
        'title' => 'AP Invoices',
        'desc'  => "Record vendor bills for hotels, airlines and tour costs — each one posts straight to the General Ledger.",
        'href'  => 'invoices.php',
        'stat'  => money($outstanding) . ' outstanding',
        'icon'  => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 2v8"/><path d="M2 10h20"/>',
    ],
    [
        'title' => 'Aging Report',
        'desc'  => "See what's current, overdue, and how far past due — bucketed by vendor.",
        'href'  => 'aging-report.php',
        'stat'  => "$overdue_count invoice" . ($overdue_count === 1 ? '' : 's') . " overdue",
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
