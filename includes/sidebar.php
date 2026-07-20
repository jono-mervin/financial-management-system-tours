<?php
$nav_items = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'href' => "$base/index.php",
        'icon' => '<circle cx="12" cy="12" r="9"/><polygon points="16 8 13.5 13.5 8 16 10.5 10.5 16 8"/>'],
    ['key' => 'gl', 'label' => 'General Ledger', 'href' => "$base/modules/general-ledger/index.php",
        'icon' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'],
    ['key' => 'ap', 'label' => 'Accounts Payable', 'href' => "$base/modules/accounts-payable/index.php",
        'icon' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M16 2v8"/><path d="M2 10h20"/>'],
    ['key' => 'ar', 'label' => 'Accounts Receivable', 'href' => "$base/modules/accounts-receivable/index.php",
        'icon' => '<rect x="2" y="6" width="20" height="14" rx="2"/><path d="M8 2v8"/><path d="M2 10h20"/>'],
    ['key' => 'disb', 'label' => 'Disbursement', 'href' => "$base/modules/disbursement/index.php",
        'icon' => '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/>'],
    ['key' => 'budget', 'label' => 'Budget Management', 'href' => "$base/modules/budget/index.php",
        'icon' => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>'],
    ['key' => 'audit', 'label' => 'Audit Logs', 'href' => "$base/modules/audit/index.php",
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
];
?>
<aside class="app-sidebar no-print bg-primary text-white shadow-xl shadow-primary/20" id="app-sidebar">
    <div class="h-16 flex items-center gap-2.5 px-4 border-b border-white/10 sidebar-brand shrink-0">
        <a href="<?= $base ?>/index.php" class="flex items-center gap-2.5 min-w-0 group">
            <div class="w-8 h-8 rounded-lg bg-accent flex items-center justify-center shrink-0 transition-transform duration-300 group-hover:scale-105">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0E3B43" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
            </div>
            <span class="sidebar-brand-text font-display font-bold text-[15px] tracking-tight truncate">TourFlow</span>
        </a>
    </div>

    <nav class="flex-1 py-4 px-2.5 space-y-0.5 overflow-y-auto thin-scroll min-h-0">
        <?php foreach ($nav_items as $item):
            $is_active = $active_module === $item['key']; ?>
        <a href="<?= $item['href'] ?>"
           title="<?= e($item['label']) ?>"
           class="nav-link group flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium
                  <?= $is_active ? 'is-active bg-white text-primary shadow-sm' : 'text-white/70 hover:bg-white/10 hover:text-white' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
                 stroke="<?= $is_active ? '#0E3B43' : 'currentColor' ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
                <?= $item['icon'] ?>
            </svg>
            <span class="sidebar-label truncate"><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="route-dots h-px mx-4 opacity-40 shrink-0"></div>
    <div class="p-4 text-[11px] text-white/40 leading-relaxed shrink-0 sidebar-footer-text">
        TourFlow Finance v1.0<br>Financial Management
    </div>
</aside>
