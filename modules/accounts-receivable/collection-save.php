<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $ar_invoice_id  = (int)($_POST['ar_invoice_id'] ?? 0);
    $collection_date = $_POST['collection_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $bank_account   = trim($_POST['bank_account'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $reference_no   = trim($_POST['reference_no'] ?? '');

    if (!$ar_invoice_id || $amount <= 0) {
        flash('error', 'Please select an invoice and enter an amount greater than zero.');
        header('Location: collections.php' . ($ar_invoice_id ? "?ar_invoice_id=$ar_invoice_id" : '?new=1'));
        exit;
    }

    try {
        $istmt = $pdo->prepare("SELECT i.*, c.customer_name, c.customer_type FROM ar_invoices i JOIN customers c ON c.customer_id = i.customer_id WHERE i.ar_invoice_id = ? FOR UPDATE");
        $pdo->beginTransaction();
        $istmt->execute([$ar_invoice_id]);
        $inv = $istmt->fetch();
        if (!$inv) throw new RuntimeException('Invoice not found.');

        $balance = (float)$inv['amount'] - (float)$inv['amount_received'];
        if ($amount > $balance + 0.005) {
            throw new RuntimeException('Collection amount (' . money($amount) . ') exceeds the remaining balance (' . money($balance) . ').');
        }

        $cash_id = account_id_by_code($pdo, '1010');
        $ar_code = $inv['customer_type'] === 'Travel Agent' ? '1150' : '1100';
        $ar_id   = account_id_by_code($pdo, $ar_code);
        if (!$cash_id || !$ar_id) throw new RuntimeException('Required GL accounts (1010, ' . $ar_code . ') are missing from the Chart of Accounts.');

        $collection_no = next_doc_no($pdo, 'collections', 'collection_no', 'COLL');
        $stmt = $pdo->prepare("INSERT INTO collections (collection_no, ar_invoice_id, collection_date, payment_method, bank_account, amount, reference_no) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$collection_no, $ar_invoice_id, $collection_date, $payment_method, $bank_account, $amount, $reference_no]);
        $collection_id = (int)$pdo->lastInsertId();

        $new_received = (float)$inv['amount_received'] + $amount;
        $new_status = $new_received >= (float)$inv['amount'] - 0.005 ? 'Paid' : 'Partially Paid';
        $pdo->prepare("UPDATE ar_invoices SET amount_received = ?, status = ? WHERE ar_invoice_id = ?")->execute([$new_received, $new_status, $ar_invoice_id]);

        $entry_id = post_gl_entry($pdo, [
            'entry_date'    => $collection_date,
            'description'   => "Collection $collection_no — {$inv['customer_name']} (Inv {$inv['invoice_no']})",
            'reference'     => $collection_no,
            'source_module' => 'Collection',
        ], [
            ['account_id' => $cash_id, 'debit' => $amount, 'credit' => 0, 'memo' => "Receipt via $payment_method"],
            ['account_id' => $ar_id,   'debit' => 0, 'credit' => $amount, 'memo' => "Receipt via $payment_method"],
        ]);

        $pdo->prepare("UPDATE collections SET linked_entry_id = ? WHERE collection_id = ?")->execute([$entry_id, $collection_id]);

        audit_log($pdo, [
            'action' => 'create', 'module' => 'Accounts Receivable', 'entity_type' => 'collection',
            'entity_id' => $collection_id, 'entity_no' => $collection_no,
            'description' => "Recorded collection $collection_no from {$inv['customer_name']} — " . money($amount),
            'new_values' => ['amount' => $amount, 'ar_invoice' => $inv['invoice_no'], 'method' => $payment_method],
        ]);

        $pdo->commit();
        flash('success', "Collection $collection_no recorded and posted to the General Ledger.");
        header('Location: collection-view.php?id=' . $collection_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not record collection: ' . $e->getMessage());
        header('Location: collections.php' . ($ar_invoice_id ? "?ar_invoice_id=$ar_invoice_id" : '?new=1'));
        exit;
    }
}

header('Location: collections.php');
exit;
