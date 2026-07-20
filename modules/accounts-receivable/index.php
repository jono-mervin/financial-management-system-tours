<?php
$page_title = 'Accounts Receivable';
$page_subtitle = 'Customers, invoices, collections, and aging — one receivables workflow.';
$active_module = 'ar';
$breadcrumb = ['Accounts Receivable'];
require_once __DIR__ . '/../../includes/header.php';

$customer_count = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
$outstanding = (float)$pdo->query("SELECT COALESCE(SUM(amount - amount_received),0) FROM ar_invoices WHERE status IN ('Unpaid','Partially Paid')")->fetchColumn();
$overdue_count = (int)$pdo->query("SELECT COUNT(*) FROM ar_invoices WHERE status IN ('Unpaid','Partially Paid') AND due_date < CURDATE()")->fetchColumn();
$coll_count = (int)$pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
$coll_month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE collection_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

$cards = [
    [
        'title' => 'Customers',
        'desc'  => 'Manage traveller, corporate, travel-agent and group client records.',
        'href'  => 'customers.php',
        'stat'  => "$customer_count active customers",
        'icon'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    ],
    [
        'title' => 'AR Invoices',
        'desc'  => 'Issue invoices for tour packages, ticketing, and add-ons — each one posts straight to the General Ledger.',
        'href'  => 'invoices.php',
        'stat'  => money($outstanding) . ' outstanding',
        'icon'  => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M8 2v8"/><path d="M2 10h20"/>',
    ],
    [
        'title' => 'Collections',
        'desc'  => 'Record receipts against open invoices — updates AR balance and posts cash to the ledger.',
        'href'  => 'collections.php',
        'stat'  => "$coll_count recorded · " . money($coll_month) . ' MTD',
        'icon'  => '<path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M3 8h18l-1.5 11.5A2 2 0 0 1 17.53 21H6.47a2 2 0 0 1-1.97-1.5L3 8z"/><path d="M12 12v4"/><path d="m9.5 13.5 2.5 2.5 2.5-2.5"/>',
    ],
    [
        'title' => 'Aging Report',
        'desc'  => "See what's current, overdue, and how far past due — bucketed by customer.",
        'href'  => 'aging-report.php',
        'stat'  => "$overdue_count invoice" . ($overdue_count === 1 ? '' : 's') . " overdue",
        'icon'  => '<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4"/><path d="M9 15v-2a2 2 0 0 1 2-2h2"/><path d="m22 3-8 8"/><path d="M17 3h5v5"/>',
    ],
];
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
