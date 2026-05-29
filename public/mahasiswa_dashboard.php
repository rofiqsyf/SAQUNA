<?php
require_once __DIR__ . '/../autoload.php';
use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();

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

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaProfile($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$_SESSION['nama_lengkap'] = $mhs['nama'];
$_SESSION['foto'] = $mhs['foto'];

// Fetch Data
$pengumumanAkademik = $repo->getPengumumanByRole('Umum');
$pengumumanEvent = $repo->getPengumumanByRole('Event');
$pengumumanBeasiswa = $repo->getPengumumanByRole('Beasiswa');
$jadwalKuliah = $repo->getJadwalKuliah($mhs['id'], $repo->getSemesterAktif());
$jadwalUjian = $repo->getJadwalUjianMahasiswa($mhs['id'], $repo->getSemesterAktif());

if (!function_exists('generateSvgAvatar')) {
    function generateSvgAvatar($name) {
        $initials = strtoupper(substr(trim($name), 0, 2));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" fill="#196b50"/><text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="64" font-weight="bold">' . htmlspecialchars($initials) . '</text></svg>';
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
$foto = !empty($mhs['foto']) ? $mhs['foto'] : generateSvgAvatar($mhs['nama']);

$statistik = $repo->getStatistikIPK($mhs['id']);
$ipk = 0.00;
$semester_angka = $mhs['semester'] ?? (count($statistik) + 1);

// Prepare Chart Data
$chartLabels = [];
$chartData = [];
if (!empty($statistik)) {
    $latest = end($statistik);
    $ipk = $latest['ipk'];
    foreach ($statistik as $stat) {
        $chartLabels[] = 'Smt ' . $stat['semester'];
        $chartData[] = $stat['ipk'];
    }
}
// chartLabels & chartData tetap kosong [] jika belum ada data — chart akan menampilkan empty state

// Fetch Tagihan
$tagihanList = $repo->getTagihan($mhs['id']);
$latestTagihan = !empty($tagihanList) ? $tagihanList[0] : null;
$statusUKT = $latestTagihan ? $latestTagihan['status'] : 'Belum Ada Tagihan';

// Fetch Institusi (Sinkron dengan Pengaturan Operator)
$namaKampus = 'Universitas Saquna';
try {
    $stmtInst = \Config\Database::getConnection()->prepare("SELECT nilai FROM pengaturan_institusi WHERE kunci = 'nama_universitas' LIMIT 1");
    $stmtInst->execute();
    if ($res = $stmtInst->fetch()) {
        $namaKampus = $res['nilai'];
    }
} catch (\Exception $e) {}

// Fetch Data Baru (Widget Dashboard)
$jadwalHariIni = $repo->getJadwalHariIni($mhs['id'], $repo->getSemesterAktif());
$progressStudi = $repo->getProgressStudi($mhs['id']);
$rekapKehadiran = $repo->getRekapKehadiran($mhs['id'], $repo->getSemesterAktif());
$statusKrs = $repo->getStatusKRS($mhs['id'], $repo->getSemesterAktif());

// Todo Items
$todoList = [];
if ($statusKrs === 'Belum Diisi' || $statusKrs === 'Ditolak') {
    $todoList[] = ['teks' => 'Isi KRS Semester ' . $repo->getSemesterAktif(), 'link' => 'mahasiswa_krs.php', 'btn' => 'Isi Sekarang', 'done' => false];
} else {
    $todoList[] = ['teks' => 'KRS sudah ' . strtolower($statusKrs), 'link' => 'mahasiswa_krs.php', 'btn' => '', 'done' => true];
}

if ($statusUKT === 'Belum Lunas') {
    $todoList[] = ['teks' => 'Bayar UKT Semester ' . $repo->getSemesterAktif(), 'link' => 'mahasiswa_tagihan.php', 'btn' => 'Info Bayar', 'done' => false];
}

$edomLengkap = $repo->cekEdomLengkap($mhs['id'], $repo->getSemesterAktif());
if (!$edomLengkap) {
    $todoList[] = ['teks' => 'Isi Evaluasi Dosen (EDoM)', 'link' => 'mahasiswa_edom.php', 'btn' => 'Isi Sekarang', 'done' => false];
}

// Alerts
$alerts = [];
if ($statusUKT === 'Belum Lunas') {
    $alerts[] = ['type' => 'error', 'icon' => 'warning', 'text' => 'Tunggakan UKT semester ini belum dibayar. Segera lakukan pelunasan.'];
}
if ($statusKrs === 'Belum Diisi') {
    $alerts[] = ['type' => 'info', 'icon' => 'info', 'text' => 'Periode pengisian KRS sedang berlangsung. Segera susun rencana studimu!'];
}
foreach ($rekapKehadiran as $rk) {
    if ($rk['total'] > 0 && $rk['persentase'] < 75) {
        $alerts[] = ['type' => 'warning', 'icon' => 'error', 'text' => 'Perhatian: Kehadiran ' . $rk['mk_nama'] . ' di bawah 75% (' . $rk['persentase'] . '%).'];
    }
}

// Filter closed alerts
if (isset($_SESSION['closed_alerts'])) {
    $alerts = array_filter($alerts, function($al) {
        return !in_array(md5($al['text']), $_SESSION['closed_alerts']);
    });
}

$title = "Dashboard Mahasiswa - SAQUNA";
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
    
    <!-- ROW 1: WELCOME & KTM -->
    
    <!-- Welcome Card (Span 8) -->
    <section class="lg:col-span-8">
        <div class="glass-panel rounded-3xl p-stack-lg relative overflow-hidden shadow-sm h-full flex flex-col justify-between group">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary-container/20 rounded-full -mr-20 -mt-20 blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
            
            <div class="relative z-10 flex justify-between items-start">
                <div>
                    <span class="bg-secondary-container text-on-secondary-container px-4 py-1 rounded-full font-label-md text-label-md mb-stack-sm inline-block">Semester Aktif</span>
                    <h2 class="font-display-lg text-display-lg text-primary mt-stack-xs"><?= $sapaan ?>, <?= htmlspecialchars(explode(' ', trim($mhs['nama']))[0]) ?>!</h2>
                    <p class="font-body-lg text-body-lg text-on-surface-variant max-w-md mt-stack-sm">Pertahankan prestasimu. Kamu selangkah lagi menuju kelulusan tepat waktu!</p>
                    <?php if(!empty($mhs['dosen_wali_nama'])): ?>
                    <div class="mt-4 inline-flex items-center gap-2 bg-secondary/10 text-secondary px-4 py-2 rounded-xl">
                        <span class="material-symbols-outlined text-sm">person_raised_hand</span>
                        <span class="font-label-md text-sm">Dosen Wali: <strong><?= htmlspecialchars($mhs['dosen_wali_nama']) ?></strong></span>
                    </div>
                    <?php else: ?>
                    <div class="mt-4 inline-flex items-center gap-2 bg-error/10 text-error px-4 py-2 rounded-xl">
                        <span class="material-symbols-outlined text-sm">warning</span>
                        <span class="font-label-md text-sm">Belum ada Dosen Wali. Hubungi Admin Prodi.</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Nilai IPK di Kanan Atas -->
                <a href="mahasiswa_nilai.php" class="bg-primary hover:bg-on-primary-fixed-variant p-4 rounded-2xl shadow-lg transition-all cursor-pointer group/ipk flex flex-col items-end min-w-[120px] text-on-primary">
                    <p class="font-label-md text-xs uppercase tracking-wider mb-1 opacity-80 group-hover/ipk:underline">IPK Kumulatif</p>
                    <div class="flex items-center gap-2">
                        <p class="font-display-lg font-bold"><?= number_format($ipk, 2) ?></p>
                        <span class="material-symbols-outlined group-hover/ipk:translate-x-1 transition-transform">arrow_forward</span>
                    </div>
                </a>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-stack-sm mt-stack-lg relative z-10">
                <div class="bg-white/50 p-4 rounded-2xl border border-white/60">
                    <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider mb-1">NIM</p>
                    <p class="font-headline-sm text-primary font-bold line-clamp-1"><?= htmlspecialchars($mhs['nim']) ?></p>
                </div>
                <div class="bg-white/50 p-4 rounded-2xl border border-white/60">
                    <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider mb-1">Prodi</p>
                    <p class="font-headline-sm text-primary font-bold line-clamp-1"><?= htmlspecialchars($mhs['program_studi']) ?></p>
                </div>
                <div class="bg-white/50 p-4 rounded-2xl border border-white/60">
                    <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider mb-1">Semester</p>
                    <p class="font-headline-sm text-primary font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[20px]">calendar_month</span> <?= $semester_angka ?>
                    </p>
                </div>
                <div class="bg-white/50 p-4 rounded-2xl border border-white/60">
                    <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider mb-1">Status</p>
                    <p class="font-headline-sm text-secondary font-bold flex items-center gap-1">
                        <span class="material-symbols-outlined text-[20px]" style="font-variation-settings: 'FILL' 1;">check_circle</span> Aktif
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- KTM Digital (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md lg:mt-0">
        <div class="glass-panel rounded-3xl p-stack-md h-full flex flex-col shadow-sm border border-white/40 overflow-hidden group">
            
            <!-- Wrapper untuk dicetak (Desain KTM Baru) -->
            <div id="ktm-card" class="relative z-10 w-full rounded-2xl overflow-hidden bg-white shadow-md mx-auto flex" style="aspect-ratio: 1.58 / 1; border: 1px solid #e1e3e4; font-family: 'Inter', sans-serif;">
                
                <!-- Left Section (Green curved) -->
                <div class="relative flex flex-col items-center justify-between p-3 text-white z-10" style="width: 38%; background: #196b50; clip-path: polygon(0 0, 100% 0, 80% 100%, 0 100%); border-right: 4px solid #a5f2d0;">
                    <!-- Logo -->
                    <div class="text-center mt-1 flex flex-col items-center">
                        <div class="font-bold tracking-wide text-white" style="font-family: 'Outfit', sans-serif; font-size: 16px; line-height: 1;">SAQUNA</div>
                        <div class="text-white font-semibold mt-1" style="font-size: 7px; line-height: 1.2;">Sistem Akademik<br>Universitas Sains Al-Qur'an</div>
                    </div>
                    
                    <!-- QR Code -->
                    <div class="bg-white p-1 rounded-md shadow-sm w-12 h-12 md:w-16 md:h-16 mb-1">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($mhs['nim']) ?>" alt="QR" class="w-full h-full">
                    </div>
                </div>
                
                <!-- Right Section (Details) -->
                <div class="flex-1 p-3 flex flex-col relative bg-[#f8fafb]">
                    <div class="text-right border-b border-primary/20 pb-1 mb-1">
                        <div class="text-[8px] md:text-[10px] text-primary uppercase tracking-wider font-semibold"><?= htmlspecialchars($namaKampus) ?></div>
                        <div class="font-black text-primary text-[12px] md:text-[16px]">Kartu Tanda Mahasiswa</div>
                    </div>
                    
                    <div class="flex items-center justify-between flex-1 gap-2 mt-1">
                        <div class="text-primary flex-1">
                            <table class="w-full text-left border-collapse table-fixed">
                                <tbody>
                                    <tr>
                                        <td class="w-[25%] md:w-[22%] text-[6px] md:text-[8px] text-primary/70 uppercase font-bold tracking-wider align-top pb-0.5 md:pb-1">Nama</td>
                                        <td class="w-[75%] md:w-[78%] font-bold text-[8px] md:text-[11px] text-on-surface leading-tight align-top pb-0.5 md:pb-1 break-words">:<span class="ml-1"></span><?= htmlspecialchars($mhs['nama']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-[6px] md:text-[8px] text-primary/70 uppercase font-bold tracking-wider align-top pb-0.5 md:pb-1">NIM</td>
                                        <td class="font-bold text-[8px] md:text-[11px] text-on-surface leading-tight align-top pb-0.5 md:pb-1 break-words">:<span class="ml-1"></span><?= htmlspecialchars($mhs['nim']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-[6px] md:text-[8px] text-primary/70 uppercase font-bold tracking-wider align-top pb-0.5 md:pb-1">Fakultas</td>
                                        <td class="font-bold text-[7px] md:text-[10px] text-on-surface leading-tight align-top pb-0.5 md:pb-1 break-words">:<span class="ml-1"></span><?= htmlspecialchars($mhs['fakultas'] ?? '-') ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-[6px] md:text-[8px] text-primary/70 uppercase font-bold tracking-wider align-top pb-0.5 md:pb-1">Prodi</td>
                                        <td class="font-bold text-[7px] md:text-[10px] text-on-surface leading-tight align-top pb-0.5 md:pb-1 break-words">:<span class="ml-1"></span><?= htmlspecialchars($mhs['program_studi']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-[6px] md:text-[8px] text-primary/70 uppercase font-bold tracking-wider align-top pb-0.5 md:pb-1">Alamat</td>
                                        <td class="font-bold text-[6px] md:text-[9px] text-on-surface leading-tight align-top pb-0.5 md:pb-1 break-words">:<span class="ml-1"></span><?= htmlspecialchars($mhs['alamat_asal'] ?: ($mhs['domisili'] ?: '-')) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Foto Mahasiswa -->
                        <div class="w-14 md:w-20 aspect-[3/4] bg-gray-200 border-2 border-white shadow-md flex-shrink-0 rounded-md bg-cover bg-center self-start" style="background-image: url('<?= htmlspecialchars($foto, ENT_QUOTES) ?>');">
                        </div>
                    </div>
                </div>
            </div> <!-- End KTM Card Wrapper -->
            
            <div class="flex-1"></div>
            
            <!-- Tombol Download -->
            <button onclick="downloadKTM(event)" class="mt-4 w-full bg-primary text-on-primary hover:bg-on-primary-fixed-variant font-label-md py-3 rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                <span class="material-symbols-outlined">download</span> Download KTM (PNG)
            </button>
        </div>
    </section>

    <!-- ROW 2: JADWAL HARI INI, PROGRESS STUDI, QUICK MENU -->
    
    <!-- Jadwal Kuliah (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-sm">
                <h3 class="font-headline-md text-headline-md text-primary">Jadwal Kuliah</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">calendar_month</span>
            </div>
            
            <?php 
                $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']; 
                $mapHari = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu'];
                $hariIni = $mapHari[date('N')] ?? 'Senin';
                if (!in_array($hariIni, $hariList)) $hariIni = 'Senin';
            ?>
            <div class="flex gap-2 overflow-x-auto custom-scrollbar pb-2 mb-4">
                <?php foreach($hariList as $hari): ?>
                    <button id="tab-btn-<?= $hari ?>" onclick="switchTabHari('<?= $hari ?>')" class="px-3 py-1.5 rounded-lg font-label-md text-sm whitespace-nowrap transition-all <?= $hari === $hariIni ? 'bg-primary text-white shadow-md' : 'bg-surface-variant/30 text-on-surface-variant hover:bg-surface-variant' ?>">
                        <?= $hari ?>
                    </button>
                <?php endforeach; ?>
            </div>
            
            <div class="flex-1 relative overflow-y-auto overflow-x-hidden pr-2 h-[200px]">
                <?php foreach($hariList as $hari): ?>
                    <div id="tab-content-<?= $hari ?>" class="space-y-3 absolute w-full transition-opacity duration-300 <?= $hari === $hariIni ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none' ?>">
                        <?php 
                        $hasJadwal = false;
                        $currentTime = date('H:i');
                        foreach($jadwalKuliah as $j): 
                            if($j['hari'] === $hari): $hasJadwal = true; 
                                $start = substr($j['jam_mulai'], 0, 5);
                                $end = substr($j['jam_selesai'], 0, 5);
                                
                                $statusJadwal = 'Akan Datang';
                                $statusColor = 'text-tertiary';
                                $statusIcon = 'radio_button_unchecked';
                                $showPresensi = false;
                                
                                if ($hari === $hariIni) {
                                    if ($currentTime >= $start && $currentTime <= $end) {
                                        $statusJadwal = 'BERLANGSUNG';
                                        $statusColor = 'text-error';
                                        $statusIcon = 'trip_origin';
                                        $showPresensi = true;
                                    } elseif ($currentTime > $end) {
                                        $statusJadwal = 'Selesai';
                                        $statusColor = 'text-success';
                                        $statusIcon = 'check_circle';
                                    }
                                }
                        ?>
                            <div class="bg-surface-container-lowest p-3 rounded-xl border border-outline-variant/30 relative overflow-hidden group hover:border-primary/40 transition-colors">
                                <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $showPresensi ? 'bg-error animate-pulse' : 'bg-primary' ?> rounded-l-xl"></div>
                                <div class="ml-2 flex justify-between items-center mb-1">
                                    <span class="font-label-sm text-xs text-primary font-bold"><?= $start ?> - <?= $end ?></span>
                                    <?php if ($hari === $hariIni): ?>
                                    <span class="text-[10px] font-bold <?= $statusColor ?> flex items-center gap-1 uppercase tracking-wider">
                                        <span class="material-symbols-outlined text-[12px]"><?= $statusIcon ?></span> <?= $statusJadwal ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <h4 class="font-title-sm font-bold text-on-surface line-clamp-1 ml-2"><?= htmlspecialchars($j['mk_nama']) ?></h4>
                                <div class="flex justify-between items-end ml-2 mt-2">
                                    <p class="font-body-sm text-on-surface-variant flex items-center gap-1">
                                        <span class="material-symbols-outlined text-[14px]">meeting_room</span> <?= htmlspecialchars($j['ruangan'] ?? 'TBD') ?>
                                    </p>
                                    <?php if ($showPresensi): ?>
                                        <a href="mahasiswa_presensi.php?id=<?= $j['id'] ?>" class="text-[10px] bg-error text-white px-2 py-1 rounded-md font-bold hover:bg-error/80 transition-colors shadow-sm animate-pulse">Presensi</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; endforeach; ?>
                        
                        <?php if(!$hasJadwal): ?>
                            <div class="text-center mt-8 text-on-surface-variant opacity-60">
                                <span class="material-symbols-outlined text-4xl mb-2">event_busy</span>
                                <p class="text-sm">Tidak ada kelas hari <?= strtolower($hari) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <a href="mahasiswa_jadwal.php" class="text-center text-primary font-label-md hover:underline mt-4">Lihat Jadwal Lengkap &rarr;</a>
        </div>
    </section>

    <!-- Progress Studi (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-md">
                <h3 class="font-headline-md text-headline-md text-primary">Progress Studi</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">school</span>
            </div>
            
            <?php 
                $persenSks = min(100, round(($progressStudi['sks_lulus'] / 144) * 100)); 
            ?>
            <div class="mb-4">
                <div class="flex justify-between text-sm font-label-md mb-1">
                    <span class="text-on-surface-variant">SKS Ditempuh</span>
                    <span class="text-primary font-bold"><?= $progressStudi['sks_lulus'] ?> / 144</span>
                </div>
                <div class="w-full bg-surface-variant/30 rounded-full h-3">
                    <div class="bg-primary h-3 rounded-full" style="width: <?= $persenSks ?>%"></div>
                </div>
            </div>
            
            <div class="bg-primary/5 rounded-2xl p-stack-sm border border-primary/10 flex-1 grid grid-cols-2 gap-4">
                <div>
                    <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-wider mb-1">Matakuliah Lulus</p>
                    <p class="font-headline-sm text-primary font-bold text-xl"><?= $progressStudi['mk_lulus'] ?></p>
                </div>
                <div>
                    <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-wider mb-1">SKS Smt Ini</p>
                    <p class="font-headline-sm text-primary font-bold text-xl"><?= $progressStudi['sks_semester'] ?> <span class="text-sm font-normal">SKS</span></p>
                </div>
                <div class="col-span-2 pt-2 border-t border-primary/10">
                    <p class="font-label-sm text-[10px] text-on-surface-variant uppercase tracking-wider mb-1">Estimasi Lulus</p>
                    <p class="font-headline-sm text-secondary font-bold flex items-center gap-1 text-lg">
                        <span class="material-symbols-outlined text-[20px]">workspace_premium</span> Semester <?= $progressStudi['estimasi_semester'] ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Menu (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-md h-full flex flex-col space-y-4 shadow-sm">
            <div class="flex justify-between items-end mb-2 px-2 pt-2">
                <h3 class="font-headline-md text-headline-md text-primary">Akses Cepat</h3>
            </div>
            
            <a href="mahasiswa_krs.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-primary-container/30 text-primary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">description</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Isi KRS</h4>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>
            
            <a href="mahasiswa_presensi.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-success/20 text-success flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">qr_code_scanner</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-success transition-colors">Presensi</h4>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>
            
            <a href="mahasiswa_tugas.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-secondary-container/30 text-secondary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">assignment</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Tugas Kuliah</h4>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>

            <a href="mahasiswa_khs.php" class="flex items-center gap-stack-md p-stack-sm rounded-2xl hover:bg-white/60 transition-all border border-transparent hover:border-white/40 group">
                <div class="w-14 h-14 rounded-xl bg-tertiary-container/30 text-tertiary flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined">grade</span>
                </div>
                <div class="flex-1">
                    <h4 class="font-title-lg text-title-lg group-hover:text-primary transition-colors">Lihat KHS</h4>
                </div>
                <span class="material-symbols-outlined text-on-surface-variant opacity-30">chevron_right</span>
            </a>
        </div>
    </section>

    <!-- ROW 3: REKAP KEHADIRAN, KRS STATUS, TO-DO LIST -->
    
    <!-- Rekap Kehadiran (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-sm">
                <h3 class="font-headline-md text-headline-md text-primary">Kehadiran</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">fact_check</span>
            </div>
            <div class="flex-1 overflow-y-auto pr-2 space-y-4">
                <?php if(empty($rekapKehadiran)): ?>
                    <p class="text-on-surface-variant opacity-60 text-center mt-8">Belum ada data kehadiran.</p>
                <?php else: ?>
                    <?php foreach($rekapKehadiran as $rk): 
                        $statusColor = $rk['persentase'] >= 75 ? 'bg-success' : ($rk['persentase'] > 60 ? 'bg-tertiary' : 'bg-error');
                        $textColor = $rk['persentase'] >= 75 ? 'text-success' : ($rk['persentase'] > 60 ? 'text-tertiary' : 'text-error');
                        $isEligible = $rk['persentase'] >= 75;
                    ?>
                    <div>
                        <div class="flex justify-between font-label-md text-xs mb-1">
                            <span class="text-on-surface line-clamp-1 flex-1 pr-2"><?= htmlspecialchars($rk['mk_nama']) ?></span>
                            <span class="font-bold <?= $textColor ?>"><?= $rk['hadir'] ?>/<?= $rk['total'] ?> (<?= $rk['persentase'] ?>%)</span>
                        </div>
                        <div class="w-full bg-surface-variant/30 rounded-full h-2 mb-1">
                            <div class="<?= $statusColor ?> h-2 rounded-full" style="width: <?= min(100, $rk['persentase']) ?>%"></div>
                        </div>
                        <div class="flex justify-end">
                            <span class="text-[9px] font-bold uppercase tracking-wider <?= $isEligible ? 'text-success' : 'text-error' ?>">
                                <?= $isEligible ? 'ELIGIBLE' : 'NOT ELIGIBLE' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- KRS Status (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-sm">
                <h3 class="font-headline-md text-headline-md text-primary">KRS & Status</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">assignment_ind</span>
            </div>
            
            <div class="flex-1 flex flex-col justify-center">
                <div class="bg-primary/5 rounded-2xl p-stack-md border border-primary/10 text-center mb-4">
                    <p class="font-label-md text-xs text-on-surface-variant uppercase tracking-wider mb-2">Status Rencana Studi</p>
                    <?php if ($statusKrs === 'Disetujui'): ?>
                        <p class="font-display-sm text-success font-bold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-3xl">check_circle</span> DISETUJUI
                        </p>
                    <?php elseif ($statusKrs === 'Menunggu'): ?>
                        <p class="font-display-sm text-tertiary font-bold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-3xl">hourglass_empty</span> MENUNGGU
                        </p>
                    <?php else: ?>
                        <p class="font-display-sm text-error font-bold flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-3xl">error</span> BELUM DIISI
                        </p>
                    <?php endif; ?>
                </div>
                
                <a href="mahasiswa_krs.php" class="w-full py-3 bg-secondary text-on-secondary rounded-xl font-label-md text-center shadow-lg hover:bg-secondary/90 transition-all">
                    Kelola KRS &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- To-Do List (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg h-full flex flex-col shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-stack-sm">
                <h3 class="font-headline-md text-headline-md text-primary">To-Do List</h3>
                <span class="material-symbols-outlined text-primary-container text-3xl">task_alt</span>
            </div>
            <div class="flex-1 overflow-y-auto space-y-3 pr-2">
                <?php if(empty($todoList)): ?>
                    <div class="text-center mt-8 text-on-surface-variant opacity-60">
                        <span class="material-symbols-outlined text-4xl mb-2">done_all</span>
                        <p class="text-sm">Semua tugas beres!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($todoList as $todo): ?>
                    <div class="flex items-center gap-3 p-3 rounded-xl border border-outline-variant/30 <?= $todo['done'] ? 'opacity-50' : 'bg-surface-container-lowest shadow-sm' ?>">
                        <span class="material-symbols-outlined <?= $todo['done'] ? 'text-success' : 'text-error' ?>">
                            <?= $todo['done'] ? 'check_circle' : 'radio_button_unchecked' ?>
                        </span>
                        <div class="flex-1">
                            <p class="font-label-sm <?= $todo['done'] ? 'line-through' : '' ?>"><?= htmlspecialchars($todo['teks']) ?></p>
                        </div>
                        <?php if(!$todo['done'] && !empty($todo['btn'])): ?>
                            <a href="<?= $todo['link'] ?>" class="text-[10px] font-bold bg-error hover:bg-error/80 transition-colors text-white px-2 py-1.5 rounded-lg whitespace-nowrap"><?= $todo['btn'] ?></a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- NEW ROW: ANALYTICS -->
    <section class="lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="font-headline-md text-headline-md text-primary">Tren Prestasi Akademik</h3>
                    <p class="text-on-surface-variant font-body-sm mt-1">Pergerakan Indeks Prestasi Kumulatif (IPK) per Semester</p>
                </div>
                <span class="material-symbols-outlined text-primary-container text-3xl">monitoring</span>
            </div>
            
            <div class="w-full h-[300px] relative">
                <canvas id="ipkChart"></canvas>
            </div>
        </div>
    </section>

    <!-- NEW ROW: JADWAL UJIAN -->
    <?php if(!empty($jadwalUjian)): ?>
    <section class="lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-secondary/40 relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-64 h-64 bg-secondary-container/20 rounded-full -mr-20 -mt-20 blur-3xl group-hover:scale-110 transition-transform duration-700"></div>
            
            <div class="flex justify-between items-start mb-4 relative z-10">
                <div>
                    <h3 class="font-headline-md text-headline-md text-secondary">Jadwal Ujian Terdekat</h3>
                    <p class="text-on-surface-variant font-body-sm mt-1">Jadwal UTS/UAS terpusat yang telah diplot oleh Akademik</p>
                </div>
                <span class="material-symbols-outlined text-secondary-container text-3xl">event_available</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 relative z-10">
                <?php foreach($jadwalUjian as $ju): ?>
                <div class="bg-surface p-4 rounded-2xl border border-outline-variant/30 flex flex-col justify-between">
                    <div>
                        <div class="flex justify-between items-start mb-2">
                            <span class="px-2 py-1 <?= $ju['jenis_ujian'] === 'UTS' ? 'bg-tertiary-container text-on-tertiary-container' : 'bg-primary-container text-on-primary-container' ?> rounded-md font-label-xs font-bold uppercase tracking-wider">
                                <?= htmlspecialchars($ju['jenis_ujian']) ?>
                            </span>
                            <span class="text-secondary font-bold font-label-md flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span> <?= htmlspecialchars($ju['kode_ruangan']) ?></span>
                        </div>
                        <h4 class="font-title-md font-bold text-primary line-clamp-2"><?= htmlspecialchars($ju['mk_nama']) ?></h4>
                        <p class="text-xs text-on-surface-variant mt-1 opacity-80"><?= htmlspecialchars($ju['kode']) ?></p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-outline-variant/20 flex justify-between items-center">
                        <div class="flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-primary">calendar_month</span>
                            <span class="font-body-sm font-semibold"><?= date('d M Y', strtotime($ju['tanggal'])) ?></span>
                        </div>
                        <div class="flex items-center gap-2 text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-tertiary">schedule</span>
                            <span class="font-body-sm font-semibold"><?= substr($ju['jam_mulai'], 0, 5) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- ROW 3: PENGUMUMAN, EVENT, BEASISWA -->
    
    <!-- Akademik (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-md h-full flex flex-col shadow-sm">
            <h3 class="font-headline-md text-headline-md text-primary mb-stack-sm px-2 pt-2 flex items-center gap-2">
                <span class="material-symbols-outlined">campaign</span> Akademik
            </h3>
            <div class="flex-1 overflow-y-auto pr-2 space-y-3 h-[300px]">
                <?php if(empty($pengumumanAkademik)): ?>
                    <p class="text-on-surface-variant opacity-60 text-center mt-4">Belum ada info.</p>
                <?php else: foreach($pengumumanAkademik as $p): ?>
                    <div class="bg-white/50 p-3 rounded-xl hover:bg-white/80 transition-colors cursor-pointer border border-transparent hover:border-primary/20">
                        <h4 class="font-title-md font-semibold text-primary line-clamp-2"><?= htmlspecialchars($p['judul']) ?></h4>
                        <p class="font-body-sm text-on-surface-variant line-clamp-2 mt-1"><?= htmlspecialchars($p['isi']) ?></p>
                        <small class="text-xs text-on-surface-variant opacity-60 mt-2 block"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <!-- Kemahasiswaan/Event (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-md h-full flex flex-col shadow-sm border-t-4 border-t-secondary">
            <h3 class="font-headline-md text-headline-md text-secondary mb-stack-sm px-2 pt-2 flex items-center gap-2">
                <span class="material-symbols-outlined">celebration</span> Event Kampus
            </h3>
            <div class="flex-1 overflow-y-auto pr-2 space-y-3 h-[300px]">
                <?php if(empty($pengumumanEvent)): ?>
                    <p class="text-on-surface-variant opacity-60 text-center mt-4">Belum ada event terdekat.</p>
                <?php else: foreach($pengumumanEvent as $p): ?>
                    <div class="bg-secondary-container/20 p-3 rounded-xl hover:bg-secondary-container/40 transition-colors cursor-pointer border border-transparent hover:border-secondary/20">
                        <h4 class="font-title-md font-semibold text-secondary line-clamp-2"><?= htmlspecialchars($p['judul']) ?></h4>
                        <p class="font-body-sm text-on-surface-variant line-clamp-2 mt-1"><?= htmlspecialchars($p['isi']) ?></p>
                        <small class="text-xs text-on-surface-variant opacity-60 mt-2 block"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <!-- Beasiswa (Span 4) -->
    <section class="lg:col-span-4 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-md h-full flex flex-col shadow-sm border-t-4 border-t-tertiary">
            <h3 class="font-headline-md text-headline-md text-tertiary mb-stack-sm px-2 pt-2 flex items-center gap-2">
                <span class="material-symbols-outlined">school</span> Info Beasiswa
            </h3>
            <div class="flex-1 overflow-y-auto pr-2 space-y-3 h-[300px]">
                <?php if(empty($pengumumanBeasiswa)): ?>
                    <p class="text-on-surface-variant opacity-60 text-center mt-4">Belum ada info beasiswa.</p>
                <?php else: foreach($pengumumanBeasiswa as $p): ?>
                    <div class="bg-tertiary-container/20 p-3 rounded-xl hover:bg-tertiary-container/40 transition-colors cursor-pointer border border-transparent hover:border-tertiary/20">
                        <h4 class="font-title-md font-semibold text-tertiary line-clamp-2"><?= htmlspecialchars($p['judul']) ?></h4>
                        <p class="font-body-sm text-on-surface-variant line-clamp-2 mt-1"><?= htmlspecialchars($p['isi']) ?></p>
                        <small class="text-xs text-on-surface-variant opacity-60 mt-2 block"><?= date('d M Y', strtotime($p['created_at'])) ?></small>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

</div>

<script src="assets/js/html2canvas.min.js"></script>
<script src="assets/js/chart.min.js"></script>
<script>
// Chart.js Configuration
document.addEventListener('DOMContentLoaded', function() {
    const chartLabels = <?= json_encode($chartLabels) ?>;
    const chartDataRaw = <?= json_encode($chartData) ?>;
    const ctx = document.getElementById('ipkChart').getContext('2d');

    // Tampilkan empty state jika belum ada data IPK
    if (chartLabels.length === 0) {
        const canvas = document.getElementById('ipkChart');
        canvas.style.display = 'none';
        const emptyDiv = document.createElement('div');
        emptyDiv.className = 'flex flex-col items-center justify-center h-full text-on-surface-variant opacity-60';
        emptyDiv.innerHTML = `<span class="material-symbols-outlined text-5xl mb-3">monitoring</span>
                              <p class="font-body-md text-center">Belum ada data IPK.<br>Data akan muncul setelah nilai semester pertama diinput.</p>`;
        canvas.parentNode.appendChild(emptyDiv);
    } else {
    
    // Gradient for the line
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(25, 107, 80, 0.4)'); // primary color with opacity
    gradient.addColorStop(1, 'rgba(25, 107, 80, 0.0)');
    
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#a6b7b2' : '#51635e';
    const gridColor = isDarkMode ? 'rgba(166, 183, 178, 0.1)' : 'rgba(81, 99, 94, 0.1)';

    const ipkChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'IPK Kumulatif',
                data: chartDataRaw,
                borderColor: '#196b50', // primary
                backgroundColor: gradient,
                borderWidth: 3,
                pointBackgroundColor: '#fff',
                pointBorderColor: '#196b50',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true,
                tension: 0.4 // Smooth curve
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
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'IPK: ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: false,
                    min: 0,
                    max: 4.0,
                    grid: { color: gridColor, drawBorder: false },
                    ticks: { color: textColor, font: { family: 'Inter', size: 12 }, stepSize: 1.0 }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: textColor, font: { family: 'Inter', size: 12 } }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index',
            },
        }
    });

    // Observer for theme change
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === "class") {
                const dark = document.documentElement.classList.contains('dark');
                ipkChart.options.scales.x.ticks.color = dark ? '#a6b7b2' : '#51635e';
                ipkChart.options.scales.y.ticks.color = dark ? '#a6b7b2' : '#51635e';
                ipkChart.options.scales.y.grid.color = dark ? 'rgba(166, 183, 178, 0.1)' : 'rgba(81, 99, 94, 0.1)';
                ipkChart.options.plugins.tooltip.backgroundColor = dark ? '#1a3028' : '#fff';
                ipkChart.options.plugins.tooltip.titleColor = dark ? '#fff' : '#196b50';
                ipkChart.options.plugins.tooltip.bodyColor = dark ? '#fff' : '#196b50';
                ipkChart.update();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true });
    } // end else (chartLabels.length > 0)
});

function downloadKTM(event) {
    if(event) event.preventDefault();
    
    // Create a hidden container with fixed absolute dimensions for perfect rendering
    const container = document.createElement('div');
    container.innerHTML = `
        <div id="ktm-download-template" style="width: 856px; height: 540px; background: #fff; border-radius: 20px; font-family: 'Inter', sans-serif; display: flex; overflow: hidden; border: 2px solid #e1e3e4; position: relative; z-index: -9999;">
            <!-- Left Side -->
            <div style="width: 38%; background: #196b50; border-right: 8px solid #a5f2d0; padding: 30px; display: flex; flex-direction: column; justify-content: space-between; align-items: center; color: white;">
                <div style="text-align: center;">
                    <div style="font-size: 32px; font-weight: bold; letter-spacing: 2px; font-family: 'Outfit', sans-serif;">SAQUNA</div>
                    <div style="font-size: 13px; margin-top: 5px; opacity: 0.9; line-height: 1.3;">Sistem Akademik<br>Universitas Sains Al-Qur'an</div>
                </div>
                <div style="background: white; padding: 10px; border-radius: 10px; width: 150px; height: 150px;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode($mhs['nim']) ?>" style="width: 100%; height: 100%;" crossorigin="anonymous">
                </div>
            </div>
            <!-- Right Side -->
            <div style="flex: 1; padding: 30px 40px; display: flex; flex-direction: column; background: #f8fafb;">
                <div style="text-align: right; border-bottom: 2px solid rgba(25, 107, 80, 0.2); padding-bottom: 10px;">
                    <div style="font-size: 16px; color: #196b50; text-transform: uppercase; letter-spacing: 2px; font-weight: 600;"><?= htmlspecialchars($namaKampus) ?></div>
                    <div style="font-size: 28px; color: #196b50; font-weight: 900;">Kartu Tanda Mahasiswa</div>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                    <div style="color: #196b50; flex: 1; padding-right: 15px;">
                        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <tr>
                                <td style="width: 25%; font-size: 10px; opacity: 0.7; text-transform: uppercase; font-weight: 600; vertical-align: top; padding-bottom: 4px;">Nama</td>
                                <td style="width: 75%; font-size: 14px; font-weight: bold; vertical-align: top; padding-bottom: 4px; line-height: 1.2; word-wrap: break-word;">: <?= htmlspecialchars($mhs['nama']) ?></td>
                            </tr>
                            <tr>
                                <td style="font-size: 10px; opacity: 0.7; text-transform: uppercase; font-weight: 600; vertical-align: top; padding-bottom: 4px;">NIM</td>
                                <td style="font-size: 14px; font-weight: bold; vertical-align: top; padding-bottom: 4px; line-height: 1.2; word-wrap: break-word;">: <?= htmlspecialchars($mhs['nim']) ?></td>
                            </tr>
                            <tr>
                                <td style="font-size: 10px; opacity: 0.7; text-transform: uppercase; font-weight: 600; vertical-align: top; padding-bottom: 4px;">Fakultas</td>
                                <td style="font-size: 12px; font-weight: bold; vertical-align: top; padding-bottom: 4px; line-height: 1.2; word-wrap: break-word;">: <?= htmlspecialchars($mhs['fakultas'] ?? '-') ?></td>
                            </tr>
                            <tr>
                                <td style="font-size: 10px; opacity: 0.7; text-transform: uppercase; font-weight: 600; vertical-align: top; padding-bottom: 4px;">Prodi</td>
                                <td style="font-size: 12px; font-weight: bold; vertical-align: top; padding-bottom: 4px; line-height: 1.2; word-wrap: break-word;">: <?= htmlspecialchars($mhs['program_studi']) ?></td>
                            </tr>
                            <tr>
                                <td style="font-size: 10px; opacity: 0.7; text-transform: uppercase; font-weight: 600; vertical-align: top; padding-bottom: 4px;">Alamat</td>
                                <td style="font-size: 10px; font-weight: bold; vertical-align: top; padding-bottom: 4px; line-height: 1.3; word-wrap: break-word;">: <?= htmlspecialchars($mhs['alamat_asal'] ?: ($mhs['domisili'] ?: '-')) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div style="width: 160px; height: 213px; background-color: #eee; background-image: url('<?= htmlspecialchars($foto, ENT_QUOTES) ?>'); background-size: cover; background-position: center; background-repeat: no-repeat; border: 4px solid white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); flex-shrink: 0;">
                    </div>
                </div>
            </div>
        </div>
    `;
    document.body.appendChild(container);
    
    // Need a small timeout to allow images to load in the hidden DOM before capturing
    setTimeout(() => {
        const target = container.firstElementChild;
        html2canvas(target, { scale: 2, useCORS: true, backgroundColor: null }).then(canvas => {
            document.body.removeChild(container);
            const imgData = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = 'KTM_<?= htmlspecialchars($mhs['nim']) ?>.png';
            link.href = imgData;
            link.click();
        }).catch(err => {
            document.body.removeChild(container);
            console.error('Error generating KTM:', err);
            alert('Gagal mengunduh KTM. Silakan coba lagi.');
        });
    }, 500);
}

function switchTabHari(hariTarget) {
    const hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    hariList.forEach(hari => {
        const btn = document.getElementById('tab-btn-' + hari);
        const content = document.getElementById('tab-content-' + hari);
        if (btn && content) {
            if (hari === hariTarget) {
                btn.className = "px-3 py-1.5 rounded-lg font-label-md text-sm whitespace-nowrap transition-all bg-primary text-white shadow-md";
                content.className = "space-y-3 absolute w-full transition-opacity duration-300 opacity-100 z-10";
            } else {
                btn.className = "px-3 py-1.5 rounded-lg font-label-md text-sm whitespace-nowrap transition-all bg-surface-variant/30 text-on-surface-variant hover:bg-surface-variant";
                content.className = "space-y-3 absolute w-full transition-opacity duration-300 opacity-0 z-0 pointer-events-none";
            }
        }
    });
}

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
    
    fetch('mahasiswa_dashboard.php?dismiss_alert_ajax=' + hash)
        .catch(err => console.error('Error dismissing alert:', err));
}
</script>

<?php include 'components/footer.php'; ?>
