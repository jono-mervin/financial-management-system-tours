<?php
/**
 * Shared page header. Expects $page_title and optional $breadcrumb (array) to be set
 * before including this file.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/helpers.php';

require_auth();

$page_title = $page_title ?? 'Dashboard';
$page_subtitle = $page_subtitle ?? '';
$breadcrumb = $breadcrumb ?? [];
$active_module = $active_module ?? '';
$page_icon = $page_icon ?? module_icon($active_module ?: 'dashboard');

$base = (strpos($_SERVER['SCRIPT_NAME'], '/modules/') !== false) ? '../..' : '.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> · TourFlow Finance</title>

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary:   { DEFAULT: '#0E3B43', light: '#145C68', dark: '#092A30' },
          accent:    { DEFAULT: '#E0A458', light: '#F0C68A', dark: '#C4823A' },
          canvas:    '#F7F9F8',
          ink:       '#1C2B2E',
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
<link rel="stylesheet" href="<?= $base ?>/assets/css/custom.css">
</head>
<body class="bg-canvas text-ink font-sans antialiased">

<div class="app-shell" id="app-shell">
    <div class="sidebar-backdrop no-print" id="sidebar-backdrop" aria-hidden="true"></div>
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="app-main">
        <?php include __DIR__ . '/topbar.php'; ?>

        <div class="app-content thin-scroll">
            <main class="p-5 lg:p-7 max-w-[1600px] w-full mx-auto page-enter">
            <?php if (empty($hide_title)): ?>
            <div class="module-title">
                <div class="module-title-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?= $page_icon ?></svg>
                </div>
                <div class="module-title-body">
                    <?php if (!empty($breadcrumb)): ?>
                    <nav class="mb-1 text-[11px] font-medium text-ink/35 flex items-center gap-1.5 flex-wrap">
                        <?php foreach ($breadcrumb as $i => $crumb): ?>
                            <?php if ($i > 0): ?><span class="text-ink/20">/</span><?php endif; ?>
                            <span class="<?= $i === array_key_last($breadcrumb) ? 'text-primary' : '' ?>"><?= e($crumb) ?></span>
                        <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                    <h1><?= e($page_title) ?></h1>
                    <?php if ($page_subtitle !== ''): ?>
                    <p class="module-subtitle"><?= e($page_subtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($page_actions)): ?>
                <div class="module-title-meta"><?= $page_actions ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php foreach (get_flashes() as $f): ?>
                <div class="flash-banner mb-4 rounded-xl px-4 py-3 text-sm font-medium ring-1 <?= $f['type'] === 'error' ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' ?>">
                    <?= e($f['message']) ?>
                </div>
            <?php endforeach; ?>
