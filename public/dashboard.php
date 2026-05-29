<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::requireOperator();

$db = \Config\Database::getConnection();
$stmt = $db->query("SELECT * FROM lupa_sandi_requests WHERE status = 'pending' ORDER BY created_at ASC");
$reset_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['dismiss_alert_ajax'])) {
    $hash = $_GET['dismiss_alert_ajax'];
    if (!isset($_SESSION['closed_alerts'])) {
        $_SESSION['closed_alerts'] = [];
    }
    if (!in_array($hash, $_SESSION['closed_alerts'])) {
        $_SESSION['closed_alerts'][] = $hash;
    }
    exit;
}

// Fetch Quick Stats
$stmtTotalMhs = $db->query("SELECT COUNT(*) FROM mahasiswa");
$totalMhs = $stmtTotalMhs->fetchColumn();

$stmtTagihan = $db->query("SELECT COUNT(*) FROM tagihan_pembayaran WHERE status = 'Belum Lunas'");
$totalTagihanBelumLunas = $stmtTagihan->fetchColumn();

$stmtPengumuman = $db->query("SELECT COUNT(*) FROM pengumuman");
$totalPengumuman = $stmtPengumuman->fetchColumn();

// Ambil Semester Aktif secara Dinamis
$stmtSmt = $db->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn();
if (!$semesterAktif) {
    // Fallback: semester terakhir yang ada
    $stmtSmt2 = $db->query("SELECT semester FROM periode_krs ORDER BY id DESC LIMIT 1");
    $semesterAktif = $stmtSmt2->fetchColumn() ?: 'Ganjil';
}

// Fetch Statistik Program Studi
$stmtProdi = $db->query("SELECT program_studi, COUNT(*) as total FROM mahasiswa GROUP BY program_studi");
$prodiStats = $stmtProdi->fetchAll(PDO::FETCH_ASSOC);

$totalMhsStats = array_sum(array_column($prodiStats, 'total'));
$maxProdi = '-';
$maxProdiCount = 0;
foreach ($prodiStats as $p) {
    if ($p['total'] > $maxProdiCount) {
        $maxProdiCount = $p['total'];
        $maxProdi = $p['program_studi'] ?: 'Tidak Diketahui';
    }
}

// Fetch Statistik Pembayaran UKT
$stmtUkt = $db->query("SELECT status, COUNT(*) as total FROM tagihan_pembayaran GROUP BY status");
$uktStats = $stmtUkt->fetchAll(PDO::FETCH_ASSOC);

$lunasCount = 0;
$belumLunasCount = 0;
foreach ($uktStats as $u) {
    if ($u['status'] === 'Lunas') $lunasCount = (int)$u['total'];
    if ($u['status'] === 'Belum Lunas') $belumLunasCount = (int)$u['total'];
}
$totalUktStats = $lunasCount + $belumLunasCount;
$persenLunas = $totalUktStats > 0 ? round(($lunasCount / $totalUktStats) * 100, 1) : 0;

// Status KRS Mahasiswa (semester aktif)
$stmtKrs = $db->prepare("
    SELECT 
        COUNT(*) as total, 
        SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu
    FROM (SELECT DISTINCT mahasiswa_id, status FROM krs WHERE semester_aktif = ?) k
");
$stmtKrs->execute([$semesterAktif]);
$krsStats = $stmtKrs->fetch(PDO::FETCH_ASSOC);
$krsTotalMhs = $totalMhs;
$krsSudah = (int)($krsStats['total'] ?? 0);
$krsDisetujui = (int)($krsStats['disetujui'] ?? 0);
$krsMenunggu = (int)($krsStats['menunggu'] ?? 0);
$krsBelum = max(0, $krsTotalMhs - $krsSudah);

// Status Input Nilai Dosen (semester aktif)
$stmtNilai = $db->prepare("
    SELECT 
        COUNT(DISTINCT dosen_id) as total_dosen,
        SUM(CASE WHEN nilai_huruf IS NOT NULL THEN 1 ELSE 0 END) as sudah_dinilai,
        COUNT(id) as total_krs
    FROM krs WHERE semester_aktif = ? AND status = 'Disetujui'
");
$stmtNilai->execute([$semesterAktif]);
$nilaiStats = $stmtNilai->fetch(PDO::FETCH_ASSOC);

// Mahasiswa Bermasalah (UKT Belum Lunas)
$stmtMhsMasalah = $db->query("
    SELECT m.nim, m.nama, t.status 
    FROM tagihan_pembayaran t
    JOIN mahasiswa m ON t.mahasiswa_id = m.id
    WHERE t.status = 'Belum Lunas'
    LIMIT 3
");
$mhsMasalah = $stmtMhsMasalah->fetchAll(PDO::FETCH_ASSOC);

// Fetch Audit Log
try {
    $stmtAudit = $db->query("SELECT a.*, u.username, u.role FROM activity_log a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 5");
    $auditLogs = $stmtAudit->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    $auditLogs = [];
}

// Tindakan Diperlukan (Hanya data nyata - reset sandi pending)
$tindakanList = [];
foreach($reset_requests as $req) {
    $tindakanList[] = [
        'id'         => 'req-' . $req['id'],
        'waktu'      => $req['created_at'],
        'identitas'  => $req['nomor_induk'],
        'jenis'      => 'Reset Sandi',
        'keterangan' => $req['catatan'] ?: 'Lupa sandi',
        'prioritas'  => 'Tinggi',
        'is_reset'   => true,
        'real_id'    => $req['id']
    ];
}

// KRS yang sudah lebih dari 3 hari menunggu persetujuan (Tindakan nyata)
try {
    $stmtKrsPending = $db->prepare("
        SELECT m.nim, m.nama, COUNT(k.id) as jumlah_mk, MIN(k.created_at) as waktu_pertama
        FROM krs k
        JOIN mahasiswa m ON k.mahasiswa_id = m.id
        WHERE k.status = 'Menunggu' AND k.semester_aktif = ?
        GROUP BY k.mahasiswa_id, m.nim, m.nama
        HAVING DATEDIFF(NOW(), MIN(k.created_at)) >= 2
        ORDER BY waktu_pertama ASC
        LIMIT 5
    ");
    $stmtKrsPending->execute([$semesterAktif]);
    foreach ($stmtKrsPending->fetchAll(PDO::FETCH_ASSOC) as $kp) {
        $tindakanList[] = [
            'id'         => 'krs-' . $kp['nim'],
            'waktu'      => $kp['waktu_pertama'],
            'identitas'  => $kp['nim'] . ' - ' . $kp['nama'],
            'jenis'      => 'KRS Pending',
            'keterangan' => $kp['jumlah_mk'] . ' MK belum disetujui dosen wali',
            'prioritas'  => 'Sedang',
            'is_reset'   => false
        ];
    }
} catch (\PDOException $e) {
    // Abaikan jika tabel belum ada kolom created_at
}

// Statistik Akademik Prodi dari Database
try {
    $bobotCase = "CASE k.nilai_huruf WHEN 'A' THEN 4 WHEN 'B' THEN 3 WHEN 'C' THEN 2 WHEN 'D' THEN 1 ELSE 0 END";
    $stmtProdiAkademik = $db->query("
        SELECT 
            m.program_studi as prodi,
            ROUND(SUM(mk.sks * ($bobotCase)) / NULLIF(SUM(CASE WHEN k.nilai_huruf IS NOT NULL THEN mk.sks ELSE 0 END), 0), 2) as ipk
        FROM mahasiswa m
        LEFT JOIN krs k ON k.mahasiswa_id = m.id AND k.nilai_huruf IS NOT NULL
        LEFT JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
        WHERE m.program_studi IS NOT NULL AND m.program_studi != ''
        GROUP BY m.program_studi
        ORDER BY m.program_studi ASC
        LIMIT 10
    ");
    $akademikProdiStats = $stmtProdiAkademik->fetchAll(PDO::FETCH_ASSOC);
    // Tambahkan field sp (studi panjang) - jumlah mahasiswa semester > 8
    foreach ($akademikProdiStats as &$ap) {
        $stmtSP = $db->prepare("SELECT COUNT(*) FROM mahasiswa WHERE program_studi = ? AND semester > 8");
        $stmtSP->execute([$ap['prodi']]);
        $ap['sp'] = (int)$stmtSP->fetchColumn();
        $ap['tepat_waktu'] = 0; // Tidak bisa dihitung tanpa data historis kelulusan
    }
    unset($ap);
} catch (\PDOException $e) {
    $akademikProdiStats = [];
}

// Alerts
$alerts = [];
if (count($reset_requests) > 0) {
    $alerts[] = ['type' => 'error', 'icon' => 'key', 'text' => count($reset_requests) . ' pengajuan reset sandi menunggu tindakan.'];
}
if ($krsMenunggu > 0) {
    $alerts[] = ['type' => 'warning', 'icon' => 'pending_actions', 'text' => "$krsMenunggu mahasiswa menunggu persetujuan KRS oleh Dosen Wali."];
}

// Filter closed alerts
if (isset($_SESSION['closed_alerts'])) {
    $alerts = array_filter($alerts, function($al) {
        return !in_array(md5($al['text']), $_SESSION['closed_alerts']);
    });
}

$title = "Dashboard Operator - SAQUNA";
include 'components/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- Welcome Card (Span 12) -->
    <section class="lg:col-span-12">
        <div class="glass-panel rounded-3xl p-stack-lg relative overflow-hidden shadow-sm h-full flex flex-col justify-between group bg-primary text-on-primary border-none">
            <div class="absolute top-0 right-0 w-96 h-96 bg-primary-fixed/20 rounded-full -mr-20 -mt-20 blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-stack-md">
                <div>
                    <span class="bg-primary-container text-on-primary-container px-4 py-1 rounded-full font-label-md text-label-md mb-stack-sm inline-block">Super Admin</span>
                    <h2 class="font-display-lg text-display-lg mt-stack-xs text-white">Dashboard Operator</h2>
                    <p class="font-body-lg text-body-lg text-primary-fixed max-w-xl mt-stack-sm">Akses penuh untuk mengelola master data, validasi pembayaran, dan konfigurasi sistem SAQUNA.</p>
                </div>
                <div class="w-32 h-32 bg-primary-container/20 rounded-full flex items-center justify-center backdrop-blur-md border border-white/20">
                    <span class="material-symbols-outlined text-white text-6xl">admin_panel_settings</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ALERTS -->
    <?php if(!empty($alerts)): ?>
    <section class="lg:col-span-12 mt-stack-sm space-y-2" id="alerts-section">
        <?php foreach($alerts as $al): 
            $alertHash = md5($al['text']);
            $bg = $al['type'] === 'error' ? 'bg-error-container text-on-error-container border-error/30' : 
                  ($al['type'] === 'warning' ? 'bg-tertiary-container text-on-tertiary-container border-tertiary/30' : 'bg-secondary-container text-on-secondary-container border-secondary/30');
        ?>
        <div class="p-4 rounded-2xl border <?= $bg ?> flex items-start gap-3 shadow-sm relative transition-all duration-300 alert-item" id="alert-<?= $alertHash ?>">
            <span class="material-symbols-outlined mt-0.5 flex-shrink-0"><?= htmlspecialchars($al['icon']) ?></span>
            <span class="font-label-md flex-1 pr-6"><?= htmlspecialchars($al['text']) ?></span>
            <button type="button" class="absolute top-4 right-4 text-inherit opacity-70 hover:opacity-100 transition-opacity" onclick="dismissAlert('<?= $alertHash ?>')">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- NEW DASHBOARD WIDGETS (Row 2) -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-stack-md">
            
            <!-- Periode Akademik -->
            <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-primary">calendar_month</span>
                        <h3 class="font-headline-sm text-primary text-sm font-bold">Periode Akademik</h3>
                    </div>
                    <p class="font-display-md text-primary text-2xl font-bold mt-2"><?= htmlspecialchars($semesterAktif) ?></p>
                    <p class="font-label-sm text-on-surface-variant text-[10px] uppercase mt-1">Sedang Berjalan</p>
                </div>
                <div class="mt-4 pt-3 border-t border-outline-variant/30 text-xs text-on-surface-variant space-y-2">
                    <div class="flex items-center gap-2 text-success"><span class="material-symbols-outlined text-[14px]">check_circle</span> <span class="line-through opacity-70">Her-registrasi</span></div>
                    <div class="flex items-center gap-2 text-success"><span class="material-symbols-outlined text-[14px]">check_circle</span> <span class="line-through opacity-70">Pengisian KRS</span></div>
                    <div class="flex items-center gap-2 text-primary font-bold"><span class="material-symbols-outlined text-[14px]">radio_button_checked</span> Perkuliahan (Mg ke-14)</div>
                    <div class="flex items-center gap-2 opacity-50"><span class="material-symbols-outlined text-[14px]">radio_button_unchecked</span> Ujian Akhir Semester</div>
                </div>
            </div>

            <!-- Status KRS -->
            <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-secondary">assignment_ind</span>
                        <h3 class="font-headline-sm text-secondary text-sm font-bold">Pengisian KRS</h3>
                    </div>
                    <div class="flex justify-between items-end mt-2">
                        <div>
                            <p class="font-display-md text-secondary text-2xl font-bold"><?= $krsDisetujui ?></p>
                            <p class="font-label-sm text-on-surface-variant text-[10px] uppercase mt-1">Disetujui</p>
                        </div>
                        <div class="text-right">
                            <p class="font-title-md text-tertiary font-bold"><?= $krsMenunggu ?></p>
                            <p class="font-label-sm text-on-surface-variant text-[10px] uppercase">Menunggu</p>
                        </div>
                    </div>
                </div>
                <div class="w-full bg-surface-variant/30 rounded-full h-1.5 mt-4">
                    <div class="bg-secondary h-1.5 rounded-full" style="width: <?= $krsTotalMhs > 0 ? round(($krsDisetujui/$krsTotalMhs)*100) : 0 ?>%"></div>
                </div>
            </div>

            <!-- Status Input Nilai -->
            <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-tertiary">grading</span>
                        <h3 class="font-headline-sm text-tertiary text-sm font-bold">Input Nilai Dosen</h3>
                    </div>
                    <div class="flex justify-between items-end mt-2">
                        <div>
                            <p class="font-display-md text-tertiary text-2xl font-bold"><?= $nilaiStats['total_krs'] > 0 ? round(($nilaiStats['sudah_dinilai']/$nilaiStats['total_krs'])*100) : 0 ?>%</p>
                            <p class="font-label-sm text-on-surface-variant text-[10px] uppercase mt-1">Progress Keseluruhan</p>
                        </div>
                    </div>
                </div>
                <div class="w-full bg-surface-variant/30 rounded-full h-1.5 mt-4">
                    <div class="bg-tertiary h-1.5 rounded-full" style="width: <?= $nilaiStats['total_krs'] > 0 ? round(($nilaiStats['sudah_dinilai']/$nilaiStats['total_krs'])*100) : 0 ?>%"></div>
                </div>
            </div>

            <!-- Mahasiswa Bermasalah -->
            <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex flex-col justify-between">
                <div>
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-error">warning</span>
                        <h3 class="font-headline-sm text-error text-sm font-bold">Perlu Perhatian</h3>
                    </div>
                    <div class="mt-2 space-y-2">
                        <?php if(empty($mhsMasalah)): ?>
                            <p class="text-xs text-on-surface-variant mt-2">Tidak ada isu mendesak.</p>
                        <?php else: ?>
                            <?php foreach($mhsMasalah as $mm): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-on-surface line-clamp-1 flex-1 pr-1"><?= htmlspecialchars($mm['nama']) ?></span>
                                <span class="text-[9px] font-bold bg-error/10 text-error px-1.5 py-0.5 rounded uppercase border border-error/20">UKT</span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="operator_tagihan.php" class="text-[10px] text-error font-bold mt-3 hover:underline">Lihat Semua &rarr;</a>
            </div>

        </div>
    </section>

    <!-- Quick Stats -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-stack-md">
            <a href="master_mahasiswa.php" class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex items-center gap-stack-md hover:bg-white/40 transition-colors group cursor-pointer" style="text-decoration: none;">
                <div class="w-16 h-16 rounded-2xl bg-secondary-container/50 flex items-center justify-center text-secondary group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-3xl">groups</span>
                </div>
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase">Total Mahasiswa</p>
                    <p class="font-headline-lg text-headline-lg text-primary"><?= number_format((float)$totalMhs) ?> <span class="text-sm font-normal">Aktif</span></p>
                    <p class="font-body-sm text-on-surface-variant text-[10px] mt-1">12 Cuti • 4 DO/SP</p>
                </div>
            </a>
            
            <a href="operator_tagihan.php" class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex items-center gap-stack-md hover:bg-white/40 transition-colors group cursor-pointer" style="text-decoration: none;">
                <div class="w-16 h-16 rounded-2xl bg-tertiary-container/50 flex items-center justify-center text-tertiary group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-3xl">account_balance_wallet</span>
                </div>
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase">Tagihan UKT Belum Lunas</p>
                    <p class="font-headline-lg text-headline-lg text-primary"><?= number_format((float)$totalTagihanBelumLunas) ?> <span class="text-sm font-normal">Tagihan</span></p>
                </div>
            </a>
            
            <a href="operator_pengumuman.php" class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex items-center gap-stack-md hover:bg-white/40 transition-colors group cursor-pointer" style="text-decoration: none;">
                <div class="w-16 h-16 rounded-2xl bg-error-container/50 flex items-center justify-center text-error group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-3xl">campaign</span>
                </div>
                <div>
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase">Total Pengumuman</p>
                    <p class="font-headline-lg text-headline-lg text-primary"><?= number_format((float)$totalPengumuman) ?> <span class="text-sm font-normal">Info</span></p>
                </div>
            </a>
        </div>
    </section>

    <!-- Statistics Charts -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
            <!-- Chart 1: Mahasiswa per Prodi -->
            <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-headline-md text-headline-md text-primary">Sebaran Mahasiswa</h3>
                        <p class="font-body-sm text-on-surface-variant">Berdasarkan program studi terdaftar</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-primary-container/30 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[20px]">pie_chart</span>
                    </div>
                </div>
                <div class="h-64 w-full relative flex-1">
                    <canvas id="chartProdi"></canvas>
                </div>
                <div class="mt-4 pt-4 border-t border-outline-variant/30 text-center">
                    <p class="font-body-md text-on-surface-variant">
                        Sebagian besar mahasiswa mengambil program studi <strong class="text-primary"><?= htmlspecialchars($maxProdi) ?></strong> dengan jumlah <strong class="text-primary"><?= $maxProdiCount ?></strong> mahasiswa.
                    </p>
                </div>
            </div>
            
            <!-- Chart 2: Status UKT -->
            <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-headline-md text-headline-md text-secondary">Status Pembayaran UKT</h3>
                        <p class="font-body-sm text-on-surface-variant">Rasio pelunasan tagihan aktif</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-secondary-container/30 flex items-center justify-center text-secondary">
                        <span class="material-symbols-outlined text-[20px]">donut_large</span>
                    </div>
                </div>
                <div class="h-64 w-full relative flex-1">
                    <canvas id="chartUkt"></canvas>
                </div>
                <div class="mt-4 pt-4 border-t border-outline-variant/30 text-center">
                    <p class="font-body-md text-on-surface-variant">
                        Terdapat <strong class="text-secondary"><?= $persenLunas ?>%</strong> mahasiswa (<?= $lunasCount ?> orang) yang sudah <strong class="text-secondary">LUNAS</strong>, dan <strong class="text-error"><?= $belumLunasCount ?></strong> tagihan masih tertunggak.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Pending Task Requests / Tindakan Diperlukan -->
    <?php if (count($tindakanList) > 0): ?>
    <section class="lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-error/40 bg-error-container/10">
            <div class="flex justify-between items-center mb-stack-md">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-error text-3xl">notification_important</span>
                    <h3 class="font-headline-md text-headline-md text-error">Tindakan Diperlukan</h3>
                </div>
                <span class="bg-error text-white px-3 py-1 rounded-full font-label-md"><?= count($tindakanList) ?> Menunggu</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-on-surface-variant font-label-md">
                            <th class="p-3">Waktu</th>
                            <th class="p-3">Jenis Permintaan</th>
                            <th class="p-3">Identitas Pengguna</th>
                            <th class="p-3">Catatan / Detail</th>
                            <th class="p-3 text-center">Prioritas</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tindakanList as $req): 
                            $badgeColor = $req['prioritas'] === 'Tinggi' ? 'bg-error/20 text-error border-error/30' : 
                                         ($req['prioritas'] === 'Sedang' ? 'bg-warning/20 text-warning-dark border-warning/30' : 'bg-success/20 text-success border-success/30');
                        ?>
                        <tr class="border-b border-outline-variant/10 hover:bg-white/5 transition-colors" id="<?= $req['id'] ?>">
                            <td class="p-3 font-body-sm text-on-surface-variant"><?= date('d M H:i', strtotime($req['waktu'])) ?></td>
                            <td class="p-3 font-bold text-primary"><?= htmlspecialchars($req['jenis']) ?></td>
                            <td class="p-3 font-body-sm text-on-surface-variant"><?= htmlspecialchars($req['identitas']) ?></td>
                            <td class="p-3 font-body-sm text-on-surface-variant italic"><?= htmlspecialchars($req['keterangan'] ?: '-') ?></td>
                            <td class="p-3 text-center">
                                <span class="px-2 py-0.5 border rounded text-[10px] uppercase font-bold <?= $badgeColor ?>"><?= $req['prioritas'] ?></span>
                            </td>
                            <td class="p-3 text-right">
                                <?php if($req['is_reset']): ?>
                                <button onclick="markAsDone(<?= $req['real_id'] ?>, '<?= $req['id'] ?>')" class="bg-primary hover:bg-primary-hover text-white px-4 py-2 rounded-lg font-label-md transition-colors flex items-center gap-2 ml-auto border-none cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">check</span> Selesai
                                </button>
                                <?php else: 
                                    $link = '#';
                                    if ($req['jenis'] === 'KRS Pending') $link = 'operator_perwalian.php';
                                    elseif ($req['jenis'] === 'Beasiswa') $link = 'operator_beasiswa.php';
                                    elseif ($req['jenis'] === 'Surat') $link = 'operator_layanan.php';
                                ?>
                                <a href="<?= $link ?>" style="text-decoration: none;" class="bg-surface-variant hover:bg-outline-variant/40 text-on-surface px-4 py-2 rounded-lg font-label-md transition-colors inline-flex items-center gap-2 ml-auto border-none cursor-pointer">
                                    <span class="material-symbols-outlined text-sm">visibility</span> Detail
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Statistik Akademik Prodi -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <div class="flex justify-between items-center mb-stack-sm">
                <div>
                    <h3 class="font-headline-md text-headline-md text-primary">Statistik Akademik Program Studi</h3>
                    <p class="font-body-sm text-on-surface-variant mt-1">Perbandingan rata-rata IPK dan tingkat kelulusan tepat waktu.</p>
                </div>
            </div>
            
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-on-surface-variant font-label-md">
                            <th class="p-3">Program Studi</th>
                            <th class="p-3 text-center">Rata-rata IPK</th>
                            <th class="p-3 text-center">Lulus Tepat Waktu</th>
                            <th class="p-3 text-center">Mahasiswa SP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($akademikProdiStats as $stat): ?>
                        <tr class="border-b border-outline-variant/10 hover:bg-white/5 transition-colors">
                            <td class="p-3 font-title-sm text-on-surface font-bold"><?= htmlspecialchars($stat['prodi']) ?></td>
                            <td class="p-3 text-center font-bold text-primary"><?= number_format($stat['ipk'], 2) ?></td>
                            <td class="p-3 text-center">
                                <span class="bg-success/10 text-success px-2 py-1 rounded text-xs font-bold"><?= $stat['tepat_waktu'] ?>%</span>
                            </td>
                            <td class="p-3 text-center font-body-sm text-error font-bold"><?= $stat['sp'] ?> Orang</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Audit Log / Aktivitas Sistem -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <div class="flex justify-between items-center mb-stack-sm">
                <div>
                    <h3 class="font-headline-md text-headline-md text-primary">Aktivitas Sistem Terbaru</h3>
                    <p class="font-body-sm text-on-surface-variant mt-1">Log audit *real-time* dari berbagai aktor di dalam sistem.</p>
                </div>
                <a href="operator_log.php" class="text-sm font-bold text-primary hover:underline">Lihat Semua Log &rarr;</a>
            </div>
            
            <div class="space-y-3 mt-4">
                <?php if (empty($auditLogs)): ?>
                    <p class="text-on-surface-variant text-sm italic">Belum ada catatan aktivitas.</p>
                <?php else: ?>
                    <?php foreach ($auditLogs as $log): 
                        $icon = 'history';
                        $color = 'text-primary';
                        if (strpos(strtolower($log['aksi']), 'delete') !== false || strpos(strtolower($log['aksi']), 'hapus') !== false) {
                            $icon = 'delete'; $color = 'text-error';
                        } elseif (strpos(strtolower($log['aksi']), 'update') !== false || strpos(strtolower($log['aksi']), 'ubah') !== false) {
                            $icon = 'edit'; $color = 'text-warning-dark';
                        } elseif (strpos(strtolower($log['aksi']), 'create') !== false || strpos(strtolower($log['aksi']), 'tambah') !== false) {
                            $icon = 'add_circle'; $color = 'text-success';
                        } elseif (strpos(strtolower($log['aksi']), 'login') !== false) {
                            $icon = 'login'; $color = 'text-secondary';
                        }
                    ?>
                    <div class="flex items-start gap-4 p-3 rounded-xl hover:bg-surface-variant/30 transition-colors border border-transparent hover:border-outline-variant/20">
                        <div class="w-10 h-10 rounded-full bg-surface-variant flex items-center justify-center flex-shrink-0 <?= $color ?>">
                            <span class="material-symbols-outlined text-xl"><?= $icon ?></span>
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <p class="font-title-sm text-on-surface font-bold">
                                    <span class="capitalize text-primary"><?= htmlspecialchars($log['username'] ?? 'Sistem') ?></span> 
                                    <span class="font-normal text-on-surface-variant">melakukan aksi</span> 
                                    <span class="uppercase tracking-wider text-[10px] border px-1 py-0.5 mx-1 rounded"><?= htmlspecialchars($log['aksi']) ?></span> 
                                    <span class="font-normal text-on-surface-variant">pada</span> <?= htmlspecialchars($log['entitas']) ?>
                                </p>
                                <span class="text-[10px] text-on-surface-variant flex-shrink-0"><?= date('H:i, d M Y', strtotime($log['created_at'])) ?></span>
                            </div>
                            <p class="font-body-sm text-on-surface-variant mt-1"><?= htmlspecialchars($log['keterangan'] ?? '-') ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

</div>

<?php include 'components/footer.php'; ?>

<script src="assets/js/chart.min.js"></script>
<script>
function markAsDone(id, htmlId) {
    if (!confirm('Apakah Anda yakin sudah memproses pengajuan ini dan ingin menandai sebagai selesai?')) return;
    
    const formData = new FormData();
    formData.append('request_id', id);
    formData.append('status', 'selesai');
    formData.append('csrf_token', '<?= Auth::generateCsrf() ?>');
    
    fetch('operator_update_sandi_status.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(htmlId);
            if(row) {
                row.style.opacity = '0';
                row.style.transition = 'opacity 0.3s ease';
                setTimeout(() => row.remove(), 300);
            }
        } else {
            alert(data.message || 'Gagal memperbarui status.');
        }
    })
    .catch(err => {
        alert('Terjadi kesalahan jaringan.');
    });
}

// Inisialisasi Chart
document.addEventListener("DOMContentLoaded", function() {
    const prodiData = <?= json_encode($prodiStats) ?>;
    const uktData = <?= json_encode($uktStats) ?>;

    const prodiLabels = prodiData.map(d => d.program_studi || 'Tidak Diketahui');
    const prodiValues = prodiData.map(d => parseInt(d.total));

    const uktLabels = uktData.map(d => d.status || 'Tidak Diketahui');
    const uktValues = uktData.map(d => parseInt(d.total));

    Chart.defaults.font.family = "'Outfit', 'Inter', sans-serif";
    Chart.defaults.color = '#5f6368'; 

    // Chart Prodi
    new Chart(document.getElementById('chartProdi'), {
        type: 'doughnut',
        data: {
            labels: prodiLabels,
            datasets: [{
                data: prodiValues,
                backgroundColor: [
                    '#196b50', // Emerald/Primary
                    '#3b82f6', // Blue
                    '#eab308', // Yellow
                    '#ec4899', // Pink
                    '#8b5cf6', // Violet
                    '#0ea5e9', // Sky Blue
                    '#f97316', // Orange
                    '#14b8a6', // Teal
                    '#f43f5e', // Rose
                    '#64748b', // Slate
                    '#84cc16', // Lime
                    '#6366f1'  // Indigo
                ],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, padding: 20 } },
                tooltip: { backgroundColor: 'rgba(25, 107, 80, 0.9)', titleFont: { size: 13 }, bodyFont: { size: 14, weight: 'bold' }, padding: 12, cornerRadius: 8 }
            },
            cutout: '70%',
            animation: { animateScale: true, animateRotate: true }
        }
    });

    // Chart UKT
    new Chart(document.getElementById('chartUkt'), {
        type: 'doughnut',
        data: {
            labels: uktLabels,
            datasets: [{
                data: uktValues,
                backgroundColor: [
                    '#ba1a1a', // error (Belum Lunas usually comes first if sorted)
                    '#2d5b43'  // secondary (Lunas)
                ],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, padding: 20 } },
                tooltip: { backgroundColor: 'rgba(9, 31, 26, 0.9)', titleFont: { size: 13 }, bodyFont: { size: 14, weight: 'bold' }, padding: 12, cornerRadius: 8 }
            },
            cutout: '70%',
            animation: { animateScale: true, animateRotate: true }
        }
    });
    
    // Sort colors based on label content for UKT Chart just in case
    const uktChart = Chart.getChart('chartUkt');
    if (uktChart) {
        const bgColors = uktLabels.map(label => label === 'Lunas' ? '#196b50' : '#ba1a1a');
        uktChart.data.datasets[0].backgroundColor = bgColors;
        uktChart.update();
    }
});

function dismissAlert(hash) {
    const alertEl = document.getElementById('alert-' + hash);
    if (alertEl) {
        alertEl.style.opacity = '0';
        alertEl.style.transform = 'scale(0.95)';
        setTimeout(() => {
            alertEl.remove();
            const section = document.getElementById('alerts-section');
            if (section && section.querySelectorAll('.alert-item').length === 0) {
                section.remove();
            }
        }, 300);
    }
    
    fetch('dashboard.php?dismiss_alert_ajax=' + hash)
        .catch(err => console.error('Error dismissing alert:', err));
}
</script>
