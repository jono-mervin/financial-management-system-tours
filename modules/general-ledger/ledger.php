<?php
$page_title = 'General Ledger View';
$page_subtitle = 'Per-account transaction history and running balance.';
$active_module = 'gl';
$breadcrumb = ['General Ledger', 'Ledger View'];
require_once __DIR__ . '/../../includes/header.php';

$accounts = $pdo->query("SELECT account_id, account_code, account_name, normal_balance FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code")->fetchAll();

$account_id = (int)($_GET['account_id'] ?? ($accounts[0]['account_id'] ?? 0));
$date_from  = $_GET['date_from'] ?? date('Y-m-01');
$date_to    = $_GET['date_to'] ?? date('Y-m-d');

$selected = null;
foreach ($accounts as $a) if ($a['account_id'] == $account_id) $selected = $a;

$rows = [];
$opening_balance = 0;
if ($selected) {
    // Opening balance = all posted activity before date_from
    $ostmt = $pdo->prepare("SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c FROM journal_entry_lines l JOIN journal_entries je ON je.entry_id=l.entry_id WHERE l.account_id=? AND je.status='Posted' AND je.entry_date < ?");
    $ostmt->execute([$account_id, $date_from]);
    $o = $ostmt->fetch();
    $opening_balance = $selected['normal_balance'] === 'Debit' ? ($o['d'] - $o['c']) : ($o['c'] - $o['d']);

    $stmt = $pdo->prepare("SELECT je.entry_no, je.entry_date, je.description, l.debit, l.credit, l.memo
                            FROM journal_entry_lines l
                            JOIN journal_entries je ON je.entry_id = l.entry_id
                            WHERE l.account_id = ? AND je.status = 'Posted' AND je.entry_date BETWEEN ? AND ?
                            ORDER BY je.entry_date, je.entry_id");
    $stmt->execute([$account_id, $date_from, $date_to]);
    $rows = $stmt->fetchAll();
}
?>

<div class="bg-white rounded-2xl border border-black/5 p-5 mb-6">
    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold text-ink/50 mb-1.5">Account</label>
            <select name="account_id" onchange="this.form.submit()" class="w-full rounded-lg border border-black/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                <?php foreach ($accounts as $a): ?>
                <option value="<?= $a['account_id'] ?>" <?= $a['account_id'] == $account_id ? 'selected' : '' ?>><?= e($a['account_code'] . ' — ' . $a['account_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink/50 mb-1.5">From</label>
            <input type="date" name="date_from" value="<?= e($date_from) ?>" class="w-full rounded-lg border border-black/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>
        <div>
            <label class="block text-xs font-semibold text-ink/50 mb-1.5">To</label>
            <input type="date" name="date_to" value="<?= e($date_to) ?>" class="w-full rounded-lg border border-black/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
        </div>
        <div class="md:col-span-4">
            <button type="submit" class="bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2 rounded-xl">Apply Filter</button>
        </div>
    </form>
</div>

<?php if ($selected): ?>
<div class="bg-white rounded-2xl border border-black/5 overflow-hidden">
    <div class="px-6 py-4 border-b border-black/5">
        <h2 class="font-display font-semibold text-ink"><?= e($selected['account_code'] . ' — ' . $selected['account_name']) ?></h2>
        <p class="text-xs text-ink/40 mt-0.5">Normal balance: <?= e($selected['normal_balance']) ?> · Showing posted transactions only</p>
    </div>
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5">
                    <th class="px-6 py-3 font-medium">Date</th>
                    <th class="px-3 py-3 font-medium">Entry No.</th>
                    <th class="px-3 py-3 font-medium">Description</th>
                    <th class="px-3 py-3 font-medium text-right">Debit</th>
                    <th class="px-3 py-3 font-medium text-right">Credit</th>
                    <th class="px-6 py-3 font-medium text-right">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <tr class="bg-canvas/60">
                    <td colspan="5" class="px-6 py-2.5 text-ink/50 italic">Opening Balance</td>
                    <td class="px-6 py-2.5 text-right font-mono font-semibold"><?= money($opening_balance) ?></td>
                </tr>
                <?php
                $running = $opening_balance;
                foreach ($rows as $r):
                    $running += $selected['normal_balance'] === 'Debit' ? ($r['debit'] - $r['credit']) : ($r['credit'] - $r['debit']);
                ?>
                <tr class="hover:bg-canvas/60">
                    <td class="px-6 py-3 text-ink/60"><?= date('M j, Y', strtotime($r['entry_date'])) ?></td>
                    <td class="px-3 py-3 font-mono text-xs text-ink/70"><?= e($r['entry_no']) ?></td>
                    <td class="px-3 py-3 text-ink"><?= e($r['memo'] ?: $r['description']) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= $r['debit'] > 0 ? number_format($r['debit'], 2) : '—' ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= $r['credit'] > 0 ? number_format($r['credit'], 2) : '—' ?></td>
                    <td class="px-6 py-3 text-right font-mono font-semibold"><?= money($running) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                <tr><td colspan="6" class="px-6 py-10 text-center text-ink/40">No posted activity in this date range.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-black/5 p-10 text-center text-ink/40">Add accounts in the Chart of Accounts to begin viewing ledger activity.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
