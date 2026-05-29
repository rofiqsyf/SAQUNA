<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireMahasiswa();

$pdo = Database::getConnection();

// Ambil semua event kalender akademik
$stmtEvents = $pdo->query("SELECT * FROM kalender_akademik ORDER BY tanggal_mulai ASC");
$events = $stmtEvents ? $stmtEvents->fetchAll() : [];

// Ambil semester aktif
$stmtSmt = $pdo->query("SELECT semester, tahun_ajaran FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$periodeAktif = $stmtSmt->fetch(\PDO::FETCH_ASSOC);
$semesterText = $periodeAktif ? $periodeAktif['semester'] . ' ' . $periodeAktif['tahun_ajaran'] : 'Belum Ditentukan';

// Bulan sekarang / dari query param
$tahun = (int)($_GET['tahun'] ?? date('Y'));
$bulan = (int)($_GET['bulan'] ?? date('n'));
if ($bulan < 1) { $bulan = 12; $tahun--; }
if ($bulan > 12) { $bulan = 1; $tahun++; }

// Buat mapping event per tanggal
$eventMap = [];
foreach ($events as $ev) {
    $start = strtotime($ev['tanggal_mulai']);
    $end   = strtotime($ev['tanggal_akhir']);
    // Loop setiap hari dalam rentang event
    for ($ts = $start; $ts <= $end; $ts += 86400) {
        $tgl = date('Y-m-d', $ts);
        $eventMap[$tgl][] = $ev;
    }
}

// Event mendatang (dari hari ini)
$today = date('Y-m-d');
$eventMendatang = array_filter($events, fn($e) => $e['tanggal_akhir'] >= $today);
$eventMendatang = array_slice(array_values($eventMendatang), 0, 6);

// Warna per jenis event
function getEventColor(string $jenis): string {
    return match(strtolower($jenis)) {
        'periode krs', 'perubahan krs' => 'bg-primary/80',
        'uts' => 'bg-tertiary/80',
        'uas' => 'bg-error/80',
        'wisuda' => 'bg-secondary/80',
        'libur' => 'bg-success/80',
        default => 'bg-on-surface-variant/60',
    };
}
function getEventColorBg(string $jenis): string {
    return match(strtolower($jenis)) {
        'periode krs', 'perubahan krs' => 'bg-primary/10 border-primary/30 text-primary',
        'uts' => 'bg-tertiary/10 border-tertiary/30 text-tertiary',
        'uas' => 'bg-error/10 border-error/30 text-error',
        'wisuda' => 'bg-secondary/10 border-secondary/30 text-secondary',
        'libur' => 'bg-success/10 border-success/30 text-success',
        default => 'bg-surface-variant/30 border-outline-variant/30 text-on-surface-variant',
    };
}

// Kalender bulan ini
$firstDay = mktime(0, 0, 0, $bulan, 1, $tahun);
$daysInMonth = (int)date('t', $firstDay);
$startDayOfWeek = (int)date('N', $firstDay); // 1=Senin, 7=Minggu

$namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$prevBulan = $bulan === 1 ? 12 : $bulan - 1;
$nextBulan = $bulan === 12 ? 1 : $bulan + 1;
$prevTahun = $bulan === 1 ? $tahun - 1 : $tahun;
$nextTahun = $bulan === 12 ? $tahun + 1 : $tahun;

$title = "Kalender Akademik — SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="font-display-sm text-display-sm text-primary">Kalender Akademik</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">Jadwal penting semester: KRS, ujian, libur, dan event akademik.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">

    <!-- Kalender Utama -->
    <div class="lg:col-span-8">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <!-- Header Navigasi Bulan -->
            <div class="flex items-center justify-between mb-6">
                <a href="?bulan=<?= $prevBulan ?>&tahun=<?= $prevTahun ?>" 
                   class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined">chevron_left</span>
                </a>
                <h3 class="font-headline-md text-primary font-bold">
                    <?= $namaBulan[$bulan] ?> <?= $tahun ?>
                </h3>
                <a href="?bulan=<?= $nextBulan ?>&tahun=<?= $nextTahun ?>"
                   class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center hover:bg-primary/20 transition-colors">
                    <span class="material-symbols-outlined">chevron_right</span>
                </a>
            </div>

            <!-- Header Hari -->
            <div class="grid grid-cols-7 mb-2">
                <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $hari): ?>
                <div class="text-center font-label-md text-xs font-bold text-on-surface-variant py-2">
                    <?= $hari ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Grid Kalender -->
            <div class="grid grid-cols-7 gap-1">
                <!-- Padding hari kosong di awal -->
                <?php for ($i = 1; $i < $startDayOfWeek; $i++): ?>
                <div class="aspect-square rounded-xl"></div>
                <?php endfor; ?>

                <!-- Hari-hari dalam bulan -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                    $tgl = sprintf('%04d-%02d-%02d', $tahun, $bulan, $day);
                    $isToday = $tgl === $today;
                    $dayEvents = $eventMap[$tgl] ?? [];
                    $hasEvent = !empty($dayEvents);
                ?>
                <div class="aspect-square rounded-xl flex flex-col items-center justify-start pt-1.5 px-1 relative group cursor-default
                            <?= $isToday ? 'bg-primary text-on-primary' : ($hasEvent ? 'bg-primary/5 hover:bg-primary/10' : 'hover:bg-surface-variant/20') ?> 
                            transition-colors overflow-hidden"
                     title="<?= $hasEvent ? implode(', ', array_column($dayEvents, 'nama_event')) : '' ?>">
                    <span class="font-label-md text-xs font-bold <?= $isToday ? 'text-on-primary' : 'text-on-surface' ?>">
                        <?= $day ?>
                    </span>
                    <?php if ($hasEvent): ?>
                    <div class="flex gap-0.5 mt-1 flex-wrap justify-center">
                        <?php foreach (array_slice($dayEvents, 0, 3) as $ev): ?>
                        <div class="w-1.5 h-1.5 rounded-full <?= $isToday ? 'bg-white/80' : getEventColor($ev['jenis_event']) ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Tooltip hover (untuk desktop) -->
                    <?php if ($hasEvent): ?>
                    <div class="hidden group-hover:block absolute top-full left-0 z-50 bg-surface shadow-xl rounded-xl p-3 min-w-[180px] border border-outline-variant/30 text-left">
                        <?php foreach ($dayEvents as $ev): ?>
                        <div class="flex items-start gap-2 mb-2 last:mb-0">
                            <div class="w-2 h-2 rounded-full <?= getEventColor($ev['jenis_event']) ?> mt-1.5 flex-shrink-0"></div>
                            <div>
                                <p class="text-xs font-bold text-on-surface"><?= htmlspecialchars($ev['nama_event']) ?></p>
                                <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($ev['jenis_event']) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>

            <!-- Legenda -->
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-outline-variant/20">
                <?php
                $legendItems = [
                    ['bg-primary/80', 'Periode KRS'],
                    ['bg-tertiary/80', 'UTS'],
                    ['bg-error/80', 'UAS'],
                    ['bg-secondary/80', 'Wisuda'],
                    ['bg-success/80', 'Libur'],
                    ['bg-on-surface-variant/60', 'Lainnya'],
                ];
                foreach ($legendItems as [$color, $label]):
                ?>
                <div class="flex items-center gap-2 text-xs text-on-surface-variant">
                    <div class="w-3 h-3 rounded-full <?= $color ?>"></div>
                    <span><?= $label ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar: Event Mendatang -->
    <div class="lg:col-span-4 space-y-gutter">
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">upcoming</span> Event Mendatang
            </h3>
            
            <?php if (empty($eventMendatang)): ?>
            <div class="text-center py-6 text-on-surface-variant opacity-60">
                <span class="material-symbols-outlined text-4xl mb-2">event_busy</span>
                <p>Tidak ada event mendatang.</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($eventMendatang as $ev): 
                    $mulaiTs = strtotime($ev['tanggal_mulai']);
                    $akhirTs = strtotime($ev['tanggal_akhir']);
                    $selisih = ceil(($mulaiTs - strtotime($today)) / 86400);
                    $isSoon = $selisih <= 7 && $selisih >= 0;
                    $isOngoing = $mulaiTs <= strtotime($today) && $akhirTs >= strtotime($today);
                ?>
                <div class="<?= getEventColorBg($ev['jenis_event']) ?> border rounded-2xl p-3">
                    <div class="flex justify-between items-start gap-2">
                        <div class="flex-1">
                            <p class="font-bold text-sm leading-tight"><?= htmlspecialchars($ev['nama_event']) ?></p>
                            <p class="text-xs opacity-80 mt-0.5"><?= htmlspecialchars($ev['jenis_event']) ?></p>
                            <p class="text-xs mt-2 opacity-70">
                                <?= date('d M', strtotime($ev['tanggal_mulai'])) ?>
                                <?php if ($ev['tanggal_mulai'] !== $ev['tanggal_akhir']): ?>
                                — <?= date('d M Y', strtotime($ev['tanggal_akhir'])) ?>
                                <?php else: ?>
                                <?= date(' Y', strtotime($ev['tanggal_mulai'])) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($isOngoing): ?>
                        <span class="bg-success text-white px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0">Berlangsung</span>
                        <?php elseif ($isSoon): ?>
                        <span class="bg-error/80 text-white px-2 py-0.5 rounded-full text-xs font-bold flex-shrink-0"><?= $selisih ?>H lagi</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <a href="?bulan=<?= date('n') ?>&tahun=<?= date('Y') ?>" 
               class="flex items-center justify-center gap-1 mt-4 text-xs text-primary font-bold hover:underline">
                <span class="material-symbols-outlined text-sm">today</span> Kembali ke Bulan Ini
            </a>
        </div>

        <!-- Semester Info -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 bg-gradient-to-br from-primary/5 to-secondary/5">
            <h3 class="font-headline-sm text-primary font-bold mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">info</span> Info Semester Aktif
            </h3>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Semester</span>
                    <span class="font-bold text-primary"><?= htmlspecialchars($semesterText) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-on-surface-variant">Status KRS</span>
                    <span class="font-bold text-success">Ditutup</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
