<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::startSession();

// Jika sudah login
if (Auth::check()) {
    if (Auth::getRole() === 'mahasiswa') header("Location: mahasiswa_dashboard.php");
    else header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid. Silakan muat ulang halaman.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = "Username dan password wajib diisi.";
        } else {
            // Parameter 'mahasiswa' memastikan hanya role mahasiswa yang bisa masuk
            $result = Auth::login($username, $password, 'mahasiswa');
            if ($result['success']) {
                header("Location: mahasiswa_dashboard.php");
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="id">
<head>
<script>
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>SAQUNA | Student Portal Login</title>
<script src="assets/js/tailwindcss.js"></script>
<link href="assets/css/fonts.css" rel="stylesheet">
<?php include 'components/theme_config.php'; ?>
    <style>
        .glass-panel {
            backdrop-filter: blur(20px);
            background: var(--color-glass-bg);
            border: 1px solid var(--color-glass-border);
        }
        .mint-glow-shadow {
            box-shadow: 0 0 40px 0 var(--color-mint-glow);
        }
        .bg-blob {
            filter: blur(80px);
            z-index: -1;
        }
        .pulse-hover:hover {
            animation: pulse-ring 1.5s cubic-bezier(0.24, 0, 0.38, 1) infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(25, 107, 80, 0.4); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(25, 107, 80, 0); }
            100% { transform: scale(0.98); box-shadow: 0 0 0 0 rgba(25, 107, 80, 0); }
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md min-h-screen flex flex-col overflow-x-hidden">
<!-- Atmospheric Background -->
<div class="fixed inset-0 pointer-events-none overflow-hidden">
<div class="bg-blob absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-primary-fixed opacity-20 rounded-full animate-float"></div>
<div class="bg-blob absolute bottom-[-5%] right-[-5%] w-[40%] h-[40%] bg-secondary-fixed opacity-30 rounded-full animate-pulse"></div>
<div class="bg-blob absolute top-[20%] right-[15%] w-[30%] h-[30%] bg-mint-glow opacity-25 rounded-full" style="animation: float 8s ease-in-out infinite reverse;"></div>
</div>
<!-- Top Navigation (Shell Implementation) -->
<header class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-gutter py-4 bg-glass-bg backdrop-blur-xl border-b border-glass-border shadow-mint-glow">
<div class="flex items-center gap-2">
<div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-lg overflow-hidden">
    <img src="assets/logo.png" alt="SAQUNA Logo" class="w-8 h-8 object-contain">
</div>
<div>
    <h1 class="font-bold text-primary tracking-tight" style="font-family: Outfit; font-size: 24px; line-height: 1;">SAQUNA</h1>
    <p class="text-on-surface-variant opacity-75 font-medium" style="font-size: 10px; line-height: 1.3; margin-top: 2px; max-width: 140px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
</div>
</div>
        <nav class="flex items-center gap-stack-md">
            <div class="flex items-center gap-stack-sm text-on-surface-variant font-body-md">
                <div class="group relative flex items-center justify-center">
                    <span class="material-symbols-outlined hover:bg-primary-container/10 p-2 rounded-full cursor-pointer transition-all">help_outline</span>
                    <div class="absolute top-full right-0 mt-2 w-64 bg-surface-container-high text-on-surface p-3 rounded-lg shadow-lg text-sm border border-outline-variant/30 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 font-normal">
                        Gunakan NIM dan password yang diberikan saat pendaftaran ulang. Hubungi akademik jika mengalami kendala.
                    </div>
                </div>
                <button id="theme-toggle" class="material-symbols-outlined hover:bg-primary-container/10 p-2 rounded-full cursor-pointer transition-all focus:outline-none">dark_mode</button>
            </div>
        </nav>
</header>
<!-- Main Content -->
<main class="flex-grow flex items-center justify-center px-gutter pt-24 pb-12 relative z-10">
<div class="w-full max-w-[1000px] grid grid-cols-1 lg:grid-cols-2 gap-stack-xl items-center">
<!-- Left Side: Immersive Visuals -->
<div class="hidden lg:flex flex-col justify-center items-start space-y-stack-md">
<div class="relative w-full aspect-square max-w-[400px]">
<!-- Abstract Academic Mesh / 3D Orb Representation -->
<div class="absolute inset-0 rounded-full glass-panel border-mint-glow animate-float flex items-center justify-center overflow-hidden">
<img alt="Futuristic geometry" class="w-full h-full opacity-40 scale-150 rotate-12" data-alt="A sophisticated minimalist 3D geometric mesh floating in a bright, ethereal digital space. The structure is composed of translucent mint green nodes and delicate glowing lines, creating a web-like academic neural network. Soft ambient lighting illuminates the scene with a light-mode aesthetic, emphasizing a zen-like, professional academic atmosphere. Subtle bokeh particles drift in the background against a pristine white and soft emerald gradient." src="https://lh3.googleusercontent.com/aida-public/AB6AXuC0hO19RSR3MmiKSkhIMmtGu-cFdEkIkKYY1ne8biThrQ-rMsGzNqmH3yAtrpWx0fLnGZdl6AWB-z9J6tf9ksYTTW5715lbejYjsvCKjay8khmJK23HRaGMkyHQ0dwaNHvBP-be6Uw6PPuSUMM4jagLGZN2EME7dfq7IB5GyzVY4FaEVJ66hLF6MzQt0IoaX2QkdxXQHFcaVlvuA41erkJ9fAwQXXEaPzTK0cdCQj3oJWFqzwh7msqWex_Xs9qc-0HGtCjfA3IUu1o"/>
<div class="absolute inset-0 bg-gradient-to-tr from-primary/10 to-transparent"></div>
</div>
<!-- Orbiting accent -->
<div class="absolute top-0 right-0 w-24 h-24 glass-panel rounded-full animate-pulse border-white/60"></div>
</div>
<div class="space-y-stack-sm">
<h1 class="font-display-lg text-display-lg text-primary tracking-tight">Masa Depan Akademik Anda Dimulai di Sini.</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant max-w-[380px]">Platform terintegrasi untuk kecemerlangan riset, kolaborasi, dan pencapaian akademik global.</p>
</div>
</div>
<!-- Right Side: Login Card -->
<div class="flex justify-center">
<div class="glass-panel w-full max-w-[480px] p-10 rounded-[2rem] mint-glow-shadow flex flex-col items-center">
<div class="w-full text-center mb-stack-lg">
<h2 class="font-headline-lg text-headline-lg text-on-surface mb-2">Selamat Datang</h2>
<p class="font-body-md text-body-md text-on-surface-variant">Masuk untuk mengakses portal mahasiswa Anda.</p>
</div>

<?php if ($error): ?>
    <div class="w-full bg-error-container text-on-error-container p-4 rounded-xl mb-6 font-body-sm flex items-center gap-2">
        <span class="material-symbols-outlined">error</span>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form method="POST" class="w-full space-y-stack-md">
<?= Auth::csrfField() ?>
<!-- Input 1 -->
<div class="space-y-2">
<label class="font-label-md text-label-md text-on-surface-variant ml-1">Username atau NIM</label>
<div class="relative group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">person</span>
<input name="username" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl py-4 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-on-surface placeholder:text-outline-variant" placeholder="Contoh: 12345678" type="text" required/>
</div>
</div>
<!-- Input 2 -->
<div class="space-y-2">
<div class="flex justify-between items-center px-1">
<label class="font-label-md text-label-md text-on-surface-variant">Password</label>
<a class="font-label-md text-label-md text-primary hover:underline" href="lupa_sandi.php?role=mahasiswa">Lupa Password?</a>
</div>
<div class="relative group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">lock</span>
<input id="password" name="password" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl py-4 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-on-surface placeholder:text-outline-variant" placeholder="••••••••" type="password" required/>
<button type="button" class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant cursor-pointer hover:text-on-surface bg-transparent border-none" onclick="const p=document.getElementById('password'); p.type=p.type==='password'?'text':'password'; this.textContent=p.type==='password'?'visibility_off':'visibility';">
visibility_off
</button>
</div>
</div>
<button class="w-full bg-primary-container text-on-primary-container font-headline-md text-headline-md py-4 rounded-xl shadow-mint-glow pulse-hover active:scale-95 transition-all duration-300 mt-stack-sm flex justify-center items-center gap-2 border-none cursor-pointer" type="submit">
    Masuk ke Portal
</button>

<div class="mt-4 text-center">
    <a href="login.php" class="text-on-surface-variant text-label-md hover:text-primary transition-colors flex items-center justify-center gap-1 no-underline">
        <span class="material-symbols-outlined" style="font-size: 16px;">arrow_back</span>
        Kembali ke Pilihan Portal
    </a>
</div>

</form>
<!-- Security Indicators -->
<div class="mt-stack-lg flex flex-wrap justify-center gap-stack-sm">
<div class="flex items-center gap-2 bg-secondary-container/30 px-4 py-2 rounded-full border border-secondary-container/50">
<span class="material-symbols-outlined text-[18px] text-primary" style="font-variation-settings: 'FILL' 1;">verified_user</span>
<span class="font-label-md text-label-md text-on-secondary-container">End-to-End Encrypted</span>
</div>
<div class="flex items-center gap-2 bg-secondary-container/30 px-4 py-2 rounded-full border border-secondary-container/50">
<span class="material-symbols-outlined text-[18px] text-primary" style="font-variation-settings: 'FILL' 1;">shield_with_heart</span>
<span class="font-label-md text-label-md text-on-secondary-container">Protected by SHA-256</span>
</div>
</div>
</div>
</div>
</div>
</main>
<!-- Footer -->
<footer class="w-full py-8 px-margin-page flex flex-col md:flex-row justify-between items-center gap-stack-md bg-transparent relative z-10">
<div class="flex flex-col items-center md:items-start gap-1">
<div class="flex items-center gap-2">
    <img src="assets/logo.png" alt="SAQUNA Logo" class="w-6 h-6 object-contain">
    <span class="font-title-lg text-title-lg font-bold text-secondary" style="line-height: 1;">SAQUNA</span>
</div>
<p class="text-on-surface-variant opacity-75 font-medium text-center md:text-left" style="font-size: 11px; line-height: 1.3; max-width: 200px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
<p class="font-body-sm text-body-sm text-on-surface-variant opacity-70 mt-2">© <?= date('Y') ?> SAQUNA Academic Portal.</p>
</div>
<div class="flex flex-wrap justify-center gap-stack-md">
<button type="button" onclick="openPolicyModal('security')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Security Policy</button>
<button type="button" onclick="openPolicyModal('privacy')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Privacy Center</button>
<button type="button" onclick="openPolicyModal('terms')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Terms of Service</button>
<button type="button" onclick="openPolicyModal('accessibility')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Accessibility</button>
</div>
</footer>
<script>
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', () => {
            input.closest('.glass-panel')?.classList.add('shadow-lg');
        });
        input.addEventListener('blur', () => {
            input.closest('.glass-panel')?.classList.remove('shadow-lg');
        });
    });

    const form = document.querySelector('form');
    if(form) {
        form.addEventListener('submit', (e) => {
            const btn = e.target.querySelector('button[type="submit"]');
            if(btn.dataset.submitted) return;
            btn.dataset.submitted = 'true';
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Memproses...';
        });
    }

    const themeToggleBtn = document.getElementById('theme-toggle');
    function updateThemeIcon() {
        if (!themeToggleBtn) return;
        if (document.documentElement.classList.contains('dark')) {
            themeToggleBtn.textContent = 'light_mode';
        } else {
            themeToggleBtn.textContent = 'dark_mode';
        }
    }
    
    updateThemeIcon();

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', function() {
            if (document.documentElement.classList.contains('dark')) {
                document.documentElement.classList.remove('dark');
                localStorage.theme = 'light';
            } else {
                document.documentElement.classList.add('dark');
                localStorage.theme = 'dark';
            }
            updateThemeIcon();
        });
    }
</script>
<?php include 'components/policy_modals.php'; ?>
</body>
</html>
