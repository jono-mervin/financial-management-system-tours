# TourFlow Finance — Financial Management System for Travel & Tours Companies

A modular financial system covering **General Ledger, Accounts Payable, Accounts
Receivable (including Collections), Disbursement Management,** and **Budget
Management**, built with PHP, MySQL, Tailwind CSS and vanilla JS.

**All core modules are fully functional.** Every real-world transaction —
issuing an invoice, paying a vendor, receiving from a customer, approving a
budget — posts a balanced, traceable entry straight to the General Ledger,
which stays the single source of truth for every report in the system.

---

## 1. Setup

**Requirements:** PHP 8+, MySQL/MariaDB, a webserver (XAMPP/WAMP/MAMP or PHP's
built-in server). No Composer/npm build step — Tailwind is loaded via CDN.

1. **Create the database.** Open phpMyAdmin → *Import* → select
   `database/schema.sql` → Go. This creates the `tourflow_finance` database, all
   tables, a seeded Chart of Accounts tailored to a tour company, and a
   default admin user.
2. **Set your DB credentials** in `config/db.php` (defaults to
   `root` / no password, matching a stock XAMPP install).
3. **Serve the app.** Point your webserver's document root at this folder, or
   run:
   ```bash
   php -S localhost:8000
   ```
   then visit `http://localhost/tourflow-finance/landing.php` (or your local URL).
4. **Sign in.** Default admin is `admin` / `admin123`. Guests see the landing
   page; the workspace (dashboard and modules) requires authentication.
5. **Existing databases.** If you already imported an older schema, also run
   `database/migrations/001_audit_logs.sql` to add the audit trail table.
6. **Seed some data.** Add a customer and a vendor first
   (Accounts Receivable → Customers, Accounts Payable → Vendors), then try
   issuing an AR invoice and an AP invoice — you'll see both post
   automatically into General Ledger → Journal Entries, and show up on the
   dashboard and Trial Balance immediately.

Every create, update, delete, post, void, approve, login, and logout is written
to **Audit Logs** (sidebar).

---

## 2. Project structure

```
config/db.php              PDO connection (edit credentials here)
includes/                  Shared layout: header, sidebar, topbar, footer, helpers.php
assets/css/custom.css      Fonts + the "boarding pass" stat-card styling
assets/js/app.js           Dynamic line items (JE + budget), live totals, confirm-dialogs
database/schema.sql        Full schema for all 6 modules + seed Chart of Accounts
docs/MODULE_BUILD_GUIDE.md Reference doc: the GL debit/credit mapping used by every module
index.php                  Dashboard (live KPIs from posted GL data)

modules/general-ledger/       Chart of Accounts · Journal Entries · Ledger View · Trial Balance
modules/accounts-receivable/  Customers · AR Invoices · Collections · Aging Report
modules/accounts-payable/     Vendors · AP Invoices (auto-posts to GL) · Aging Report
modules/disbursement/         Pay down AP invoices · auto-posts to GL, updates invoice status
modules/budget/                Budgets with line items · Budget vs. Actual report against live GL
modules/audit/                 System-wide audit trail of transactions and actions
landing.php / login.php        Public landing page and authenticated sign-in
```

Every module follows the same shape: a list page, a `-form.php` (or modal)
for creating records, a `-save.php` POST handler doing the validation + GL
posting inside a DB transaction, and a `-view.php` read-only/print page.

---

## 3. Design system

- **Palette:** deep teal primary (`#0E3B43`), sandy-gold accent (`#E0A458`),
  off-white canvas (`#F7F9F8`) — a clean, corporate palette with warm travel
  accents rather than a beach/tourist-brochure look.
- **Type:** Plus Jakarta Sans (headings), Inter (body), IBM Plex Mono (all
  monetary figures and document numbers, for tabular alignment).
- **Signature element:** KPI stat cards use a "boarding pass" motif — a
  perforated divider between the figure and a small gate-code badge — a
  subtle nod to the travel industry without tipping into clip-art.
- Icons are hand-drawn inline SVG (no icon-font dependency); the sidebar uses
  a paper-plane mark for Disbursement. Collections sit under Accounts Receivable.

---

## 4. How each module connects to the General Ledger

Every AR/AP invoice, disbursement, and collection row has a `linked_entry_id`
pointing back to `journal_entries`, and every one of those postings goes
through two shared helpers in `includes/helpers.php`:

- **`post_gl_entry($pdo, $header, $lines)`** — inserts a balanced,
  immediately-`Posted` journal entry (throws if the lines don't balance) and
  returns its `entry_id`. Used by AR/AP invoice creation, Disbursements, and
  Collections.
- **`void_gl_entry($pdo, $entry_id)`** — reverses a posted entry when an
  invoice with no payments yet is cancelled.

The mapping used by each transaction type:

| Transaction | Debit | Credit |
|---|---|---|
| AR Invoice issued | Accounts Receivable (1100/1150) | Selected Revenue account |
| AP Invoice recorded | Selected Expense account | Accounts Payable (2000) |
| Disbursement (pays AP) | Accounts Payable (2000) | Cash in Bank - Operating (1010) |
| Collection (receives AR) | Cash in Bank - Operating (1010) | Accounts Receivable (1100/1150) |

`docs/MODULE_BUILD_GUIDE.md` has the full reasoning and edge cases (partial
payments, cancellations, aging buckets) if you want to extend any module
further — e.g. supporting multiple bank accounts, partial invoice line
items, or multi-currency.

---

## 5. Suggested next steps

- **Authentication** — the `users` table and a bcrypt-hashed seed admin
  exist, but no session/login page is wired up. Add one before deploying
  anywhere shared.
- **CSRF tokens** on the POST forms.
- **Role-based access** — the `users.role` enum (admin, accountant, ap_clerk,
  ar_clerk, budget_officer, viewer) is defined but not yet enforced anywhere.
- **Multi-bank-account support** — Disbursement/Collection currently default
  to Cash in Bank - Operating (1010); the `bank_account` text field is
  captured but not tied to a specific GL account per bank.
