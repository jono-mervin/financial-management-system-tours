<?php
$page_title = 'AR Aging Report';
$page_subtitle = 'Outstanding AR balances by aging bucket.';
$active_module = 'ar';
$breadcrumb = ['Accounts Receivable', 'Aging Report'];
require_once __DIR__ . '/../../includes/header.php';

$rows = $pdo->query("
    SELECT i.*, c.customer_name
    FROM ar_invoices i JOIN customers c ON c.customer_id = i.customer_id
    WHERE i.status IN ('Unpaid','Partially Paid')
    ORDER BY c.customer_name
")->fetchAll();

$buckets = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$byCustomer = [];
$today = strtotime(date('Y-m-d'));

foreach ($rows as $r) {
    $balance = (float)$r['amount'] - (float)$r['amount_received'];
    if ($balance <= 0) continue;
    $days = (int)floor(($today - strtotime($r['due_date'])) / 86400);
    $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')));

    $buckets[$bucket] += $balance;
    $byCustomer[$r['customer_name']]['name'] = $r['customer_name'];
    $byCustomer[$r['customer_name']]['buckets'][$bucket] = ($byCustomer[$r['customer_name']]['buckets'][$bucket] ?? 0) + $balance;
    $byCustomer[$r['customer_name']]['total'] = ($byCustomer[$r['customer_name']]['total'] ?? 0) + $balance;
}
ksort($byCustomer);
$grand_total = array_sum($buckets);
$bucket_labels = array_keys($buckets);
?>

<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <?php foreach ($buckets as $label => $amt): ?>
    <div class="pass-card p-4">
        <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide"><?= $label === 'Current' ? 'Current' : $label . ' days' ?></p>
        <p class="font-mono text-lg font-bold <?= $label === 'Current' ? 'text-ink' : ($label === '90+' ? 'text-rose-600' : 'text-amber-600') ?> mt-1.5"><?= money($amt) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-black/5">
        <h2 class="font-display font-semibold text-ink">Receivables Aging by Customer</h2>
        <span class="font-mono text-sm font-bold text-ink">Total: <?= money($grand_total) ?></span>
    </div>
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                    <th class="px-6 py-3 font-medium">Customer</th>
                    <?php foreach ($bucket_labels as $label): ?>
                    <th class="px-3 py-3 font-medium text-right"><?= $label === 'Current' ? 'Current' : $label ?></th>
                    <?php endforeach; ?>
                    <th class="px-6 py-3 font-medium text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$byCustomer): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-ink/40">No outstanding receivables. Everything's collected 🎉</td></tr>
                <?php endif; ?>
                <?php foreach ($byCustomer as $c): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-medium text-ink"><?= e($c['name']) ?></td>
                    <?php foreach ($bucket_labels as $label): ?>
                    <td class="px-3 py-3 text-right font-mono <?= empty($c['buckets'][$label]) ? 'text-ink/25' : 'text-ink' ?>"><?= !empty($c['buckets'][$label]) ? number_format($c['buckets'][$label], 2) : '—' ?></td>
                    <?php endforeach; ?>
                    <td class="px-6 py-3 text-right font-mono font-semibold"><?= number_format($c['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($byCustomer): ?>
            <tfoot>
                <tr class="border-t-2 border-ink/10 font-semibold bg-canvas/40">
                    <td class="px-6 py-3">Total</td>
                    <?php foreach ($bucket_labels as $label): ?>
                    <td class="px-3 py-3 text-right font-mono"><?= number_format($buckets[$label], 2) ?></td>
                    <?php endforeach; ?>
                    <td class="px-6 py-3 text-right font-mono"><?= number_format($grand_total, 2) ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
