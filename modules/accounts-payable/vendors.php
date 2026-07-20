<?php
$page_title = 'Vendors';
$page_subtitle = 'Hotels, airlines, DMCs, and other payees for accounts payable.';
$active_module = 'ap';
$breadcrumb = ['Accounts Payable', 'Vendors'];
require_once __DIR__ . '/../../includes/header.php';

$type_filter = $_GET['type'] ?? '';
$sql = "SELECT v.*,
        (SELECT COUNT(*) FROM ap_invoices WHERE vendor_id = v.vendor_id) AS invoice_count,
        (SELECT COALESCE(SUM(amount - amount_paid),0) FROM ap_invoices WHERE vendor_id = v.vendor_id AND status IN ('Unpaid','Partially Paid')) AS outstanding
        FROM vendors v";
$params = [];
if ($type_filter) {
    $sql .= " WHERE v.vendor_type = ?";
    $params[] = $type_filter;
}
$sql .= " ORDER BY v.vendor_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vendors = $stmt->fetchAll();

$types = ['Hotel', 'Airline', 'Transport', 'Tour Guide/DMC', 'Restaurant', 'Insurance', 'Other'];
?>

<div class="flex items-center justify-between mb-5 flex-wrap gap-3">
    <div class="flex items-center gap-2 flex-wrap">
        <a href="vendors.php" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === '' ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>">All</a>
        <?php foreach ($types as $t): ?>
        <a href="?type=<?= urlencode($t) ?>" class="px-3 py-1.5 rounded-full text-xs font-semibold ring-1 transition-all <?= $type_filter === $t ? 'bg-primary text-white ring-primary' : 'bg-white text-ink/60 ring-black/10 hover:bg-canvas' ?>"><?= e($t) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center gap-3">
        <a href="invoices.php" class="text-sm font-medium text-primary hover:underline">View Invoices →</a>
        <button onclick="openVendorModal()" class="flex items-center gap-2 bg-primary hover:bg-primary-light text-white text-sm font-medium px-4 py-2.5 rounded-xl transition-all hover:shadow-lg hover:shadow-primary/20 hover:scale-[1.02]">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            Add Vendor
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
                    <th class="px-3 py-3 font-medium">Terms</th>
                    <th class="px-3 py-3 font-medium text-right">Invoices</th>
                    <th class="px-3 py-3 font-medium text-right">Outstanding</th>
                    <th class="px-3 py-3 font-medium text-center">Status</th>
                    <th class="px-6 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-black/5">
                <?php if (!$vendors): ?>
                <tr><td colspan="8" class="px-6 py-10 text-center text-ink/40">No vendors found. <button onclick="openVendorModal()" class="text-primary font-medium hover:underline">Add the first one →</button></td></tr>
                <?php endif; ?>
                <?php foreach ($vendors as $v): ?>
                <tr class="hover:bg-canvas/60 <?= !$v['is_active'] ? 'opacity-40' : '' ?>">
                    <td class="px-6 py-3 font-mono text-xs text-ink/70"><?= e($v['vendor_code']) ?></td>
                    <td class="px-3 py-3 font-medium text-ink"><?= e($v['vendor_name']) ?></td>
                    <td class="px-3 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 bg-violet-50 text-violet-700 ring-violet-600/20"><?= e($v['vendor_type']) ?></span></td>
                    <td class="px-3 py-3 text-ink/50 text-xs"><?= e($v['payment_terms'] ?: '—') ?></td>
                    <td class="px-3 py-3 text-right font-mono"><?= (int)$v['invoice_count'] ?></td>
                    <td class="px-3 py-3 text-right font-mono <?= $v['outstanding'] > 0 ? 'text-amber-600 font-semibold' : 'text-ink/40' ?>"><?= money((float)$v['outstanding']) ?></td>
                    <td class="px-3 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 <?= $v['is_active'] ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-500 ring-slate-500/20' ?>"><?= $v['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <button onclick='openVendorModal(<?= json_encode($v) ?>)' class="text-primary hover:underline text-xs font-semibold">Edit</button>
                            <form method="post" action="vendors-save.php" class="inline" data-confirm="Delete this vendor? This cannot be undone.">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="vendor_id" value="<?= (int)$v['vendor_id'] ?>">
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

<dialog id="vendor-modal" class="tf-modal">
    <div class="tf-modal-panel">
        <form method="post" action="vendors-save.php">
            <input type="hidden" name="action" id="modal-action" value="create">
            <input type="hidden" name="vendor_id" id="modal-vendor-id">

            <div class="tf-modal-header">
                <div>
                    <h3 id="modal-title">Add Vendor</h3>
                    <p id="modal-subtitle">Register a hotel, airline, or supplier</p>
                </div>
                <button type="button" class="tf-modal-close" onclick="document.getElementById('vendor-modal').close()" aria-label="Close">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>

            <div class="tf-modal-body space-y-4">
                <div>
                    <label class="tf-modal-label">Vendor Name *</label>
                    <input required name="vendor_name" id="modal-name" class="tf-input w-full" placeholder="e.g. Shangri-La Boracay">
                </div>
                <div>
                    <label class="tf-modal-label">Vendor Type *</label>
                    <select required name="vendor_type" id="modal-type" class="tf-input w-full">
                        <?php foreach ($types as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="tf-modal-label">Contact Person</label>
                    <input name="contact_person" id="modal-contact" class="tf-input w-full">
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
                <div>
                    <label class="tf-modal-label">Payment Terms</label>
                    <input name="payment_terms" id="modal-terms" placeholder="e.g. Net 30" class="tf-input w-full">
                </div>
                <label class="flex items-center gap-2.5 text-sm text-ink/70 cursor-pointer select-none">
                    <input type="checkbox" name="is_active" id="modal-active" checked class="rounded border-black/20 text-primary focus:ring-primary/30 w-4 h-4">
                    Active vendor
                </label>
            </div>

            <div class="tf-modal-footer">
                <button type="button" onclick="document.getElementById('vendor-modal').close()" class="px-4 py-2.5 rounded-xl text-sm font-medium text-ink/60 hover:bg-canvas transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-primary text-white hover:bg-primary-light transition-all hover:shadow-lg hover:shadow-primary/20">Save Vendor</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function openVendorModal(data) {
    const modal = document.getElementById('vendor-modal');
    const isEdit = !!data;
    document.getElementById('modal-title').textContent = isEdit ? 'Edit Vendor' : 'Add Vendor';
    document.getElementById('modal-subtitle').textContent = isEdit ? 'Update vendor details' : 'Register a hotel, airline, or supplier';
    document.getElementById('modal-action').value = isEdit ? 'update' : 'create';
    document.getElementById('modal-vendor-id').value = isEdit ? data.vendor_id : '';
    document.getElementById('modal-name').value = isEdit ? data.vendor_name : '';
    document.getElementById('modal-type').value = isEdit ? data.vendor_type : 'Hotel';
    document.getElementById('modal-contact').value = isEdit ? (data.contact_person || '') : '';
    document.getElementById('modal-email').value = isEdit ? (data.email || '') : '';
    document.getElementById('modal-phone').value = isEdit ? (data.phone || '') : '';
    document.getElementById('modal-terms').value = isEdit ? (data.payment_terms || '') : '';
    document.getElementById('modal-active').checked = isEdit ? !!Number(data.is_active) : true;
    modal.showModal();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
