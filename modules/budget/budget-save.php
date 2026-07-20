<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $budget_name = trim($_POST['budget_name'] ?? '');
    $period_id   = (int)($_POST['period_id'] ?? 0);
    $department  = trim($_POST['department'] ?? '');
    $account_ids = $_POST['account_id'] ?? [];
    $amounts     = $_POST['budgeted_amount'] ?? [];
    $notes       = $_POST['notes'] ?? [];

    $clean_lines = [];
    foreach ($account_ids as $i => $acc_id) {
        $amt = (float)($amounts[$i] ?? 0);
        if (!$acc_id || $amt <= 0) continue;
        $clean_lines[] = ['account_id' => (int)$acc_id, 'amount' => $amt, 'notes' => trim($notes[$i] ?? '')];
    }

    if ($budget_name === '' || !$period_id || count($clean_lines) < 1) {
        flash('error', 'Please name the budget, choose a fiscal period, and add at least one line.');
        header('Location: budgets.php?new=1');
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO budgets (budget_name, period_id, department, status) VALUES (?,?,?,'Draft')");
        $stmt->execute([$budget_name, $period_id, $department]);
        $budget_id = (int)$pdo->lastInsertId();

        $lstmt = $pdo->prepare("INSERT INTO budget_lines (budget_id, account_id, budgeted_amount, notes) VALUES (?,?,?,?)");
        foreach ($clean_lines as $l) {
            $lstmt->execute([$budget_id, $l['account_id'], $l['amount'], $l['notes']]);
        }

        audit_log($pdo, [
            'action' => 'create', 'module' => 'Budget', 'entity_type' => 'budget',
            'entity_id' => $budget_id, 'entity_no' => $budget_name,
            'description' => "Created budget \"$budget_name\" with " . count($clean_lines) . ' line(s)',
            'new_values' => ['department' => $department, 'lines' => count($clean_lines)],
        ]);

        $pdo->commit();
        flash('success', "Budget \"$budget_name\" created as Draft.");
        header('Location: budget-vs-actual.php?id=' . $budget_id);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('error', 'Could not create budget: ' . $e->getMessage());
        header('Location: budgets.php?new=1');
        exit;
    }
}

if ($action === 'approve') {
    $id = (int)($_POST['budget_id'] ?? 0);
    $name = $pdo->prepare("SELECT budget_name FROM budgets WHERE budget_id=?");
    $name->execute([$id]);
    $budget_name = $name->fetchColumn() ?: (string)$id;
    $pdo->prepare("UPDATE budgets SET status='Approved' WHERE budget_id=? AND status='Draft'")->execute([$id]);
    audit_log($pdo, [
        'action' => 'approve', 'module' => 'Budget', 'entity_type' => 'budget',
        'entity_id' => $id, 'entity_no' => $budget_name,
        'description' => "Approved budget \"$budget_name\"",
    ]);
    flash('success', 'Budget approved.');
    header('Location: budget-vs-actual.php?id=' . $id);
    exit;
}

if ($action === 'close') {
    $id = (int)($_POST['budget_id'] ?? 0);
    $name = $pdo->prepare("SELECT budget_name FROM budgets WHERE budget_id=?");
    $name->execute([$id]);
    $budget_name = $name->fetchColumn() ?: (string)$id;
    $pdo->prepare("UPDATE budgets SET status='Closed' WHERE budget_id=? AND status='Approved'")->execute([$id]);
    audit_log($pdo, [
        'action' => 'close', 'module' => 'Budget', 'entity_type' => 'budget',
        'entity_id' => $id, 'entity_no' => $budget_name,
        'description' => "Closed budget \"$budget_name\"",
    ]);
    flash('success', 'Budget closed.');
    header('Location: budget-vs-actual.php?id=' . $id);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['budget_id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT budget_name, status FROM budgets WHERE budget_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || $row['status'] !== 'Draft') {
            flash('error', 'Only Draft budgets can be deleted.');
        } else {
            $pdo->prepare("DELETE FROM budgets WHERE budget_id = ?")->execute([$id]);
            audit_log($pdo, [
                'action' => 'delete', 'module' => 'Budget', 'entity_type' => 'budget',
                'entity_id' => $id, 'entity_no' => $row['budget_name'],
                'description' => "Deleted budget \"{$row['budget_name']}\"",
            ]);
            flash('success', 'Budget deleted.');
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: budgets.php');
exit;
