<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::startSession();

// Jika sudah login, redirect sesuai rolenya
if (Auth::check()) {
    $role = Auth::getRole();
    if ($role === 'mahasiswa') header("Location: mahasiswa_dashboard.php");
    elseif ($role === 'dosen') header("Location: dosen_dashboard.php");
    else header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html class="light" lang="id" style="">
<head>
<script>
    if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
</script>
<meta charset="utf-8">
<meta content="width=device-width, initial-scale=1.0" name="viewport">
<title>SAQUNA - Portal Akademik Terpadu</title>
<script src="assets/js/tailwindcss.js"></script>
<link href="assets/css/fonts.css" rel="stylesheet">
<?php include 'components/theme_config.php'; ?>
    <style>
        .glass-panel {
            backdrop-filter: blur(20px);
            background: var(--color-glass-bg);
            border: 1px solid var(--color-glass-border);
        }
        .bg-mesh {
            background-color: var(--color-background);
            background-image: radial-gradient(at 0% 0%, var(--color-primary-fixed-dim) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, var(--color-secondary-fixed) 0, transparent 50%), 
                              radial-gradient(at 0% 100%, var(--color-mint-glow) 0, transparent 50%);
        }
        .floating-blob {
            position: fixed;
            z-index: -1;
            filter: blur(80px);
            opacity: 0.4;
            border-radius: 9999px;
        }
        .animate-float {
            animation: float 15s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -50px); }
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-mesh min-h-screen flex flex-col font-body-md text-on-surface">
<!-- Background Blobs -->
<div class="floating-blob bg-primary-fixed w-[500px] h-[500px] top-[-100px] left-[-100px] animate-float"></div>
<div class="floating-blob bg-secondary-fixed w-[400px] h-[400px] bottom-[0px] right-[-100px] animate-float" style="animation-delay: -5s;"></div>
<!-- TopAppBar Shell -->
<nav class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-gutter py-4 bg-glass-bg backdrop-blur-xl border-b border-glass-border shadow-mint-glow">
<div class="flex items-center gap-2">
    <div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-lg">
        <span class="material-symbols-outlined text-white text-[24px]">school</span>
    </div>
    <div>
        <h1 class="font-bold text-primary tracking-tight" style="font-family: Outfit; font-size: 24px; line-height: 1;">SAQUNA</h1>
        <p class="text-on-surface-variant opacity-75 font-medium" style="font-size: 10px; line-height: 1.3; margin-top: 2px; max-width: 140px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
    </div>
</div>
<div class="flex items-center gap-stack-sm text-on-surface-variant font-body-md">
    <div class="group relative flex items-center justify-center">
        <span class="material-symbols-outlined hover:bg-primary-container/10 p-2 rounded-full cursor-pointer transition-all text-primary">help_outline</span>
        <div class="absolute top-full right-0 mt-2 w-64 bg-surface-container-high text-on-surface p-3 rounded-lg shadow-lg text-sm border border-outline-variant/30 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 font-normal">
            Halaman portal utama. Silakan pilih jalur akses sesuai dengan peran Anda (Mahasiswa, Dosen, atau Operator).
        </div>
    </div>
    <button id="theme-toggle" class="material-symbols-outlined hover:bg-primary-container/10 p-2 rounded-full cursor-pointer transition-all focus:outline-none text-primary">dark_mode</button>
</div>
</nav>
<!-- Main Content -->
<main class="flex-grow flex items-center justify-center pt-24 pb-12 px-gutter relative z-10">
<div class="max-w-[1100px] w-full grid md:grid-cols-2 gap-stack-xl items-center">
<!-- Left Side: Hero Text & Visual -->
<div class="flex flex-col gap-stack-md">
<div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-secondary-container/30 border border-glass-border w-fit">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
<span class="font-label-md text-on-secondary-container">Sistem Terenkripsi 256-bit</span>
</div>
<h1 class="font-display-lg text-display-lg text-primary leading-tight">
                    Gerbang Digital <br><span class="text-secondary">Masa Depan Akademik.</span>
</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant max-w-[480px]">
                    Masuk ke ekosistem SAQUNA untuk mengakses riset, manajemen kursus, dan administrasi kampus dalam satu platform zen yang terintegrasi.
                </p>
<!-- Security Badges -->
<div class="flex gap-stack-md mt-4">
<div class="flex items-center gap-2 text-on-surface-variant/60">
<span class="material-symbols-outlined text-body-sm">verified_user</span>
<span class="font-label-md">SSL SECURE</span>
</div>
<div class="flex items-center gap-2 text-on-surface-variant/60">
<span class="material-symbols-outlined text-body-sm">lock</span>
<span class="font-label-md">ENCRYPTED</span>
</div>
</div>
</div>
<!-- Right Side: Login Path Selection Card -->
<div class="glass-panel p-stack-lg rounded-[2rem] shadow-mint-glow relative overflow-hidden">
<!-- Decorative element -->
<div class="absolute top-0 right-0 p-8 opacity-10 pointer-events-none">
<span class="material-symbols-outlined text-[120px] text-primary">hub</span>
</div>
<div class="relative z-10 flex flex-col gap-stack-md">
<div>
<h2 class="font-headline-md text-headline-md text-primary">Pilih Jalur Akses</h2>
<p class="font-body-md text-body-md text-on-surface-variant">Silakan pilih peran Anda untuk melanjutkan ke portal spesifik.</p>
</div>
<!-- Interactive Cards Cluster -->
<div class="flex flex-col gap-stack-sm">
<!-- Mahasiswa -->
<a class="group flex items-center gap-stack-md p-stack-sm rounded-xl border border-outline-variant hover:border-primary hover:bg-primary-container/5 transition-all duration-300" href="login_mahasiswa.php">
<div class="w-14 h-14 rounded-lg bg-secondary-container/50 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-[32px]">school</span>
</div>
<div class="flex-grow">
<h3 class="font-title-lg text-title-lg text-on-surface group-hover:text-primary">Mahasiswa</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">KRS, Jadwal Kuliah, &amp; Nilai</p>
</div>
<span class="material-symbols-outlined text-outline-variant group-hover:text-primary group-hover:translate-x-1 transition-all">chevron_right</span>
</a>
<!-- Dosen -->
<a class="group flex items-center gap-stack-md p-stack-sm rounded-xl border border-outline-variant hover:border-primary hover:bg-primary-container/5 transition-all duration-300" href="login_dosen.php">
<div class="w-14 h-14 rounded-lg bg-secondary-container/50 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-[32px]">psychology</span>
</div>
<div class="flex-grow">
<h3 class="font-title-lg text-title-lg text-on-surface group-hover:text-primary">Dosen</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">E-Learning &amp; Manajemen Riset</p>
</div>
<span class="material-symbols-outlined text-outline-variant group-hover:text-primary group-hover:translate-x-1 transition-all">chevron_right</span>
</a>
<!-- Operator -->
<a class="group flex items-center gap-stack-md p-stack-sm rounded-xl border border-outline-variant hover:border-primary hover:bg-primary-container/5 transition-all duration-300" href="login_operator.php">
<div class="w-14 h-14 rounded-lg bg-secondary-container/50 flex items-center justify-center text-primary group-hover:scale-110 transition-transform">
<span class="material-symbols-outlined text-[32px]">settings_account_box</span>
</div>
<div class="flex-grow">
<h3 class="font-title-lg text-title-lg text-on-surface group-hover:text-primary">Operator</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">Administrasi &amp; Kontrol Sistem</p>
</div>
<span class="material-symbols-outlined text-outline-variant group-hover:text-primary group-hover:translate-x-1 transition-all">chevron_right</span>
</a>
</div>

</div>
</div>
</div>
</main>
<!-- Footer Shell -->
<footer class="w-full py-8 px-margin-page flex flex-col md:flex-row justify-between items-center gap-stack-md bg-transparent mt-auto relative z-10">
<div class="flex flex-col items-center md:items-start gap-1">
<div class="flex items-center gap-2">
    <span class="material-symbols-outlined text-secondary text-[24px]">school</span>
    <span class="font-title-lg text-title-lg font-bold text-secondary" style="line-height: 1;">SAQUNA</span>
</div>
<p class="text-on-surface-variant opacity-75 font-medium text-center md:text-left" style="font-size: 11px; line-height: 1.3; max-width: 200px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
<p class="font-body-sm text-body-sm text-on-surface-variant opacity-70 mt-2">© <?= date('Y') ?> SAQUNA Academic Portal.</p>
</div>
<div class="flex flex-wrap justify-center gap-stack-md">
<button type="button" onclick="openPolicyModal('security')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-opacity focus:underline decoration-2 underline-offset-4 cursor-pointer bg-transparent border-none">Security Policy</button>
<button type="button" onclick="openPolicyModal('privacy')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-opacity focus:underline decoration-2 underline-offset-4 cursor-pointer bg-transparent border-none">Privacy Center</button>
<button type="button" onclick="openPolicyModal('terms')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-opacity focus:underline decoration-2 underline-offset-4 cursor-pointer bg-transparent border-none">Terms of Service</button>
<button type="button" onclick="openPolicyModal('accessibility')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-opacity focus:underline decoration-2 underline-offset-4 cursor-pointer bg-transparent border-none">Accessibility</button>
</div>
</footer>
<script>
        // Simple micro-interaction for card hover focus
        document.querySelectorAll('a.group').forEach(card => {
            card.addEventListener('mouseenter', () => {
                card.style.transform = 'translateY(-4px)';
            });
            card.addEventListener('mouseleave', () => {
                card.style.transform = 'translateY(0)';
            });
        });

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
