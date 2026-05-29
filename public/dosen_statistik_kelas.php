<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireDosen();

$repo = new DosenRepository();
$pdo = Database::getConnection();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}
$dosenId = (int)$dosen['id'];

// Data Kelas yang Diajar
$mkDosenIds = $repo->getDosenMataKuliahIds($dosenId);
$semuaMk = $repo->getAllMataKuliah();
$mkDosenFull = array_filter($semuaMk, fn(array $m) => in_array($m['id'], $mkDosenIds));

// Filter Aktif
$firstMk = reset($mkDosenFull);
$selected_mk = isset($_GET['matakuliah_id']) ? (int)$_GET['matakuliah_id'] : ($firstMk ? (int)$firstMk['id'] : null);
$stmtSmt = $pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
$selected_semester = $_GET['semester'] ?? $semesterAktif;

$mahasiswaKelas = [];
$stats = [
    'avg_tugas' => 0, 'avg_uts' => 0, 'avg_uas' => 0, 'avg_praktikum' => 0, 'avg_akhir' => 0,
    'tertinggi' => 0, 'terendah' => 100, 'distribusi' => ['A'=>0, 'B'=>0, 'C'=>0, 'D'=>0, 'E'=>0, '-'=>0],
    'total_mhs' => 0
];

if ($selected_mk) {
    $sqlMhs = "SELECT k.id as krs_id, k.nilai_huruf, m.nama as mahasiswa_nama, m.nim,
                      kn.nilai_tugas, kn.nilai_uts, kn.nilai_uas, kn.nilai_praktikum
               FROM krs k
               JOIN mahasiswa m ON k.mahasiswa_id = m.id
               LEFT JOIN komponen_nilai kn ON k.id = kn.krs_id
               WHERE k.dosen_id = ? AND k.matakuliah_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'";
    $stmtMhs = $pdo->prepare($sqlMhs);
    $stmtMhs->execute([$dosenId, $selected_mk, $selected_semester]);
    $mahasiswaKelas = $stmtMhs->fetchAll();
    
    if (count($mahasiswaKelas) > 0) {
        $sumTugas = $sumUts = $sumUas = $sumPrak = $sumAkhir = 0;
        foreach ($mahasiswaKelas as $m) {
            $t = (float)($m['nilai_tugas'] ?? 0);
            $u = (float)($m['nilai_uts'] ?? 0);
            $a = (float)($m['nilai_uas'] ?? 0);
            $p = (float)($m['nilai_praktikum'] ?? 0);
            
            $akhir = ($t * 0.2) + ($u * 0.3) + ($a * 0.4) + ($p * 0.1);
            
            $sumTugas += $t;
            $sumUts += $u;
            $sumUas += $a;
            $sumPrak += $p;
            $sumAkhir += $akhir;
            
            if ($akhir > $stats['tertinggi']) $stats['tertinggi'] = $akhir;
            if ($akhir < $stats['terendah']) $stats['terendah'] = $akhir;
            
            $huruf = $m['nilai_huruf'] ?? '-';
            if (isset($stats['distribusi'][$huruf])) {
                $stats['distribusi'][$huruf]++;
            }
        }
        $count = count($mahasiswaKelas);
        $stats['total_mhs'] = $count;
        $stats['avg_tugas'] = $sumTugas / $count;
        $stats['avg_uts'] = $sumUts / $count;
        $stats['avg_uas'] = $sumUas / $count;
        $stats['avg_praktikum'] = $sumPrak / $count;
        $stats['avg_akhir'] = $sumAkhir / $count;
        if ($stats['terendah'] === 100 && $stats['tertinggi'] === 0) $stats['terendah'] = 0; // Jika semua 0
    } else {
        $stats['terendah'] = 0;
    }
}

$title = "Statistik Nilai Kelas";
$current_page = "dosen_statistik_kelas.php";
include 'components/header.php';
?>

<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-black text-primary">Statistik Nilai Kelas</h1>
    <p class="text-on-surface/70 mt-1">Analitik persebaran dan rata-rata komponen nilai per mata kuliah.</p>
</div>

<!-- Filter Kelas -->
<div class="card p-4 rounded-2xl bg-surface shadow-sm border border-outline-variant/30 mb-6 flex flex-col md:flex-row gap-4 items-end">
    <form method="GET" class="flex flex-col md:flex-row gap-4 w-full">
        <div class="flex-1">
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Mata Kuliah</label>
            <select name="matakuliah_id" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none text-sm">
                <?php foreach ($mkDosenFull as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $selected_mk === (int)$mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-full md:w-48">
            <label class="block text-xs font-bold text-on-surface-variant uppercase mb-1">Semester</label>
            <select name="semester" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none text-sm">
                <option value="Ganjil" <?= $selected_semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $selected_semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                <option value="Pendek" <?= $selected_semester === 'Pendek' ? 'selected' : '' ?>>Pendek</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn-primary w-full md:w-auto h-10 px-6 font-bold flex items-center justify-center text-sm shadow-sm">Filter</button>
        </div>
    </form>
</div>

<?php if (empty($mahasiswaKelas)): ?>
    <div class="card p-10 bg-surface rounded-2xl border border-outline-variant/30 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30 mb-2">analytics</span>
        <p>Tidak ada data mahasiswa atau nilai untuk kelas ini.</p>
    </div>
<?php else: ?>

<!-- Overview Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="card p-4 bg-surface rounded-2xl border border-outline-variant/30 flex flex-col justify-center items-center relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-primary/10 rounded-full blur-xl"></div>
        <p class="text-xs font-bold text-on-surface-variant uppercase mb-1">Total Mahasiswa</p>
        <p class="text-3xl font-black text-on-surface"><?= $stats['total_mhs'] ?></p>
    </div>
    <div class="card p-4 bg-surface rounded-2xl border border-outline-variant/30 flex flex-col justify-center items-center relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-secondary/10 rounded-full blur-xl"></div>
        <p class="text-xs font-bold text-on-surface-variant uppercase mb-1">Rata-Rata Kelas</p>
        <p class="text-3xl font-black text-secondary"><?= number_format($stats['avg_akhir'], 1) ?></p>
    </div>
    <div class="card p-4 bg-surface rounded-2xl border border-outline-variant/30 flex flex-col justify-center items-center relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-success/10 rounded-full blur-xl"></div>
        <p class="text-xs font-bold text-on-surface-variant uppercase mb-1">Nilai Tertinggi</p>
        <p class="text-3xl font-black text-success"><?= number_format($stats['tertinggi'], 1) ?></p>
    </div>
    <div class="card p-4 bg-surface rounded-2xl border border-outline-variant/30 flex flex-col justify-center items-center relative overflow-hidden">
        <div class="absolute -right-4 -top-4 w-16 h-16 bg-error/10 rounded-full blur-xl"></div>
        <p class="text-xs font-bold text-on-surface-variant uppercase mb-1">Nilai Terendah</p>
        <p class="text-3xl font-black text-error"><?= number_format($stats['terendah'], 1) ?></p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Chart Distribusi -->
    <div class="card p-5 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 lg:col-span-1">
        <h3 class="font-bold text-primary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">pie_chart</span> Distribusi Nilai Huruf
        </h3>
        
        <div class="space-y-3">
            <?php 
            $colors = ['A' => 'bg-success', 'B' => 'bg-primary', 'C' => 'bg-tertiary', 'D' => 'bg-error', 'E' => 'bg-error', '-' => 'bg-surface-variant'];
            foreach ($stats['distribusi'] as $huruf => $count): 
                if ($count == 0 && $huruf == '-') continue;
                $pct = $stats['total_mhs'] > 0 ? ($count / $stats['total_mhs']) * 100 : 0;
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-bold">Nilai <?= $huruf ?></span>
                    <span class="text-on-surface-variant text-xs"><?= $count ?> Mhs (<?= round($pct) ?>%)</span>
                </div>
                <div class="w-full bg-surface-container-low h-2.5 rounded-full overflow-hidden">
                    <div class="<?= $colors[$huruf] ?? 'bg-primary' ?> h-full rounded-full" style="width: <?= $pct ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Radar Chart (Mock with bars) untuk Komponen Rata-Rata -->
    <div class="card p-5 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 lg:col-span-2">
        <h3 class="font-bold text-primary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">bar_chart</span> Rata-Rata Per Komponen
        </h3>
        
        <div class="flex h-48 items-end gap-2 mt-8 px-4 justify-between">
            <?php 
            $komponen = [
                'Tugas' => $stats['avg_tugas'],
                'UTS' => $stats['avg_uts'],
                'UAS' => $stats['avg_uas'],
                'Prak' => $stats['avg_praktikum']
            ];
            foreach ($komponen as $lbl => $val):
                $height = max(5, $val); // min 5%
            ?>
            <div class="flex flex-col items-center flex-1 group">
                <div class="text-xs font-bold text-on-surface-variant mb-2 opacity-0 group-hover:opacity-100 transition-opacity">
                    <?= number_format($val, 1) ?>
                </div>
                <div class="w-full max-w-[60px] bg-primary/20 rounded-t-lg relative group-hover:bg-primary/40 transition-colors" style="height: <?= $height ?>%">
                    <div class="absolute bottom-0 w-full bg-primary rounded-t-lg transition-all" style="height: <?= $height ?>%"></div>
                </div>
                <div class="mt-3 text-sm font-bold text-on-surface text-center w-full truncate"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Mahasiswa dengan Perhatian Khusus -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden mb-8">
    <div class="p-5 border-b border-outline-variant/30 bg-error/5">
        <h2 class="text-lg font-bold text-error flex items-center gap-2">
            <span class="material-symbols-outlined">warning</span> Mahasiswa Perhatian Khusus (D/E)
        </h2>
    </div>
    <div class="p-0">
        <?php 
        $mhsBahaya = array_filter($mahasiswaKelas, function($m) {
            return in_array($m['nilai_huruf'], ['D', 'E']);
        });
        
        if (empty($mhsBahaya)): ?>
        <div class="p-6 text-center text-on-surface-variant text-sm">
            Tidak ada mahasiswa dengan nilai D atau E di kelas ini.
        </div>
        <?php else: ?>
        <table class="w-full text-left">
            <tbody class="divide-y divide-outline-variant/10">
                <?php foreach ($mhsBahaya as $m): ?>
                <tr class="hover:bg-surface-variant/10">
                    <td class="px-5 py-3">
                        <p class="font-bold text-sm text-on-surface"><?= htmlspecialchars($m['mahasiswa_nama']) ?></p>
                        <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($m['nim']) ?></p>
                    </td>
                    <td class="px-5 py-3 text-right">
                        <span class="px-3 py-1 bg-error/10 text-error rounded-full text-xs font-bold border border-error/20">
                            Nilai <?= htmlspecialchars($m['nilai_huruf']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php include 'components/footer.php'; ?>
