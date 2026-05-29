<?php
$tailwindConfig = <<<EOF
tailwind.config = {
  darkMode: "class",
  theme: {
    extend: {
      "colors": {
              "secondary-fixed": "#bdedd6",
              "inverse-primary": "#8ad6b5",
              "on-tertiary-fixed": "#091f1a",
              "surface-container-highest": "#e1e3e4",
              "outline": "#6f7973",
              "tertiary-fixed-dim": "#b3ccc3",
              "surface-container": "#eceeef",
              "on-secondary-fixed": "#002116",
              "surface-dim": "#d8dadb",
              "error-container": "#ffdad6",
              "outline-variant": "#bec9c2",
              "primary-fixed-dim": "#8ad6b5",
              "background": "#f8fafb",
              "surface-container-lowest": "#ffffff",
              "on-tertiary-container": "#3e544e",
              "tertiary-container": "#b0c8c0",
              "on-secondary-container": "#3f6b59",
              "secondary-fixed-dim": "#a1d1bb",
              "surface-bright": "#f8fafb",
              "on-error-container": "#93000a",
              "surface-container-low": "#f2f4f5",
              "on-surface": "#191c1d",
              "on-tertiary": "#ffffff",
              "error": "#ba1a1a",
              "primary-container": "#86d2b1",
              "on-primary": "#ffffff",
              "on-surface-variant": "#3f4944",
              "inverse-surface": "#2e3132",
              "tertiary": "#4d635c",
              "on-primary-fixed-variant": "#00513a",
              "tertiary-fixed": "#cfe8df",
              "on-primary-container": "#005c42",
              "surface-container-high": "#e6e8e9",
              "on-background": "#191c1d",
              "primary": "#196b50",
              "on-primary-fixed": "#002115",
              "inverse-on-surface": "#eff1f2",
              "secondary-container": "#baead3",
              "secondary": "#3a6755",
              "on-error": "#ffffff",
              "on-tertiary-fixed-variant": "#354b45",
              "on-secondary-fixed-variant": "#224f3e",
              "surface-variant": "#e1e3e4",
              "on-secondary": "#ffffff",
              "primary-fixed": "#a5f2d0",
              "surface": "#f8fafb",
              "surface-tint": "#196b50"
      },
      "borderRadius": {
              "DEFAULT": "0.25rem",
              "lg": "0.5rem",
              "xl": "0.75rem",
              "full": "9999px"
      },
      "spacing": {
              "unit": "8px",
              "stack-xs": "4px",
              "stack-sm": "12px",
              "stack-md": "24px",
              "gutter": "24px",
              "stack-lg": "40px",
              "container-max": "1440px",
              "margin-page": "48px",
              "stack-xl": "64px"
      },
      "fontFamily": {
              "title-lg": ["Outfit"],
              "headline-md": ["Outfit"],
              "body-md": ["Inter"],
              "display-lg": ["Outfit"],
              "label-md": ["Inter"],
              "headline-lg": ["Outfit"],
              "body-lg": ["Inter"],
              "body-sm": ["Inter"]
      }
    }
  }
}
EOF;

$headerContent = <<<PHP
<?php
use Src\Auth;
\$role = Auth::getRole();
\$username = \$_SESSION['username'] ?? 'User';
\$current_page = basename(\$_SERVER['PHP_SELF']);

function getLinkClass(\$page, \$current) {
    if (\$page === \$current) {
        return "flex items-center gap-4 px-4 py-3 bg-primary text-on-primary rounded-xl shadow-[0_4px_12px_rgba(25,107,80,0.2)] transition-all ring-2 ring-primary-container ring-offset-2";
    }
    return "flex items-center gap-4 px-4 py-3 text-on-surface-variant hover:text-primary hover:bg-primary-container/10 rounded-xl transition-transform duration-200 hover:translate-x-1";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= isset(\$title) ? htmlspecialchars(\$title) : "SAQUNA | Academic Portal" ?></title>
    <script src="assets/js/tailwindcss.js"></script>
    <link href="assets/css/fonts.css" rel="stylesheet">
<script id="tailwind-config">
$tailwindConfig
    </script>
    <style>
        .glass-panel { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.4); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { background-color: #F8FAFB; background-image: radial-gradient(circle at 10% 20%, rgba(134, 210, 177, 0.1) 0%, transparent 40%), radial-gradient(circle at 90% 80%, rgba(186, 234, 211, 0.1) 0%, transparent 40%); }
    </style>
</head>
<body class="font-body-md text-on-surface">

<aside class="fixed left-0 top-0 h-screen w-72 z-40 bg-gradient-to-b from-white/80 to-secondary-container/30 backdrop-blur-2xl border-r border-white/40 flex flex-col p-stack-md gap-stack-sm shadow-xl">
    <div class="flex items-center gap-stack-sm mb-stack-lg px-2">
        <div class="w-12 h-12 rounded-xl bg-primary flex items-center justify-center shadow-lg">
            <span class="material-symbols-outlined text-white text-3xl">school</span>
        </div>
        <div>
            <h1 class="font-headline-lg text-headline-lg font-bold text-primary tracking-tight" style="font-family: Outfit; font-size: 28px;">SAQUNA</h1>
            <p class="font-label-md text-label-md text-on-surface-variant opacity-70" style="margin-top:-5px;">Academic Portal</p>
        </div>
    </div>
    
    <nav class="flex-1 space-y-2" style="overflow-y: auto;">
        <?php if (\$role === 'mahasiswa'): ?>
            <a href="mahasiswa_dashboard.php" class="<?= getLinkClass('mahasiswa_dashboard.php', \$current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="mahasiswa_krs.php" class="<?= getLinkClass('mahasiswa_krs.php', \$current_page) ?>"><span class="material-symbols-outlined">description</span><span class="font-body-lg text-body-lg">KRS</span></a>
            <a href="mahasiswa_tugas.php" class="<?= getLinkClass('mahasiswa_tugas.php', \$current_page) ?>"><span class="material-symbols-outlined">assignment</span><span class="font-body-lg text-body-lg">Tugas</span></a>
            <a href="mahasiswa_khs.php" class="<?= getLinkClass('mahasiswa_khs.php', \$current_page) ?>"><span class="material-symbols-outlined">grade</span><span class="font-body-lg text-body-lg">KHS</span></a>
            <a href="mahasiswa_transkrip.php" class="<?= getLinkClass('mahasiswa_transkrip.php', \$current_page) ?>"><span class="material-symbols-outlined">school</span><span class="font-body-lg text-body-lg">Transkrip</span></a>
            <a href="mahasiswa_ta.php" class="<?= getLinkClass('mahasiswa_ta.php', \$current_page) ?>"><span class="material-symbols-outlined">history_edu</span><span class="font-body-lg text-body-lg">Skripsi (TA)</span></a>
            <a href="mahasiswa_tagihan.php" class="<?= getLinkClass('mahasiswa_tagihan.php', \$current_page) ?>"><span class="material-symbols-outlined">payments</span><span class="font-body-lg text-body-lg">UKT / SPP</span></a>
            <a href="mahasiswa_chat.php" class="<?= getLinkClass('mahasiswa_chat.php', \$current_page) ?>"><span class="material-symbols-outlined">chat</span><span class="font-body-lg text-body-lg">Pesan</span></a>
        <?php elseif (\$role === 'dosen'): ?>
            <a href="dosen_dashboard.php" class="<?= getLinkClass('dosen_dashboard.php', \$current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="dosen_krs.php" class="<?= getLinkClass('dosen_krs.php', \$current_page) ?>"><span class="material-symbols-outlined">fact_check</span><span class="font-body-lg text-body-lg">Perwalian KRS</span></a>
            <a href="dosen_tugas.php" class="<?= getLinkClass('dosen_tugas.php', \$current_page) ?>"><span class="material-symbols-outlined">assignment_turned_in</span><span class="font-body-lg text-body-lg">Penilaian Tugas</span></a>
            <a href="dosen_ta.php" class="<?= getLinkClass('dosen_ta.php', \$current_page) ?>"><span class="material-symbols-outlined">history_edu</span><span class="font-body-lg text-body-lg">Bimbingan TA</span></a>
            <a href="dosen_chat.php" class="<?= getLinkClass('dosen_chat.php', \$current_page) ?>"><span class="material-symbols-outlined">forum</span><span class="font-body-lg text-body-lg">Inbox</span></a>
        <?php elseif (\$role === 'operator'): ?>
            <a href="dashboard.php" class="<?= getLinkClass('dashboard.php', \$current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="master_mahasiswa.php" class="<?= getLinkClass('master_mahasiswa.php', \$current_page) ?>"><span class="material-symbols-outlined">groups</span><span class="font-body-lg text-body-lg">Mahasiswa</span></a>
            <a href="master_matakuliah.php" class="<?= getLinkClass('master_matakuliah.php', \$current_page) ?>"><span class="material-symbols-outlined">library_books</span><span class="font-body-lg text-body-lg">Mata Kuliah</span></a>
            <a href="index.php" class="<?= getLinkClass('index.php', \$current_page) ?>"><span class="material-symbols-outlined">badge</span><span class="font-body-lg text-body-lg">Data Dosen</span></a>
            <a href="operator_tagihan.php" class="<?= getLinkClass('operator_tagihan.php', \$current_page) ?>"><span class="material-symbols-outlined">account_balance_wallet</span><span class="font-body-lg text-body-lg">Tagihan</span></a>
            <a href="operator_pengumuman.php" class="<?= getLinkClass('operator_pengumuman.php', \$current_page) ?>"><span class="material-symbols-outlined">campaign</span><span class="font-body-lg text-body-lg">Pengumuman</span></a>
            <a href="operator_chat.php" class="<?= getLinkClass('operator_chat.php', \$current_page) ?>"><span class="material-symbols-outlined">support_agent</span><span class="font-body-lg text-body-lg">Helpdesk</span></a>
            <a href="operator_log.php" class="<?= getLinkClass('operator_log.php', \$current_page) ?>"><span class="material-symbols-outlined">history</span><span class="font-body-lg text-body-lg">Audit Log</span></a>
        <?php endif; ?>
    </nav>
    <div class="mt-auto pt-stack-md border-t border-primary-container/20">
        <a href="logout.php" class="flex items-center gap-4 px-4 py-3 text-error hover:bg-error-container/20 rounded-xl transition-all">
            <span class="material-symbols-outlined">logout</span><span class="font-label-md text-label-md">Logout</span>
        </a>
    </div>
</aside>

<header class="fixed top-0 right-0 left-72 h-20 z-30 glass-panel flex justify-between items-center px-margin-page border-b border-white/40 shadow-[0_8px_32px_0_rgba(134,210,177,0.15)]">
    <div class="flex items-center gap-stack-sm">
        <div class="p-2 rounded-full bg-primary-container/20">
            <span class="material-symbols-outlined text-primary">search</span>
        </div>
        <input class="bg-transparent border-none focus:ring-0 text-body-md w-64 placeholder:text-on-surface-variant/50" placeholder="Search academic records..." type="text"/>
    </div>
    <div class="flex items-center gap-stack-md">
        <button class="relative p-2 text-on-surface-variant hover:bg-primary-container/20 rounded-full transition-all">
            <span class="material-symbols-outlined">notifications</span>
        </button>
        <div class="flex items-center gap-stack-sm border-l border-outline-variant/30 pl-stack-md">
            <div class="text-right">
                <p class="font-title-lg text-title-lg text-primary"><?= htmlspecialchars(\$username) ?></p>
                <p class="font-label-md text-label-md text-on-surface-variant uppercase"><?= htmlspecialchars(\$role) ?></p>
            </div>
            <div class="w-12 h-12 rounded-full bg-primary-container text-primary flex items-center justify-center font-bold text-xl">
                <?= strtoupper(substr(\$username, 0, 1)) ?>
            </div>
        </div>
    </div>
</header>

<main class="ml-72 pt-28 min-h-screen px-margin-page pb-stack-xl max-w-container-max mx-auto relative z-10">
PHP;

$footerContent = <<<PHP
</main>
<script>
    // Micro-interactions for glass panels
    document.querySelectorAll('.glass-panel').forEach(panel => {
        panel.addEventListener('mousemove', (e) => {
            const rect = panel.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            panel.style.setProperty('--mouse-x', `\${x}px`);
            panel.style.setProperty('--mouse-y', `\${y}px`);
        });
    });

    // Simple Entrance Animation
    document.addEventListener('DOMContentLoaded', () => {
        const mainContent = document.querySelector('main');
        if(mainContent) {
            mainContent.style.opacity = '0';
            mainContent.style.transform = 'translateY(20px)';
            setTimeout(() => {
                mainContent.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
                mainContent.style.opacity = '1';
                mainContent.style.transform = 'translateY(0)';
            }, 100);
        }
    });
</script>
</body>
</html>
PHP;

file_put_contents(__DIR__ . '/public/components/header.php', $headerContent);
file_put_contents(__DIR__ . '/public/components/footer.php', $footerContent);
echo "Header & Footer updated.\n";
