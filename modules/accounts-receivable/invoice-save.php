<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $customer_id  = (int)($_POST['customer_id'] ?? 0);
    $booking_ref  = trim($_POST['booking_ref'] ?? '');
    $invoice_date = $_POST['invoice_date'] ?? date('Y-m-d');
    $due_date     = $_POST['due_date'] ?? date('Y-m-d');
    $amount       = (float)($_POST['amount'] ?? 0);
    $revenue_id   = (int)($_POST['revenue_account_id'] ?? 0);

    if (!$customer_id || $amount <= 0 || !$revenue_id) {
        flash('error', 'Please select a customer, a revenue account, and enter an amount greater than zero.');
        header('Location: invoices.php?new=1');
        exit;
    }

    try {
        $cstmt = $pdo->prepare("SELECT customer_name, customer_type FROM customers WHERE customer_id = ?");
        $cstmt->execute([$customer_id]);
        $customer = $cstmt->fetch();
        if (!$customer) throw new RuntimeException('Customer not found.');

        $ar_code = $customer['customer_type'] === 'Travel Agent' ? '1150' : '1100';
        $ar_account_id = account_id_by_code($pdo, $ar_code);
        if (!$ar_account_id) throw new RuntimeException("AR control account $ar_code is missing from the Chart of Accounts.");

        $pdo->beginTransaction();

        $invoice_no = next_doc_no($pdo, 'ar_invoices', 'invoice_no', 'ARINV');
        $stmt = $pdo->prepare("INSERT INTO ar_invoices (invoice_no, customer_id, booking_ref, invoice_date, due_date, amount, amount_received, status) VALUES (?,?,?,?,?,?,0,'Unpaid')");
        $stmt->execute([$invoice_no, $customer_id, $booking_ref, $invoice_date, $due_date, $amount]);
        $ar_invoice_id = (int)$pdo->lastInsertId();

        $entry_id = post_gl_entry($pdo, [
            'entry_date'    => $invoice_date,
            'description'   => "AR Invoice $invoice_no — {$customer['customer_name']}" . ($booking_ref ? " (Booking $booking_ref)" : ''),
            'reference'     => $invoice_no,
            'source_module' => 'Accounts Receivable',
        ], [
            ['account_id' => $ar_account_id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Invoice issued'],
            ['account_id' => $revenue_id,    'debit' => 0, 'credit' => $amount, 'memo' => 'Invoice issued'],
        ]);

        $pdo->prepare("UPDATE ar_invoices SET linked_entry_id = ? WHERE ar_invoice_id = ?")->execute([$entry_id, $ar_invoice_id]);

        audit_log($pdo, [
            'action' => 'create', 'module' => 'Accounts Receivable', 'entity_type' => 'ar_invoice',
            'entity_id' => $ar_invoice_id, 'entity_no' => $invoice_no,
            'description' => "Created AR invoice $invoice_no for {$customer['customer_name']} — " . money($amount),
            'new_values' => ['amount' => $amount, 'customer_id' => $customer_id, 'booking_ref' => $booking_ref],
        ]);

        $pdo->commit();
        flash('success', "Invoice $invoice_no created and posted to the General Ledger.");
        header('Location: invoice-view.php?id=' . $ar_invoice_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not create invoice: ' . $e->getMessage());
        header('Location: invoices.php?new=1');
        exit;
    }
}

if ($action === 'cancel') {
    $id = (int)($_POST['ar_invoice_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT * FROM ar_invoices WHERE ar_invoice_id = ?");
        $stmt->execute([$id]);
        $inv = $stmt->fetch();
        if (!$inv) throw new RuntimeException('Invoice not found.');
        if ($inv['amount_received'] > 0) throw new RuntimeException('Invoices with payments received cannot be cancelled — void the related collection first.');

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ar_invoices SET status = 'Cancelled' WHERE ar_invoice_id = ?")->execute([$id]);
        void_gl_entry($pdo, $inv['linked_entry_id']);
        audit_log($pdo, [
            'action' => 'cancel', 'module' => 'Accounts Receivable', 'entity_type' => 'ar_invoice',
            'entity_id' => $id, 'entity_no' => $inv['invoice_no'],
            'description' => "Cancelled AR invoice {$inv['invoice_no']}",
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
