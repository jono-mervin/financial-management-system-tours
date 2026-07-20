<?php
$page_title = 'Journal Entry Detail';
$page_subtitle = 'Review lines, status, and posting details.';
$active_module = 'gl';

$entry_id = (int)($_GET['id'] ?? 0);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

$stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE entry_id = ?");
$stmt->execute([$entry_id]);
$entry = $stmt->fetch();

if (!$entry) { flash('error', 'Journal entry not found.'); header('Location: journal-entries.php'); exit; }

$breadcrumb = ['General Ledger', 'Journal Entries', $entry['entry_no']];
require_once __DIR__ . '/../../includes/header.php';

$lstmt = $pdo->prepare("SELECT l.*, a.account_code, a.account_name FROM journal_entry_lines l JOIN chart_of_accounts a ON a.account_id = l.account_id WHERE l.entry_id = ? ORDER BY l.line_order");
$lstmt->execute([$entry_id]);
$lines = $lstmt->fetchAll();

$total_debit = array_sum(array_column($lines, 'debit'));
$total_credit = array_sum(array_column($lines, 'credit'));
?>

<div class="flex items-center justify-between mb-5 -mt-2">
    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 <?= badge_class($entry['status']) ?>"><?= e($entry['status']) ?></span>
    <div class="flex items-center gap-3 no-print">
        <button onclick="window.print()" class="text-sm font-medium text-ink/60 hover:text-ink flex items-center gap-1.5">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            Print
        </button>
        <?php if ($entry['status'] === 'Draft'): ?>
        <a href="journal-entries.php?edit=<?= $entry_id ?>" class="text-sm font-medium text-primary hover:underline">Edit Entry</a>
        <form method="post" action="journal-entry-save.php" data-confirm="Post this entry? It will affect account balances.">
            <input type="hidden" name="action" value="post">
            <input type="hidden" name="entry_id" value="<?= $entry_id ?>">
            <button type="submit" class="bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2 rounded-xl">Post Entry</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 p-8">
    <div class="flex items-start justify-between pb-6 mb-6 border-b border-black/5">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Journal Entry</p>
            <h2 class="font-display font-bold text-2xl text-ink font-mono"><?= e($entry['entry_no']) ?></h2>
        </div>
        <div class="text-right text-sm">
            <p class="text-ink/40">Date</p>
            <p class="font-medium text-ink"><?= date('F j, Y', strtotime($entry['entry_date'])) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-8 text-sm">
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Description</p>
            <p class="text-ink"><?= e($entry['description']) ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Reference</p>
            <p class="text-ink"><?= e($entry['reference'] ?: '—') ?></p>
        </div>
        <div>
            <p class="text-xs font-semibold text-ink/40 uppercase tracking-wide mb-1">Source Module</p>
            <p class="text-ink"><?= e($entry['source_module']) ?></p>
        </div>
    </div>

    <table class="w-full text-sm mb-2">
        <thead>
            <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/10">
                <th class="py-2 font-medium">Account</th>
                <th class="py-2 font-medium">Memo</th>
                <th class="py-2 font-medium text-right">Debit</th>
                <th class="py-2 font-medium text-right">Credit</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-black/5">
            <?php foreach ($lines as $l): ?>
            <tr>
                <td class="py-3"><span class="font-mono text-xs text-ink/50 mr-2"><?= e($l['account_code']) ?></span><?= e($l['account_name']) ?></td>
                <td class="py-3 text-ink/50"><?= e($l['memo'] ?: '—') ?></td>
                <td class="py-3 text-right font-mono"><?= $l['debit'] > 0 ? number_format($l['debit'], 2) : '—' ?></td>
                <td class="py-3 text-right font-mono"><?= $l['credit'] > 0 ? number_format($l['credit'], 2) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="border-t-2 border-ink/10 font-semibold">
                <td class="py-3" colspan="2">Total</td>
                <td class="py-3 text-right font-mono"><?= number_format($total_debit, 2) ?></td>
                <td class="py-3 text-right font-mono"><?= number_format($total_credit, 2) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
