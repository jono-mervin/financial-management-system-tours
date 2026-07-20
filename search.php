<?php
$page_title = 'Search';
$page_subtitle = 'Find invoices, vendors, customers, and journal entries.';
$active_module = 'dashboard';
$breadcrumb = ['Search'];
require_once __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("SELECT vendor_id AS id, vendor_code AS code, vendor_name AS name, 'Vendor' AS type, 'modules/accounts-payable/vendors.php' AS href FROM vendors WHERE vendor_name LIKE ? OR vendor_code LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;

    $stmt = $pdo->prepare("SELECT customer_id AS id, customer_code AS code, customer_name AS name, 'Customer' AS type, 'modules/accounts-receivable/customers.php' AS href FROM customers WHERE customer_name LIKE ? OR customer_code LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;

    $stmt = $pdo->prepare("SELECT ap_invoice_id AS id, invoice_no AS code, CONCAT('AP · ', invoice_no) AS name, 'AP Invoice' AS type, CONCAT('modules/accounts-payable/invoice-view.php?id=', ap_invoice_id) AS href FROM ap_invoices WHERE invoice_no LIKE ? LIMIT 10");
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;

    $stmt = $pdo->prepare("SELECT ar_invoice_id AS id, invoice_no AS code, CONCAT('AR · ', invoice_no) AS name, 'AR Invoice' AS type, CONCAT('modules/accounts-receivable/invoice-view.php?id=', ar_invoice_id) AS href FROM ar_invoices WHERE invoice_no LIKE ? OR booking_ref LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;

    $stmt = $pdo->prepare("SELECT entry_id AS id, entry_no AS code, description AS name, 'Journal Entry' AS type, CONCAT('modules/general-ledger/journal-entry-view.php?id=', entry_id) AS href FROM journal_entries WHERE entry_no LIKE ? OR description LIKE ? OR reference LIKE ? LIMIT 10");
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;

    $stmt = $pdo->prepare("SELECT c.collection_id AS id, c.collection_no AS code, CONCAT(cu.customer_name, ' — ', c.collection_no) AS name, 'Collection' AS type, CONCAT('modules/accounts-receivable/collection-view.php?id=', c.collection_id) AS href FROM collections c JOIN ar_invoices i ON i.ar_invoice_id = c.ar_invoice_id JOIN customers cu ON cu.customer_id = i.customer_id WHERE c.collection_no LIKE ? OR c.reference_no LIKE ? LIMIT 10");
    $stmt->execute([$like, $like]);
    foreach ($stmt->fetchAll() as $r) $results[] = $r;
}
?>

<form method="get" class="mb-6 max-w-xl md:hidden">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search…" class="tf-input w-full" autofocus>
</form>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="px-6 py-4 border-b border-black/5 flex items-center justify-between">
        <h2 class="font-display font-semibold text-ink">
            <?php if ($q === ''): ?>Enter a search term
            <?php else: ?>Results for “<?= e($q) ?>” <span class="text-ink/40 font-sans font-medium text-sm">(<?= count($results) ?>)</span>
            <?php endif; ?>
        </h2>
    </div>
    <?php if ($q === ''): ?>
    <p class="px-6 py-12 text-center text-ink/40 text-sm">Use the topbar search to find documents and master records.</p>
    <?php elseif (!$results): ?>
    <p class="px-6 py-12 text-center text-ink/40 text-sm">No matches found.</p>
    <?php else: ?>
    <div class="divide-y divide-black/5">
        <?php foreach ($results as $r): ?>
        <a href="<?= e($r['href']) ?>" class="flex items-center gap-4 px-6 py-3.5 hover:bg-canvas/70 transition-colors">
            <span class="text-[10px] font-semibold uppercase tracking-wide text-primary bg-primary/5 rounded-md px-2 py-1 shrink-0"><?= e($r['type']) ?></span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-ink truncate"><?= e($r['name']) ?></p>
                <p class="text-xs font-mono text-ink/40"><?= e($r['code']) ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink/25 shrink-0"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
