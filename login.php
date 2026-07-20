<?php
/**
 * Login page
 */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = (int)$user['user_id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role']      = $user['role'];

            audit_log($pdo, [
                'action'      => 'login',
                'module'      => 'Auth',
                'entity_type' => 'user',
                'entity_id'   => (int)$user['user_id'],
                'entity_no'   => $user['username'],
                'description' => $user['full_name'] . ' signed in',
            ]);

            flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid username or password.';
        audit_log($pdo, [
            'action'      => 'login_failed',
            'module'      => 'Auth',
            'entity_type' => 'user',
            'entity_no'   => $username,
            'description' => 'Failed login attempt for "' . $username . '"',
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in · TourFlow Finance</title>
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
<body class="min-h-screen font-sans antialiased text-ink overflow-x-hidden">

<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Brand panel -->
    <div class="relative hidden lg:flex flex-col justify-between p-12 bg-primary text-white overflow-hidden">
        <div class="absolute inset-0 opacity-[0.1] route-dots"></div>
        <div class="absolute -bottom-24 -left-24 w-96 h-96 rounded-full bg-accent/20 blur-3xl animate-float"></div>
        <div class="absolute top-20 right-10 w-64 h-64 rounded-full bg-primary-light/40 blur-3xl animate-float-delayed"></div>

        <a href="landing.php" class="relative flex items-center gap-2.5 group">
            <div class="w-10 h-10 rounded-xl bg-accent flex items-center justify-center transition-transform duration-300 group-hover:scale-105">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0E3B43" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
            </div>
            <span class="font-display font-bold text-xl tracking-tight">TourFlow</span>
        </a>

        <div class="relative max-w-md animate-slide-up">
            <p class="font-mono text-accent text-xs tracking-[0.2em] uppercase mb-4">Workspace access</p>
            <h1 class="font-display text-4xl font-extrabold leading-tight mb-4">Your books,<br>boarding-pass clear.</h1>
            <p class="text-white/55 leading-relaxed">Sign in to post journals, settle vendors, collect from clients, and keep every peso on the ledger.</p>
        </div>

        <p class="relative text-xs text-white/30">TourFlow Finance · Travel &amp; Tours</p>
    </div>

    <!-- Form panel -->
    <div class="relative flex flex-col justify-center px-6 sm:px-12 py-12 bg-canvas">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(224,164,88,0.08),_transparent_50%),radial-gradient(ellipse_at_bottom_left,_rgba(14,59,67,0.06),_transparent_50%)]"></div>

        <div class="relative w-full max-w-md mx-auto animate-slide-up">
            <a href="landing.php" class="lg:hidden flex items-center gap-2 mb-10">
                <div class="w-9 h-9 rounded-lg bg-primary flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#E0A458" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7z"/></svg>
                </div>
                <span class="font-display font-bold text-lg text-primary">TourFlow</span>
            </a>

            <h2 class="font-display text-2xl font-bold text-ink mb-1">Sign in</h2>
            <p class="text-sm text-ink/45 mb-8">Enter your credentials to open the workspace.</p>

            <?php if ($error): ?>
            <div class="mb-5 rounded-xl px-4 py-3 text-sm font-medium bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 animate-shake">
                <?= e($error) ?>
            </div>
            <?php endif; ?>

            <?php foreach (get_flashes() as $f): ?>
            <div class="mb-5 rounded-xl px-4 py-3 text-sm font-medium ring-1 <?= $f['type'] === 'error' ? 'bg-rose-50 text-rose-700 ring-rose-600/20' : 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' ?>">
                <?= e($f['message']) ?>
            </div>
            <?php endforeach; ?>

            <form method="post" class="space-y-5" autocomplete="on">
                <div>
                    <label class="block text-xs font-semibold text-ink/50 mb-1.5 uppercase tracking-wide">Username</label>
                    <input
                        type="text"
                        name="username"
                        required
                        autofocus
                        value="<?= e($_POST['username'] ?? '') ?>"
                        class="tf-input w-full"
                        placeholder="admin"
                    >
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink/50 mb-1.5 uppercase tracking-wide">Password</label>
                    <input
                        type="password"
                        name="password"
                        required
                        class="tf-input w-full"
                        placeholder="••••••••"
                    >
                </div>
                <button type="submit" class="w-full flex items-center justify-center gap-2 rounded-xl bg-primary hover:bg-primary-light text-white font-display font-semibold text-sm px-5 py-3.5 transition-all duration-300 hover:shadow-lg hover:shadow-primary/25 hover:scale-[1.01] active:scale-[0.99]">
                    Sign in to workspace
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            <p class="mt-8 text-center text-xs text-ink/35">
                Default admin · <span class="font-mono text-ink/50">admin</span> / <span class="font-mono text-ink/50">admin123</span>
            </p>
            <p class="mt-3 text-center">
                <a href="landing.php" class="text-sm font-medium text-primary hover:underline">← Back to home</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>
