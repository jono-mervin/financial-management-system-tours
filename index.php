<?php
$page_title = 'Dashboard';
$page_subtitle = 'Live cash, revenue, and expense position from posted ledger activity.';
$active_module = 'dashboard';
require_once __DIR__ . '/includes/header.php';

function acct_balance(PDO $pdo, array $codes, string $side): float {
    $in = implode(',', array_fill(0, count($codes), '?'));
    $sql = "SELECT COALESCE(SUM(l.debit),0) AS d, COALESCE(SUM(l.credit),0) AS c
            FROM journal_entry_lines l
            JOIN journal_entries je ON je.entry_id = l.entry_id
            JOIN chart_of_accounts a ON a.account_id = l.account_id
            WHERE je.status = 'Posted' AND a.account_code IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($codes);
    $row = $stmt->fetch();
    return $side === 'debit' ? ($row['d'] - $row['c']) : ($row['c'] - $row['d']);
}

function type_balance(PDO $pdo, string $type, string $side): float {
    $sql = "SELECT COALESCE(SUM(l.debit),0) AS d, COALESCE(SUM(l.credit),0) AS c
            FROM journal_entry_lines l
            JOIN journal_entries je ON je.entry_id = l.entry_id
            JOIN chart_of_accounts a ON a.account_id = l.account_id
            WHERE je.status = 'Posted' AND a.account_type = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$type]);
    $row = $stmt->fetch();
    return $side === 'debit' ? ($row['d'] - $row['c']) : ($row['c'] - $row['d']);
}

$cash_balance   = acct_balance($pdo, ['1000', '1010', '1020'], 'debit');
$total_revenue  = type_balance($pdo, 'Revenue', 'credit');
$total_expense  = type_balance($pdo, 'Expense', 'debit');
$net_position   = $total_revenue - $total_expense;

$draft_count = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE status = 'Draft'")->fetchColumn();
$posted_count = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries WHERE status = 'Posted'")->fetchColumn();
$account_count = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active = 1")->fetchColumn();

$ap_outstanding = (float)$pdo->query("SELECT COALESCE(SUM(amount - amount_paid),0) FROM ap_invoices WHERE status IN ('Unpaid','Partially Paid')")->fetchColumn();
$ar_outstanding = (float)$pdo->query("SELECT COALESCE(SUM(amount - amount_received),0) FROM ar_invoices WHERE status IN ('Unpaid','Partially Paid')")->fetchColumn();
$ap_overdue = (int)$pdo->query("SELECT COUNT(*) FROM ap_invoices WHERE status IN ('Unpaid','Partially Paid') AND due_date < CURDATE()")->fetchColumn();
$ar_overdue = (int)$pdo->query("SELECT COUNT(*) FROM ar_invoices WHERE status IN ('Unpaid','Partially Paid') AND due_date < CURDATE()")->fetchColumn();
$vendor_count = (int)$pdo->query("SELECT COUNT(*) FROM vendors WHERE is_active = 1")->fetchColumn();
$customer_count = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE is_active = 1")->fetchColumn();
$disb_month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
$coll_month = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE collection_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();

// Monthly revenue / expense for last 6 months (from posted JE lines)
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    $label = date('M Y', strtotime($start));

    $rev = $pdo->prepare(
        "SELECT COALESCE(SUM(l.credit - l.debit),0)
         FROM journal_entry_lines l
         JOIN journal_entries je ON je.entry_id = l.entry_id
         JOIN chart_of_accounts a ON a.account_id = l.account_id
         WHERE je.status='Posted' AND a.account_type='Revenue'
           AND je.entry_date BETWEEN ? AND ?"
    );
    $rev->execute([$start, $end]);
    $exp = $pdo->prepare(
        "SELECT COALESCE(SUM(l.debit - l.credit),0)
         FROM journal_entry_lines l
         JOIN journal_entries je ON je.entry_id = l.entry_id
         JOIN chart_of_accounts a ON a.account_id = l.account_id
         WHERE je.status='Posted' AND a.account_type='Expense'
           AND je.entry_date BETWEEN ? AND ?"
    );
    $exp->execute([$start, $end]);
    $monthly[] = [
        'label' => $label,
        'revenue' => (float)$rev->fetchColumn(),
        'expense' => (float)$exp->fetchColumn(),
    ];
}

$cash_flow = [];
for ($i = 5; $i >= 0; $i--) {
    $start = date('Y-m-01', strtotime("-$i months"));
    $end = date('Y-m-t', strtotime("-$i months"));
    $label = date('M', strtotime($start));
    $in = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM collections WHERE collection_date BETWEEN ? AND ?");
    $in->execute([$start, $end]);
    $out = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM disbursements WHERE payment_date BETWEEN ? AND ?");
    $out->execute([$start, $end]);
    $cash_flow[] = [
        'label' => $label,
        'in' => (float)$in->fetchColumn(),
        'out' => (float)$out->fetchColumn(),
    ];
}

$recent_entries = $pdo->query(
    "SELECT je.*, (SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines WHERE entry_id = je.entry_id) AS total
     FROM journal_entries je ORDER BY je.created_at DESC LIMIT 8"
)->fetchAll();

$top_ap = $pdo->query(
    "SELECT i.invoice_no, i.due_date, i.amount, i.amount_paid, i.status, v.vendor_name,
            (i.amount - i.amount_paid) AS balance
     FROM ap_invoices i
     JOIN vendors v ON v.vendor_id = i.vendor_id
     WHERE i.status IN ('Unpaid','Partially Paid')
     ORDER BY i.due_date ASC LIMIT 6"
)->fetchAll();

$top_ar = $pdo->query(
    "SELECT i.invoice_no, i.due_date, i.amount, i.amount_received, i.status, c.customer_name,
            (i.amount - i.amount_received) AS balance
     FROM ar_invoices i
     JOIN customers c ON c.customer_id = i.customer_id
     WHERE i.status IN ('Unpaid','Partially Paid')
     ORDER BY i.due_date ASC LIMIT 6"
)->fetchAll();

$chart_rev_exp = [
    'labels' => array_column($monthly, 'label'),
    'revenue' => array_column($monthly, 'revenue'),
    'expense' => array_column($monthly, 'expense'),
];
$chart_cash = [
    'labels' => array_column($cash_flow, 'label'),
    'in' => array_column($cash_flow, 'in'),
    'out' => array_column($cash_flow, 'out'),
];
$chart_mix = [
    'labels' => ['Revenue', 'Expenses', 'Cash', 'AP Due', 'AR Due'],
    'values' => [
        max(0, $total_revenue),
        max(0, $total_expense),
        max(0, $cash_balance),
        max(0, $ap_outstanding),
        max(0, $ar_outstanding),
    ],
];
?>

<!-- Primary KPIs -->
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="pass-card p-5 flex items-stretch gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide">Cash &amp; Bank</p>
            <p class="font-mono text-2xl font-bold text-ink mt-2 truncate"><?= money($cash_balance) ?></p>
            <p class="text-xs text-ink/40 mt-1">Operating cash position</p>
        </div>
        <div class="pass-divider"></div>
        <div class="w-16 shrink-0 flex flex-col items-center justify-center text-center">
            <span class="font-mono text-[9px] text-ink/30 tracking-widest">CODE</span>
            <span class="font-display font-bold text-primary text-base">CSH</span>
        </div>
    </div>
    <div class="pass-card p-5 flex items-stretch gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide">Revenue</p>
            <p class="font-mono text-2xl font-bold text-emerald-600 mt-2 truncate"><?= money($total_revenue) ?></p>
            <p class="text-xs text-ink/40 mt-1">Posted YTD</p>
        </div>
        <div class="pass-divider"></div>
        <div class="w-16 shrink-0 flex flex-col items-center justify-center text-center">
            <span class="font-mono text-[9px] text-ink/30 tracking-widest">CODE</span>
            <span class="font-display font-bold text-primary text-base">REV</span>
        </div>
    </div>
    <div class="pass-card p-5 flex items-stretch gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide">Expenses</p>
            <p class="font-mono text-2xl font-bold text-rose-600 mt-2 truncate"><?= money($total_expense) ?></p>
            <p class="text-xs text-ink/40 mt-1">Posted YTD</p>
        </div>
        <div class="pass-divider"></div>
        <div class="w-16 shrink-0 flex flex-col items-center justify-center text-center">
            <span class="font-mono text-[9px] text-ink/30 tracking-widest">CODE</span>
            <span class="font-display font-bold text-primary text-base">EXP</span>
        </div>
    </div>
    <div class="pass-card p-5 flex items-stretch gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide">Net Position</p>
            <p class="font-mono text-2xl font-bold <?= $net_position >= 0 ? 'text-ink' : 'text-rose-600' ?> mt-2 truncate"><?= money($net_position) ?></p>
            <p class="text-xs text-ink/40 mt-1">Revenue − expenses</p>
        </div>
        <div class="pass-divider"></div>
        <div class="w-16 shrink-0 flex flex-col items-center justify-center text-center">
            <span class="font-mono text-[9px] text-ink/30 tracking-widest">CODE</span>
            <span class="font-display font-bold text-primary text-base">NET</span>
        </div>
    </div>
</div>

<!-- Secondary stats strip -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <?php
    $mini = [
        ['AP Outstanding', money($ap_outstanding), $ap_overdue ? "$ap_overdue overdue" : 'On track', 'text-amber-600'],
        ['AR Outstanding', money($ar_outstanding), $ar_overdue ? "$ar_overdue overdue" : 'On track', 'text-sky-600'],
        ['Collected MTD', money($coll_month), 'This month', 'text-emerald-600'],
        ['Disbursed MTD', money($disb_month), 'This month', 'text-rose-600'],
        ['Vendors', (string)$vendor_count, 'Active', 'text-ink'],
        ['Customers', (string)$customer_count, 'Active', 'text-ink'],
    ];
    foreach ($mini as [$label, $val, $sub, $color]): ?>
    <div class="bg-white rounded-xl border border-black/5 px-4 py-3.5">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-ink/40"><?= e($label) ?></p>
        <p class="font-mono text-lg font-bold <?= e($color) ?> mt-1 truncate"><?= e($val) ?></p>
        <p class="text-[11px] text-ink/35 mt-0.5"><?= e($sub) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts row -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
    <div class="xl:col-span-2 bg-white rounded-2xl border border-black/5 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-display font-semibold text-ink">Revenue vs Expenses</h2>
                <p class="text-xs text-ink/40 mt-0.5">Last 6 months · posted ledger</p>
            </div>
        </div>
        <div class="chart-wrap">
            <canvas id="chart-rev-exp"></canvas>
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-black/5 p-5">
        <div class="mb-4">
            <h2 class="font-display font-semibold text-ink">Position Mix</h2>
            <p class="text-xs text-ink/40 mt-0.5">Key balances at a glance</p>
        </div>
        <div class="chart-wrap" style="height:240px">
            <canvas id="chart-mix"></canvas>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-6">
    <div class="bg-white rounded-2xl border border-black/5 p-5">
        <div class="mb-4">
            <h2 class="font-display font-semibold text-ink">Cash Movement</h2>
            <p class="text-xs text-ink/40 mt-0.5">Collections in · Disbursements out</p>
        </div>
        <div class="chart-wrap" style="height:220px">
            <canvas id="chart-cash"></canvas>
        </div>
    </div>

    <div class="xl:col-span-2 bg-white rounded-2xl border border-black/5 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-black/5">
            <h2 class="font-display font-semibold text-ink">Recent Journal Entries</h2>
            <a href="modules/general-ledger/journal-entries.php" class="text-sm font-medium text-primary hover:underline">View all →</a>
        </div>
        <div class="overflow-x-auto thin-scroll">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/40">
                        <th class="px-5 py-3 font-medium">Entry No.</th>
                        <th class="px-3 py-3 font-medium">Date</th>
                        <th class="px-3 py-3 font-medium">Description</th>
                        <th class="px-3 py-3 font-medium text-right">Amount</th>
                        <th class="px-5 py-3 font-medium text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    <?php if (!$recent_entries): ?>
                    <tr><td colspan="5" class="px-5 py-10 text-center text-ink/40">No journal entries yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent_entries as $row): ?>
                    <tr class="hover:bg-canvas/60">
                        <td class="px-5 py-3 font-mono text-xs text-ink/70">
                            <a href="modules/general-ledger/journal-entry-view.php?id=<?= (int)$row['entry_id'] ?>" class="hover:text-primary hover:underline"><?= e($row['entry_no']) ?></a>
                        </td>
                        <td class="px-3 py-3 text-ink/60 whitespace-nowrap"><?= date('M j, Y', strtotime($row['entry_date'])) ?></td>
                        <td class="px-3 py-3 text-ink truncate max-w-[240px]"><?= e($row['description']) ?></td>
                        <td class="px-3 py-3 text-right font-mono"><?= money((float)$row['total']) ?></td>
                        <td class="px-5 py-3 text-right">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 <?= badge_class($row['status']) ?>"><?= e($row['status']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AP / AR tables + snapshot -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-2">
    <div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-black/5">
            <h2 class="font-display font-semibold text-ink text-sm">AP Due Soon</h2>
            <a href="modules/accounts-payable/aging-report.php" class="text-xs font-medium text-primary hover:underline">Aging →</a>
        </div>
        <div class="overflow-x-auto thin-scroll">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink/40 text-[10px] uppercase tracking-wide border-b border-black/5">
                        <th class="px-4 py-2 font-medium">Vendor</th>
                        <th class="px-2 py-2 font-medium">Due</th>
                        <th class="px-4 py-2 font-medium text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    <?php if (!$top_ap): ?>
                    <tr><td colspan="3" class="px-4 py-8 text-center text-ink/35 text-xs">No open AP invoices</td></tr>
                    <?php endif; ?>
                    <?php foreach ($top_ap as $r):
                        $overdue = strtotime($r['due_date']) < strtotime(date('Y-m-d')); ?>
                    <tr class="hover:bg-canvas/60">
                        <td class="px-4 py-2.5">
                            <p class="text-xs font-medium text-ink truncate max-w-[120px]"><?= e($r['vendor_name']) ?></p>
                            <p class="font-mono text-[10px] text-ink/35"><?= e($r['invoice_no']) ?></p>
                        </td>
                        <td class="px-2 py-2.5 text-xs <?= $overdue ? 'text-rose-600 font-semibold' : 'text-ink/50' ?>"><?= date('M j', strtotime($r['due_date'])) ?></td>
                        <td class="px-4 py-2.5 text-right font-mono text-xs font-semibold"><?= money((float)$r['balance']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-black/5">
            <h2 class="font-display font-semibold text-ink text-sm">AR Due Soon</h2>
            <a href="modules/accounts-receivable/aging-report.php" class="text-xs font-medium text-primary hover:underline">Aging →</a>
        </div>
        <div class="overflow-x-auto thin-scroll">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-ink/40 text-[10px] uppercase tracking-wide border-b border-black/5">
                        <th class="px-4 py-2 font-medium">Customer</th>
                        <th class="px-2 py-2 font-medium">Due</th>
                        <th class="px-4 py-2 font-medium text-right">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-black/5">
                    <?php if (!$top_ar): ?>
                    <tr><td colspan="3" class="px-4 py-8 text-center text-ink/35 text-xs">No open AR invoices</td></tr>
                    <?php endif; ?>
                    <?php foreach ($top_ar as $r):
                        $overdue = strtotime($r['due_date']) < strtotime(date('Y-m-d')); ?>
                    <tr class="hover:bg-canvas/60">
                        <td class="px-4 py-2.5">
                            <p class="text-xs font-medium text-ink truncate max-w-[120px]"><?= e($r['customer_name']) ?></p>
                            <p class="font-mono text-[10px] text-ink/35"><?= e($r['invoice_no']) ?></p>
                        </td>
                        <td class="px-2 py-2.5 text-xs <?= $overdue ? 'text-rose-600 font-semibold' : 'text-ink/50' ?>"><?= date('M j', strtotime($r['due_date'])) ?></td>
                        <td class="px-4 py-2.5 text-right font-mono text-xs font-semibold"><?= money((float)$r['balance']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="bg-white rounded-2xl border border-black/5 p-5">
            <h2 class="font-display font-semibold text-ink mb-3 text-sm">Quick Actions</h2>
            <div class="space-y-2">
                <a href="modules/general-ledger/journal-entries.php?new=1" class="flex items-center gap-3 rounded-xl px-4 py-2.5 bg-primary text-white text-sm font-medium hover:bg-primary-light transition-colors">New Journal Entry</a>
                <a href="modules/accounts-receivable/invoices.php?new=1" class="flex items-center gap-3 rounded-xl px-4 py-2.5 bg-canvas text-ink text-sm font-medium hover:bg-black/5 ring-1 ring-black/5 transition-colors">New AR Invoice</a>
                <a href="modules/accounts-payable/invoices.php?new=1" class="flex items-center gap-3 rounded-xl px-4 py-2.5 bg-canvas text-ink text-sm font-medium hover:bg-black/5 ring-1 ring-black/5 transition-colors">New AP Invoice</a>
                <a href="modules/accounts-receivable/collections.php?new=1" class="flex items-center gap-3 rounded-xl px-4 py-2.5 bg-canvas text-ink text-sm font-medium hover:bg-black/5 ring-1 ring-black/5 transition-colors">Record Collection</a>
            </div>
        </div>
        <div class="bg-primary rounded-2xl p-5 text-white relative overflow-hidden">
            <div class="route-dots absolute inset-0 opacity-[0.06]"></div>
            <h2 class="font-display font-semibold mb-3 relative text-sm">Ledger Snapshot</h2>
            <div class="space-y-2.5 relative text-sm">
                <div class="flex justify-between"><span class="text-white/55">Active Accounts</span><span class="font-mono font-semibold"><?= $account_count ?></span></div>
                <div class="flex justify-between"><span class="text-white/55">Posted Entries</span><span class="font-mono font-semibold"><?= $posted_count ?></span></div>
                <div class="flex justify-between"><span class="text-white/55">Draft Entries</span><span class="font-mono font-semibold text-accent"><?= $draft_count ?></span></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(() => {
    const teal = '#0E3B43';
    const tealSoft = 'rgba(14, 59, 67, 0.15)';
    const gold = '#E0A458';
    const emerald = '#059669';
    const rose = '#E11D48';
    const sky = '#0284C7';

    const revExp = <?= json_encode($chart_rev_exp) ?>;
    const cash = <?= json_encode($chart_cash) ?>;
    const mix = <?= json_encode($chart_mix) ?>;

    const grid = { color: 'rgba(28,43,46,0.06)' };
    const ticks = { color: 'rgba(28,43,46,0.45)', font: { size: 11, family: 'Inter' } };

    new Chart(document.getElementById('chart-rev-exp'), {
        type: 'bar',
        data: {
            labels: revExp.labels,
            datasets: [
                { label: 'Revenue', data: revExp.revenue, backgroundColor: emerald, borderRadius: 6, maxBarThickness: 28 },
                { label: 'Expenses', data: revExp.expense, backgroundColor: rose, borderRadius: 6, maxBarThickness: 28 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks },
                y: { grid, ticks: { ...ticks, callback: v => '₱' + Number(v).toLocaleString() }, beginAtZero: true }
            }
        }
    });

    new Chart(document.getElementById('chart-mix'), {
        type: 'doughnut',
        data: {
            labels: mix.labels,
            datasets: [{
                data: mix.values,
                backgroundColor: [emerald, rose, teal, gold, sky],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true, pointStyle: 'circle', padding: 12, font: { size: 11 } } }
            }
        }
    });

    new Chart(document.getElementById('chart-cash'), {
        type: 'line',
        data: {
            labels: cash.labels,
            datasets: [
                {
                    label: 'Collections',
                    data: cash.in,
                    borderColor: emerald,
                    backgroundColor: 'rgba(5,150,105,0.12)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: emerald,
                },
                {
                    label: 'Disbursements',
                    data: cash.out,
                    borderColor: rose,
                    backgroundColor: 'rgba(225,29,72,0.08)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: rose,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'top', labels: { boxWidth: 12, usePointStyle: true, pointStyle: 'circle' } } },
            scales: {
                x: { grid: { display: false }, ticks },
                y: { grid, ticks: { ...ticks, callback: v => '₱' + Number(v).toLocaleString() }, beginAtZero: true }
            }
        }
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
