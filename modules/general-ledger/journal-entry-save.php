<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create' || $action === 'update') {
    $entry_date  = $_POST['entry_date'] ?? date('Y-m-d');
    $reference   = trim($_POST['reference'] ?? '');
    $source      = $_POST['source_module'] ?? 'Manual';
    $description = trim($_POST['description'] ?? '');

    $account_ids = $_POST['account_id'] ?? [];
    $debits      = $_POST['debit'] ?? [];
    $credits     = $_POST['credit'] ?? [];
    $memos       = $_POST['memo'] ?? [];

    $clean_lines = [];
    $total_debit = 0; $total_credit = 0;
    foreach ($account_ids as $i => $acc_id) {
        $debit  = (float)($debits[$i] ?? 0);
        $credit = (float)($credits[$i] ?? 0);
        if (!$acc_id || ($debit == 0 && $credit == 0)) continue;
        $clean_lines[] = ['account_id' => (int)$acc_id, 'debit' => $debit, 'credit' => $credit, 'memo' => trim($memos[$i] ?? '')];
        $total_debit += $debit;
        $total_credit += $credit;
    }

    if ($description === '' || count($clean_lines) < 2) {
        flash('error', 'A journal entry needs a description and at least two lines.');
        header('Location: journal-entries.php' . (!empty($_POST['entry_id']) ? '?edit=' . (int)$_POST['entry_id'] : '?new=1'));
        exit;
    }
    if (abs($total_debit - $total_credit) > 0.005) {
        flash('error', 'Total debits (' . money($total_debit) . ') must equal total credits (' . money($total_credit) . ').');
        header('Location: journal-entries.php' . (!empty($_POST['entry_id']) ? '?edit=' . (int)$_POST['entry_id'] : '?new=1'));
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($action === 'create') {
            $entry_no = next_doc_no($pdo, 'journal_entries', 'entry_no', 'JE');
            $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_no, entry_date, reference, description, source_module, status, created_by) VALUES (?,?,?,?,?, 'Draft', ?)");
            $stmt->execute([$entry_no, $entry_date, $reference, $description, $source, current_user_id()]);
            $entry_id = (int)$pdo->lastInsertId();
        } else {
            $entry_id = (int)$_POST['entry_id'];
            $stmt = $pdo->prepare("UPDATE journal_entries SET entry_date=?, reference=?, description=?, source_module=? WHERE entry_id=? AND status='Draft'");
            $stmt->execute([$entry_date, $reference, $description, $source, $entry_id]);
            $pdo->prepare("DELETE FROM journal_entry_lines WHERE entry_id = ?")->execute([$entry_id]);
            $entry_no = $pdo->prepare("SELECT entry_no FROM journal_entries WHERE entry_id=?");
            $entry_no->execute([$entry_id]);
            $entry_no = $entry_no->fetchColumn() ?: (string)$entry_id;
        }

        $lstmt = $pdo->prepare("INSERT INTO journal_entry_lines (entry_id, account_id, debit, credit, memo, line_order) VALUES (?,?,?,?,?,?)");
        foreach ($clean_lines as $order => $l) {
            $lstmt->execute([$entry_id, $l['account_id'], $l['debit'], $l['credit'], $l['memo'], $order]);
        }

        audit_log($pdo, [
            'action' => $action, 'module' => 'General Ledger', 'entity_type' => 'journal_entry',
            'entity_id' => $entry_id, 'entity_no' => $entry_no ?? null,
            'description' => ($action === 'create' ? 'Created' : 'Updated') . ' draft journal entry ' . ($entry_no ?? $entry_id) . " — $description",
            'new_values' => ['amount' => $total_debit, 'lines' => count($clean_lines)],
        ]);

        $pdo->commit();
        flash('success', 'Journal entry saved as draft.');
        header('Location: journal-entry-view.php?id=' . $entry_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        flash('error', 'Could not save entry: ' . $e->getMessage());
        header('Location: journal-entries.php');
        exit;
    }
}

if ($action === 'post') {
    $entry_id = (int)$_POST['entry_id'];
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(debit),0) AS d, COALESCE(SUM(credit),0) AS c FROM journal_entry_lines WHERE entry_id = ?");
        $stmt->execute([$entry_id]);
        $tot = $stmt->fetch();
        if (abs($tot['d'] - $tot['c']) > 0.005 || $tot['d'] == 0) {
            flash('error', 'This entry is out of balance and cannot be posted.');
        } else {
            $info = $pdo->prepare("SELECT entry_no, description FROM journal_entries WHERE entry_id=?");
            $info->execute([$entry_id]);
            $je = $info->fetch();
            $pdo->prepare("UPDATE journal_entries SET status='Posted', posted_by=?, posted_at=NOW() WHERE entry_id=? AND status='Draft'")
                ->execute([current_user_id(), $entry_id]);
            audit_log($pdo, [
                'action' => 'post', 'module' => 'General Ledger', 'entity_type' => 'journal_entry',
                'entity_id' => $entry_id, 'entity_no' => $je['entry_no'] ?? null,
                'description' => 'Posted journal entry ' . ($je['entry_no'] ?? $entry_id) . ' — ' . ($je['description'] ?? ''),
                'new_values' => ['amount' => (float)$tot['d']],
            ]);
            flash('success', 'Journal entry posted.');
        }
    } catch (Exception $e) {
        flash('error', 'Could not post entry: ' . $e->getMessage());
    }
    header('Location: journal-entries.php');
    exit;
}

if ($action === 'void') {
    $entry_id = (int)$_POST['entry_id'];
    $info = $pdo->prepare("SELECT entry_no, description FROM journal_entries WHERE entry_id=?");
    $info->execute([$entry_id]);
    $je = $info->fetch();
    $pdo->prepare("UPDATE journal_entries SET status='Void' WHERE entry_id=? AND status='Posted'")->execute([$entry_id]);
    audit_log($pdo, [
        'action' => 'void', 'module' => 'General Ledger', 'entity_type' => 'journal_entry',
        'entity_id' => $entry_id, 'entity_no' => $je['entry_no'] ?? null,
        'description' => 'Voided journal entry ' . ($je['entry_no'] ?? $entry_id),
    ]);
    flash('success', 'Journal entry voided.');
    header('Location: journal-entries.php');
    exit;
}

header('Location: journal-entries.php');
exit;
