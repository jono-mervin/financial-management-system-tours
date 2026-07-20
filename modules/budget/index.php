<?php
$page_title = 'Budget Management';
$page_subtitle = 'Plan spending by period and compare against actuals.';
$active_module = 'budget';
$breadcrumb = ['Budget Management'];
require_once __DIR__ . '/../../includes/header.php';

$budget_count = (int)$pdo->query("SELECT COUNT(*) FROM budgets")->fetchColumn();
$approved_count = (int)$pdo->query("SELECT COUNT(*) FROM budgets WHERE status = 'Approved'")->fetchColumn();
$total_budgeted = (float)$pdo->query("SELECT COALESCE(SUM(budgeted_amount),0) FROM budget_lines bl JOIN budgets b ON b.budget_id = bl.budget_id WHERE b.status = 'Approved'")->fetchColumn();

$cards = [
    [
        'title' => 'Budgets',
        'desc'  => 'Department and fiscal-period budgets — draft, approve, and track through to close.',
        'href'  => 'budgets.php',
        'stat'  => "$budget_count on file",
        'icon'  => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
    ],
    [
        'title' => 'New Budget',
        'desc'  => 'Plan a budget line by line against Revenue and Expense accounts.',
        'href'  => 'budgets.php?new=1',
        'stat'  => money($total_budgeted) . ' approved this period',
        'icon'  => '<path d="M12 5v14"/><path d="M5 12h14"/>',
    ],
    [
        'title' => 'Trial Balance',
        'desc'  => 'Cross-check budget actuals against the live General Ledger.',
        'href'  => '../general-ledger/trial-balance.php',
        'stat'  => "$approved_count budget" . ($approved_count === 1 ? '' : 's') . " approved",
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
