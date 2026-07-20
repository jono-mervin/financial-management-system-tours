<?php
$page_title = 'AP Aging Report';
$page_subtitle = 'Outstanding AP balances by aging bucket.';
$active_module = 'ap';
$breadcrumb = ['Accounts Payable', 'Aging Report'];
require_once __DIR__ . '/../../includes/header.php';

$rows = $pdo->query("
    SELECT i.*, v.vendor_name
    FROM ap_invoices i JOIN vendors v ON v.vendor_id = i.vendor_id
    WHERE i.status IN ('Unpaid','Partially Paid')
    ORDER BY v.vendor_name
")->fetchAll();

$buckets = ['Current' => 0, '1-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
$byVendor = [];
$today = strtotime(date('Y-m-d'));

foreach ($rows as $r) {
    $balance = (float)$r['amount'] - (float)$r['amount_paid'];
    if ($balance <= 0) continue;
    $days = (int)floor(($today - strtotime($r['due_date'])) / 86400);
    $bucket = $days <= 0 ? 'Current' : ($days <= 30 ? '1-30' : ($days <= 60 ? '31-60' : ($days <= 90 ? '61-90' : '90+')));

    $buckets[$bucket] += $balance;
    $byVendor[$r['vendor_name']]['name'] = $r['vendor_name'];
    $byVendor[$r['vendor_name']]['buckets'][$bucket] = ($byVendor[$r['vendor_name']]['buckets'][$bucket] ?? 0) + $balance;
    $byVendor[$r['vendor_name']]['total'] = ($byVendor[$r['vendor_name']]['total'] ?? 0) + $balance;
}
ksort($byVendor);
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
        <h2 class="font-display font-semibold text-ink">Payables Aging by Vendor</h2>
        <span class="font-mono text-sm font-bold text-ink">Total: <?= money($grand_total) ?></span>
    </div>
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                    <th class="px-6 py-3 font-medium">Vendor</th>
                    <?php foreach ($bucket_labels as $label): ?>
                    <th class="px-3 py-3 font-medium text-right"><?= $label === 'Current' ? 'Current' : $label ?></th>
                    <?php endforeach; ?>
                    <th class="px-6 py-3 font-medium text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$byVendor): ?>
                <tr><td colspan="7" class="px-6 py-10 text-center text-ink/40">No outstanding payables.</td></tr>
                <?php endif; ?>
                <?php foreach ($byVendor as $v): ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 font-medium text-ink"><?= e($v['name']) ?></td>
                    <?php foreach ($bucket_labels as $label): ?>
                    <td class="px-3 py-3 text-right font-mono <?= empty($v['buckets'][$label]) ? 'text-ink/25' : 'text-ink' ?>"><?= !empty($v['buckets'][$label]) ? number_format($v['buckets'][$label], 2) : '—' ?></td>
                    <?php endforeach; ?>
                    <td class="px-6 py-3 text-right font-mono font-semibold"><?= number_format($v['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($byVendor): ?>
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
