<?php
$page_title = 'General Ledger';
$page_subtitle = 'Chart of accounts, journals, ledgers, and trial balance.';
$active_module = 'gl';
$breadcrumb = ['General Ledger'];
require_once __DIR__ . '/../../includes/header.php';

$account_count = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active = 1")->fetchColumn();
$draft_count   = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE status = 'Draft'")->fetchColumn();
$posted_count  = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE status = 'Posted'")->fetchColumn();

$cards = [
    [
        'title' => 'Chart of Accounts',
        'desc'  => 'Maintain the account structure — assets, liabilities, equity, revenue and expense accounts used across every module.',
        'href'  => 'chart-of-accounts.php',
        'stat'  => "$account_count active accounts",
        'icon'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    ],
    [
        'title' => 'Journal Entries',
        'desc'  => 'Record, review, and post manual and system-generated double-entry transactions.',
        'href'  => 'journal-entries.php',
        'stat'  => "$posted_count posted · $draft_count draft",
        'icon'  => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    ],
    [
        'title' => 'General Ledger View',
        'desc'  => 'Drill into any account to see its full transaction history and running balance.',
        'href'  => 'ledger.php',
        'stat'  => 'Per-account detail',
        'icon'  => '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
    ],
    [
        'title' => 'Trial Balance',
        'desc'  => 'Verify that total debits equal total credits as of any date, across all accounts.',
        'href'  => 'trial-balance.php',
        'stat'  => 'Real-time report',
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
