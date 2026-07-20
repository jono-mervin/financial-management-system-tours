<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $ap_invoice_id  = (int)($_POST['ap_invoice_id'] ?? 0);
    $payment_date   = $_POST['payment_date'] ?? date('Y-m-d');
    $payment_method = $_POST['payment_method'] ?? 'Bank Transfer';
    $bank_account   = trim($_POST['bank_account'] ?? '');
    $amount         = (float)($_POST['amount'] ?? 0);
    $reference_no   = trim($_POST['reference_no'] ?? '');

    if (!$ap_invoice_id || $amount <= 0) {
        flash('error', 'Please select an invoice and enter an amount greater than zero.');
        header('Location: disbursements.php' . ($ap_invoice_id ? "?ap_invoice_id=$ap_invoice_id" : '?new=1'));
        exit;
    }

    try {
        $istmt = $pdo->prepare("SELECT i.*, v.vendor_name FROM ap_invoices i JOIN vendors v ON v.vendor_id = i.vendor_id WHERE i.ap_invoice_id = ? FOR UPDATE");
        $pdo->beginTransaction();
        $istmt->execute([$ap_invoice_id]);
        $inv = $istmt->fetch();
        if (!$inv) throw new RuntimeException('Invoice not found.');

        $balance = (float)$inv['amount'] - (float)$inv['amount_paid'];
        if ($amount > $balance + 0.005) {
            throw new RuntimeException('Payment amount (' . money($amount) . ') exceeds the remaining balance (' . money($balance) . ').');
        }

        $cash_id = account_id_by_code($pdo, '1010');
        $ap_id   = account_id_by_code($pdo, '2000');
        if (!$cash_id || !$ap_id) throw new RuntimeException('Required GL accounts (1010, 2000) are missing from the Chart of Accounts.');

        $disbursement_no = next_doc_no($pdo, 'disbursements', 'disbursement_no', 'DISB');
        $stmt = $pdo->prepare("INSERT INTO disbursements (disbursement_no, ap_invoice_id, payment_date, payment_method, bank_account, amount, reference_no) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([$disbursement_no, $ap_invoice_id, $payment_date, $payment_method, $bank_account, $amount, $reference_no]);
        $disbursement_id = (int)$pdo->lastInsertId();

        $new_paid = (float)$inv['amount_paid'] + $amount;
        $new_status = $new_paid >= (float)$inv['amount'] - 0.005 ? 'Paid' : 'Partially Paid';
        $pdo->prepare("UPDATE ap_invoices SET amount_paid = ?, status = ? WHERE ap_invoice_id = ?")->execute([$new_paid, $new_status, $ap_invoice_id]);

        $entry_id = post_gl_entry($pdo, [
            'entry_date'    => $payment_date,
            'description'   => "Disbursement $disbursement_no — {$inv['vendor_name']} (Inv {$inv['invoice_no']})",
            'reference'     => $disbursement_no,
            'source_module' => 'Disbursement',
        ], [
            ['account_id' => $ap_id,   'debit' => $amount, 'credit' => 0, 'memo' => "Payment via $payment_method"],
            ['account_id' => $cash_id, 'debit' => 0, 'credit' => $amount, 'memo' => "Payment via $payment_method"],
        ]);

        $pdo->prepare("UPDATE disbursements SET linked_entry_id = ? WHERE disbursement_id = ?")->execute([$entry_id, $disbursement_id]);

        audit_log($pdo, [
            'action' => 'create', 'module' => 'Disbursement', 'entity_type' => 'disbursement',
            'entity_id' => $disbursement_id, 'entity_no' => $disbursement_no,
            'description' => "Recorded disbursement $disbursement_no to {$inv['vendor_name']} — " . money($amount),
            'new_values' => ['amount' => $amount, 'ap_invoice' => $inv['invoice_no'], 'method' => $payment_method],
        ]);

        $pdo->commit();
        flash('success', "Disbursement $disbursement_no recorded and posted to the General Ledger.");
        header('Location: disbursement-view.php?id=' . $disbursement_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not record disbursement: ' . $e->getMessage());
        header('Location: disbursements.php' . ($ap_invoice_id ? "?ap_invoice_id=$ap_invoice_id" : '?new=1'));
        exit;
    }
}

header('Location: disbursements.php');
exit;
