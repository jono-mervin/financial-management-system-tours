<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'create' || $action === 'update') {
    $code        = trim($_POST['account_code'] ?? '');
    $name        = trim($_POST['account_name'] ?? '');
    $type        = $_POST['account_type'] ?? '';
    $subtype     = trim($_POST['account_subtype'] ?? '');
    $normal      = $_POST['normal_balance'] ?? '';
    $desc        = trim($_POST['description'] ?? '');
    $is_active   = isset($_POST['is_active']) ? 1 : 0;

    if ($code === '' || $name === '' || !$type || !$normal) {
        flash('error', 'Please complete all required fields.');
        header('Location: chart-of-accounts.php');
        exit;
    }

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO chart_of_accounts (account_code, account_name, account_type, account_subtype, normal_balance, description, is_active) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$code, $name, $type, $subtype, $normal, $desc, $is_active]);
            $id = (int)$pdo->lastInsertId();
            audit_log($pdo, [
                'action' => 'create', 'module' => 'General Ledger', 'entity_type' => 'account',
                'entity_id' => $id, 'entity_no' => $code,
                'description' => "Created account $code — $name",
                'new_values' => compact('code', 'name', 'type', 'subtype', 'normal', 'is_active'),
            ]);
            flash('success', "Account \"$name\" created.");
        } else {
            $id = (int)$_POST['account_id'];
            $old = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE account_id=?");
            $old->execute([$id]);
            $prev = $old->fetch() ?: [];
            $stmt = $pdo->prepare("UPDATE chart_of_accounts SET account_code=?, account_name=?, account_type=?, account_subtype=?, normal_balance=?, description=?, is_active=? WHERE account_id=?");
            $stmt->execute([$code, $name, $type, $subtype, $normal, $desc, $is_active, $id]);
            audit_log($pdo, [
                'action' => 'update', 'module' => 'General Ledger', 'entity_type' => 'account',
                'entity_id' => $id, 'entity_no' => $code,
                'description' => "Updated account $code — $name",
                'old_values' => $prev,
                'new_values' => compact('code', 'name', 'type', 'subtype', 'normal', 'is_active'),
            ]);
            flash('success', "Account \"$name\" updated.");
        }
    } catch (PDOException $e) {
        flash('error', $e->getCode() == 23000 ? 'That account code already exists.' : 'Database error: ' . $e->getMessage());
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['account_id'] ?? $_GET['account_id'] ?? 0);
    try {
        $used = $pdo->prepare("SELECT COUNT(*) FROM journal_entry_lines WHERE account_id = ?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            flash('error', 'This account has journal entry activity and cannot be deleted. You can deactivate it instead.');
        } else {
            $row = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE account_id=?");
            $row->execute([$id]);
            $prev = $row->fetch();
            $pdo->prepare("DELETE FROM chart_of_accounts WHERE account_id = ?")->execute([$id]);
            if ($prev) {
                audit_log($pdo, [
                    'action' => 'delete', 'module' => 'General Ledger', 'entity_type' => 'account',
                    'entity_id' => $id, 'entity_no' => $prev['account_code'],
                    'description' => "Deleted account {$prev['account_code']} — {$prev['account_name']}",
                    'old_values' => $prev,
                ]);
            }
            flash('success', 'Account deleted.');
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: chart-of-accounts.php');
exit;
