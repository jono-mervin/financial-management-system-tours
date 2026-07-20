<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create' || $action === 'update') {
    $name    = trim($_POST['customer_name'] ?? '');
    $type    = $_POST['customer_type'] ?? 'Individual';
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $active  = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash('error', 'Customer name is required.');
        header('Location: customers.php');
        exit;
    }

    try {
        if ($action === 'create') {
            $code = 'CUST-' . str_pad((string)((int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE customer_code = ?");
            do {
                $check->execute([$code]);
                if ($check->fetchColumn() > 0) {
                    $code = 'CUST-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                } else break;
            } while (true);

            $stmt = $pdo->prepare("INSERT INTO customers (customer_code, customer_name, customer_type, email, phone, is_active) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$code, $name, $type, $email, $phone, $active]);
            $id = (int)$pdo->lastInsertId();
            audit_log($pdo, [
                'action' => 'create', 'module' => 'Accounts Receivable', 'entity_type' => 'customer',
                'entity_id' => $id, 'entity_no' => $code,
                'description' => "Created customer $code — $name",
                'new_values' => ['customer_code' => $code, 'customer_name' => $name, 'customer_type' => $type],
            ]);
            flash('success', "Customer \"$name\" added.");
        } else {
            $id = (int)$_POST['customer_id'];
            $old = $pdo->prepare("SELECT * FROM customers WHERE customer_id=?");
            $old->execute([$id]);
            $prev = $old->fetch() ?: [];
            $stmt = $pdo->prepare("UPDATE customers SET customer_name=?, customer_type=?, email=?, phone=?, is_active=? WHERE customer_id=?");
            $stmt->execute([$name, $type, $email, $phone, $active, $id]);
            audit_log($pdo, [
                'action' => 'update', 'module' => 'Accounts Receivable', 'entity_type' => 'customer',
                'entity_id' => $id, 'entity_no' => $prev['customer_code'] ?? null,
                'description' => 'Updated customer ' . ($prev['customer_code'] ?? $id) . " — $name",
                'old_values' => $prev,
                'new_values' => compact('name', 'type', 'email', 'phone', 'active'),
            ]);
            flash('success', "Customer \"$name\" updated.");
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['customer_id'] ?? 0);
    try {
        $used = $pdo->prepare("SELECT COUNT(*) FROM ar_invoices WHERE customer_id = ?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            flash('error', 'This customer has invoices on record and cannot be deleted. Deactivate instead.');
        } else {
            $row = $pdo->prepare("SELECT * FROM customers WHERE customer_id=?");
            $row->execute([$id]);
            $prev = $row->fetch();
            $pdo->prepare("DELETE FROM customers WHERE customer_id = ?")->execute([$id]);
            if ($prev) {
                audit_log($pdo, [
                    'action' => 'delete', 'module' => 'Accounts Receivable', 'entity_type' => 'customer',
                    'entity_id' => $id, 'entity_no' => $prev['customer_code'],
                    'description' => "Deleted customer {$prev['customer_code']} — {$prev['customer_name']}",
                    'old_values' => $prev,
                ]);
            }
            flash('success', 'Customer deleted.');
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: customers.php');
exit;
