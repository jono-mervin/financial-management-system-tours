// ---------------------------------------------------------------------------
// TourFlow Finance — shared front-end behaviour
// ---------------------------------------------------------------------------

document.addEventListener('DOMContentLoaded', () => {
    initConfirmDelete();
    initJournalEntryForm();
    initBudgetForm();
    initModals();
    initSidebar();
    initTopbarClock();
    initProfileMenu();
    initBalanceSelects();
    initAutoOpenModals();
});

/** Any element with [data-confirm] shows a native confirm dialog before navigating/submitting */
function initConfirmDelete() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            if (!confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });
}

/** Smooth dialog open/close + ESC / backdrop click */
function initModals() {
    document.querySelectorAll('dialog.tf-modal').forEach(dialog => {
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) dialog.close();
        });
    });
}

/** Collapsible / mobile sidebar */
function initSidebar() {
    const shell = document.getElementById('app-shell');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!shell || !toggle) return;

    const mq = window.matchMedia('(max-width: 1023px)');

    function applyDesktopState() {
        const collapsed = localStorage.getItem('tf_sidebar_collapsed') === '1';
        shell.classList.toggle('sidebar-collapsed', collapsed);
        shell.classList.remove('sidebar-open');
    }

    function applyMobileState() {
        shell.classList.remove('sidebar-collapsed');
    }

    if (mq.matches) {
        applyMobileState();
    } else {
        applyDesktopState();
    }

    toggle.addEventListener('click', () => {
        if (mq.matches) {
            shell.classList.toggle('sidebar-open');
        } else {
            const next = !shell.classList.contains('sidebar-collapsed');
            shell.classList.toggle('sidebar-collapsed', next);
            localStorage.setItem('tf_sidebar_collapsed', next ? '1' : '0');
        }
    });

    backdrop?.addEventListener('click', () => shell.classList.remove('sidebar-open'));

    // Close mobile drawer after nav click
    document.querySelectorAll('#app-sidebar a').forEach(a => {
        a.addEventListener('click', () => {
            if (mq.matches) shell.classList.remove('sidebar-open');
        });
    });

    mq.addEventListener('change', (e) => {
        if (e.matches) applyMobileState();
        else applyDesktopState();
    });
}

/** Live clock in topbar */
function initTopbarClock() {
    const clockEl = document.getElementById('topbar-clock');
    const dateEl = document.getElementById('topbar-date');
    if (!clockEl && !dateEl) return;

    function tick() {
        const now = new Date();
        if (clockEl) {
            clockEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        if (dateEl) {
            dateEl.textContent = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
        }
    }
    tick();
    setInterval(tick, 1000);
}

/** User profile dropdown */
function initProfileMenu() {
    const root = document.getElementById('profile-dropdown');
    const btn = document.getElementById('profile-toggle');
    const menu = document.getElementById('profile-menu');
    if (!root || !btn || !menu) return;

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const open = menu.classList.toggle('is-open');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', (e) => {
        if (!root.contains(e.target)) {
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            menu.classList.remove('is-open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });
}

/**
 * Journal Entry form: dynamically add/remove debit-credit lines
 * and keep a live running total + balance indicator.
 */
function initJournalEntryForm() {
    const table = document.getElementById('je-lines-body');
    if (!table) return;

    const addBtn = document.getElementById('je-add-line');
    const template = document.getElementById('je-line-template');

    addBtn?.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        table.appendChild(clone);
        recalcTotals();
        attachRowEvents();
    });

    function attachRowEvents() {
        table.querySelectorAll('tr').forEach(row => {
            row.querySelectorAll('input[type="number"]').forEach(input => {
                input.removeEventListener('input', recalcTotals);
                input.addEventListener('input', recalcTotals);
            });
            const removeBtn = row.querySelector('.je-remove-line');
            if (removeBtn && !removeBtn.dataset.bound) {
                removeBtn.dataset.bound = '1';
                removeBtn.addEventListener('click', () => {
                    if (table.querySelectorAll('tr').length > 1) {
                        row.remove();
                        recalcTotals();
                    }
                });
            }
        });
    }

    function recalcTotals() {
        let totalDebit = 0, totalCredit = 0;
        table.querySelectorAll('tr').forEach(row => {
            const d = parseFloat(row.querySelector('.je-debit')?.value) || 0;
            const c = parseFloat(row.querySelector('.je-credit')?.value) || 0;
            totalDebit += d;
            totalCredit += c;
        });

        const debitEl = document.getElementById('je-total-debit');
        const creditEl = document.getElementById('je-total-credit');
        const statusEl = document.getElementById('je-balance-status');
        const submitBtn = document.getElementById('je-submit-btn');

        if (debitEl) debitEl.textContent = totalDebit.toFixed(2);
        if (creditEl) creditEl.textContent = totalCredit.toFixed(2);

        const diff = Math.abs(totalDebit - totalCredit);
        const balanced = diff < 0.005 && totalDebit > 0;

        if (statusEl) {
            if (balanced) {
                statusEl.textContent = 'Balanced';
                statusEl.className = 'inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 px-3 py-1 text-xs font-semibold';
            } else {
                statusEl.textContent = totalDebit === 0 && totalCredit === 0 ? 'Enter amounts' : `Out of balance by ${money(diff)}`;
                statusEl.className = 'inline-flex items-center gap-1.5 rounded-full bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 px-3 py-1 text-xs font-semibold';
            }
        }
        if (submitBtn) submitBtn.disabled = !balanced;
    }

    function money(n) {
        return '₱' + n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    attachRowEvents();
    recalcTotals();
}

/**
 * Budget form: dynamically add/remove line items (account + budgeted amount)
 * and keep a live running total. No debit/credit balancing needed here.
 */
function initBudgetForm() {
    const table = document.getElementById('budget-lines-body');
    if (!table) return;

    const addBtn = document.getElementById('budget-add-line');
    const template = document.getElementById('budget-line-template');

    addBtn?.addEventListener('click', () => {
        const clone = template.content.cloneNode(true);
        table.appendChild(clone);
        recalcTotal();
        attachRowEvents();
    });

    function attachRowEvents() {
        table.querySelectorAll('tr').forEach(row => {
            const amountInput = row.querySelector('.budget-amount');
            if (amountInput) {
                amountInput.removeEventListener('input', recalcTotal);
                amountInput.addEventListener('input', recalcTotal);
            }
            const removeBtn = row.querySelector('.budget-remove-line');
            if (removeBtn && !removeBtn.dataset.bound) {
                removeBtn.dataset.bound = '1';
                removeBtn.addEventListener('click', () => {
                    if (table.querySelectorAll('tr').length > 1) {
                        row.remove();
                        recalcTotal();
                    }
                });
            }
        });
    }

    function recalcTotal() {
        let total = 0;
        table.querySelectorAll('.budget-amount').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        const totalEl = document.getElementById('budget-total');
        if (totalEl) totalEl.textContent = '₱' + total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    attachRowEvents();
    recalcTotal();
}

/** Shared remaining-balance helper for collection / disbursement invoice selects */
function initBalanceSelects() {
    document.querySelectorAll('[data-balance-select]').forEach(select => {
        const amountInput = document.querySelector(select.getAttribute('data-balance-amount') || '#amount-input');
        const hint = document.querySelector(select.getAttribute('data-balance-hint') || '#balance-hint');
        const update = () => {
            const opt = select.options[select.selectedIndex];
            const balance = parseFloat(opt?.dataset?.balance || 0);
            if (opt && opt.value && amountInput) {
                if (hint) hint.textContent = 'Remaining balance: ₱' + balance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                amountInput.max = balance;
                if (!amountInput.value || parseFloat(amountInput.value) > balance) {
                    amountInput.value = balance.toFixed(2);
                }
            } else {
                if (hint) hint.textContent = '';
                amountInput?.removeAttribute('max');
            }
        };
        select.addEventListener('change', update);
        update();
    });
}

/** Open modals from query string: ?new=1 or ?edit=ID or ?ar_invoice_id= / ?ap_invoice_id= */
function initAutoOpenModals() {
    const params = new URLSearchParams(window.location.search);
    const openId = params.get('modal') || (params.has('new') ? 'create-modal' : null)
        || (params.has('edit') ? 'je-modal' : null)
        || (params.has('ar_invoice_id') || params.has('ap_invoice_id') ? 'create-modal' : null);
    if (!openId) return;
    const modal = document.getElementById(openId);
    if (modal && typeof modal.showModal === 'function') {
        modal.showModal();
    }
}
