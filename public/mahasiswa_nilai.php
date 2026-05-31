<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();
$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$statistik = $repo->getStatistikIPK($mhs['id']);

$labels = [];
$dataIps = [];
$dataIpk = [];

foreach ($statistik as $stat) {
    $labels[] = $stat['semester'];
    $dataIps[] = $stat['ips'];
    $dataIpk[] = $stat['ipk'];
}

$title = "Statistik Nilai - SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md flex justify-between items-end">
    <div>
        <h2 class="font-display-lg text-display-lg text-primary">Statistik Nilai</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Pantau perkembangan akademik Anda dari semester awal hingga saat ini.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- Chart Section (Span 8) -->
    <section class="lg:col-span-8">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm h-full flex flex-col border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-stack-md flex items-center gap-2">
                <span class="material-symbols-outlined">monitoring</span> Grafik Perkembangan IPK & IPS
            </h3>
            
            <?php if (empty($statistik)): ?>
                <div class="flex-1 flex items-center justify-center bg-surface-container-lowest rounded-2xl border border-outline-variant/30">
                    <p class="text-on-surface-variant opacity-60">Belum ada data nilai.</p>
                </div>
            <?php else: ?>
                <div class="flex-1 bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-4 relative" style="min-height: 400px;">
                    <canvas id="nilaiChart"></canvas>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Rangkuman / Summary (Span 4) -->
    <section class="lg:col-span-4">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm h-full flex flex-col border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-stack-md">Rangkuman Nilai</h3>
            
            <div class="space-y-4 flex-1">
                <?php if (empty($statistik)): ?>
                    <p class="text-on-surface-variant opacity-60">Data belum tersedia.</p>
                <?php else: 
                    $latest = end($statistik);
                ?>
                    <div class="bg-primary/5 border border-primary/20 rounded-2xl p-4 text-center">
                        <p class="font-label-md text-on-surface-variant mb-1">IPK Saat Ini</p>
                        <p class="font-display-lg text-primary font-bold"><?= number_format($latest['ipk'], 2) ?></p>
                    </div>
                    
                    <div class="bg-secondary-container/20 border border-secondary/20 rounded-2xl p-4 text-center">
                        <p class="font-label-md text-on-surface-variant mb-1">Total SKS Diperoleh</p>
                        <p class="font-display-lg text-secondary font-bold"><?= $latest['sks_total'] ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <a href="mahasiswa_transkrip.php" class="w-full mt-stack-md py-3 bg-primary hover:bg-on-primary-fixed-variant text-on-primary rounded-xl font-title-sm text-center shadow-lg transition-all">
                Lihat Detail Transkrip
            </a>
        </div>
    </section>

    <!-- Tabel Rekam Jejak (Span 12) -->
    <section class="col-span-1 lg:col-span-12 mt-stack-md">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-stack-md">Rekam Jejak Semester</h3>
            
            <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30 bg-white/50">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-primary-container/20 border-b border-outline-variant/30">
                        <tr>
                            <th class="p-4 font-label-md text-primary">Semester</th>
                            <th class="p-4 font-label-md text-primary text-center">SKS Diambil</th>
                            <th class="p-4 font-label-md text-primary text-center">Total SKS (Kumulatif)</th>
                            <th class="p-4 font-label-md text-primary text-center">IPS</th>
                            <th class="p-4 font-label-md text-primary text-center">IPK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statistik)): ?>
                            <tr>
                                <td colspan="5" class="p-6 text-center text-on-surface-variant opacity-60">Tidak ada data.</td>
                            </tr>
                        <?php else: foreach ($statistik as $stat): ?>
                            <tr class="border-b border-outline-variant/20 hover:bg-white/60 transition-colors">
                                <td class="p-4 font-body-md font-semibold text-on-surface"><?= htmlspecialchars($stat['semester']) ?></td>
                                <td class="p-4 text-center font-body-md"><?= $stat['sks_semester'] ?></td>
                                <td class="p-4 text-center font-body-md"><?= $stat['sks_total'] ?></td>
                                <td class="p-4 text-center font-body-md font-bold text-secondary"><?= number_format($stat['ips'], 2) ?></td>
                                <td class="p-4 text-center font-body-md font-bold text-primary"><?= number_format($stat['ipk'], 2) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<!-- Masukkan Chart.js -->
<script src="assets/js/chart.min.js"></script>
<script>
    <?php if (!empty($statistik)): ?>
    const ctx = document.getElementById('nilaiChart').getContext('2d');
    const labels = <?= json_encode($labels) ?>;
    const dataIps = <?= json_encode($dataIps) ?>;
    const dataIpk = <?= json_encode($dataIpk) ?>;

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'IPK (Kumulatif)',
                    data: dataIpk,
                    borderColor: '#196b50', // Primary
                    backgroundColor: 'rgba(25, 107, 80, 0.1)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#196b50',
                    pointRadius: 5
                },
                {
                    label: 'IPS (Semester)',
                    data: dataIps,
                    borderColor: '#3a6755', // Secondary
                    borderWidth: 2,
                    borderDash: [5, 5],
                    tension: 0.3,
                    fill: false,
                    pointBackgroundColor: '#3a6755',
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    min: 0,
                    max: 4,
                    ticks: {
                        stepSize: 0.5
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            }
        }
    });
    <?php endif; ?>
</script>

<?php include 'components/footer.php'; ?>
