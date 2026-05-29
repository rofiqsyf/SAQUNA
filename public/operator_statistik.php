<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

Auth::requireOperator();

$db = \Config\Database::getConnection();
$current_page = 'operator_statistik.php';
$page_title = 'Statistik Akademik';

// --- DATA FETCHING ---

// 1. Statistik Mahasiswa per Program Studi
$stmtProdi = $db->query("SELECT program_studi, COUNT(*) as total FROM mahasiswa GROUP BY program_studi");
$prodiStats = $stmtProdi->fetchAll(PDO::FETCH_ASSOC);

$prodiLabels = [];
$prodiData = [];
foreach ($prodiStats as $row) {
    $prodiLabels[] = $row['program_studi'] ?: 'Tidak Diketahui';
    $prodiData[] = (int)$row['total'];
}

// 2. Statistik Status UKT
$stmtUkt = $db->query("SELECT status, COUNT(*) as total FROM tagihan_pembayaran GROUP BY status");
$uktStats = $stmtUkt->fetchAll(PDO::FETCH_ASSOC);

$uktLabels = [];
$uktData = [];
foreach ($uktStats as $row) {
    $uktLabels[] = $row['status'];
    $uktData[] = (int)$row['total'];
}

// 3. Statistik Status KRS
$stmtSmt = $db->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';

$stmtTotalMhs = $db->query("SELECT COUNT(*) FROM mahasiswa");
$totalMhs = (int)$stmtTotalMhs->fetchColumn();

$stmtKrs = $db->prepare("
    SELECT 
        COUNT(*) as total_mengisi, 
        SUM(CASE WHEN status = 'Disetujui' THEN 1 ELSE 0 END) as disetujui,
        SUM(CASE WHEN status = 'Menunggu' THEN 1 ELSE 0 END) as menunggu,
        SUM(CASE WHEN status = 'Ditolak' THEN 1 ELSE 0 END) as ditolak
    FROM (SELECT DISTINCT mahasiswa_id, status FROM krs WHERE semester_aktif = ?) k
");
$stmtKrs->execute([$semesterAktif]);
$krsStats = $stmtKrs->fetch(PDO::FETCH_ASSOC);
$mengisiKrs = (int)($krsStats['total_mengisi'] ?? 0);
$belumMengisi = max(0, $totalMhs - $mengisiKrs);

$krsLabels = ['Belum Mengisi', 'Disetujui', 'Menunggu', 'Ditolak'];
$krsData = [
    $belumMengisi,
    (int)($krsStats['disetujui'] ?? 0),
    (int)($krsStats['menunggu'] ?? 0),
    (int)($krsStats['ditolak'] ?? 0)
];

// 4. Statistik Dosen
$stmtDosen = $db->query("SELECT status, COUNT(*) as total FROM dosen GROUP BY status");
$dosenStats = $stmtDosen->fetchAll(PDO::FETCH_ASSOC);

$dosenLabels = [];
$dosenData = [];
foreach ($dosenStats as $row) {
    $dosenLabels[] = $row['status'] ?: 'Tidak Diketahui';
    $dosenData[] = (int)$row['total'];
}

// 5. Distribusi IPK Mahasiswa
$stmtIPK = $db->query("
    SELECT 
        m.id,
        ROUND(
            SUM(mk.sks * CASE k.nilai_huruf
                WHEN 'A' THEN 4.0 WHEN 'B' THEN 3.0 WHEN 'C' THEN 2.0 WHEN 'D' THEN 1.0 ELSE 0 END
            ) / NULLIF(SUM(mk.sks), 0), 2
        ) as ipk
    FROM mahasiswa m
    LEFT JOIN krs k ON k.mahasiswa_id = m.id AND k.nilai_huruf IS NOT NULL
    LEFT JOIN mata_kuliah mk ON mk.id = k.matakuliah_id
    GROUP BY m.id
");
$ipkRows = $stmtIPK->fetchAll(PDO::FETCH_ASSOC);

$ipkBuckets = ['0.0-1.0' => 0, '1.0-2.0' => 0, '2.0-2.5' => 0, '2.5-3.0' => 0, '3.0-3.5' => 0, '3.5-4.0' => 0];
$ipkSum = 0;
$ipkCount = 0;
foreach ($ipkRows as $r) {
    $ipk = (float)($r['ipk'] ?? 0);
    if ($r['ipk'] === null) continue;
    $ipkSum += $ipk;
    $ipkCount++;
    if ($ipk < 1.0) $ipkBuckets['0.0-1.0']++;
    elseif ($ipk < 2.0) $ipkBuckets['1.0-2.0']++;
    elseif ($ipk < 2.5) $ipkBuckets['2.0-2.5']++;
    elseif ($ipk < 3.0) $ipkBuckets['2.5-3.0']++;
    elseif ($ipk < 3.5) $ipkBuckets['3.0-3.5']++;
    else $ipkBuckets['3.5-4.0']++;
}
$ipkRataRata = $ipkCount > 0 ? round($ipkSum / $ipkCount, 2) : 0;

// 6. Matakuliah dengan tingkat kegagalan tertinggi
$stmtMKGagal = $db->query("
    SELECT mk.nama, mk.kode,
           COUNT(k.id) as total_peserta,
           SUM(CASE WHEN k.nilai_huruf IN ('D', 'E') THEN 1 ELSE 0 END) as gagal,
           ROUND(SUM(CASE WHEN k.nilai_huruf IN ('D', 'E') THEN 1 ELSE 0 END) / COUNT(k.id) * 100, 1) as persen_gagal
    FROM krs k
    JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
    WHERE k.nilai_huruf IS NOT NULL AND k.status = 'Disetujui'
    GROUP BY mk.id
    HAVING total_peserta >= 3
    ORDER BY persen_gagal DESC
    LIMIT 8
");
$mkGagalList = $stmtMKGagal ? $stmtMKGagal->fetchAll(PDO::FETCH_ASSOC) : [];

// 7. Progress Tugas Akhir
$stmtTA = $db->query("SELECT status, COUNT(*) as total FROM tugas_akhir GROUP BY status");
$taStats = $stmtTA->fetchAll(PDO::FETCH_ASSOC);
$taByStatus = [];
foreach ($taStats as $ts) { $taByStatus[$ts['status']] = (int)$ts['total']; }
$totalTA = array_sum($taByStatus);

require_once __DIR__ . '/components/header.php';
?>

<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-primary">Statistik Akademik</h1>
            <p class="text-on-surface/70 mt-1">Laporan dan analisis data akademik universitas secara keseluruhan.</p>
        </div>
        <button onclick="window.print()" class="btn-primary">
            <span class="material-symbols-outlined">print</span>
            Cetak Laporan
        </button>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Chart 1: Mahasiswa per Prodi -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 flex flex-col h-[400px]">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">groups</span>
                Distribusi Mahasiswa per Program Studi
            </h2>
            <div class="flex-1 relative w-full h-full min-h-[250px]">
                <canvas id="chartProdi"></canvas>
            </div>
        </div>

        <!-- Chart 2: Status Pembayaran UKT -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 flex flex-col h-[400px]">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">payments</span>
                Status Pembayaran UKT Mahasiswa
            </h2>
            <div class="flex-1 relative w-full h-full min-h-[250px]">
                <canvas id="chartUkt"></canvas>
            </div>
        </div>

        <!-- Chart 3: Status KRS -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 flex flex-col h-[400px]">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">fact_check</span>
                Status Pengisian KRS (Semester <?= htmlspecialchars($semesterAktif) ?>)
            </h2>
            <div class="flex-1 relative w-full h-full min-h-[250px]">
                <canvas id="chartKrs"></canvas>
            </div>
        </div>

        <!-- Chart 4: Status Dosen -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 flex flex-col h-[400px]">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">school</span>
                Distribusi Status Dosen
            </h2>
            <div class="flex-1 relative w-full h-full min-h-[250px]">
                <canvas id="chartDosen"></canvas>
            </div>
        </div>

    </div>

    <!-- Analytics Mendalam -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">

        <!-- Distribusi IPK -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30">
            <div class="flex justify-between items-start mb-4">
                <h2 class="text-lg font-bold text-primary flex items-center gap-2">
                    <span class="material-symbols-outlined text-secondary">bar_chart_4_bars</span>
                    Distribusi IPK Mahasiswa
                </h2>
                <div class="text-right">
                    <p class="text-2xl font-black text-secondary"><?= number_format($ipkRataRata, 2) ?></p>
                    <p class="text-xs text-on-surface/60">Rata-rata IPK</p>
                </div>
            </div>
            <div class="space-y-2">
                <?php
                $bucketColors = ['0.0-1.0' => 'bg-error', '1.0-2.0' => 'bg-error/60', '2.0-2.5' => 'bg-tertiary', '2.5-3.0' => 'bg-tertiary/70', '3.0-3.5' => 'bg-success/70', '3.5-4.0' => 'bg-success'];
                $maxBucket = max(array_values($ipkBuckets)) ?: 1;
                foreach ($ipkBuckets as $range => $count):
                    $pct = round(($count / max($maxBucket, 1)) * 100);
                ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-bold text-on-surface-variant w-20 text-right flex-shrink-0"><?= $range ?></span>
                    <div class="flex-1 bg-surface-variant/30 rounded-full h-5 overflow-hidden">
                        <div class="<?= $bucketColors[$range] ?> h-5 rounded-full flex items-center justify-end pr-2 transition-all duration-700"
                             style="width: <?= max($pct, $count > 0 ? 8 : 0) ?>%">
                            <?php if ($count > 0): ?>
                            <span class="text-white text-xs font-bold"><?= $count ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="text-xs text-on-surface-variant w-12 text-right"><?= $count ?> mhs</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- MK Kegagalan Tertinggi -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-error">trending_down</span>
                Matakuliah Tingkat Kegagalan Tertinggi
            </h2>
            <?php if (empty($mkGagalList)): ?>
            <p class="text-on-surface-variant opacity-60 text-sm text-center py-8">Belum ada data nilai yang cukup.</p>
            <?php else: ?>
            <div class="space-y-2">
                <?php foreach ($mkGagalList as $mk):
                    $pGagal = (float)$mk['persen_gagal'];
                    $barColor = $pGagal >= 50 ? 'bg-error' : ($pGagal >= 25 ? 'bg-tertiary' : 'bg-success/60');
                ?>
                <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-surface-variant/20 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-sm text-on-surface truncate"><?= htmlspecialchars($mk['nama']) ?></p>
                        <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($mk['kode']) ?> · <?= $mk['total_peserta'] ?> peserta</p>
                    </div>
                    <div class="flex items-center gap-2 w-36 flex-shrink-0">
                        <div class="flex-1 bg-surface-variant/30 rounded-full h-3 overflow-hidden">
                            <div class="<?= $barColor ?> h-3 rounded-full" style="width: <?= min(100, $pGagal) ?>%"></div>
                        </div>
                        <span class="text-xs font-black <?= $pGagal >= 50 ? 'text-error' : ($pGagal >= 25 ? 'text-tertiary' : 'text-success') ?> w-12 text-right"><?= $pGagal ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Progress TA -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">history_edu</span>
                Progress Tugas Akhir / Skripsi
            </h2>
            <?php if ($totalTA === 0): ?>
            <p class="text-on-surface-variant opacity-60 text-sm">Belum ada data TA.</p>
            <?php else: ?>
            <div class="grid grid-cols-2 gap-4">
                <?php
                $taColors = [
                    'Diajukan' => ['bg-tertiary/10 text-tertiary border-tertiary/20', 'pending'],
                    'Disetujui' => ['bg-primary/10 text-primary border-primary/20', 'check_circle'],
                    'Sedang Bimbingan' => ['bg-secondary/10 text-secondary border-secondary/20', 'school'],
                    'Selesai' => ['bg-success/10 text-success border-success/20', 'task_alt'],
                    'Ditolak' => ['bg-error/10 text-error border-error/20', 'cancel'],
                ];
                foreach ($taColors as $status => [$cls, $icon]):
                    $count = $taByStatus[$status] ?? 0;
                    if ($count == 0) continue;
                ?>
                <div class="<?= $cls ?> border rounded-2xl p-4 text-center">
                    <span class="material-symbols-outlined text-3xl mb-1" style="font-variation-settings: 'FILL' 1"><?= $icon ?></span>
                    <p class="text-2xl font-black"><?= $count ?></p>
                    <p class="text-xs font-bold opacity-80"><?= $status ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-on-surface-variant mt-4">Total <?= $totalTA ?> mahasiswa dalam proses TA</p>
            <?php endif; ?>
        </div>

        <!-- Summary Card -->
        <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30">
            <h2 class="text-lg font-bold text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">summarize</span>
                Ringkasan Eksekutif
            </h2>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-primary/5 rounded-xl p-3 text-center">
                    <p class="text-2xl font-black text-primary"><?= $totalMhs ?></p>
                    <p class="text-xs text-on-surface-variant">Total Mahasiswa</p>
                </div>
                <div class="bg-secondary/5 rounded-xl p-3 text-center">
                    <p class="text-2xl font-black text-secondary"><?= number_format($ipkRataRata, 2) ?></p>
                    <p class="text-xs text-on-surface-variant">Rata-rata IPK</p>
                </div>
                <div class="bg-success/5 rounded-xl p-3 text-center">
                    <p class="text-2xl font-black text-success"><?= (int)($krsStats['disetujui'] ?? 0) ?></p>
                    <p class="text-xs text-on-surface-variant">KRS Disetujui</p>
                </div>
                <div class="bg-tertiary/5 rounded-xl p-3 text-center">
                    <p class="text-2xl font-black text-tertiary"><?= $totalTA ?></p>
                    <p class="text-xs text-on-surface-variant">Mahasiswa TA</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-6 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 mt-6 print-hidden">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-full bg-secondary/10 flex items-center justify-center flex-shrink-0">
                <span class="material-symbols-outlined text-secondary text-2xl">info</span>
            </div>
            <div>
                <h3 class="font-bold text-lg text-primary">Informasi Statistik</h3>
                <p class="text-on-surface/80 mt-1">
                    Statistik di atas dihitung secara <em>real-time</em> berdasarkan data yang ada di database saat ini. Anda dapat mencetak halaman ini sebagai laporan dengan menekan tombol <strong>Cetak Laporan</strong> di sudut kanan atas.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js Library -->
<script src="assets/js/chart.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    
    // Shared Chart Options for consistency
    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    font: {
                        family: "'Outfit', sans-serif"
                    }
                }
            }
        }
    };

    // 1. Chart Prodi (Bar)
    const ctxProdi = document.getElementById('chartProdi').getContext('2d');
    new Chart(ctxProdi, {
        type: 'bar',
        data: {
            labels: <?= json_encode($prodiLabels) ?>,
            datasets: [{
                label: 'Jumlah Mahasiswa',
                data: <?= json_encode($prodiData) ?>,
                backgroundColor: 'rgba(25, 107, 80, 0.7)',
                borderColor: 'rgba(25, 107, 80, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            ...chartOptions,
            plugins: {
                legend: { display: false } // Hide legend for single dataset bar chart
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    // 2. Chart UKT (Pie)
    const ctxUkt = document.getElementById('chartUkt').getContext('2d');
    new Chart(ctxUkt, {
        type: 'pie',
        data: {
            labels: <?= json_encode($uktLabels) ?>,
            datasets: [{
                data: <?= json_encode($uktData) ?>,
                backgroundColor: [
                    'rgba(46, 125, 50, 0.8)', // Lunas - Green
                    'rgba(198, 40, 40, 0.8)', // Belum Lunas - Red
                    'rgba(249, 168, 37, 0.8)', // Pending - Yellow
                ],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    // 3. Chart KRS (Doughnut)
    const ctxKrs = document.getElementById('chartKrs').getContext('2d');
    new Chart(ctxKrs, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($krsLabels) ?>,
            datasets: [{
                data: <?= json_encode($krsData) ?>,
                backgroundColor: [
                    'rgba(158, 158, 158, 0.8)', // Belum - Grey
                    'rgba(46, 125, 50, 0.8)',   // Disetujui - Green
                    'rgba(249, 168, 37, 0.8)',  // Menunggu - Yellow
                    'rgba(198, 40, 40, 0.8)'    // Ditolak - Red
                ],
                borderWidth: 1
            }]
        },
        options: chartOptions
    });

    // 4. Chart Dosen (Bar Horizontal)
    const ctxDosen = document.getElementById('chartDosen').getContext('2d');
    new Chart(ctxDosen, {
        type: 'bar',
        data: {
            labels: <?= json_encode($dosenLabels) ?>,
            datasets: [{
                label: 'Jumlah Dosen',
                data: <?= json_encode($dosenData) ?>,
                backgroundColor: 'rgba(230, 161, 5, 0.8)', // Secondary color
                borderColor: 'rgba(230, 161, 5, 1)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            ...chartOptions,
            indexAxis: 'y', // Make it horizontal
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });

});
</script>

<style>
@media print {
    body {
        background-color: white !important;
    }
    .print-hidden {
        display: none !important;
    }
    nav, header, footer {
        display: none !important;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }
}
</style>

<?php require_once __DIR__ . '/components/footer.php'; ?>
