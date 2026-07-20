<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_auth();

$action = $_POST['action'] ?? '';

if ($action === 'create' || $action === 'update') {
    $name           = trim($_POST['vendor_name'] ?? '');
    $type           = $_POST['vendor_type'] ?? 'Other';
    $contact_person = trim($_POST['contact_person'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $terms          = trim($_POST['payment_terms'] ?? '');
    $active         = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        flash('error', 'Vendor name is required.');
        header('Location: vendors.php');
        exit;
    }

    try {
        if ($action === 'create') {
            $code = 'VEND-' . str_pad((string)((int)$pdo->query("SELECT COUNT(*) FROM vendors")->fetchColumn() + 1), 4, '0', STR_PAD_LEFT);
            $check = $pdo->prepare("SELECT COUNT(*) FROM vendors WHERE vendor_code = ?");
            do {
                $check->execute([$code]);
                if ($check->fetchColumn() > 0) {
                    $code = 'VEND-' . str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
                } else break;
            } while (true);

            $stmt = $pdo->prepare("INSERT INTO vendors (vendor_code, vendor_name, vendor_type, contact_person, email, phone, payment_terms, is_active) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([$code, $name, $type, $contact_person, $email, $phone, $terms, $active]);
            $id = (int)$pdo->lastInsertId();
            audit_log($pdo, [
                'action' => 'create', 'module' => 'Accounts Payable', 'entity_type' => 'vendor',
                'entity_id' => $id, 'entity_no' => $code,
                'description' => "Created vendor $code — $name",
                'new_values' => ['vendor_code' => $code, 'vendor_name' => $name, 'vendor_type' => $type],
            ]);
            flash('success', "Vendor \"$name\" added.");
        } else {
            $id = (int)$_POST['vendor_id'];
            $old = $pdo->prepare("SELECT * FROM vendors WHERE vendor_id=?");
            $old->execute([$id]);
            $prev = $old->fetch() ?: [];
            $stmt = $pdo->prepare("UPDATE vendors SET vendor_name=?, vendor_type=?, contact_person=?, email=?, phone=?, payment_terms=?, is_active=? WHERE vendor_id=?");
            $stmt->execute([$name, $type, $contact_person, $email, $phone, $terms, $active, $id]);
            audit_log($pdo, [
                'action' => 'update', 'module' => 'Accounts Payable', 'entity_type' => 'vendor',
                'entity_id' => $id, 'entity_no' => $prev['vendor_code'] ?? null,
                'description' => 'Updated vendor ' . ($prev['vendor_code'] ?? $id) . " — $name",
                'old_values' => $prev,
                'new_values' => compact('name', 'type', 'contact_person', 'email', 'phone', 'terms', 'active'),
            ]);
            flash('success', "Vendor \"$name\" updated.");
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

if ($action === 'delete') {
    $id = (int)($_POST['vendor_id'] ?? 0);
    try {
        $used = $pdo->prepare("SELECT COUNT(*) FROM ap_invoices WHERE vendor_id = ?");
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            flash('error', 'This vendor has invoices on record and cannot be deleted. Deactivate instead.');
        } else {
            $row = $pdo->prepare("SELECT * FROM vendors WHERE vendor_id=?");
            $row->execute([$id]);
            $prev = $row->fetch();
            $pdo->prepare("DELETE FROM vendors WHERE vendor_id = ?")->execute([$id]);
            if ($prev) {
                audit_log($pdo, [
                    'action' => 'delete', 'module' => 'Accounts Payable', 'entity_type' => 'vendor',
                    'entity_id' => $id, 'entity_no' => $prev['vendor_code'],
                    'description' => "Deleted vendor {$prev['vendor_code']} — {$prev['vendor_name']}",
                    'old_values' => $prev,
                ]);
            }
            flash('success', 'Vendor deleted.');
        }
    } catch (PDOException $e) {
        flash('error', 'Database error: ' . $e->getMessage());
    }
}

header('Location: vendors.php');
exit;
