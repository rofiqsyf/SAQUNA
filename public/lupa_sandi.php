<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::startSession();

$title = "SAQUNA | Pengajuan Lupa Sandi";
$error = '';
$success = '';

// Proses Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting: max 5 pengajuan per 10 menit
    $rateLimitKey = 'lupa_sandi_attempts';
    $rateLimitTime = 'lupa_sandi_last_time';
    $maxAttempts = 5;
    $windowSeconds = 600; // 10 menit
    
    if (isset($_SESSION[$rateLimitTime]) && (time() - $_SESSION[$rateLimitTime]) > $windowSeconds) {
        // Reset setelah window waktu habis
        $_SESSION[$rateLimitKey] = 0;
    }
    
    if (isset($_SESSION[$rateLimitKey]) && $_SESSION[$rateLimitKey] >= $maxAttempts) {
        $error = "Terlalu banyak pengajuan. Silakan tunggu beberapa menit sebelum mencoba lagi.";
    } else {
        $nomor_induk = trim($_POST['nomor_induk'] ?? '');
        $role = $_POST['role'] ?? '';
        $catatan = trim($_POST['catatan'] ?? '');

        if (empty($nomor_induk) || empty($role)) {
            $error = "Nomor Induk dan Peran wajib diisi.";
        } elseif (!in_array($role, ['mahasiswa', 'dosen'])) {
            $error = "Peran tidak valid.";
        } elseif (mb_strlen($catatan) > 500) {
            $error = "Catatan terlalu panjang (maksimal 500 karakter).";
        } else {
            try {
                $db = \Config\Database::getConnection();
                $stmt = $db->prepare("INSERT INTO lupa_sandi_requests (nomor_induk, role, catatan) VALUES (?, ?, ?)");
                $stmt->execute([$nomor_induk, $role, $catatan]);
                
                // Increment counter rate limiting
                $_SESSION[$rateLimitKey] = ($_SESSION[$rateLimitKey] ?? 0) + 1;
                $_SESSION[$rateLimitTime] = time();
                
                $success = "Pengajuan reset sandi berhasil dikirim! Silakan hubungi IT Helpdesk atau tunggu konfirmasi dari operator/akademik terkait proses reset sandi Anda.";
            } catch (PDOException $e) {
                $error = "Gagal mengirim pengajuan. Silakan coba beberapa saat lagi.";
            }
        }
    }
}

// Ambil default role dari parameter URL (opsional)
$default_role = $_GET['role'] ?? 'mahasiswa';
$default_role = in_array($default_role, ['mahasiswa', 'dosen']) ? $default_role : 'mahasiswa';
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
<title><?= $title ?></title>
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
        .pulse-hover:hover {
            animation: soft-pulse 2s infinite;
        }
        @keyframes soft-pulse {
            0% { box-shadow: 0 0 0 0 rgba(25, 107, 80, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(25, 107, 80, 0); }
            100% { box-shadow: 0 0 0 0 rgba(25, 107, 80, 0); }
        }
    </style>
</head>
<body class="bg-mesh min-h-screen flex flex-col font-body-md text-on-surface">
<!-- Background Blobs -->
<div class="floating-blob bg-primary-fixed w-[500px] h-[500px] top-[-100px] left-[-100px] animate-float"></div>
<div class="floating-blob bg-secondary-fixed w-[400px] h-[400px] bottom-[0px] right-[-100px] animate-float" style="animation-delay: -5s;"></div>

<!-- Top Navigation -->
<header class="fixed top-0 left-0 w-full z-50 flex justify-between items-center px-gutter py-4 bg-glass-bg backdrop-blur-xl border-b border-glass-border shadow-mint-glow">
<div class="flex items-center gap-2">
<div class="w-10 h-10 rounded-xl bg-primary flex items-center justify-center shadow-lg">
    <span class="material-symbols-outlined text-white text-[24px]">school</span>
</div>
<div>
    <h1 class="font-bold text-primary tracking-tight" style="font-family: Outfit; font-size: 24px; line-height: 1;">SAQUNA</h1>
    <p class="text-on-surface-variant opacity-75 font-medium" style="font-size: 10px; line-height: 1.3; margin-top: 2px; max-width: 140px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
</div>
</div>
<nav class="hidden md:flex items-center gap-stack-md">
<div class="flex items-center gap-stack-sm text-on-surface-variant font-body-md">
<button id="theme-toggle" class="material-symbols-outlined hover:bg-primary-container/10 p-2 rounded-full cursor-pointer transition-all focus:outline-none">dark_mode</button>
</div>
</nav>
</header>

<!-- Main Content -->
<main class="flex-grow flex items-center justify-center px-gutter pt-24 pb-12 relative z-10">
<div class="w-full max-w-[500px]">
    
    <?php if ($success): ?>
    <div class="glass-panel rounded-2xl p-stack-lg shadow-mint-glow border-l-4 border-l-primary text-center">
        <div class="w-16 h-16 bg-secondary-container text-primary rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="material-symbols-outlined text-4xl">check_circle</span>
        </div>
        <h2 class="font-headline-md text-headline-md text-primary mb-2">Permintaan Terkirim</h2>
        <p class="font-body-md text-on-surface-variant mb-6"><?= htmlspecialchars($success) ?></p>
        <a href="login.php" class="inline-flex justify-center items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-label-md hover:bg-primary/90 transition-colors">
            <span class="material-symbols-outlined text-xl">arrow_back</span> Kembali ke Pilihan Portal
        </a>
    </div>
    <?php else: ?>
    
    <div class="glass-panel rounded-2xl p-stack-lg shadow-mint-glow relative">
        <!-- Decorative subtle element -->
        <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
            <span class="material-symbols-outlined text-[100px] text-primary">lock_reset</span>
        </div>
        
        <div class="relative z-10">
            <div class="mb-stack-md">
                <a href="login_<?= $default_role ?>.php" class="inline-flex items-center gap-2 text-primary font-label-md hover:underline mb-4">
                    <span class="material-symbols-outlined text-sm">arrow_back</span> Kembali ke Login
                </a>
                <h2 class="font-display-lg text-headline-lg text-primary tracking-tight">Lupa Sandi?</h2>
                <p class="font-body-md text-body-md text-on-surface-variant mt-2">Masukkan identitas Anda dan keterangan tambahan untuk diverifikasi oleh operator kami.</p>
            </div>
            
            <?php if ($error): ?>
                <div class="bg-error-container text-on-error-container p-4 rounded-xl mb-6 flex gap-3 items-start text-sm">
                    <span class="material-symbols-outlined text-error text-[20px]">error</span>
                    <p><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="flex flex-col gap-stack-md">
                
                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant">Nomor Induk (NIM / NIDN)</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">badge</span>
                        <input name="nomor_induk" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl py-4 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-on-surface placeholder:text-outline-variant" placeholder="Contoh: 20210001" type="text" required/>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant">Sebagai</label>
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors">account_circle</span>
                        <select name="role" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl py-4 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-on-surface appearance-none" required>
                            <option value="mahasiswa" <?= $default_role === 'mahasiswa' ? 'selected' : '' ?>>Mahasiswa</option>
                            <option value="dosen" <?= $default_role === 'dosen' ? 'selected' : '' ?>>Dosen</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">arrow_drop_down</span>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="font-label-md text-label-md text-on-surface-variant">Catatan Tambahan (Opsional)</label>
                    <div class="relative group">
                        <textarea name="catatan" rows="3" class="w-full bg-surface-container-lowest border border-outline-variant rounded-xl py-3 px-4 focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-on-surface placeholder:text-outline-variant" placeholder="Misal: Nomor WA yang bisa dihubungi..."></textarea>
                    </div>
                </div>

                <button class="w-full bg-primary text-on-primary font-headline-md text-headline-md py-4 rounded-xl shadow-mint-glow pulse-hover active:scale-95 transition-all duration-300 mt-stack-sm flex justify-center items-center gap-2 border-none cursor-pointer" type="submit">
                    Kirim Permintaan Reset
                    <span class="material-symbols-outlined">send</span>
                </button>
            </form>
        </div>
    </div>
    
    <?php endif; ?>

</div>
</main>

<!-- Footer -->
<footer class="w-full py-8 px-margin-page flex flex-col md:flex-row justify-between items-center gap-stack-md bg-transparent relative z-10">
<div class="flex flex-col items-center md:items-start gap-1">
<div class="flex items-center gap-2">
    <span class="material-symbols-outlined text-secondary text-[24px]">school</span>
    <span class="font-title-lg text-title-lg font-bold text-secondary" style="line-height: 1;">SAQUNA</span>
</div>
<p class="text-on-surface-variant opacity-75 font-medium text-center md:text-left" style="font-size: 11px; line-height: 1.3; max-width: 200px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
<p class="font-body-sm text-body-sm text-on-surface-variant opacity-70 mt-2">© <?= date('Y') ?> SAQUNA Academic Portal. Protected by SSL encryption.</p>
</div>
<div class="flex flex-wrap justify-center gap-stack-md">
<button type="button" onclick="openPolicyModal('security')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Security Policy</button>
<button type="button" onclick="openPolicyModal('privacy')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Privacy Center</button>
<button type="button" onclick="openPolicyModal('terms')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Terms of Service</button>
<button type="button" onclick="openPolicyModal('accessibility')" class="font-label-md text-label-md text-on-surface-variant opacity-70 hover:text-primary hover:opacity-100 transition-all focus:underline decoration-2 underline-offset-4 no-underline cursor-pointer bg-transparent border-none">Accessibility</button>
</div>
</footer>
<script>
    const form = document.querySelector('form');
    if(form) {
        form.addEventListener('submit', (e) => {
            const btn = e.target.querySelector('button[type="submit"]');
            if(btn.dataset.submitted) return;
            btn.dataset.submitted = 'true';
            btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span> Mengirim...';
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
