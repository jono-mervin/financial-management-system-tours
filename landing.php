<?php
/**
 * Public landing page — TourFlow Finance
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>TourFlow Finance — Financial Management for Travel &amp; Tours</title>
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: { DEFAULT: '#0E3B43', light: '#145C68', dark: '#092A30' },
          accent:  { DEFAULT: '#E0A458', light: '#F0C68A', dark: '#C4823A' },
          canvas:  '#F7F9F8',
          ink:     '#1C2B2E',
        },
        fontFamily: {
          display: ['"Plus Jakarta Sans"', 'sans-serif'],
          sans: ['Inter', 'sans-serif'],
          mono: ['"IBM Plex Mono"', 'monospace'],
        },
      }
    }
  }
</script>
<link rel="stylesheet" href="assets/css/custom.css">
</head>
<body class="bg-canvas text-ink font-sans antialiased overflow-x-hidden">

<!-- Atmospheric background -->
<div class="fixed inset-0 -z-10 pointer-events-none">
    <div class="absolute inset-0 bg-gradient-to-br from-[#0E3B43] via-[#0E3B43] to-[#145C68]"></div>
    <div class="absolute inset-0 opacity-[0.12] route-dots"></div>
    <div class="absolute -top-32 -right-32 w-[520px] h-[520px] rounded-full bg-accent/20 blur-3xl animate-float"></div>
    <div class="absolute bottom-0 left-1/4 w-[400px] h-[400px] rounded-full bg-primary-light/30 blur-3xl animate-float-delayed"></div>
</div>

<header class="relative z-20 flex items-center justify-between px-6 lg:px-12 py-5 animate-fade-in">
    <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center shadow-lg shadow-accent/20">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E3B43" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
        </div>
        <span class="font-display font-bold text-xl text-white tracking-tight">TourFlow</span>
    </div>
    <a href="login.php" class="inline-flex items-center gap-2 rounded-xl bg-white/10 hover:bg-white/20 text-white text-sm font-semibold px-5 py-2.5 ring-1 ring-white/20 transition-all duration-300 hover:scale-[1.02]">
        Sign in
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
    </a>
</header>

<main class="relative z-10 min-h-[calc(100vh-88px)] flex flex-col justify-center px-6 lg:px-12 pb-16">
    <div class="max-w-3xl">
        <p class="font-mono text-accent text-xs tracking-[0.25em] uppercase mb-5 animate-slide-up" style="animation-delay:0.1s">Financial Management System</p>
        <h1 class="font-display text-5xl sm:text-6xl lg:text-7xl font-extrabold text-white leading-[1.05] tracking-tight mb-6 animate-slide-up" style="animation-delay:0.2s">
            TourFlow
        </h1>
        <p class="text-lg sm:text-xl text-white/65 max-w-xl leading-relaxed mb-10 animate-slide-up" style="animation-delay:0.35s">
            Ledger, payables, receivables, and cash — built for travel and tours companies that need clean books without the noise.
        </p>
        <div class="flex flex-wrap items-center gap-4 animate-slide-up" style="animation-delay:0.5s">
            <a href="login.php" class="inline-flex items-center gap-2.5 rounded-2xl bg-accent hover:bg-accent-light text-primary font-display font-bold text-base px-7 py-3.5 shadow-xl shadow-accent/25 transition-all duration-300 hover:scale-[1.03] hover:shadow-2xl hover:shadow-accent/30">
                Enter workspace
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
            <span class="text-sm text-white/40 font-medium">GL · AP · AR · Cash · Budget</span>
        </div>
    </div>

    <!-- Bottom strip: module names as route markers -->
    <div class="mt-20 lg:mt-28 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 max-w-5xl animate-slide-up" style="animation-delay:0.65s">
        <?php
        $modules = [
            ['General Ledger', 'Double-entry core'],
            ['Accounts Payable', 'Vendor bills'],
            ['Accounts Receivable', 'Invoices & receipts'],
            ['Disbursement', 'Vendor payments'],
            ['Budget', 'Plan vs actual'],
        ];
        foreach ($modules as $i => [$title, $sub]): ?>
        <div class="rounded-xl bg-white/[0.06] ring-1 ring-white/10 px-4 py-3.5 backdrop-blur-sm hover:bg-white/[0.1] transition-colors duration-300" style="animation-delay:<?= 0.7 + $i * 0.05 ?>s">
            <p class="font-display text-sm font-semibold text-white"><?= e($title) ?></p>
            <p class="text-[11px] text-white/40 mt-0.5"><?= e($sub) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<footer class="relative z-10 px-6 lg:px-12 py-5 flex items-center justify-between text-xs text-white/30">
    <span>TourFlow Finance v1.0</span>
    <span>Travel &amp; Tours · Philippines</span>
</footer>

</body>
</html>
