<?php
$open_period = null;
try {
    $open_period = $pdo->query("SELECT period_name FROM fiscal_periods WHERE status='Open' ORDER BY start_date DESC LIMIT 1")->fetchColumn();
} catch (Exception $ex) { /* ignore */ }
?>
<header class="app-topbar no-print border-b border-black/5 bg-white/90 backdrop-blur-md flex items-center gap-3 px-4 lg:px-6">
    <!-- Toggle + brand title -->
    <div class="flex items-center gap-3 shrink-0">
        <button type="button" id="sidebar-toggle" class="w-9 h-9 rounded-lg flex items-center justify-center text-ink/50 hover:text-primary hover:bg-canvas transition-colors" title="Toggle sidebar" aria-label="Toggle sidebar">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/></svg>
        </button>
        <div class="hidden sm:block leading-tight border-l border-black/10 pl-3">
            <p class="font-display font-bold text-sm text-primary tracking-tight">TourFlow</p>
            <p class="text-[10px] font-medium text-ink/40 uppercase tracking-wider">Financial Management</p>
        </div>
    </div>

    <!-- Search -->
    <form action="<?= $base ?>/search.php" method="get" class="topbar-search hidden md:block">
        <svg class="topbar-search-icon" xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="search" name="q" placeholder="Search invoices, vendors, customers…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
    </form>

    <div class="flex-1"></div>

    <!-- Date / time + period -->
    <div class="hidden lg:flex items-center gap-3 text-sm text-ink/50 shrink-0">
        <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
            <span id="topbar-date"><?= date('D, M j, Y') ?></span>
        </div>
        <div class="flex items-center gap-2 font-mono text-xs text-ink/45 tabular-nums min-w-[4.5rem]">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span id="topbar-clock"><?= date('h:i:s A') ?></span>
        </div>
        <?php if ($open_period): ?>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 px-2.5 py-0.5 text-xs font-medium">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span><?= e($open_period) ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Profile -->
    <div class="relative shrink-0" id="profile-dropdown">
        <button type="button" id="profile-toggle" class="flex items-center gap-2.5 pl-3 border-l border-black/10 hover:opacity-90 transition-opacity" aria-expanded="false" aria-haspopup="true">
            <div class="w-9 h-9 rounded-full bg-primary text-white font-display font-semibold text-sm flex items-center justify-center ring-2 ring-accent/35 ring-offset-1 ring-offset-white">
                <?= e(strtoupper(substr(current_user_name(), 0, 1))) ?>
            </div>
            <div class="leading-tight hidden xl:block text-left">
                <p class="text-sm font-semibold text-ink"><?= e(current_user_name()) ?></p>
                <p class="text-xs text-ink/40"><?= e(role_label(current_user_role())) ?></p>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-ink/30 hidden sm:block"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div class="profile-menu" id="profile-menu" role="menu">
            <div class="px-3 py-2.5 border-b border-black/5 mb-1">
                <p class="text-sm font-semibold text-ink truncate"><?= e(current_user_name()) ?></p>
                <p class="text-xs text-ink/40"><?= e(role_label(current_user_role())) ?> · <?= e($_SESSION['username'] ?? '') ?></p>
            </div>
            <a href="<?= $base ?>/profile.php" role="menuitem">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                Profile settings
            </a>
            <a href="<?= $base ?>/modules/audit/index.php" role="menuitem">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                Audit logs
            </a>
            <div class="my-1 border-t border-black/5"></div>
            <a href="<?= $base ?>/logout.php" class="!text-rose-600 hover:!bg-rose-50" role="menuitem">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Sign out
            </a>
        </div>
    </div>
</header>
