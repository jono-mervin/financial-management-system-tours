-- ============================================================================
-- TOURFLOW FINANCE — Financial Management System for Travel & Tours Companies
-- Database Schema
-- Import this file via phpMyAdmin (or `mysql -u root -p < schema.sql`)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS tourflow_finance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tourflow_finance;

-- ----------------------------------------------------------------------------
-- SYSTEM: Users & Roles
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    user_id         INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(150),
    role            ENUM('admin','accountant','ap_clerk','ar_clerk','budget_officer','viewer') NOT NULL DEFAULT 'viewer',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed a default admin (password: admin123 — change immediately after import)
INSERT INTO users (username, password_hash, full_name, email, role)
VALUES ('admin', '$2y$10$qhC1ucWiNI/GX3S2ZkpVm.cPuETK7wea3YX8U1zKDEwC7CU7bYC8i', 'System Administrator', 'admin@tourflow.test', 'admin');

-- ----------------------------------------------------------------------------
-- FISCAL PERIODS (used across all modules)
-- ----------------------------------------------------------------------------
CREATE TABLE fiscal_periods (
    period_id       INT AUTO_INCREMENT PRIMARY KEY,
    period_name     VARCHAR(50) NOT NULL,      -- e.g. "FY2026 - July"
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          ENUM('Open','Closed') NOT NULL DEFAULT 'Open',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- GENERAL LEDGER MODULE
-- ----------------------------------------------------------------------------
CREATE TABLE chart_of_accounts (
    account_id          INT AUTO_INCREMENT PRIMARY KEY,
    account_code        VARCHAR(20) NOT NULL UNIQUE,   -- e.g. 1000, 1010, 4000
    account_name        VARCHAR(150) NOT NULL,
    account_type        ENUM('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
    account_subtype      VARCHAR(80),                  -- e.g. "Current Asset", "Tour Package Revenue"
    parent_account_id   INT NULL,
    normal_balance       ENUM('Debit','Credit') NOT NULL,
    is_active            TINYINT(1) NOT NULL DEFAULT 1,
    description          VARCHAR(255),
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
    entry_id        INT AUTO_INCREMENT PRIMARY KEY,
    entry_no        VARCHAR(30) NOT NULL UNIQUE,        -- e.g. JE-2026-0001
    entry_date      DATE NOT NULL,
    period_id       INT NULL,
    reference       VARCHAR(100),                       -- PO#, Invoice#, Booking ref, etc.
    description     VARCHAR(255) NOT NULL,
    source_module   ENUM('General Ledger','Accounts Payable','Accounts Receivable','Disbursement','Collection','Budget','Manual') NOT NULL DEFAULT 'Manual',
    status          ENUM('Draft','Posted','Void') NOT NULL DEFAULT 'Draft',
    created_by      INT NULL,
    posted_by       INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    posted_at       TIMESTAMP NULL,
    FOREIGN KEY (period_id) REFERENCES fiscal_periods(period_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (posted_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE journal_entry_lines (
    line_id         INT AUTO_INCREMENT PRIMARY KEY,
    entry_id        INT NOT NULL,
    account_id      INT NOT NULL,
    debit           DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    credit          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    memo            VARCHAR(255),
    line_order      INT NOT NULL DEFAULT 0,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ACCOUNTS PAYABLE MODULE (stub — ready for build-out)
-- ----------------------------------------------------------------------------
CREATE TABLE vendors (
    vendor_id       INT AUTO_INCREMENT PRIMARY KEY,
    vendor_code     VARCHAR(20) NOT NULL UNIQUE,
    vendor_name     VARCHAR(150) NOT NULL,        -- e.g. hotel partner, airline, DMC
    vendor_type     ENUM('Hotel','Airline','Transport','Tour Guide/DMC','Restaurant','Insurance','Other') DEFAULT 'Other',
    contact_person  VARCHAR(120),
    email           VARCHAR(150),
    phone           VARCHAR(50),
    payment_terms   VARCHAR(50),                  -- e.g. "Net 30"
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ap_invoices (
    ap_invoice_id   INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no      VARCHAR(50) NOT NULL,
    vendor_id       INT NOT NULL,
    invoice_date    DATE NOT NULL,
    due_date        DATE NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    amount_paid     DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status          ENUM('Unpaid','Partially Paid','Paid','Overdue','Cancelled') NOT NULL DEFAULT 'Unpaid',
    linked_entry_id INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id),
    FOREIGN KEY (linked_entry_id) REFERENCES journal_entries(entry_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- ACCOUNTS RECEIVABLE MODULE (stub — ready for build-out)
-- ----------------------------------------------------------------------------
CREATE TABLE customers (
    customer_id     INT AUTO_INCREMENT PRIMARY KEY,
    customer_code   VARCHAR(20) NOT NULL UNIQUE,
    customer_name   VARCHAR(150) NOT NULL,        -- individual traveller or corporate/travel-agent client
    customer_type   ENUM('Individual','Corporate','Travel Agent','Group') DEFAULT 'Individual',
    email           VARCHAR(150),
    phone           VARCHAR(50),
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE ar_invoices (
    ar_invoice_id   INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no      VARCHAR(50) NOT NULL,
    customer_id     INT NOT NULL,
    booking_ref     VARCHAR(50),                  -- tour package / booking reference
    invoice_date    DATE NOT NULL,
    due_date        DATE NOT NULL,
    amount          DECIMAL(15,2) NOT NULL,
    amount_received DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    status          ENUM('Unpaid','Partially Paid','Paid','Overdue','Cancelled') NOT NULL DEFAULT 'Unpaid',
    linked_entry_id INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (linked_entry_id) REFERENCES journal_entries(entry_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- DISBURSEMENT MANAGEMENT MODULE (stub — outgoing payments against AP)
-- ----------------------------------------------------------------------------
CREATE TABLE disbursements (
    disbursement_id INT AUTO_INCREMENT PRIMARY KEY,
    disbursement_no VARCHAR(30) NOT NULL UNIQUE,
    ap_invoice_id   INT NOT NULL,
    payment_date    DATE NOT NULL,
    payment_method  ENUM('Bank Transfer','Cheque','Cash','Credit Card','Online Wallet') NOT NULL,
    bank_account    VARCHAR(100),
    amount          DECIMAL(15,2) NOT NULL,
    reference_no    VARCHAR(100),
    linked_entry_id INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ap_invoice_id) REFERENCES ap_invoices(ap_invoice_id),
    FOREIGN KEY (linked_entry_id) REFERENCES journal_entries(entry_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- COLLECTION MANAGEMENT MODULE (stub — incoming payments against AR)
-- ----------------------------------------------------------------------------
CREATE TABLE collections (
    collection_id   INT AUTO_INCREMENT PRIMARY KEY,
    collection_no   VARCHAR(30) NOT NULL UNIQUE,
    ar_invoice_id   INT NOT NULL,
    collection_date DATE NOT NULL,
    payment_method  ENUM('Bank Transfer','Cheque','Cash','Credit Card','Online Wallet') NOT NULL,
    bank_account    VARCHAR(100),
    amount          DECIMAL(15,2) NOT NULL,
    reference_no    VARCHAR(100),
    linked_entry_id INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ar_invoice_id) REFERENCES ar_invoices(ar_invoice_id),
    FOREIGN KEY (linked_entry_id) REFERENCES journal_entries(entry_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- BUDGET MANAGEMENT MODULE (stub)
-- ----------------------------------------------------------------------------
CREATE TABLE budgets (
    budget_id       INT AUTO_INCREMENT PRIMARY KEY,
    budget_name     VARCHAR(150) NOT NULL,        -- e.g. "FY2026 Operations Budget"
    period_id       INT NOT NULL,
    department      VARCHAR(100),                 -- e.g. "Tour Operations", "Marketing"
    status          ENUM('Draft','Approved','Closed') NOT NULL DEFAULT 'Draft',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES fiscal_periods(period_id)
) ENGINE=InnoDB;

CREATE TABLE budget_lines (
    budget_line_id  INT AUTO_INCREMENT PRIMARY KEY,
    budget_id       INT NOT NULL,
    account_id      INT NOT NULL,
    budgeted_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    actual_amount   DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    notes           VARCHAR(255),
    FOREIGN KEY (budget_id) REFERENCES budgets(budget_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id)
) ENGINE=InnoDB;

-- ============================================================================
-- SEED DATA — Chart of Accounts tailored to a travel & tours company
-- ============================================================================
INSERT INTO chart_of_accounts (account_code, account_name, account_type, account_subtype, normal_balance, description) VALUES
('1000','Cash on Hand','Asset','Current Asset','Debit','Petty cash at branch offices'),
('1010','Cash in Bank - Operating','Asset','Current Asset','Debit','Main operating bank account'),
('1020','Cash in Bank - Client Trust','Asset','Current Asset','Debit','Client deposits held in trust for bookings'),
('1100','Accounts Receivable - Customers','Asset','Current Asset','Debit','Amounts due from travellers and clients'),
('1150','Accounts Receivable - Travel Agents','Asset','Current Asset','Debit','Amounts due from partner travel agents'),
('1200','Prepaid Tour Costs','Asset','Current Asset','Debit','Advance payments to hotels/airlines for upcoming tours'),
('1500','Office Equipment','Asset','Fixed Asset','Debit','Computers, furniture, fixtures'),
('1510','Accumulated Depreciation - Equipment','Asset','Fixed Asset','Credit','Contra-asset for equipment depreciation'),
('2000','Accounts Payable - Vendors','Liability','Current Liability','Credit','Amounts owed to hotels, airlines, DMCs'),
('2100','Client Deposits Payable','Liability','Current Liability','Credit','Unearned deposits collected for future tours'),
('2200','Accrued Expenses','Liability','Current Liability','Credit','Accrued utilities, salaries, etc.'),
('2300','Taxes Payable','Liability','Current Liability','Credit','VAT/withholding taxes payable'),
('3000',"Owner's Capital",'Equity','Equity','Credit','Owner/shareholder contributed capital'),
('3100','Retained Earnings','Equity','Equity','Credit','Accumulated retained earnings'),
('4000','Tour Package Revenue','Revenue','Operating Revenue','Credit','Revenue from domestic and international tour packages'),
('4100','Airline Ticketing Revenue','Revenue','Operating Revenue','Credit','Commission/markup from airline ticketing'),
('4200','Hotel Booking Revenue','Revenue','Operating Revenue','Credit','Commission/markup from hotel bookings'),
('4300','Travel Insurance Commission','Revenue','Operating Revenue','Credit','Commission earned from travel insurance sales'),
('4900','Other Income','Revenue','Non-Operating Revenue','Credit','Miscellaneous income'),
('5000','Cost of Tour Packages','Expense','Cost of Sales','Debit','Direct costs of hotels, transport, guides, meals for tours'),
('5100','Airline Ticket Costs','Expense','Cost of Sales','Debit','Cost of airline tickets sold'),
('6000','Salaries and Wages','Expense','Operating Expense','Debit','Employee compensation'),
('6100','Marketing and Advertising','Expense','Operating Expense','Debit','Promotions, campaigns, travel fairs'),
('6200','Office Rent','Expense','Operating Expense','Debit','Branch and head office rent'),
('6300','Utilities','Expense','Operating Expense','Debit','Electricity, water, internet, phone'),
('6400','Depreciation Expense','Expense','Operating Expense','Debit','Depreciation of fixed assets'),
('6500','Bank Charges','Expense','Operating Expense','Debit','Bank fees and transaction charges'),
('6900','Miscellaneous Expense','Expense','Operating Expense','Debit','Other minor operating expenses');

-- Sample fiscal period
INSERT INTO fiscal_periods (period_name, start_date, end_date, status) VALUES
('FY2026 - July', '2026-07-01', '2026-07-31', 'Open');

-- ----------------------------------------------------------------------------
-- SYSTEM: Audit Logs (every create / update / delete / post / void / login)
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NULL,
    username        VARCHAR(50) NULL,
    action          VARCHAR(40) NOT NULL,          -- create, update, delete, post, void, approve, close, login, logout, cancel
    module          VARCHAR(60) NOT NULL,          -- General Ledger, Accounts Payable, Auth, etc.
    entity_type     VARCHAR(60) NOT NULL,          -- account, vendor, journal_entry, ap_invoice, …
    entity_id       INT NULL,
    entity_no       VARCHAR(60) NULL,             -- human-readable doc no / code
    description     VARCHAR(255) NOT NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    ip_address      VARCHAR(45) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at DESC),
    INDEX idx_audit_module (module),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;
