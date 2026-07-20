<?php
$page_title = 'Customers';
$page_subtitle = 'Travellers, corporates, and agents you bill through accounts receivable.';
$active_module = 'ar';
$breadcrumb = ['Accounts Receivable', 'Customers'];
require_once __DIR__ . '/../../includes/header.php';

$type_filter = $_GET['type'] ?? '';
$sql = "SELECT c.*,
        (SELECT COUNT(*) FROM ar_invoices WHERE customer_id = c.customer_id) AS invoice_count,
        (SELECT COALESCE(SUM(amount - amount_received),0) FROM ar_invoices WHERE customer_id = c.customer_id AND status IN ('Unpaid','Partially Paid')) AS outstanding
        FROM customers c";
$params = [];
if ($type_filter) {
    $sql .= " WHERE c.customer_type = ?";
    $params[] = $type_filter;
}
$sql .= " ORDER BY c.customer_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$types = ['Individual', 'Corporate', 'Travel Agent', 'Group'];
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="customers.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($types as $t): ?>
        <a href="?type=<?= urlencode($t) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === $t ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3">
        <a href="invoices.php" class="text-sm font-medium text-primary hover:underline">View Invoices →</a>
        <button onclick="openCustomerModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20 hover:scale-[1.02]">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Add Customer
        </button>
    </div>
</div>

<div class="bg-white rounded-2xl border border-black/5 overflow-hidden tf-card">
    <div class="overflow-x-auto thin-scroll">
        <table class="w-full text-sm tf-table">
            <thead>
                <tr class="text-left text-ink/40 text-xs uppercase tracking-wide border-b border-black/5 bg-canvas/50">
                    <th class="px-6 py-3 font-medium">Code</th>
                    <th class="px-3 py-3 font-medium">Name</th>
                    <th class="px-3 py-3 font-medium">Type</th>
                    <th class="px-3 py-3 font-medium">Contact</th>
                    <th class="px-3 py-3 font-medium text-right">Invoices</th>
                    <th class="px-3 py-3 font-medium text-right">Outstanding</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$customers): ?>
                <tr><td colspan="8" class="px-6 py-10 text-center text-ink/40">No customers found. <button onclick="openCustomerModal()" class="text-primary font-medium hover:underline">Add the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($customers as $c): ?>
                <tr class="hover:bg-canvas/60 <?= !$c['is_active'] ? 'opacity-40' : '' ?>">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($c['customer_code']) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($c['customer_name']) ?></td>
                    <td class="px-3 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 bg-sky-50 text-sky-700 ring-sky-600/20"><?= e($c['customer_type']) ?></span></td>
                    <td class="px-3 py-3 text-ink/50 text-xs"><?= e($c['email'] ?: ($c['phone'] ?: '—')) ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= (int)$c['invoice_count'] ?></td>
                    <td class="px-3 py-3 text-right font-mono <?= $c['outstanding'] > 0 ? 'text-amber-600 font-semibold' : 'text-ink/40' ?>"><?= money((float)$c['outstanding']) ?></td>
                    <td class="px-3 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $c['is_active'] ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/20' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button onclick='openCustomerModal(<?= json_encode($c) ?>)' class="text-primary hover:underline text-xs font-semibold">Edit</button>
                            <form method="post" action="customers-save.php" class="inline" data-confirm="Delete this customer? This cannot be undone.">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="customer_id" value="<?= (int)$c['customer_id'] ?>">
                                <button type="submit" class="text-rose-500 hover:underline text-xs font-semibold">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<dialog id="customer-modal" class="tf-modal">
    <div class="tf-modal-panel">
        <form method="post" action="customers-save.php">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="customer_id" id="modal-customer-id">

            <div class="tf-modal-header">
                <div>
                    <h3 id="modal-title">Add Customer</h3>
                    <p id="modal-subtitle">Register a traveller, corporate, or agent</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="document.getElementById('customer-modal').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="tf-modal-body space-y-4">
                <div>
                    <label class="tf-modal-label">Customer / Traveller Name *</label>
                    <input required name="customer_name" id="modal-name" class="tf-input w-full" placeholder="e.g. Maria Santos, or ABC Corporate Travel">
                </div>
                <div>
                    <label class="tf-modal-label">Customer Type *</label>
                    <select required name="customer_type" id="modal-type" class="tf-input w-full">
                        <?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="tf-modal-label">Email</label>
                        <input type="email" name="email" id="modal-email" class="tf-input w-full">
                    </div>
                    <div>
                        <label class="tf-modal-label">Phone</label>
                        <input name="phone" id="modal-phone" class="tf-input w-full">
                    </div>
                </div>
                <label class="flex items-center gap-2.5 text-sm text-ink/70 cursor-pointer select-none">
                    <input type="checkbox" name="is_active" id="modal-active" checked class="rounded border-black/20 text-primary focus:ring-primary/30 w-4 h-4">
                    Active customer
                </label>
            </div>

            <div class="tf-modal-footer">
                <button type="button" onclick="document.getElementById('customer-modal').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-all hover:shadow-lg hover:shadow-primary/20">Save Customer</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openCustomerModal(data) {
    const modal = document.getElementById('customer-modal');
    const isEdit = !!data;
    document.getElementById('modal-title').textContent = isEdit ? 'Edit Customer' : 'Add Customer';
    document.getElementById('modal-subtitle').textContent = isEdit ? 'Update customer details' : 'Register a traveller, corporate, or agent';
    document.getElementById('modal-action').value = isEdit ? 'update' : 'create';
    document.getElementById('modal-customer-id').value = isEdit ? data.customer_id : '';
    document.getElementById('modal-name').value = isEdit ? data.customer_name : '';
    document.getElementById('modal-type').value = isEdit ? data.customer_type : 'Individual';
    document.getElementById('modal-email').value = isEdit ? (data.email || '') : '';
    document.getElementById('modal-phone').value = isEdit ? (data.phone || '') : '';
    document.getElementById('modal-active').checked = isEdit ? !!Number(data.is_active) : true;
    modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
