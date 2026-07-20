<?php
/**
 * Shared helper functions
 */

function money(float $amount): string {
    return '₱' . number_format($amount, 2);
}

function badge_class(string $status): string {
    $map = [
        'Posted'          => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'Paid'            => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'Approved'        => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'Open'            => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'Draft'           => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'Partially Paid'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'Unpaid'          => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'Overdue'         => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'Void'            => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'Cancelled'       => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'Closed'          => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    ];
    return $map[$status] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20';
}

/** Generate the next sequential document number, e.g. JE-2026-0007 */
function next_doc_no(PDO $pdo, string $table, string $column, string $prefix): string {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT $column FROM $table WHERE $column LIKE ? ORDER BY $column DESC LIMIT 1");
    $stmt->execute(["{$prefix}-{$year}-%"]);
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last) {
        $parts = explode('-', $last);
        $next = (int)end($parts) + 1;
    }
    return sprintf('%s-%s-%04d', $prefix, $year, $next);
}

function current_user_name(): string {
    return $_SESSION['full_name'] ?? 'Guest User';
}

function current_user_role(): string {
    return $_SESSION['role'] ?? 'viewer';
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function role_label(string $role): string {
    $map = [
        'admin'          => 'Administrator',
        'accountant'     => 'Accountant',
        'ap_clerk'       => 'AP Clerk',
        'ar_clerk'       => 'AR Clerk',
        'budget_officer' => 'Budget Officer',
        'viewer'         => 'Viewer',
    ];
    return $map[$role] ?? ucfirst(str_replace('_', ' ', $role));
}

/** Redirect guests to login (or landing from root dashboard). */
function require_auth(): void {
    if (!is_logged_in()) {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base = (strpos($script, '/modules/') !== false) ? '../..' : '.';
        $is_root_index = basename($script) === 'index.php' && strpos($script, '/modules/') === false;
        if ($is_root_index) {
            header('Location: ' . $base . '/landing.php');
        } else {
            flash('error', 'Please sign in to continue.');
            header('Location: ' . $base . '/login.php');
        }
        exit;
    }
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/** Client IP for audit trails */
function client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Write an audit log entry. Safe to call inside or outside a transaction.
 * Never throws to the caller — logging failure must not break business writes.
 *
 * $payload keys:
 *   action, module, entity_type, description (required)
 *   entity_id, entity_no, old_values, new_values (optional)
 */
function audit_log(PDO $pdo, array $payload): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO audit_logs
             (user_id, username, action, module, entity_type, entity_id, entity_no, description, old_values, new_values, ip_address)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $old = isset($payload['old_values']) ? json_encode($payload['old_values'], JSON_UNESCAPED_UNICODE) : null;
        $new = isset($payload['new_values']) ? json_encode($payload['new_values'], JSON_UNESCAPED_UNICODE) : null;
        $stmt->execute([
            current_user_id(),
            $_SESSION['username'] ?? null,
            $payload['action'],
            $payload['module'],
            $payload['entity_type'],
            $payload['entity_id'] ?? null,
            $payload['entity_no'] ?? null,
            $payload['description'],
            $old,
            $new,
            client_ip(),
        ]);
    } catch (Throwable $ex) {
        // Table may not exist yet on first deploy — swallow quietly
        error_log('audit_log failed: ' . $ex->getMessage());
    }
}

/** Look up an account_id by its chart-of-accounts code (e.g. '1010'), cached per request. */
function account_id_by_code(PDO $pdo, string $code): ?int {
    static $cache = [];
    if (array_key_exists($code, $cache)) return $cache[$code];
    $stmt = $pdo->prepare("SELECT account_id FROM chart_of_accounts WHERE account_code = ? LIMIT 1");
    $stmt->execute([$code]);
    $id = $stmt->fetchColumn();
    return $cache[$code] = $id ? (int)$id : null;
}

/**
 * Create a balanced, immediately-Posted journal entry. Used by every module
 * (AR/AP invoices, Disbursements, Collections) so each real-world transaction
 * lands in the General Ledger the moment it happens.
 *
 * $header: ['entry_date'=>, 'description'=>, 'reference'=>, 'source_module'=>]
 * $lines:  list of ['account_id'=>int, 'debit'=>float, 'credit'=>float, 'memo'=>string]
 * Returns the new entry_id. Throws on an unbalanced set of lines — callers
 * should be inside a $pdo transaction already so this rolls back cleanly.
 */
function post_gl_entry(PDO $pdo, array $header, array $lines): int {
    $total_debit = array_sum(array_column($lines, 'debit'));
    $total_credit = array_sum(array_column($lines, 'credit'));
    if (abs($total_debit - $total_credit) > 0.005) {
        throw new RuntimeException('Unbalanced GL entry: debit ' . $total_debit . ' vs credit ' . $total_credit);
    }

    $entry_no = next_doc_no($pdo, 'journal_entries', 'entry_no', 'JE');
    $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_no, entry_date, reference, description, source_module, status, posted_at, created_by, posted_by) VALUES (?,?,?,?,?,'Posted',NOW(),?,?)");
    $uid = current_user_id();
    $stmt->execute([$entry_no, $header['entry_date'], $header['reference'] ?? null, $header['description'], $header['source_module'], $uid, $uid]);
    $entry_id = (int)$pdo->lastInsertId();

    $lstmt = $pdo->prepare("INSERT INTO journal_entry_lines (entry_id, account_id, debit, credit, memo, line_order) VALUES (?,?,?,?,?,?)");
    foreach (array_values($lines) as $i => $l) {
        $lstmt->execute([$entry_id, $l['account_id'], $l['debit'] ?? 0, $l['credit'] ?? 0, $l['memo'] ?? null, $i]);
    }
    return $entry_id;
}

/** Void a posted journal entry (used when an invoice with no payments yet is cancelled). */
function void_gl_entry(PDO $pdo, ?int $entry_id): void {
    if (!$entry_id) return;
    $pdo->prepare("UPDATE journal_entries SET status='Void' WHERE entry_id=? AND status='Posted'")->execute([$entry_id]);
}

/** Effective display status for an AR/AP invoice — computed, not stored. */
function invoice_display_status(string $status, string $due_date): string {
    if (in_array($status, ['Unpaid', 'Partially Paid'], true) && strtotime($due_date) < strtotime(date('Y-m-d'))) {
        return 'Overdue';
    }
    return $status;
}

/** Default icon paths (SVG inner) keyed by active_module */
function module_icon(string $key): string {
    $icons = [
        'dashboard' => '<circle cx="12" cy="12" r="9"/><polygon points="16 8 13.5 13.5 8 16 10.5 10.5 16 8"/>',
        'gl'        => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'ap'        => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 2v8"/><path d="M2 10h20"/>',
        'ar'        => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M8 2v8"/><path d="M2 10h20"/>',
        'disb'      => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>',
        'coll'      => '<path d="M21 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v3"/><path d="M3 8h18l-1.5 11.5A2 2 0 0 1 17.53 21H6.47a2 2 0 0 1-1.97-1.5L3 8z"/><path d="M12 12v4"/><path d="m9.5 13.5 2.5 2.5 2.5-2.5"/>',
        'budget'    => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'audit'     => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
    ];
    return $icons[$key] ?? $icons['dashboard'];
}
