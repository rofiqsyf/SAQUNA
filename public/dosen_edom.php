<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$pdo = Database::getConnection();

// Ambil statistik EDoM real dari database
// Ambil semua kelas yang diampu dosen ini beserta data EDoM
$stmtEdom = $pdo->prepare("
    SELECT 
        mk.id as mk_id,
        mk.kode,
        mk.nama as mk_nama,
        k.semester_aktif,
        COUNT(DISTINCT k.id) as total_mahasiswa,
        COUNT(DISTINCT e.id) as total_edom,
        AVG(e.skala_nilai) as rata_rata,
        MIN(e.skala_nilai) as nilai_min,
        MAX(e.skala_nilai) as nilai_maks
    FROM krs k
    JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
    LEFT JOIN edom e ON e.krs_id = k.id
    WHERE k.dosen_id = ? AND k.status = 'Disetujui'
    GROUP BY mk.id, mk.nama, mk.kode, k.semester_aktif
    ORDER BY k.semester_aktif DESC, mk.nama ASC
");
$stmtEdom->execute([$dosenId]);
$edomPerMK = $stmtEdom->fetchAll();

// Total statistik keseluruhan
$totalResponden = 0;
$totalSkor = 0;
$totalMK = 0;
foreach ($edomPerMK as $e) {
    if ($e['total_edom'] > 0) {
        $totalResponden += $e['total_edom'];
        $totalSkor += ($e['rata_rata'] * $e['total_edom']);
        $totalMK++;
    }
}
$skorRataRata = $totalResponden > 0 ? round($totalSkor / $totalResponden, 2) : 0;

// Kategori
$kategori = 'Belum Ada Data';
if ($skorRataRata >= 4.5) $kategori = 'Sangat Baik';
elseif ($skorRataRata >= 3.5) $kategori = 'Baik';
elseif ($skorRataRata >= 2.5) $kategori = 'Cukup';
elseif ($skorRataRata > 0) $kategori = 'Kurang';

// Ambil komentar terbaru (anonim)
$stmtKomentar = $pdo->prepare("
    SELECT e.komentar_saran 
    FROM edom e
    JOIN krs k ON e.krs_id = k.id
    WHERE k.dosen_id = ? AND e.komentar_saran IS NOT NULL AND e.komentar_saran != ''
    ORDER BY e.id DESC
    LIMIT 5
");
$stmtKomentar->execute([$dosenId]);
$komentarList = $stmtKomentar->fetchAll(\PDO::FETCH_COLUMN);

// Distribusi nilai (1-5) untuk chart
$stmtDist = $pdo->prepare("
    SELECT e.skala_nilai, COUNT(*) as jumlah
    FROM edom e
    JOIN krs k ON e.krs_id = k.id
    WHERE k.dosen_id = ?
    GROUP BY e.skala_nilai
    ORDER BY e.skala_nilai ASC
");
$stmtDist->execute([$dosenId]);
$distribusiRaw = $stmtDist->fetchAll();
$distribusi = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($distribusiRaw as $d) {
    $distribusi[(int)$d['skala_nilai']] = (int)$d['jumlah'];
}

$title = "Evaluasi Dosen (EDoM) — SAQUNA";
$current_page = "dosen_edom.php";
include 'components/header.php';
?>

<div class="mb-stack-md">
    <h2 class="font-display-sm text-display-sm text-primary">Evaluasi Dosen (EDoM)</h2>
    <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">
        Rekap penilaian mahasiswa terhadap pengajaran Anda. Identitas mahasiswa bersifat anonim.
    </p>
</div>

<!-- Ringkasan Skor -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter mb-stack-lg">
    <!-- Skor Utama -->
    <section class="lg:col-span-4">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 h-full flex flex-col items-center justify-center text-center relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-secondary/5 to-transparent pointer-events-none"></div>
            <span class="material-symbols-outlined text-5xl text-secondary mb-3" style="font-variation-settings: 'FILL' 1;">star</span>
            <p class="font-display-lg text-6xl font-black text-secondary"><?= $totalResponden > 0 ? number_format($skorRataRata, 1) : '-' ?></p>
            <p class="font-label-md text-on-surface-variant mt-1">dari 5.0</p>
            <div class="flex gap-1 text-secondary mt-3 justify-center">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' <?= $i <= round($skorRataRata) ? '1' : '0' ?>;"><?= $i <= $skorRataRata + 0.5 ? 'star' : 'star' ?></span>
                <?php endfor; ?>
            </div>
            <p class="font-headline-sm text-secondary font-bold mt-3"><?= $kategori ?></p>
            <p class="font-body-sm text-on-surface-variant mt-1"><?= $totalResponden ?> Responden dari <?= count($edomPerMK) ?> Kelas</p>
        </div>
    </section>

    <!-- Distribusi Nilai -->
    <section class="lg:col-span-8">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 h-full">
            <h3 class="font-headline-md text-headline-md text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">bar_chart</span> Distribusi Penilaian
            </h3>
            <?php if ($totalResponden === 0): ?>
                <div class="flex flex-col items-center justify-center h-40 text-on-surface-variant opacity-50">
                    <span class="material-symbols-outlined text-5xl mb-2">sentiment_neutral</span>
                    <p>Belum ada data EDoM dari mahasiswa.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php 
                    $starLabels = [5 => 'Sangat Baik', 4 => 'Baik', 3 => 'Cukup', 2 => 'Kurang', 1 => 'Sangat Kurang'];
                    $maxVal = max($distribusi) ?: 1;
                    foreach (array_reverse([1,2,3,4,5], true) as $bintang): 
                        $jumlah = $distribusi[$bintang];
                        $persen = round(($jumlah / $totalResponden) * 100);
                        $color = $bintang >= 4 ? 'bg-success' : ($bintang == 3 ? 'bg-tertiary' : 'bg-error');
                    ?>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1 w-28 flex-shrink-0">
                            <span class="material-symbols-outlined text-secondary text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                            <span class="font-label-md text-sm font-bold"><?= $bintang ?></span>
                            <span class="font-body-sm text-xs text-on-surface-variant"><?= $starLabels[$bintang] ?></span>
                        </div>
                        <div class="flex-1 bg-surface-variant/30 rounded-full h-4 overflow-hidden">
                            <div class="<?= $color ?> h-4 rounded-full transition-all duration-700" style="width: <?= $persen ?>%"></div>
                        </div>
                        <span class="font-label-md text-sm font-bold text-on-surface-variant w-16 text-right"><?= $jumlah ?> (<?= $persen ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- EDoM Per Matakuliah -->
<section class="mb-stack-lg">
    <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
        <h3 class="font-headline-md text-headline-md text-primary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">library_books</span> Rekap Per Matakuliah
        </h3>
        
        <?php if (empty($edomPerMK)): ?>
            <div class="text-center py-8 text-on-surface-variant opacity-60">
                <span class="material-symbols-outlined text-4xl mb-2">school</span>
                <p>Belum ada kelas aktif yang terdata.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Matakuliah</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Semester</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Mhs</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Responden</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Skor Rata-rata</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($edomPerMK as $em): 
                            $skor = $em['total_edom'] > 0 ? round($em['rata_rata'], 2) : null;
                            $responRate = $em['total_mahasiswa'] > 0 ? round(($em['total_edom'] / $em['total_mahasiswa']) * 100) : 0;
                            $skorColor = $skor === null ? 'text-on-surface-variant' : ($skor >= 4 ? 'text-success' : ($skor >= 3 ? 'text-tertiary' : 'text-error'));
                        ?>
                        <tr class="hover:bg-surface-variant/10 transition-colors">
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <p class="font-bold text-on-surface"><?= htmlspecialchars($em['mk_nama']) ?></p>
                                <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($em['kode']) ?></p>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <span class="px-2 py-1 bg-secondary/10 text-secondary rounded-md text-xs font-bold">
                                    <?= htmlspecialchars($em['semester_aktif']) ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center font-bold"><?= $em['total_mahasiswa'] ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                                <span class="font-bold"><?= $em['total_edom'] ?></span>
                                <span class="text-on-surface-variant text-xs">(<?= $responRate ?>%)</span>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                                <?php if ($skor !== null): ?>
                                <div class="flex items-center justify-center gap-1">
                                    <span class="material-symbols-outlined text-secondary text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                                    <span class="font-bold <?= $skorColor ?>"><?= number_format($skor, 2) ?></span>
                                </div>
                                <?php else: ?>
                                <span class="text-on-surface-variant italic text-sm">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                                <?php if ($em['total_edom'] == 0): ?>
                                    <span class="px-2 py-1 bg-error/10 text-error border border-error/20 rounded-md text-xs font-bold uppercase">Belum Ada</span>
                                <?php elseif ($em['total_edom'] >= $em['total_mahasiswa']): ?>
                                    <span class="px-2 py-1 bg-success/10 text-success border border-success/20 rounded-md text-xs font-bold uppercase">Lengkap</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-tertiary/10 text-tertiary border border-tertiary/20 rounded-md text-xs font-bold uppercase">Parsial</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Komentar Mahasiswa (Anonim) -->
<?php if (!empty($komentarList)): ?>
<section class="mb-stack-lg">
    <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-secondary/30">
        <h3 class="font-headline-md text-headline-md text-secondary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">chat_bubble</span> Komentar & Saran Mahasiswa
            <span class="text-xs font-label-md bg-secondary/10 text-secondary px-2 py-1 rounded-full">Anonim</span>
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php foreach ($komentarList as $komen): ?>
            <div class="bg-secondary/5 border border-secondary/20 rounded-2xl p-4 relative">
                <span class="material-symbols-outlined absolute top-3 right-3 text-secondary/30 text-3xl" style="font-variation-settings: 'FILL' 1;">format_quote</span>
                <p class="font-body-md text-on-surface-variant italic pr-8">"<?= htmlspecialchars($komen) ?>"</p>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-xs text-on-surface-variant mt-4 opacity-60 flex items-center gap-1">
            <span class="material-symbols-outlined text-sm">lock</span>
            Identitas mahasiswa dijaga kerahasiaannya. Komentar ditampilkan secara anonim.
        </p>
    </div>
</section>
<?php endif; ?>

<!-- Info jika belum ada EDoM sama sekali -->
<?php if ($totalResponden === 0): ?>
<div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-outline-variant/30 text-center">
    <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-30 mb-4">sentiment_neutral</span>
    <h3 class="font-headline-sm text-on-surface-variant mb-2">Belum Ada Data EDoM</h3>
    <p class="text-on-surface-variant max-w-md mx-auto">
        Data evaluasi akan muncul di sini setelah mahasiswa mengisi formulir EDoM di akhir semester. 
        Pastikan mahasiswa mengisi EDoM sebelum batas waktu.
    </p>
</div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
