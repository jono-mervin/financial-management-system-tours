# TourFlow Finance — Remaining Module Build Guide

This spec is written for a coding agent to implement the remaining modules.
It assumes the codebase already contains a fully working **General Ledger**
module and a partially working **Accounts Receivable** module (`customers.php`
+ `customers-save.php` are done; invoices are not). Read those existing files
first — every module below should **copy their patterns exactly**, not
reinvent them.

Reference files to study before building anything:
- `modules/general-ledger/chart-of-accounts.php` + `chart-of-accounts-save.php` — the CRUD-with-modal pattern (list page + `<dialog>` modal + vanilla JS `openXModal(data)` + separate `-save.php` POST handler using `flash()` + redirect).
- `modules/general-ledger/journal-entry-form.php` + `journal-entry-save.php` — the dynamic multi-row line pattern (`<template>`, add/remove rows, live balance check via `assets/js/app.js`) and the transaction-safe GL posting pattern (`$pdo->beginTransaction()` / `next_doc_no()` / balanced debit=credit insert).
- `modules/accounts-receivable/customers.php` — already-built reference for this exact module's visual style.
- `includes/helpers.php` — reuse `money()`, `badge_class()`, `next_doc_no()`, `e()`, `flash()`. Add new helpers here rather than duplicating logic per module.

**Every page** must: set `$page_title`, `$active_module`, `$breadcrumb`, then
`require __DIR__ . '/../../includes/header.php'` at the top and
`require __DIR__ . '/../../includes/footer.php'` at the bottom. Use PDO
prepared statements everywhere — never interpolate user input into SQL.

---

## 1. Finish Accounts Receivable

Tables already exist: `customers` (done), `ar_invoices`.

### `modules/accounts-receivable/invoices.php`
List page, same shell as `journal-entries.php`: status filter pills (Unpaid,
Partially Paid, Paid, Overdue, Cancelled), columns Invoice No. / Customer /
Booking Ref / Invoice Date / Due Date / Amount / Balance / Status. "Overdue"
is a **display-only** computed status — don't change the stored `status`
value; if `status IN ('Unpaid','Partially Paid') AND due_date < CURDATE()`,
render the `Overdue` badge instead of the stored one (reuse `badge_class()`,
which already has an `Overdue` entry).

### `modules/accounts-receivable/invoice-form.php` (create only — invoices are not edited once issued, only cancelled)
Fields: customer (select, active customers only), booking reference (text),
invoice date (date, default today), due date (date), amount (number), and a
**revenue account** select filtered to `account_type = 'Revenue'` from
`chart_of_accounts` (this determines what gets credited).

### `modules/accounts-receivable/invoice-save.php`
On `action=create`:
1. Determine the AR control account: `account_code = '1150'` (AR - Travel
   Agents) if the customer's `customer_type = 'Travel Agent'`, else
   `'1100'` (AR - Customers). Look up its `account_id`.
2. `invoice_no = next_doc_no($pdo, 'ar_invoices', 'invoice_no', 'ARINV')`.
3. Insert into `ar_invoices` (`amount_received` defaults to 0, `status =
   'Unpaid'`).
4. In the **same DB transaction**, create a balanced, immediately-**Posted**
   journal entry (mirror the create-block in
   `general-ledger/journal-entry-save.php`, but skip the Draft step — an
   issued invoice is a real accounting event, not a draft):
   - Debit: the AR control account, full `amount`.
   - Credit: the selected revenue account, full `amount`.
   - `source_module = 'Accounts Receivable'`, `reference = invoice_no`,
     `status = 'Posted'`, `posted_at = NOW()`.
5. Save the new `entry_id` into `ar_invoices.linked_entry_id`.
6. Commit; redirect to `invoice-view.php?id=...`.

`action=cancel`: only allowed if `amount_received = 0`. Set
`ar_invoices.status = 'Cancelled'` and void the linked journal entry
(`journal_entries.status = 'Void'` where `entry_id = linked_entry_id`).

### `modules/accounts-receivable/invoice-view.php`
Read-only detail + print button, mirror
`general-ledger/journal-entry-view.php`. Show customer, booking ref, dates,
amount, amount received, balance due, linked journal entry number
(link to `../general-ledger/journal-entry-view.php?id=...`), and a Cancel
button when eligible.

### `modules/accounts-receivable/aging-report.php`
Bucket every `Unpaid`/`Partially Paid` invoice by `CURDATE() - due_date`:
Current (not yet due), 1–30, 31–60, 61–90, 90+ days. Group rows by customer
with a subtotal per bucket and a grand total row. This is a report page —
GET filters only, no writes.

### Replace `modules/accounts-receivable/index.php`
Swap the placeholder for a real landing page identical in structure to
`modules/general-ledger/index.php`: cards linking to Customers, Invoices,
Aging Report, each with a live count/stat pulled from the DB.

---

## 2. Accounts Payable

Tables already exist: `vendors`, `ap_invoices`. This module is the mirror
image of Accounts Receivable — money owed *to* vendors instead of *by*
customers.

### `modules/accounts-payable/vendors.php` + `vendors-save.php`
Copy `accounts-receivable/customers.php` + `customers-save.php` almost
line-for-line. Fields differ: `vendor_code` (auto-generate like
`VEND-0001`, same pattern as `CUST-0001`), `vendor_name`, `vendor_type`
(enum: Hotel, Airline, Transport, Tour Guide/DMC, Restaurant, Insurance,
Other), `contact_person`, `email`, `phone`, `payment_terms` (free text,
e.g. "Net 30"), `is_active`.

### `modules/accounts-payable/invoices.php`
List page mirroring the AR invoices list. Status pills: Unpaid, Partially
Paid, Paid, Overdue, Cancelled. Columns: Invoice No. / Vendor / Invoice
Date / Due Date / Amount / Balance / Status. Same computed-Overdue display
rule as AR.

### `modules/accounts-payable/invoice-form.php`
Fields: vendor (select), invoice date, due date, amount, and a **cost/expense
account** select filtered to `account_type = 'Expense'` (this is what gets
debited — e.g. "Cost of Tour Packages" for a hotel bill).

### `modules/accounts-payable/invoice-save.php`
Same shape as the AR version, reversed:
1. `invoice_no = next_doc_no($pdo, 'ap_invoices', 'invoice_no', 'APINV')`.
2. Insert `ap_invoices` row, `status = 'Unpaid'`, `amount_paid = 0`.
3. Posted journal entry in the same transaction:
   - Debit: the selected expense account, full `amount`.
   - Credit: `Accounts Payable - Vendors` (`account_code = '2000'`), full
     `amount`.
   - `source_module = 'Accounts Payable'`, `reference = invoice_no`.
4. Save `linked_entry_id`.

`action=cancel` mirrors AR: only if `amount_paid = 0`.

### `modules/accounts-payable/invoice-view.php` + `aging-report.php`
Same shape as their AR counterparts (AP aging instead of AR aging).

### Replace `modules/accounts-payable/index.php`
Same landing-page pattern as GL/AR: cards for Vendors, Invoices, Aging
Report.

---

## 3. Disbursement Management

Table already exists: `disbursements` (FK to `ap_invoices`).

Purpose: record an actual outgoing payment *against* an existing AP
invoice, reducing what's owed and moving cash out of the bank.

### `modules/disbursement/disbursements.php`
List page: Disbursement No. / Date / Vendor (via `ap_invoices` join) /
Method / Amount / Reference. Filter by date range and payment method.

### `modules/disbursement/disbursement-form.php`
Fields: **AP invoice** select — only invoices with
`status IN ('Unpaid','Partially Paid')`, label each option with vendor
name + invoice no. + remaining balance (`amount - amount_paid`). Then
payment date, payment method (enum from schema: Bank Transfer, Cheque,
Cash, Credit Card, Online Wallet), bank account (free text, e.g. "BDO
Operating - 1234"), amount (default to the invoice's remaining balance,
but editable for partial payment — validate `amount <= remaining balance`
server-side), reference no.

### `modules/disbursement/disbursement-save.php`
In one DB transaction:
1. Re-fetch the AP invoice, confirm `amount <= (amount - amount_paid)`.
   Reject with `flash('error', ...)` if not.
2. `disbursement_no = next_doc_no($pdo, 'disbursements', 'disbursement_no', 'DISB')`.
3. Insert `disbursements` row.
4. `ap_invoices.amount_paid += amount`; set
   `status = 'Paid'` if `amount_paid >= amount` (the invoice's own amount
   column) else `'Partially Paid'`.
5. Posted journal entry:
   - Debit: `Accounts Payable - Vendors` (`2000`), the disbursed amount
     (paying down the liability).
   - Credit: `Cash in Bank - Operating` (`1010`) — or let the user pick
     which cash/bank account (`1000`/`1010`) if you want to support paying
     from petty cash too; default to `1010`.
   - `source_module = 'Disbursement'`, `reference = disbursement_no`.
6. Save `linked_entry_id` on the `disbursements` row.

### `modules/disbursement/disbursement-view.php`
Read-only + print, same as other `-view.php` pages.

### Replace `modules/disbursement/index.php`
Landing page: KPI cards for "Disbursed this month", "AP outstanding total"
(reuse the pattern from `index.php`'s `acct_balance()`/`type_balance()`
helpers), quick link into the AP aging report to decide what to pay next.

---

## 4. Collections (under Accounts Receivable)

Collections live in `modules/accounts-receivable/` alongside customers,
invoices, and aging. The `collections` table (FK to `ar_invoices`) is
unchanged. Legacy URLs under `modules/collection/` redirect here.

### `modules/accounts-receivable/collections.php`
List: Collection No. / Date / Customer (via `ar_invoices` join) / Method /
Amount / Reference.

### `modules/accounts-receivable/collection-form.php`
Fields: **AR invoice** select — only `Unpaid`/`Partially Paid` invoices,
label with customer name + invoice no. + remaining balance. Then
collection date, payment method, bank account, amount (default/cap at
remaining balance), reference no.

### `modules/accounts-receivable/collection-save.php`
Mirror `disbursement-save.php` exactly, reversed:
1. Validate `amount <= (amount - amount_received)` on the AR invoice.
2. `collection_no = next_doc_no($pdo, 'collections', 'collection_no', 'COLL')`.
3. Insert `collections` row.
4. `ar_invoices.amount_received += amount`; status `Paid` or `Partially
   Paid` accordingly.
5. Posted journal entry:
   - Debit: `Cash in Bank - Operating` (`1010`).
   - Credit: the **same AR control account** the original invoice used
     (`1100` or `1150` — re-derive from the customer's type).
   - `source_module = 'Collection'`, `reference = collection_no`.
6. Save `linked_entry_id`.

### `modules/accounts-receivable/collection-view.php`
Same pattern as Disbursement's view page. Linked from invoice detail
collection history.

---

## 5. Budget Management

Tables already exist: `budgets`, `budget_lines`.

### `modules/budget/budgets.php`
List: Budget Name / Period / Department / Status (Draft/Approved/Closed) /
Total Budgeted (sum of its lines). Filter by period and status.

### `modules/budget/budget-form.php`
Header fields: budget name, fiscal period (select from `fiscal_periods`),
department (free text). Then a **dynamic line table** — copy the
`<template>` + add/remove-row + `assets/js/app.js` pattern from
`journal-entry-form.php`, but each row is: account (select, `Revenue` or
`Expense` type accounts only), budgeted amount, notes. No debit/credit
balancing needed here — just a running total display of budgeted amount is
enough (a simpler version of the JE balance widget; you can extend
`app.js` with a `initBudgetForm()` function following the same structure
as `initJournalEntryForm()`, or inline a small script on this page).

### `modules/budget/budget-save.php`
`action=create`: insert `budgets` (`status = 'Draft'`) + its `budget_lines`
in one transaction — no GL posting here; budgets don't touch the ledger.
`action=approve`: `UPDATE budgets SET status='Approved' WHERE budget_id=?
AND status='Draft'`. `action=close`: same for `Closed`.

### `modules/budget/budget-vs-actual.php`
The most valuable report in this module. For a selected `Approved` budget:
for each `budget_lines` row, compute the **actual** posted GL activity for
that `account_id` within the budget's `fiscal_periods` date range — reuse
the query shape from `index.php`'s `type_balance()`/`acct_balance()`
helpers, but scoped to one `account_id` and a date range instead of a
whole account type:

```sql
SELECT COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c
FROM journal_entry_lines l
JOIN journal_entries je ON je.entry_id = l.entry_id
WHERE l.account_id = ? AND je.status = 'Posted'
  AND je.entry_date BETWEEN ? AND ?
```
Then `actual = (account is Expense) ? d - c : c - d`. Show Budgeted /
Actual / Variance / Variance % per line, color-coded (over-budget expense
rows in rose, under in emerald), plus a totals row. Optionally write the
`actual` back into `budget_lines.actual_amount` as a cache when this report
is viewed.

### Replace `modules/budget/index.php`
Landing page with cards for Budgets list, New Budget, Budget vs. Actual.

---

## GL account codes these modules will reference

| Code | Account | Used by |
|------|---------|---------|
| 1000 | Cash on Hand | Disbursement (optional petty-cash payout) |
| 1010 | Cash in Bank - Operating | Disbursement (credit), Collection (debit) |
| 1100 | Accounts Receivable - Customers | AR invoices (individual/corporate/group customers) |
| 1150 | Accounts Receivable - Travel Agents | AR invoices (Travel Agent customers) |
| 2000 | Accounts Payable - Vendors | AP invoices (credit), Disbursement (debit) |
| 4000–4300 | Revenue accounts | Selected per AR invoice |
| 5000–5100 | Cost of Sales | Selected per AP invoice |

## Cross-module testing checklist for the agent

- [ ] Creating an AR invoice immediately shows up as a **Posted** entry in `general-ledger/journal-entries.php` and moves the Trial Balance.
- [ ] Creating an AP invoice does the same, reversed.
- [ ] A Disbursement reduces the AP invoice balance and the Cash in Bank balance on the dashboard.
- [ ] A Collection reduces the AR invoice balance and increases Cash in Bank.
- [ ] Trying to disburse/collect more than an invoice's remaining balance is rejected server-side, not just in the UI.
- [ ] Cancelling an invoice with zero payments voids its linked journal entry; cancelling one with payments is blocked.
- [ ] Budget vs. Actual totals reconcile with the Trial Balance for the same accounts/date range.
