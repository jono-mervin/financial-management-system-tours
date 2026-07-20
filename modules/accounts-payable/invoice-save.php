<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $vendor_id    = (int)($_POST['vendor_id'] ?? 0);
    $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
    $due_date     = $_POST['due_date'] ?? date('Y-m-d');
    $amount       = (float)($_POST['amount'] ?? 0);
    $expense_id   = (int)($_POST['expense_account_id'] ?? 0);
    $ref_no       = trim($_POST['vendor_invoice_no'] ?? '');

    if (!$vendor_id || $amount <= 0 || !$expense_id) {
        flash('error', 'Please select a vendor, an expense account, and enter an amount greater than zero.');
        header('Location: invoices.php?new=1');
        exit;
    }

    try {
        $vstmt = $pdo->prepare("SELECT vendor_name FROM vendors WHERE vendor_id = ?");
        $vstmt->execute([$vendor_id]);
        $vendor = $vstmt->fetch();
        if (!$vendor) throw new RuntimeException('Vendor not found.');

        $ap_account_id = account_id_by_code($pdo, '2000');
        if (!$ap_account_id) throw new RuntimeException('Accounts Payable control account (2000) is missing from the Chart of Accounts.');

        $pdo->beginTransaction();

        $invoice_no = next_doc_no($pdo, 'ap_invoices', 'invoice_no', 'APINV');
        $stmt = $pdo->prepare("INSERT INTO ap_invoices (invoice_no, vendor_id, invoice_date, due_date, amount, amount_paid, status) VALUES (?,?,?,?,?,0,'Unpaid')");
        $stmt->execute([$invoice_no, $vendor_id, $invoice_date, $due_date, $amount]);
        $ap_invoice_id = (int)$pdo->lastInsertId();

        $desc = "AP Invoice $invoice_no — {$vendor['vendor_name']}" . ($ref_no ? " (Vendor ref $ref_no)" : '');
        $entry_id = post_gl_entry($pdo, [
            'entry_date'    => $invoice_date,
            'description'   => $desc,
            'reference'     => $invoice_no,
            'source_module' => 'Accounts Payable',
        ], [
            ['account_id' => $expense_id,   'debit' => $amount, 'credit' => 0, 'memo' => 'Bill received'],
            ['account_id' => $ap_account_id, 'debit' => 0, 'credit' => $amount, 'memo' => 'Bill received'],
        ]);

        $pdo->prepare("UPDATE ap_invoices SET linked_entry_id = ? WHERE ap_invoice_id = ?")->execute([$entry_id, $ap_invoice_id]);

        audit_log($pdo, [
            'action' => 'create', 'module' => 'Accounts Payable', 'entity_type' => 'ap_invoice',
            'entity_id' => $ap_invoice_id, 'entity_no' => $invoice_no,
            'description' => "Created AP invoice $invoice_no for {$vendor['vendor_name']} — " . money($amount),
            'new_values' => ['amount' => $amount, 'vendor_id' => $vendor_id, 'due_date' => $due_date],
        ]);

        $pdo->commit();
        flash('success', "Invoice $invoice_no created and posted to the General Ledger.");
        header('Location: invoice-view.php?id=' . $ap_invoice_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not create invoice: ' . $e->getMessage());
        header('Location: invoices.php?new=1');
        exit;
    }
}

if ($action === 'cancel') {
    $id = (int)($_POST['ap_invoice_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM ap_invoices WHERE ap_invoice_id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) throw new RuntimeException('Invoice not found.');
        if ($inv['amount_paid'] > 0) throw new RuntimeException('Invoices with payments made cannot be cancelled — void the related disbursement first.');

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ap_invoices SET status = 'Cancelled' WHERE ap_invoice_id = ?")->execute([$id]);
        void_gl_entry($pdo, $inv['linked_entry_id']);
        audit_log($pdo, [
            'action' => 'cancel', 'module' => 'Accounts Payable', 'entity_type' => 'ap_invoice',
            'entity_id' => $id, 'entity_no' => $inv['invoice_no'],
            'description' => "Cancelled AP invoice {$inv['invoice_no']}",
            'old_values' => ['status' => $inv['status'], 'amount' => $inv['amount']],
        ]);
        $pdo->commit();
        flash('success', 'Invoice cancelled and its journal entry voided.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', $e->getMessage());
    }
    header('Location: invoice-view.php?id=' . $id);
    exit;
}

header('Location: invoices.php');
exit;
