<?php
use Src\Auth;
$role = Auth::getRole();
$username = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User';
$userId = $_SESSION['user_id'] ?? null;
$current_page = basename($_SERVER['PHP_SELF']);

// --- FETCH NOTIFIKASI REAL ---
$notifications = [];
if ($userId) {
    try {
        $db = \Config\Database::getConnection();
        
        // 1. Pesan/Chat belum dibaca (Semua Role)
        $stmtChat = $db->prepare("SELECT id, subjek, waktu_kirim FROM pesan_tanya_jawab WHERE penerima_user_id = ? AND is_read = 0 ORDER BY waktu_kirim DESC LIMIT 3");
        if ($stmtChat) {
            $stmtChat->execute([$userId]);
            while ($row = $stmtChat->fetch(PDO::FETCH_ASSOC)) {
                $notifications[] = [
                    'judul' => 'Pesan Baru: ' . $row['subjek'],
                    'waktu' => $row['waktu_kirim'],
                    'ikon' => 'chat',
                    'link' => $role === 'operator' ? 'operator_chat.php' : ($role === 'dosen' ? 'dosen_chat.php' : 'mahasiswa_chat.php')
                ];
            }
        }

        // Role Spesifik
        if ($role === 'mahasiswa') {
            $stmtPeng = $db->query("SELECT judul, created_at FROM pengumuman WHERE target_role IN ('semua', 'mahasiswa') ORDER BY created_at DESC LIMIT 2");
            if ($stmtPeng) {
                while ($row = $stmtPeng->fetch(PDO::FETCH_ASSOC)) {
                    $notifications[] = ['judul' => $row['judul'], 'waktu' => $row['created_at'], 'ikon' => 'campaign', 'link' => 'mahasiswa_dashboard.php'];
                }
            }
            // Beasiswa
            $stmtBea = $db->prepare("SELECT nama_beasiswa, created_at FROM beasiswa_penerima WHERE mahasiswa_id = (SELECT id FROM mahasiswa WHERE user_id = ?) ORDER BY created_at DESC LIMIT 1");
            if ($stmtBea) {
                $stmtBea->execute([$userId]);
                while ($row = $stmtBea->fetch(PDO::FETCH_ASSOC)) {
                    $notifications[] = ['judul' => 'Update Beasiswa: ' . $row['nama_beasiswa'], 'waktu' => $row['created_at'], 'ikon' => 'school', 'link' => 'mahasiswa_beasiswa.php'];
                }
            }
        } elseif ($role === 'operator') {
            $stmtReq = $db->query("SELECT id, created_at FROM lupa_sandi_requests WHERE status = 'pending' ORDER BY created_at DESC LIMIT 3");
            if ($stmtReq) {
                while ($row = $stmtReq->fetch(PDO::FETCH_ASSOC)) {
                    $notifications[] = ['judul' => 'Pengajuan Reset Sandi Baru', 'waktu' => $row['created_at'], 'ikon' => 'lock_reset', 'link' => 'dashboard.php'];
                }
            }
        } elseif ($role === 'dosen') {
            $stmtPeng = $db->query("SELECT judul, created_at FROM pengumuman WHERE target_role IN ('semua', 'dosen') ORDER BY created_at DESC LIMIT 2");
            if ($stmtPeng) {
                while ($row = $stmtPeng->fetch(PDO::FETCH_ASSOC)) {
                    $notifications[] = ['judul' => $row['judul'], 'waktu' => $row['created_at'], 'ikon' => 'campaign', 'link' => 'dosen_dashboard.php'];
                }
            }
        }
        
        // Urutkan berdasarkan waktu terbaru
        usort($notifications, function($a, $b) {
            return strtotime($b['waktu']) - strtotime($a['waktu']);
        });
        
        // Batasi maksimal 5 notifikasi
        $notifications = array_slice($notifications, 0, 5);

    } catch (Exception $e) {}
}
$unreadCount = count($notifications);

function getLinkClass($page, $current) {
    if ($page === $current) {
        return "flex items-center gap-3 px-4 py-3 bg-primary/15 text-primary dark:text-[#a5f2d0] rounded-xl relative font-medium before:content-[''] before:absolute before:left-0 before:top-1/2 before:-translate-y-1/2 before:w-1.5 before:h-8 before:bg-primary before:rounded-r-full transition-all";
    }
    return "flex items-center gap-3 px-4 py-3 text-on-surface-variant dark:text-[#8b9d97] hover:text-primary dark:hover:text-white hover:bg-primary/5 dark:hover:bg-white/5 rounded-xl transition-all font-medium group";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= isset($title) ? htmlspecialchars($title) : "SAQUNA | Academic Portal" ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<?php include __DIR__ . '/theme_config.php'; ?>
    <style>
        .btn-primary {
            background-color: var(--color-primary);
            color: var(--color-on-primary);
            padding: 0.625rem 1.25rem;
            border-radius: 9999px; /* Pill shape */
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
        }
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background-color: var(--color-surface-variant);
            color: var(--color-on-surface-variant);
            padding: 0.625rem 1.25rem;
            border-radius: 9999px; /* Pill shape */
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            border: 1px solid var(--color-outline-variant);
        }
        .btn-secondary:hover {
            background-color: var(--color-surface-container-highest);
        }
        .btn-danger {
            background-color: var(--color-error);
            color: var(--color-on-error);
            padding: 0.625rem 1.25rem;
            border-radius: 9999px; /* Pill shape */
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
        }
        .btn-danger:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        .btn-success {
            background-color: var(--color-primary-container);
            color: var(--color-on-primary-container);
            padding: 0.625rem 1.25rem;
            border-radius: 9999px; /* Pill shape */
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
        }
        .btn-success:hover {
            opacity: 0.9;
        }
        /* Fix for smaller buttons */
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            border-radius: 9999px;
        }
        .btn-lg {
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
        }
        
        .glass-panel { background: var(--color-glass-bg); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border: 1px solid var(--color-glass-border); }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { background-color: var(--color-background); background-image: radial-gradient(circle at 10% 20%, var(--color-mint-glow) 0%, transparent 40%), radial-gradient(circle at 90% 80%, var(--color-mint-glow) 0%, transparent 40%); }
        
        /* Custom Scrollbar for Sidebar */
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(156, 163, 175, 0.4); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(156, 163, 175, 0.7); }
        .custom-scrollbar { scrollbar-width: thin; scrollbar-color: rgba(156, 163, 175, 0.4) transparent; }
    </style>
</head>
<body class="font-body-md text-on-surface">

<aside id="sidebar" class="fixed left-0 top-0 h-screen w-72 z-40 bg-surface dark:bg-[#091f1a] flex flex-col shadow-2xl border-r border-outline-variant/30 dark:border-[#193a2e] transition-all duration-300">
    <div class="flex items-center gap-3 mb-8 px-6 pt-8">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-primary to-primary-fixed flex items-center justify-center shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined text-white text-[22px]">school</span>
        </div>
        <div>
            <h1 class="font-bold tracking-wide text-primary dark:text-white" style="font-family: Outfit; font-size: 24px; line-height: 1;">SAQUNA</h1>
            <p class="text-on-surface-variant dark:text-white/50 font-medium" style="font-size: 10px; line-height: 1.3; margin-top: 2px; max-width: 140px; letter-spacing: 0.2px;">Sistem Akademik Universitas Sains Al-Qur'an</p>
        </div>
    </div>
    
    <nav class="flex-1 space-y-1.5 px-4 pr-2 overflow-y-auto custom-scrollbar">
        <?php if ($role === 'mahasiswa'): ?>
            <a href="mahasiswa_dashboard.php" class="<?= getLinkClass('mahasiswa_dashboard.php', $current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="mahasiswa_profile.php" class="<?= getLinkClass('mahasiswa_profile.php', $current_page) ?>"><span class="material-symbols-outlined">manage_accounts</span><span class="font-body-lg text-body-lg">Profil Diri</span></a>
            <a href="mahasiswa_krs.php" class="<?= getLinkClass('mahasiswa_krs.php', $current_page) ?>"><span class="material-symbols-outlined">description</span><span class="font-body-lg text-body-lg">KRS</span></a>
            <a href="mahasiswa_jadwal.php" class="<?= getLinkClass('mahasiswa_jadwal.php', $current_page) ?>"><span class="material-symbols-outlined">calendar_view_week</span><span class="font-body-lg text-body-lg">Jadwal Kuliah</span></a>
            <a href="mahasiswa_kalender.php" class="<?= getLinkClass('mahasiswa_kalender.php', $current_page) ?>"><span class="material-symbols-outlined">event</span><span class="font-body-lg text-body-lg">Kalender Akademik</span></a>
            <a href="mahasiswa_presensi.php" class="<?= getLinkClass('mahasiswa_presensi.php', $current_page) ?>"><span class="material-symbols-outlined">how_to_reg</span><span class="font-body-lg text-body-lg">Kehadiran (Presensi)</span></a>
            <a href="mahasiswa_tugas.php" class="<?= getLinkClass('mahasiswa_tugas.php', $current_page) ?>"><span class="material-symbols-outlined">assignment</span><span class="font-body-lg text-body-lg">Tugas</span></a>
            <a href="mahasiswa_khs.php" class="<?= getLinkClass('mahasiswa_khs.php', $current_page) ?>"><span class="material-symbols-outlined">grade</span><span class="font-body-lg text-body-lg">KHS</span></a>
            <a href="mahasiswa_transkrip.php" class="<?= getLinkClass('mahasiswa_transkrip.php', $current_page) ?>"><span class="material-symbols-outlined">school</span><span class="font-body-lg text-body-lg">Transkrip</span></a>
            <a href="mahasiswa_nilai.php" class="<?= getLinkClass('mahasiswa_nilai.php', $current_page) ?>"><span class="material-symbols-outlined">analytics</span><span class="font-body-lg text-body-lg">Statistik Nilai</span></a>
            <a href="mahasiswa_ta.php" class="<?= getLinkClass('mahasiswa_ta.php', $current_page) ?>"><span class="material-symbols-outlined">history_edu</span><span class="font-body-lg text-body-lg">Skripsi (TA)</span></a>
            <a href="mahasiswa_perwalian.php" class="<?= getLinkClass('mahasiswa_perwalian.php', $current_page) ?>"><span class="material-symbols-outlined">person_pin</span><span class="font-body-lg text-body-lg">Riwayat Perwalian</span></a>
            <a href="mahasiswa_tagihan.php" class="<?= getLinkClass('mahasiswa_tagihan.php', $current_page) ?>"><span class="material-symbols-outlined">payments</span><span class="font-body-lg text-body-lg">UKT / SPP</span></a>
            <a href="mahasiswa_kemahasiswaan.php" class="<?= getLinkClass('mahasiswa_kemahasiswaan.php', $current_page) ?>"><span class="material-symbols-outlined">celebration</span><span class="font-body-lg text-body-lg">Kemahasiswaan</span></a>
            <a href="mahasiswa_beasiswa.php" class="<?= getLinkClass('mahasiswa_beasiswa.php', $current_page) ?>"><span class="material-symbols-outlined">workspace_premium</span><span class="font-body-lg text-body-lg">Beasiswa</span></a>
            <a href="mahasiswa_edom.php" class="<?= getLinkClass('mahasiswa_edom.php', $current_page) ?>"><span class="material-symbols-outlined">star_rate</span><span class="font-body-lg text-body-lg">Evaluasi Dosen (EDoM)</span></a>
            <a href="mahasiswa_layanan.php" class="<?= getLinkClass('mahasiswa_layanan.php', $current_page) ?>"><span class="material-symbols-outlined">description</span><span class="font-body-lg text-body-lg">Layanan Surat</span></a>
            <a href="mahasiswa_yudisium.php" class="<?= getLinkClass('mahasiswa_yudisium.php', $current_page) ?>"><span class="material-symbols-outlined">school</span><span class="font-body-lg text-body-lg">Yudisium</span></a>
            <a href="mahasiswa_chat.php" class="<?= getLinkClass('mahasiswa_chat.php', $current_page) ?>"><span class="material-symbols-outlined">chat</span><span class="font-body-lg text-body-lg">Pesan</span></a>
        <?php elseif ($role === 'dosen'): ?>
            <a href="dosen_dashboard.php" class="<?= getLinkClass('dosen_dashboard.php', $current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="dosen_profile.php" class="<?= getLinkClass('dosen_profile.php', $current_page) ?>"><span class="material-symbols-outlined">manage_accounts</span><span class="font-body-lg text-body-lg">Profil Diri</span></a>
            <a href="dosen_jadwal.php" class="<?= getLinkClass('dosen_jadwal.php', $current_page) ?>"><span class="material-symbols-outlined">calendar_view_week</span><span class="font-body-lg text-body-lg">Jadwal Mengajar</span></a>
            <a href="dosen_krs.php" class="<?= getLinkClass('dosen_krs.php', $current_page) ?>"><span class="material-symbols-outlined">fact_check</span><span class="font-body-lg text-body-lg">Perwalian KRS</span></a>
            <a href="dosen_perwalian.php" class="<?= getLinkClass('dosen_perwalian.php', $current_page) ?>"><span class="material-symbols-outlined">groups</span><span class="font-body-lg text-body-lg">Bimbingan Perwalian</span></a>
            <a href="dosen_presensi.php" class="<?= getLinkClass('dosen_presensi.php', $current_page) ?>"><span class="material-symbols-outlined">qr_code_scanner</span><span class="font-body-lg text-body-lg">Manajemen Presensi</span></a>
            <a href="dosen_input_nilai.php" class="<?= getLinkClass('dosen_input_nilai.php', $current_page) ?>"><span class="material-symbols-outlined">spellcheck</span><span class="font-body-lg text-body-lg">Input Nilai KHS</span></a>
            <a href="dosen_tugas.php" class="<?= getLinkClass('dosen_tugas.php', $current_page) ?>"><span class="material-symbols-outlined">assignment_turned_in</span><span class="font-body-lg text-body-lg">Penilaian Tugas</span></a>
            <a href="dosen_bimbingan_ta.php" class="<?= getLinkClass('dosen_bimbingan_ta.php', $current_page) ?>"><span class="material-symbols-outlined">history_edu</span><span class="font-body-lg text-body-lg">Bimbingan TA</span></a>
            <a href="dosen_portofolio.php" class="<?= getLinkClass('dosen_portofolio.php', $current_page) ?>"><span class="material-symbols-outlined">science</span><span class="font-body-lg text-body-lg">Portofolio Tridharma</span></a>
            <a href="dosen_edom.php" class="<?= getLinkClass('dosen_edom.php', $current_page) ?>"><span class="material-symbols-outlined">star_rate</span><span class="font-body-lg text-body-lg">Evaluasi Dosen (EDoM)</span></a>
            <a href="dosen_statistik_kelas.php" class="<?= getLinkClass('dosen_statistik_kelas.php', $current_page) ?>"><span class="material-symbols-outlined">analytics</span><span class="font-body-lg text-body-lg">Statistik Kelas</span></a>
            <a href="dosen_chat.php" class="<?= getLinkClass('dosen_chat.php', $current_page) ?>"><span class="material-symbols-outlined">forum</span><span class="font-body-lg text-body-lg">Inbox</span></a>
        <?php elseif ($role === 'operator'): ?>
            <p class="px-4 text-[11px] font-bold text-[#4c7a67] uppercase tracking-wider mt-6 mb-3">Master Data</p>
            <a href="dashboard.php" class="<?= getLinkClass('dashboard.php', $current_page) ?>"><span class="material-symbols-outlined">dashboard</span><span class="font-body-lg text-body-lg">Dashboard</span></a>
            <a href="master_institusi.php" class="<?= getLinkClass('master_institusi.php', $current_page) ?>"><span class="material-symbols-outlined">account_balance</span><span class="font-body-lg text-body-lg">Data Institusi</span></a>
            <a href="master_mahasiswa.php" class="<?= getLinkClass('master_mahasiswa.php', $current_page) ?>"><span class="material-symbols-outlined">groups</span><span class="font-body-lg text-body-lg">Mahasiswa</span></a>
            <a href="master_dosen.php" class="<?= getLinkClass('master_dosen.php', $current_page) ?>"><span class="material-symbols-outlined">badge</span><span class="font-body-lg text-body-lg">Data Dosen</span></a>
            <a href="operator_portofolio.php" class="<?= getLinkClass('operator_portofolio.php', $current_page) ?>"><span class="material-symbols-outlined">library_books</span><span class="font-body-lg text-body-lg">Portofolio Tridharma</span></a>
            <a href="master_matakuliah.php" class="<?= getLinkClass('master_matakuliah.php', $current_page) ?>"><span class="material-symbols-outlined">library_books</span><span class="font-body-lg text-body-lg">Data Mata Kuliah</span></a>
            <p class="px-4 text-[11px] font-bold text-[#4c7a67] uppercase tracking-wider mt-4 mb-2">Akademik & Keuangan</p>
            <a href="operator_periode_krs.php" class="<?= getLinkClass('operator_periode_krs.php', $current_page) ?>"><span class="material-symbols-outlined">event_available</span><span class="font-body-lg text-body-lg">Periode KRS</span></a>
            <a href="operator_remedial.php" class="<?= getLinkClass('operator_remedial.php', $current_page) ?>"><span class="material-symbols-outlined">autorenew</span><span class="font-body-lg text-body-lg">Remedial & SP</span></a>
            <a href="operator_jadwal.php" class="<?= getLinkClass('operator_jadwal.php', $current_page) ?>"><span class="material-symbols-outlined">calendar_month</span><span class="font-body-lg text-body-lg">Jadwal Kuliah</span></a>
            <a href="operator_ujian.php" class="<?= getLinkClass('operator_ujian.php', $current_page) ?>"><span class="material-symbols-outlined">quiz</span><span class="font-body-lg text-body-lg">Jadwal Ujian</span></a>
            <a href="operator_kalender.php" class="<?= getLinkClass('operator_kalender.php', $current_page) ?>"><span class="material-symbols-outlined">event_note</span><span class="font-body-lg text-body-lg">Kalender Akademik</span></a>
            <a href="operator_perwalian.php" class="<?= getLinkClass('operator_perwalian.php', $current_page) ?>"><span class="material-symbols-outlined">supervisor_account</span><span class="font-body-lg text-body-lg">Penugasan Wali</span></a>
            <a href="operator_tagihan.php" class="<?= getLinkClass('operator_tagihan.php', $current_page) ?>"><span class="material-symbols-outlined">account_balance_wallet</span><span class="font-body-lg text-body-lg">Tagihan</span></a>
            <a href="operator_beasiswa.php" class="<?= getLinkClass('operator_beasiswa.php', $current_page) ?>"><span class="material-symbols-outlined">workspace_premium</span><span class="font-body-lg text-body-lg">Penerima Beasiswa</span></a>
            <p class="px-4 text-[11px] font-bold text-[#4c7a67] uppercase tracking-wider mt-4 mb-2">Komunikasi & Laporan</p>
            <a href="operator_pengumuman.php" class="<?= getLinkClass('operator_pengumuman.php', $current_page) ?>"><span class="material-symbols-outlined">campaign</span><span class="font-body-lg text-body-lg">Pengumuman</span></a>
            <a href="operator_layanan.php" class="<?= getLinkClass('operator_layanan.php', $current_page) ?>"><span class="material-symbols-outlined">mark_email_read</span><span class="font-body-lg text-body-lg">Layanan Surat</span></a>
            <a href="operator_yudisium.php" class="<?= getLinkClass('operator_yudisium.php', $current_page) ?>"><span class="material-symbols-outlined">school</span><span class="font-body-lg text-body-lg">Yudisium</span></a>
            <a href="operator_chat.php" class="<?= getLinkClass('operator_chat.php', $current_page) ?>"><span class="material-symbols-outlined">support_agent</span><span class="font-body-lg text-body-lg">Helpdesk</span></a>
            <a href="operator_statistik.php" class="<?= getLinkClass('operator_statistik.php', $current_page) ?>"><span class="material-symbols-outlined">analytics</span><span class="font-body-lg text-body-lg">Statistik Akademik</span></a>
            <a href="operator_log.php" class="<?= getLinkClass('operator_log.php', $current_page) ?>"><span class="material-symbols-outlined">history</span><span class="font-body-lg text-body-lg">Audit Log</span></a>
            <p class="px-4 text-[11px] font-bold text-[#4c7a67] uppercase tracking-wider mt-4 mb-2">Manajemen Sistem</p>
            <a href="master_operator.php" class="<?= getLinkClass('master_operator.php', $current_page) ?>"><span class="material-symbols-outlined">admin_panel_settings</span><span class="font-body-lg text-body-lg">Manajemen Operator</span></a>
        <?php endif; ?>
    </nav>
    <div class="mt-auto px-4 pb-8 pt-4 border-t border-outline-variant/30 dark:border-[#193a2e] space-y-1">
        <a href="change_password.php" class="flex items-center gap-3 px-4 py-3 text-on-surface-variant dark:text-[#8b9d97] hover:text-primary dark:hover:text-white hover:bg-primary/5 dark:hover:bg-white/5 rounded-xl transition-all font-medium">
            <span class="material-symbols-outlined text-[20px]">key</span><span class="text-[15px]">Ganti Password</span>
        </a>
        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 text-error dark:text-[#ffb4ab] hover:bg-error/10 dark:hover:bg-[#ffb4ab]/10 rounded-xl transition-all font-medium">
            <span class="material-symbols-outlined text-[20px]">logout</span><span class="text-[15px]">Logout</span>
        </a>
    </div>
</aside>

<header id="main-header" class="fixed top-0 right-0 left-72 h-20 z-30 glass-panel flex justify-between items-center px-margin-page border-b border-white/40 shadow-[0_8px_32px_0_rgba(134,210,177,0.15)] transition-all duration-300">
    <div class="flex items-center gap-stack-md">
        <button id="sidebar-toggle" class="p-2 mr-2 text-on-surface-variant hover:bg-primary-container/20 rounded-full transition-all focus:outline-none">
            <span class="material-symbols-outlined">menu</span>
        </button>
        <!-- Pill Shaped Search Bar -->
        <div class="relative w-64 md:w-96 lg:w-[400px] group/search">
            <div class="flex items-center w-full bg-white dark:bg-[#091f1a]/50 border border-outline-variant/30 focus-within:border-primary/50 focus-within:shadow-md rounded-full pl-4 pr-1 py-1 transition-all duration-300">
                <input id="global-search-input" class="flex-1 bg-transparent border-0 outline-none ring-0 shadow-none focus:border-0 focus:outline-none focus:ring-0 focus:shadow-none text-body-lg text-on-surface placeholder:text-on-surface-variant/50 w-full p-0 m-0" style="border: none !important; outline: none !important; box-shadow: none !important; background: transparent !important;" placeholder="Search..." type="text" autocomplete="off"/>
                <button class="w-9 h-9 rounded-full bg-gradient-to-br from-primary to-primary-fixed flex items-center justify-center shadow flex-shrink-0 hover:scale-105 transition-transform" aria-label="Search">
                    <span class="material-symbols-outlined text-white text-[20px]">search</span>
                </button>
            </div>
            
            <!-- Search Results Dropdown -->
            <div id="search-dropdown" class="absolute top-full left-0 w-full mt-2 bg-surface dark:bg-[#0f2e26] border border-outline-variant/30 rounded-2xl shadow-xl opacity-0 invisible transition-all duration-200 z-50 max-h-60 overflow-y-auto custom-scrollbar">
                <ul id="search-results-list" class="py-2"></ul>
            </div>
        </div>
    </div>
    <div class="flex items-center gap-stack-md">
        <button id="theme-toggle" class="relative p-2 text-on-surface-variant hover:bg-primary-container/20 rounded-full transition-all focus:outline-none">
            <span class="material-symbols-outlined">dark_mode</span>
        </button>
        <div class="relative group/notif">
            <button class="relative p-2 text-on-surface-variant hover:bg-primary-container/20 rounded-full transition-all focus:outline-none cursor-pointer">
                <span class="material-symbols-outlined">notifications</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="absolute top-1 right-1 w-2.5 h-2.5 bg-error rounded-full border-2 border-white dark:border-[#091f1a]"></span>
                <?php endif; ?>
            </button>
            
            <!-- Dropdown Content -->
            <div class="absolute right-0 mt-2 w-80 bg-surface dark:bg-[#0f2e26] border border-outline-variant/30 rounded-2xl shadow-xl opacity-0 invisible group-hover/notif:opacity-100 group-hover/notif:visible transition-all duration-200 z-50 transform origin-top-right scale-95 group-hover/notif:scale-100">
                <div class="p-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low/50 rounded-t-2xl">
                    <h3 class="font-title-md font-semibold text-on-surface">Notifikasi</h3>
                    <?php if ($unreadCount > 0): ?>
                        <span class="text-xs bg-error-container text-on-error-container px-2 py-0.5 rounded-full font-medium"><?= $unreadCount ?> Baru</span>
                    <?php endif; ?>
                </div>
                
                <div class="max-h-80 overflow-y-auto custom-scrollbar">
                    <?php if (empty($notifications)): ?>
                        <div class="p-6 text-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-4xl opacity-50 mb-2">notifications_paused</span>
                            <p class="font-body-sm">Belum ada notifikasi baru.</p>
                        </div>
                    <?php else: ?>
                        <div class="flex flex-col">
                            <?php foreach ($notifications as $notif): ?>
                                <a href="<?= htmlspecialchars($notif['link']) ?>" class="p-4 hover:bg-on-surface/5 transition-colors border-b border-outline-variant/10 flex gap-3 items-start group/item">
                                    <div class="w-10 h-10 rounded-full bg-primary-container/30 flex items-center justify-center text-primary flex-shrink-0 group-hover/item:scale-110 transition-transform">
                                        <span class="material-symbols-outlined text-[20px]"><?= htmlspecialchars($notif['ikon']) ?></span>
                                    </div>
                                    <div>
                                        <p class="font-title-sm text-on-surface line-clamp-2 leading-snug"><?= htmlspecialchars($notif['judul']) ?></p>
                                        <p class="font-body-sm text-on-surface-variant opacity-80 mt-1"><?= date('d M, H:i', strtotime($notif['waktu'])) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="p-3 text-center border-t border-outline-variant/30 bg-surface-container-lowest rounded-b-2xl">
                    <button onclick="tandaiSemuaDibaca()" class="font-label-md text-primary hover:underline">Tandai semua dibaca</button>
                </div>
            </div>
        </div>
        <?php 
            $profileLink = '#';
            if ($role === 'mahasiswa') {
                $profileLink = 'mahasiswa_profile.php';
            } elseif ($role === 'dosen') {
                $profileLink = 'dosen_profile.php';
            }
        ?>
        <?php if ($profileLink !== '#'): ?>
        <a href="<?= $profileLink ?>" class="flex items-center gap-stack-sm border-l border-outline-variant/30 pl-stack-md hover:bg-primary-container/10 p-2 rounded-2xl transition-all cursor-pointer group">
        <?php else: ?>
        <div class="flex items-center gap-stack-sm border-l border-outline-variant/30 pl-stack-md">
        <?php endif; ?>
            <div class="text-right mr-1">
                <p class="font-title-lg text-title-lg text-primary group-hover:text-primary-hover transition-colors"><?= htmlspecialchars($username) ?></p>
                <p class="font-label-md text-label-md text-on-surface-variant uppercase"><?= htmlspecialchars($role) ?></p>
            </div>
            
            <?php 
            $fotoUrl = 'https://ui-avatars.com/api/?name=' . urlencode($username) . '&background=random';
            
            if (isset($_SESSION['user_id'])) {
                if ($role === 'mahasiswa' || $role === 'dosen') {
                    $pdo = \Config\Database::getConnection();
                    $table = $role === 'mahasiswa' ? 'mahasiswa' : 'dosen';
                    $stmt = $pdo->prepare("SELECT foto, nama FROM $table WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    if ($userRow = $stmt->fetch()) {
                        if (!empty($userRow['foto']) && $userRow['foto'] !== 'assets/default_mhs.png') {
                            if (strpos($userRow['foto'], '/') === false) {
                                $fotoUrl = 'https://ui-avatars.com/api/?name=' . urlencode($userRow['nama']) . '&background=random';
                            } else {
                                $fotoUrl = htmlspecialchars($userRow['foto']);
                            }
                        }
                    }
                } else if (isset($_SESSION['foto']) && !empty($_SESSION['foto'])) {
                    $fotoUrl = htmlspecialchars($_SESSION['foto']);
                }
            }
            ?>
            
            <div class="w-12 h-12 rounded-full overflow-hidden border-2 border-primary-container <?php if ($profileLink !== '#') echo 'group-hover:ring-4 group-hover:ring-primary/20 transition-all'; ?>">
                <img src="<?= $fotoUrl ?>" alt="Profile" class="w-full h-full object-cover">
            </div>
        <?php if ($profileLink !== '#'): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<main id="main-content" class="ml-72 pt-28 min-h-screen px-margin-page pb-stack-xl max-w-container-max mx-auto relative transition-all duration-300">

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const themeToggleBtn = document.getElementById('theme-toggle');
        const themeIcon = themeToggleBtn ? themeToggleBtn.querySelector('.material-symbols-outlined') : null;
        
        function updateThemeIcon() {
            if (!themeIcon) return;
            if (document.documentElement.classList.contains('dark')) {
                themeIcon.textContent = 'light_mode';
            } else {
                themeIcon.textContent = 'dark_mode';
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

        const sidebarToggleBtn = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const mainHeader = document.getElementById('main-header');
        const mainContent = document.getElementById('main-content');

        if (sidebarToggleBtn && sidebar && mainHeader && mainContent) {
            sidebarToggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('-translate-x-full');
                if (sidebar.classList.contains('-translate-x-full')) {
                    mainHeader.classList.remove('left-72');
                    mainHeader.classList.add('left-0');
                    mainContent.classList.remove('ml-72');
                    mainContent.classList.add('ml-0');
                } else {
                    mainHeader.classList.add('left-72');
                    mainHeader.classList.remove('left-0');
                    mainContent.classList.add('ml-72');
                    mainContent.classList.remove('ml-0');
                }
            });
        }

        // Global Search Logic
        const searchInput = document.getElementById('global-search-input');
        const searchDropdown = document.getElementById('search-dropdown');
        const searchResultsList = document.getElementById('search-results-list');
        
        if (searchInput && searchDropdown && searchResultsList) {
            const searchIndex = [];
            document.querySelectorAll('#sidebar a').forEach(link => {
                const iconEl = link.querySelector('.material-symbols-outlined');
                const textEl = link.querySelector('span:not(.material-symbols-outlined)');
                
                if (textEl && iconEl) {
                    searchIndex.push({
                        title: textEl.innerText.trim(),
                        href: link.getAttribute('href'),
                        icon: iconEl.innerText.trim()
                    });
                }
            });

            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                searchResultsList.innerHTML = '';
                
                if (query.length > 0) {
                    const results = searchIndex.filter(item => item.title.toLowerCase().includes(query));
                    
                    if (results.length > 0) {
                        results.forEach(item => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <a href="${item.href}" class="flex items-center gap-3 px-4 py-2 hover:bg-primary/10 transition-colors cursor-pointer text-on-surface">
                                    <span class="material-symbols-outlined text-on-surface-variant text-[20px]">${item.icon}</span>
                                    <span class="font-medium">${item.title}</span>
                                </a>
                            `;
                            searchResultsList.appendChild(li);
                        });
                    } else {
                        searchResultsList.innerHTML = `
                            <li class="px-4 py-3 text-center text-on-surface-variant text-sm">
                                Tidak ada menu yang cocok
                            </li>
                        `;
                    }
                    
                    searchDropdown.classList.remove('opacity-0', 'invisible');
                } else {
                    searchDropdown.classList.add('opacity-0', 'invisible');
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !searchDropdown.contains(e.target)) {
                    searchDropdown.classList.add('opacity-0', 'invisible');
                }
            });
            
            searchInput.addEventListener('focus', function() {
                if (this.value.trim().length > 0) {
                    searchDropdown.classList.remove('opacity-0', 'invisible');
                }
            });
        }
    });

    // Tandai semua notifikasi sebagai dibaca
    function tandaiSemuaDibaca() {
        fetch('notif_mark_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'csrf_token=<?= isset($csrfToken) ? htmlspecialchars($csrfToken) : (isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : '') ?>&action=mark_all_read'
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // Remove the notification badge dot
                const badge = document.querySelector('.absolute.top-1.right-1.w-2\\.5');
                if (badge) badge.remove();
                // Show brief confirmation
                const btn = event.target;
                btn.textContent = '✓ Semua dibaca';
                btn.classList.add('text-success');
                setTimeout(() => {
                    btn.textContent = 'Tandai semua dibaca';
                    btn.classList.remove('text-success');
                }, 2000);
            }
        })
        .catch(() => {
            // Silently fail — notification system is secondary
        });
    }
</script>