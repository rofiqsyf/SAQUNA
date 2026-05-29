<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

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

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId($_SESSION['user_id']);
$pengumumanList = $repo->getPengumumanByRole();
$db = \Config\Database::getConnection();
$stmtSmt = $db->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';

$jadwalMengajarHariIni = $repo->getJadwalMengajarHariIni($dosen['id'] ?? 0, $semesterAktif);
$bebanMengajar = $repo->getRingkasanBebanMengajar($dosen['id'] ?? 0, $semesterAktif);
$statusNilai = $repo->getStatusInputNilai($dosen['id'] ?? 0, $semesterAktif);
$mhsWali = $repo->getMahasiswaWaliOverview($dosen['id'] ?? 0);
$rekapPresensi = $repo->getRekapPresensiDosen($dosen['id'] ?? 0, $semesterAktif);

// MOCK DATA
$mhsWaliPerhatian = $repo->getMahasiswaWaliPerhatianKhusus($dosen['id'] ?? 0);
$distribusiNilai = $repo->getStatistikDistribusiNilai($dosen['id'] ?? 0, $semesterAktif);
$edomStats = $repo->getRingkasanEdom($dosen['id'] ?? 0);
$taStats = $repo->getRingkasanBimbinganTA($dosen['id'] ?? 0);

// Alerts
$alerts = [];
if ($mhsWali['krs_menunggu'] > 0) {
    $alerts[] = ['type' => 'warning', 'icon' => 'fact_check', 'text' => "Terdapat {$mhsWali['krs_menunggu']} mahasiswa perwalian yang menunggu persetujuan KRS."];
}
foreach ($statusNilai as $sn) {
    if ($sn['total_mhs'] > 0 && $sn['sudah_dinilai'] < $sn['total_mhs']) {
        $belum = $sn['total_mhs'] - $sn['sudah_dinilai'];
        $alerts[] = ['type' => 'info', 'icon' => 'edit_document', 'text' => "Input Nilai {$sn['mk_nama']}: $belum mahasiswa belum dinilai."];
    }
}

if (isset($_SESSION['closed_alerts'])) {
    $alerts = array_filter($alerts, function($al) {
        return !in_array(md5($al['text']), $_SESSION['closed_alerts']);
    });
}

// Fetch Statistik (Updated to use Proyeksi Nilai)
$chartLabels = array_keys($distribusiNilai);
$chartData = array_values($distribusiNilai);
if (empty($chartLabels)) {
    $chartLabels = ['Belum Ada Kelas'];
    $chartData = [0];
}

if (!$dosen) {
    die("Profil dosen tidak ditemukan. Hubungi administrator.");
}

$_SESSION['nama_lengkap'] = $dosen['nama'];
$_SESSION['foto'] = $dosen['foto'];

$title = "Dashboard Dosen - SAQUNA";
include 'components/header.php';

$tz = new DateTimeZone('Asia/Jakarta');
$now = new DateTime('now', $tz);
$hour = (int)$now->format('H');
if ($hour >= 5 && $hour < 11) {
    $sapaan = "Selamat Pagi";
} elseif ($hour >= 11 && $hour < 15) {
    $sapaan = "Selamat Siang";
} elseif ($hour >= 15 && $hour < 18) {
    $sapaan = "Selamat Sore";
} else {
    $sapaan = "Selamat Malam";
}
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- ALERTS -->
    <?php if(!empty($alerts)): ?>
    <section class="lg:col-span-12 mb-stack-sm space-y-2" id="alerts-section">
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

    <!-- Welcome Card (Span 8) -->
    <section class="lg:col-span-8">
        <div class="glass-panel rounded-3xl p-stack-lg relative overflow-hidden shadow-sm h-full flex flex-col justify-between group">
            <div class="absolute top-0 right-0 w-64 h-64 bg-secondary-container/20 rounded-full -mr-20 -mt-20 blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
            <div class="relative z-10">
                <span class="bg-secondary-container text-on-secondary-container px-4 py-1 rounded-full font-label-md text-label-md mb-stack-sm inline-block">Tenaga Pengajar</span>
                <h2 class="font-display-lg text-display-lg text-primary mt-stack-xs"><?= $sapaan ?>, <?= htmlspecialchars(explode(',', trim($dosen['nama']))[0]) ?>!</h2>
                <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md mt-stack-sm">Kelola kelas, jadwal, dan bimbingan mahasiswa Anda dengan mudah.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-stack-md mt-stack-lg relative z-10">
                <div class="bg-white/50 p-stack-md rounded-2xl border border-white/60">
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">NIDN</p>
                    <p class="font-headline-md text-headline-md text-primary"><?= htmlspecialchars($dosen['nidn']) ?></p>
                </div>
                <div class="bg-white/50 p-stack-md rounded-2xl border border-white/60">
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Program Studi</p>
                    <p class="font-headline-md text-headline-md text-primary line-clamp-1"><?= htmlspecialchars($dosen['program_studi']) ?></p>
                </div>
                <div class="bg-white/50 p-stack-md rounded-2xl border border-white/60">
                    <p class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wider">Mata Kuliah Diampu</p>
                    <p class="font-headline-md text-headline-md text-secondary">Lihat Detail &rarr;</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Jadwal Mengajar Hari Ini (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md lg:mt-0">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-sm">
                <h3 class="font-headline-md text-headline-md text-primary">Jadwal Hari Ini</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">event_note</span>
            </div>
            <?php $mapHariStr = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu']; ?>
            <p class="font-body-sm text-on-surface-variant mb-4"><?= $mapHariStr[date('N')] ?? 'Senin' ?>, <?= date('d M Y') ?></p>
            
            <div class="flex-1 overflow-y-auto space-y-3 pr-2 h-[200px] relative">
                <?php if(empty($jadwalMengajarHariIni)): ?>
                    <div class="text-center mt-8 text-on-surface-variant opacity-60">
                        <span class="material-symbols-outlined text-5xl mb-2">event_busy</span>
                        <p>Tidak ada jadwal kelas hari ini</p>
                    </div>
                <?php else: ?>
                    <?php foreach($jadwalMengajarHariIni as $j): ?>
                    <div class="bg-surface-container-lowest p-3 rounded-xl border border-outline-variant/30 relative overflow-hidden group hover:border-primary/40 transition-colors">
                        <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $j['status_kelas'] === 'BERLANGSUNG' ? 'bg-error' : 'bg-primary' ?> rounded-l-xl"></div>
                        <div class="ml-2 flex justify-between items-center mb-1">
                            <span class="font-label-sm text-[10px] <?= $j['status_kelas'] === 'BERLANGSUNG' ? 'text-error font-bold' : 'text-on-surface-variant' ?>"><?= $j['status_kelas'] ?></span>
                            <span class="font-label-sm text-xs text-primary font-bold"><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?></span>
                        </div>
                        <h4 class="font-title-sm font-bold text-on-surface line-clamp-1 ml-2"><?= htmlspecialchars($j['mk_nama']) ?></h4>
                        <div class="flex justify-between items-end ml-2 mt-1">
                            <p class="font-body-sm text-on-surface-variant flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">meeting_room</span> <?= htmlspecialchars($j['ruangan'] ?? 'TBD') ?>
                            </p>
                            <?php if($j['status_kelas'] === 'BERLANGSUNG'): ?>
                                <a href="dosen_presensi.php" class="text-[10px] bg-primary text-white px-2 py-1.5 rounded-md hover:bg-primary/80 transition-colors shadow flex items-center gap-1 font-bold animate-pulse">
                                    <span class="material-symbols-outlined text-[12px]">qr_code_scanner</span> Presensi
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <a href="dosen_krs.php" class="w-full mt-stack-md py-3 bg-tertiary text-on-tertiary rounded-xl font-title-sm text-center shadow-lg hover:scale-[1.02] active:scale-95 transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[20px]">fact_check</span>
                Review Perwalian KRS
            </a>
        </div>
    </section>

    <!-- ROW 2: 4 NEW WIDGETS -->
    <section class="lg:col-span-3 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary mb-4 text-sm font-bold">Beban Mengajar</h3>
            <div class="flex-1 flex items-center justify-center gap-6 mb-2">
                <div class="text-center">
                    <p class="font-display-md text-primary font-bold text-4xl"><?= $bebanMengajar['jumlah_mk'] ?></p>
                    <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-widest mt-1">Kelas</p>
                </div>
                <div class="w-px h-12 bg-outline-variant/30"></div>
                <div class="text-center">
                    <p class="font-display-md text-secondary font-bold text-4xl"><?= $bebanMengajar['total_sks'] ?></p>
                    <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-widest mt-1">SKS</p>
                </div>
            </div>
            <div class="w-full bg-surface-variant/30 rounded-full h-1.5 mt-2">
                <div class="bg-primary h-1.5 rounded-full" style="width: <?= round(($bebanMengajar['pertemuan_selesai'] / $bebanMengajar['total_pertemuan']) * 100) ?>%"></div>
            </div>
            <p class="text-center text-[10px] font-bold text-on-surface-variant mt-1">
                Pertemuan selesai: <?= $bebanMengajar['pertemuan_selesai'] ?>/<?= $bebanMengajar['total_pertemuan'] ?>
            </p>
        </div>
    </section>

    <section class="lg:col-span-3 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary mb-4 text-sm font-bold">Mahasiswa Wali</h3>
            <div class="flex-1 flex flex-col justify-start gap-2">
                <div class="flex justify-between items-center bg-surface-container-lowest p-2 rounded-xl border border-outline-variant/20 shadow-sm">
                    <span class="font-label-md text-xs text-on-surface-variant">Menunggu KRS</span>
                    <span class="font-bold text-sm <?= $mhsWali['krs_menunggu'] > 0 ? 'text-error' : 'text-primary' ?>"><?= $mhsWali['krs_menunggu'] ?></span>
                </div>
                
                <?php if(!empty($mhsWaliPerhatian)): ?>
                <div class="mt-2">
                    <p class="text-[10px] font-bold text-error uppercase mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">warning</span> Perhatian Khusus
                    </p>
                    <div class="space-y-1.5 max-h-[70px] overflow-y-auto custom-scrollbar pr-1">
                        <?php foreach($mhsWaliPerhatian as $p): ?>
                        <div class="flex justify-between items-center text-[10px] border-b border-outline-variant/10 pb-1">
                            <span class="font-bold text-on-surface line-clamp-1 flex-1 pr-1"><?= $p['nama'] ?></span>
                            <span class="bg-error/10 text-error px-1 py-0.5 rounded text-[8px] uppercase tracking-wider font-bold whitespace-nowrap border border-error/20"><?= $p['tipe'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <section class="lg:col-span-3 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary mb-4 text-sm font-bold">Status Input Nilai</h3>
            <div class="flex-1 overflow-y-auto space-y-2 pr-1 max-h-[140px] custom-scrollbar">
                <?php if(empty($statusNilai)): ?>
                    <p class="text-xs text-on-surface-variant text-center mt-4 opacity-60">Belum ada kelas.</p>
                <?php else: ?>
                    <?php foreach($statusNilai as $sn): 
                        $statusText = ($sn['total_mhs'] > 0 && $sn['sudah_dinilai'] == $sn['total_mhs']) ? 'Selesai' : 'Belum';
                        $colorText = $statusText === 'Selesai' ? 'text-success bg-success/10 border-success/20' : 'text-error bg-error/10 border-error/20';
                    ?>
                    <div class="border-b border-outline-variant/10 pb-1.5 last:border-0 last:pb-0">
                        <div class="flex justify-between text-[11px] font-bold">
                            <span class="line-clamp-1 flex-1 pr-1 text-on-surface"><?= $sn['mk_nama'] ?></span>
                        </div>
                        <div class="flex justify-between items-center mt-1">
                            <span class="text-[9px] text-on-surface-variant">UTS & UAS</span>
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border uppercase tracking-wider <?= $colorText ?>"><?= $statusText ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="lg:col-span-3 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary mb-4 text-sm font-bold">Rekap Presensi</h3>
            <div class="flex-1 overflow-y-auto space-y-2.5 pr-1 max-h-[140px] custom-scrollbar">
                <?php if(empty($rekapPresensi)): ?>
                    <p class="text-xs text-on-surface-variant text-center mt-4 opacity-60">Belum ada data.</p>
                <?php else: ?>
                    <?php foreach($rekapPresensi as $rp): ?>
                    <div class="flex justify-between items-center border-b border-outline-variant/10 pb-2">
                        <span class="text-[11px] font-medium text-on-surface-variant line-clamp-1 flex-1 pr-2"><?= $rp['mk_nama'] ?></span>
                        <span class="text-[10px] font-bold text-primary bg-primary-container/30 border border-primary/20 px-2 py-1 rounded-md"><?= $rp['sesi_dibuka'] ?> Sesi</span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ROW 3: Grafik dan EDoM -->
    <!-- Grafik Statistik Mahasiswa (Span 7) -->
    <section class="lg:col-span-7 mt-stack-md flex flex-col">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 flex-1 flex flex-col">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="font-headline-md text-headline-md text-primary">Distribusi Proyeksi Nilai</h3>
                    <p class="text-on-surface-variant font-body-sm mt-1">Estimasi nilai akhir berdasarkan progres tugas, UTS, dan absensi.</p>
                </div>
                <span class="material-symbols-outlined text-primary-container text-3xl">bar_chart</span>
            </div>
            
            <div class="w-full h-[250px] relative flex-1">
                <canvas id="kelasChart"></canvas>
            </div>
        </div>
    </section>

    <!-- Hasil Evaluasi Dosen (Span 5) -->
    <section class="lg:col-span-5 mt-stack-md flex flex-col">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 flex-1 flex flex-col">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="font-headline-md text-headline-md text-secondary">Evaluasi Dosen (EDoM)</h3>
                    <p class="text-on-surface-variant font-body-sm mt-1">Umpan balik dari mahasiswa.</p>
                </div>
                <span class="material-symbols-outlined text-secondary-container text-3xl">star</span>
            </div>
            
            <div class="flex items-center gap-4 mb-4">
                <div class="text-center">
                    <p class="font-display-md text-secondary font-bold text-4xl"><?= number_format($edomStats['skor_rata_rata'], 1) ?></p>
                    <p class="font-label-sm text-[10px] text-on-surface-variant mt-1 uppercase tracking-wider">/ <?= number_format($edomStats['skor_maksimal'], 1) ?></p>
                </div>
                <div>
                    <div class="flex gap-1 text-secondary mb-1">
                        <?php for($i=1; $i<=5; $i++): ?>
                            <span class="material-symbols-outlined text-lg <?= $i <= round($edomStats['skor_rata_rata']) ? 'fill-current' : 'opacity-30' ?>">star</span>
                        <?php endfor; ?>
                    </div>
                    <p class="font-bold text-sm text-on-surface"><?= $edomStats['kategori'] ?></p>
                    <p class="font-label-sm text-xs text-on-surface-variant"><?= $edomStats['total_responden'] ?> Responden</p>
                </div>
            </div>
            
            <div class="flex-1 overflow-y-auto space-y-2 pr-1 custom-scrollbar">
                <?php foreach($edomStats['komentar_terbaru'] as $komen): ?>
                <div class="bg-surface-variant/30 p-2.5 rounded-lg border border-outline-variant/20">
                    <p class="font-body-sm text-xs text-on-surface-variant italic">"<?= htmlspecialchars($komen) ?>"</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Papan Pengumuman (Span 7) -->
    <section class="lg:col-span-7 mt-stack-md">
        <div class="flex justify-between items-end mb-stack-sm">
            <h3 class="font-headline-md text-headline-md text-primary">Pengumuman Kampus</h3>
        </div>
        
        <?php if (empty($pengumumanList)): ?>
            <div class="glass-panel rounded-3xl p-stack-md h-[420px] flex items-center justify-center text-on-surface-variant">
                Belum ada pengumuman terbaru.
            </div>
        <?php else: ?>
            <div class="glass-panel rounded-3xl p-stack-md h-auto lg:h-[420px] overflow-y-auto space-y-4 shadow-sm">
                <?php foreach ($pengumumanList as $p): ?>
                    <div class="p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                        <span class="font-label-md text-label-md text-primary bg-primary-container/20 px-3 py-1 rounded-full mb-2 inline-block">Info</span>
                        <h4 class="font-title-lg text-title-lg mt-1 mb-1 group-hover:text-primary transition-colors"><?= htmlspecialchars($p['judul']) ?></h4>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mb-2"><?= htmlspecialchars($p['isi']) ?></p>
                        <p class="font-label-md text-label-md text-on-surface-variant opacity-60"><?= date('d M Y, H:i', strtotime($p['created_at'])) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Quick Menu (Span 5) -->
    <section class="lg:col-span-5 mt-stack-md">
        <div class="flex justify-between items-end mb-stack-sm">
            <h3 class="font-headline-md text-headline-md text-primary">Akses Cepat</h3>
        </div>
        <div class="glass-panel rounded-3xl p-stack-md h-auto lg:h-[420px] overflow-y-auto space-y-4 shadow-sm">
            <a href="dosen_tugas.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-primary-container/30 text-primary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">assignment_turned_in</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Tugas Kuliah</h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Penilaian Tugas</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>
            
            <a href="dosen_ta.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-secondary-container/30 text-secondary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">history_edu</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Bimbingan Skripsi</h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Review Proposal & Logbook</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>

            <a href="dosen_chat.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-tertiary-container/30 text-tertiary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">forum</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Pesan Mahasiswa</h4>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Tanya Jawab</p>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>
        </div>
    </section>

    <!-- Bimbingan Tugas Akhir (Span 12) -->
    <section class="lg:col-span-12 mt-stack-md mb-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <div class="flex justify-between items-center mb-stack-sm">
                <div>
                    <h3 class="font-headline-md text-headline-md text-primary">Bimbingan Tugas Akhir</h3>
                    <p class="font-body-sm text-on-surface-variant mt-1">Mahasiswa bimbingan yang membutuhkan reviu dan persetujuan.</p>
                </div>
                <a href="dosen_ta.php" class="text-sm font-bold text-primary hover:underline">Kelola Bimbingan &rarr;</a>
            </div>
            
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-outline-variant/30 text-on-surface-variant font-label-md">
                            <th class="p-3">Nama Mahasiswa</th>
                            <th class="p-3">NIM</th>
                            <th class="p-3">Progress</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($taStats as $ta): 
                            $statusColor = $ta['status'] === 'Siap Uji' ? 'bg-success/10 text-success border-success/20' : 
                                         ($ta['status'] === 'Pending' ? 'bg-warning/10 text-warning-dark border-warning/20' : 'bg-primary/10 text-primary border-primary/20');
                        ?>
                        <tr class="border-b border-outline-variant/10 hover:bg-white/5 transition-colors">
                            <td class="p-3 font-title-sm text-on-surface font-bold"><?= htmlspecialchars($ta['nama']) ?></td>
                            <td class="p-3 font-body-sm text-on-surface-variant"><?= htmlspecialchars($ta['nim']) ?></td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-24 bg-surface-variant/30 rounded-full h-1.5 overflow-hidden">
                                        <div class="bg-secondary h-1.5 rounded-full" style="width: <?= $ta['persentase'] ?>%"></div>
                                    </div>
                                    <span class="text-xs text-on-surface-variant"><?= htmlspecialchars($ta['progress']) ?></span>
                                </div>
                            </td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wider border <?= $statusColor ?>">
                                    <?= htmlspecialchars($ta['status']) ?>
                                </span>
                            </td>
                            <td class="p-3 text-right">
                                <a href="dosen_ta_detail.php?nim=<?= $ta['nim'] ?>" class="text-[11px] font-bold text-primary hover:underline">Review &rarr;</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script src="assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('kelasChart').getContext('2d');
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#a6b7b2' : '#51635e';
    const gridColor = isDarkMode ? 'rgba(166, 183, 178, 0.1)' : 'rgba(81, 99, 94, 0.1)';

    const kelasChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Jumlah Mahasiswa (Proyeksi)',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: [
                    'rgba(25, 107, 80, 0.8)', // A - Primary
                    'rgba(64, 154, 123, 0.8)', // AB - Primary var
                    'rgba(172, 209, 105, 0.8)', // B - Secondary
                    'rgba(230, 201, 71, 0.8)', // BC - Warning
                    'rgba(204, 122, 0, 0.8)', // C - Warning dark
                    'rgba(186, 26, 26, 0.8)', // D - Error
                    'rgba(100, 10, 10, 0.8)'  // E - Error dark
                ],
                borderColor: 'transparent',
                borderWidth: 0,
                borderRadius: 4, 
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDarkMode ? '#1a3028' : '#fff',
                    titleColor: isDarkMode ? '#fff' : '#196b50',
                    bodyColor: isDarkMode ? '#fff' : '#196b50',
                    borderColor: '#196b50',
                    borderWidth: 1,
                    padding: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { color: textColor, font: { family: 'Inter', size: 12 }, stepSize: 1 }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: textColor, font: { family: 'Inter', size: 12 } }
                }
            }
        }
    });

    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "class") {
                const dark = document.documentElement.classList.contains('dark');
                kelasChart.options.scales.x.ticks.color = dark ? '#a6b7b2' : '#51635e';
                kelasChart.options.scales.y.ticks.color = dark ? '#a6b7b2' : '#51635e';
                kelasChart.options.scales.y.grid.color = dark ? 'rgba(166, 183, 178, 0.1)' : 'rgba(81, 99, 94, 0.1)';
                kelasChart.options.plugins.tooltip.backgroundColor = dark ? '#1a3028' : '#fff';
                kelasChart.options.plugins.tooltip.titleColor = dark ? '#fff' : '#196b50';
                kelasChart.options.plugins.tooltip.bodyColor = dark ? '#fff' : '#196b50';
                kelasChart.update();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
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
    
    fetch('dosen_dashboard.php?dismiss_alert_ajax=' + hash)
        .catch(err => console.error('Error dismissing alert:', err));
}
</script>

<?php include 'components/footer.php'; ?>
