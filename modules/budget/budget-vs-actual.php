<?php
$page_title = 'Budget vs. Actual';
$page_subtitle = 'Compare budgeted amounts to posted actuals.';
$active_module = 'budget';

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$budget_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT b.*, p.period_name, p.start_date, p.end_date FROM budgets b JOIN fiscal_periods p ON p.period_id = b.period_id WHERE b.budget_id = ?");
$stmt->execute([$budget_id]);
$budget = $stmt->fetch();

if (!$budget) { flash('error', 'Budget not found.'); header('Location: budgets.php'); exit; }

$breadcrumb = ['Budget Management', $budget['budget_name']];
require_once __DIR__ . '/../../includes/header.php';

$lstmt = $pdo->prepare("SELECT bl.*, a.account_code, a.account_name, a.account_type FROM budget_lines bl JOIN chart_of_accounts a ON a.account_id = bl.account_id WHERE bl.budget_id = ? ORDER BY a.account_code");
$lstmt->execute([$budget_id]);
$lines = $lstmt->fetchAll();

$actual_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c
    FROM journal_entry_lines l
    JOIN journal_entries je ON je.entry_id = l.entry_id
    WHERE l.account_id = ? AND je.status = 'Posted' AND je.entry_date BETWEEN ? AND ?
");

$report = [];
$total_budgeted = 0; $total_actual = 0;
foreach ($lines as $l) {
    $actual_stmt->execute([$l['account_id'], $budget['start_date'], $budget['end_date']]);
    $a = $actual_stmt->fetch();
    $actual = $l['account_type'] === 'Expense' ? ($a['d'] - $a['c']) : ($a['c'] - $a['d']);
    $variance = $actual - (float)$l['budgeted_amount'];
    $variance_pct = $l['budgeted_amount'] > 0 ? ($variance / $l['budgeted_amount']) * 100 : 0;
    // For Expense: over budget (actual > budgeted) is bad. For Revenue: under target (actual < budgeted) is bad.
    $is_unfavorable = $l['account_type'] === 'Expense' ? ($variance > 0.005) : ($variance < -0.005);

    $total_budgeted += (float)$l['budgeted_amount'];
    $total_actual += $actual;
    $report[] = $l + ['actual' => $actual, 'variance' => $variance, 'variance_pct' => $variance_pct, 'unfavorable' => $is_unfavorable];
}
?>

<div class="flex items-center justify-between mb-5 -mt-2 flex-wrap gap-3">
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= badge_class($budget['status']) ?>"><?= e($budget['status']) ?></span>
        <span class="text-sm text-ink/50"><?= e($budget['period_name']) ?><?= $budget['department'] ? ' · ' . e($budget['department']) : '' ?></span>
    </div>
    <div class="flex items-center gap-3 no-print">
        <?php if ($budget['status'] === 'Draft'): ?>
        <form method="post" action="budget-save.php" data-confirm="Approve this budget?">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="budget_id" value="<?= $budget_id ?>">
            <button type="submit" class="bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2 rounded-xl">Approve Budget</button>
        </form>
        <?php elseif ($budget['status'] === 'Approved'): ?>
        <form method="post" action="budget-save.php" data-confirm="Close this budget? This is typically done at period end.">
            <input type="hidden" name="action" value="close">
            <input type="hidden" name="budget_id" value="<?= $budget_id ?>">
            <button type="submit" class="text-sm font-medium text-ink/60 hover:text-ink">Close Budget</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="pass-card p-5">
        <p class="text-xs text-ink/40 uppercase tracking-wide font-semibold">Total Budgeted</p>
        <p class="font-mono text-xl font-bold text-ink mt-1.5"><?= money($total_budgeted) ?></p>
    </div>
    <div class="pass-card p-5">
        <p class="text-xs text-ink/40 uppercase tracking-wide font-semibold">Total Actual</p>
        <p class="font-mono text-xl font-bold text-ink mt-1.5"><?= money($total_actual) ?></p>
    </div>
    <div class="pass-card p-5">
        <p class="text-xs text-ink/40 uppercase tracking-wide font-semibold">Net Variance</p>
        <p class="font-mono text-xl font-bold <?= ($total_actual - $total_budgeted) >= 0 ? 'text-ink' : 'text-ink' ?> mt-1.5"><?= money($total_actual - $total_budgeted) ?></p>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
    <div class="px-6 py-4 border-b border-black/5">
        <h2 class="font-display font-semibold text-ink">Line-by-Line Detail</h2>
        <p class="text-xs text-ink/40 mt-0.5"><?= date('M j', strtotime($budget['start_date'])) ?> – <?= date('M j, Y', strtotime($budget['end_date'])) ?> · Actuals from posted General Ledger activity only</p>
    </div>
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                    <th class="px-6 py-3 font-medium">Account</th>
                    <th class="px-3 py-3 font-medium">Type</th>
                    <th class="px-3 py-3 font-medium text-right">Budgeted</th>
                    <th class="px-3 py-3 font-medium text-right">Actual</th>
                    <th class="px-3 py-3 font-medium text-right">Variance</th>
                    <th class="px-6 py-3 font-medium text-right">Var %</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$report): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-ink/40">No lines on this budget.</td></tr>
                <?php endif; ?>
                <?php foreach ($report as $r): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3">
                        <span class="font-mono text-xs text-ink/50 mr-2"><?= e($r['account_code']) ?></span><?= e($r['account_name']) ?>
                        <?php if ($r['notes']): ?><p class="text-xs text-ink/40 mt-0.5"><?= e($r['notes']) ?></p><?php endif; ?>
                    </td>
                    <td class="px-3 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $r['account_type'] === 'Revenue' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20' ?>"><?= e($r['account_type']) ?></span></td>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($r['budgeted_amount'], 2) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($r['actual'], 2) ?></td>
                    <td class="px-3 py-3 text-right font-mono font-semibold <?= $r['unfavorable'] ? 'text-rose-600' : 'text-emerald-600' ?>"><?= ($r['variance'] >= 0 ? '+' : '') . number_format($r['variance'], 2) ?></td>
                    <td class="px-6 py-3 text-right font-mono <?= $r['unfavorable'] ? 'text-rose-600' : 'text-emerald-600' ?>"><?= ($r['variance_pct'] >= 0 ? '+' : '') . number_format($r['variance_pct'], 1) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($report): ?>
            <tfoot>
                <tr class="border-t-2 border-ink/10 font-semibold bg-canvas/40">
                    <td class="px-6 py-3" colspan="2">Total</td>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($total_budgeted, 2) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($total_actual, 2) ?></td>
                    <td class="px-3 py-3 text-right font-mono" colspan="2"><?= number_format($total_actual - $total_budgeted, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
