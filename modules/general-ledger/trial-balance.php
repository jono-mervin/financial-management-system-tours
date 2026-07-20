<?php
$page_title = 'Trial Balance';
$page_subtitle = 'Verify total debits equal total credits as of a date.';
$active_module = 'gl';
$breadcrumb = ['General Ledger', 'Trial Balance'];
require_once __DIR__ . '/../../includes/header.php';

$as_of = $_GET['as_of'] ?? date('Y-m-d');

$sql = "SELECT a.account_id, a.account_code, a.account_name, a.account_type, a.normal_balance,
               COALESCE(SUM(l.debit),0) AS total_debit, COALESCE(SUM(l.credit),0) AS total_credit
        FROM chart_of_accounts a
        LEFT JOIN journal_entry_lines l ON l.account_id = a.account_id
        LEFT JOIN journal_entries je ON je.entry_id = l.entry_id AND je.status = 'Posted' AND je.entry_date <= ?
        WHERE a.is_active = 1
        GROUP BY a.account_id
        ORDER BY a.account_code";
$stmt = $pdo->prepare($sql);
$stmt->execute([$as_of]);
$rows = $stmt->fetchAll();

$grand_debit = 0; $grand_credit = 0;
$report = [];
foreach ($rows as $r) {
    $net = $r['normal_balance'] === 'Debit' ? ($r['total_debit'] - $r['total_credit']) : ($r['total_credit'] - $r['total_debit']);
    if (abs($net) < 0.005) continue; // skip zero-activity accounts for a clean report
    $debit_col  = $r['normal_balance'] === 'Debit' ? max($net, 0) : max(-$net, 0);
    $credit_col = $r['normal_balance'] === 'Credit' ? max($net, 0) : max(-$net, 0);
    $grand_debit += $debit_col;
    $grand_credit += $credit_col;
    $report[] = $r + ['debit_col' => $debit_col, 'credit_col' => $credit_col];
}
$balanced = abs($grand_debit - $grand_credit) < 0.005;
?>

<div class="flex items-center justify-between mb-5 -mt-2 flex-wrap gap-3 no-print">
    <form method="get" class="flex items-center gap-3">
        <label class="text-sm font-medium text-ink/60">As of</label>
        <input type="date" name="as_of" value="<?= e($as_of) ?>" onchange="this.form.submit()" class="rounded-lg border border-black/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
    </form>
    <button onclick="window.print()" class="flex items-center gap-2 text-sm font-medium text-ink/60 hover:text-ink">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        Print
    </button>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
        <div>
            <h2 class="font-display font-semibold text-ink">Trial Balance</h2>
            <p class="text-xs text-ink/40 mt-0.5">As of <?= date('F j, Y', strtotime($as_of)) ?></p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= $balanced ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-rose-50 text-rose-700 ring-rose-600/20' ?>">
            <?= $balanced ? 'Balanced' : 'Out of Balance' ?>
        </span>
    </div>
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                    <th class="px-6 py-3 font-medium">Code</th>
                    <th class="px-3 py-3 font-medium">Account</th>
                    <th class="px-3 py-3 font-medium">Type</th>
                    <th class="px-3 py-3 font-medium text-right">Debit</th>
                    <th class="px-6 py-3 font-medium text-right">Credit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$report): ?>
                <tr><td colspan="5" class="px-6 py-10 text-center text-ink/40">No posted activity as of this date.</td></tr>
                <?php endif; ?>
                <?php foreach ($report as $r): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($r['account_code']) ?></td>
                    <td class="px-3 py-3 text-ink font-medium"><?= e($r['account_name']) ?></td>
                    <td class="px-3 py-3 text-ink/50 text-xs"><?= e($r['account_type']) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= $r['debit_col'] > 0 ? number_format($r['debit_col'], 2) : '—' ?></td>
                    <td class="px-6 py-3 text-right font-mono"><?= $r['credit_col'] > 0 ? number_format($r['credit_col'], 2) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($report): ?>
            <tfoot>
                <tr class="border-t-2 border-ink/10 font-semibold bg-canvas/40">
                    <td class="px-6 py-3" colspan="3">Total</td>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($grand_debit, 2) ?></td>
                    <td class="px-6 py-3 text-right font-mono"><?= number_format($grand_credit, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
