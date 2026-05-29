<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;
use Config\Database;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$pdo = Database::getConnection();
$semesterAktif = $repo->getSemesterAktif();

// Ambil KRS yang sudah disetujui semester ini
$stmtKrs = $pdo->prepare("
    SELECT k.id as krs_id, k.matakuliah_id, mk.nama as mk_nama, mk.kode, d.nama as dosen_nama, d.id as dosen_id
    FROM krs k
    JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
    JOIN dosen d ON k.dosen_id = d.id
    WHERE k.mahasiswa_id = ? AND k.semester_aktif = ?
    ORDER BY mk.nama ASC
");
$stmtKrs->execute([$mhs['id'], $semesterAktif]);
$allKrs = $stmtKrs->fetchAll();

// Pisahkan yang sudah dan belum diisi
$sudahEdom = [];
$belumEdom = [];

foreach ($allKrs as $k) {
    $stmtCek = $pdo->prepare("SELECT id FROM edom WHERE krs_id = ?");
    $stmtCek->execute([$k['krs_id']]);
    if ($stmtCek->fetch()) {
        $sudahEdom[] = $k;
    } else {
        $belumEdom[] = $k;
    }
}

$totalMK = count($allKrs);
$sudahCount = count($sudahEdom);
$persenSelesai = $totalMK > 0 ? round(($sudahCount / $totalMK) * 100) : 100;
$semuaSelesai = ($sudahCount >= $totalMK);

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$semuaSelesai) {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Token CSRF tidak valid.';
    } else {
        $dataEdom = $_POST['edom'] ?? [];
        if (empty($dataEdom)) {
            $errorMsg = 'Silakan isi semua formulir evaluasi sebelum mengirim.';
        } else {
            if ($repo->simpanEdom($dataEdom)) {
                header("Location: mahasiswa_edom.php?success=1");
                exit;
            } else {
                $errorMsg = 'Gagal menyimpan evaluasi. Terjadi kesalahan pada server.';
            }
        }
    }
}

if (isset($_GET['success'])) {
    $successMsg = "Evaluasi Dosen berhasil dikirim! Terima kasih atas penilaian Anda.";
    // Refresh data
    $semuaSelesai = true;
    $sudahCount = $totalMK;
    $persenSelesai = 100;
    $belumEdom = [];
}

// Dimensi EDoM
$dimensi = [
    ['kode' => 'penguasaan', 'label' => 'Penguasaan & Kesiapan Materi', 'desc' => 'Dosen menguasai materi dan siap mengajar', 'icon' => 'menu_book'],
    ['kode' => 'kejelasan', 'label' => 'Kejelasan Penyampaian', 'desc' => 'Dosen mampu menjelaskan materi dengan mudah dipahami', 'icon' => 'record_voice_over'],
    ['kode' => 'ketepatan_waktu', 'label' => 'Ketepatan Waktu & Kehadiran', 'desc' => 'Dosen hadir tepat waktu sesuai jadwal', 'icon' => 'schedule'],
    ['kode' => 'motivasi', 'label' => 'Kemampuan Memotivasi', 'desc' => 'Dosen mendorong mahasiswa untuk aktif belajar', 'icon' => 'emoji_events'],
    ['kode' => 'keterbukaan', 'label' => 'Keterbukaan & Responsif', 'desc' => 'Dosen terbuka terhadap pertanyaan dan masukan', 'icon' => 'handshake'],
];

$title = "Evaluasi Dosen (EDoM) — SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md">
    <h2 class="font-display-sm text-display-sm text-primary">Evaluasi Dosen (EDoM)</h2>
    <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">
        Penilaian Anda bersifat anonim. Dosen tidak mengetahui identitas pengisi formulir ini.
    </p>
</div>

<!-- Progress Bar -->
<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 mb-stack-lg">
    <div class="flex justify-between items-center mb-3">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">task_alt</span>
            <h3 class="font-headline-sm text-primary font-bold">Progress EDoM Semester <?= htmlspecialchars($semesterAktif) ?></h3>
        </div>
        <span class="font-headline-md text-primary font-bold"><?= $sudahCount ?>/<?= $totalMK ?> Selesai</span>
    </div>
    <div class="w-full bg-surface-variant/30 rounded-full h-4 mb-3 overflow-hidden">
        <div class="h-4 rounded-full transition-all duration-1000 <?= $semuaSelesai ? 'bg-success' : 'bg-primary' ?>" 
             style="width: <?= $persenSelesai ?>%"></div>
    </div>
    <div class="flex flex-wrap gap-3 mt-2">
        <?php foreach ($allKrs as $k): 
            $done = in_array($k, $sudahEdom) || !in_array($k, $belumEdom);
            // Re-check properly
            $isDone = false;
            foreach ($sudahEdom as $se) {
                if ($se['krs_id'] == $k['krs_id']) { $isDone = true; break; }
            }
        ?>
        <div class="flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg border <?= $isDone ? 'bg-success/10 border-success/30 text-success' : 'bg-surface-variant/20 border-outline-variant/30 text-on-surface-variant' ?>">
            <span class="material-symbols-outlined text-sm"><?= $isDone ? 'check_circle' : 'radio_button_unchecked' ?></span>
            <span class="font-bold"><?= htmlspecialchars($k['mk_nama']) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Alert -->
<?php if ($successMsg): ?>
<div class="bg-success/10 border border-success/30 text-success p-4 rounded-2xl mb-stack-md font-medium flex items-center gap-3">
    <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
    <div>
        <p class="font-bold"><?= htmlspecialchars($successMsg) ?></p>
        <a href="mahasiswa_khs.php" class="text-sm underline mt-1 inline-block">Lihat KHS Sekarang →</a>
    </div>
</div>
<?php endif; ?>

<?php if ($errorMsg): ?>
<div class="bg-error/10 border border-error/30 text-error p-4 rounded-2xl mb-stack-md font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($errorMsg) ?>
</div>
<?php endif; ?>

<!-- Formulir EDoM -->
<?php if ($semuaSelesai && empty($_GET['success'])): ?>
<div class="glass-panel rounded-3xl p-stack-xl shadow-sm border border-success/30 text-center">
    <span class="material-symbols-outlined text-6xl text-success mb-4" style="font-variation-settings: 'FILL' 1;">task_alt</span>
    <h3 class="font-headline-md text-success font-bold mb-2">Semua EDoM Telah Diisi!</h3>
    <p class="text-on-surface-variant max-w-md mx-auto mb-6">
        Anda telah menyelesaikan seluruh evaluasi dosen untuk semester <?= htmlspecialchars($semesterAktif) ?>. Terima kasih!
    </p>
    <div class="flex justify-center gap-4">
        <a href="mahasiswa_khs.php" class="bg-primary text-on-primary px-6 py-3 rounded-xl font-label-md font-bold shadow-md hover:bg-on-primary-fixed-variant transition-all">
            Lihat KHS
        </a>
        <a href="mahasiswa_dashboard.php" class="bg-surface-container-high text-on-surface px-6 py-3 rounded-xl font-label-md border border-outline-variant/30 hover:bg-surface-variant transition-all">
            Kembali ke Dashboard
        </a>
    </div>
</div>

<?php elseif (!$semuaSelesai): ?>

<form method="POST" action="" id="edomForm">
    <?= Auth::csrfField() ?>
    
    <?php foreach ($belumEdom as $kelas): ?>
    <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40 mb-stack-md">
        <!-- Header Kelas -->
        <div class="flex items-start justify-between mb-stack-md pb-4 border-b border-outline-variant/30">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="bg-primary/10 text-primary px-3 py-1 rounded-full font-label-md text-xs font-bold">
                        <?= htmlspecialchars($kelas['kode']) ?>
                    </span>
                    <span class="bg-secondary/10 text-secondary px-3 py-1 rounded-full font-label-md text-xs font-bold">
                        <?= htmlspecialchars($semesterAktif) ?>
                    </span>
                </div>
                <h3 class="font-headline-sm text-primary font-bold"><?= htmlspecialchars($kelas['mk_nama']) ?></h3>
                <p class="text-on-surface-variant font-body-sm flex items-center gap-1 mt-1">
                    <span class="material-symbols-outlined text-sm">person</span>
                    Dosen: <strong><?= htmlspecialchars($kelas['dosen_nama']) ?></strong>
                </p>
            </div>
            <div class="bg-tertiary/10 text-tertiary px-3 py-2 rounded-xl text-xs font-bold text-center">
                <span class="material-symbols-outlined text-lg block mb-0.5">lock</span>
                Anonim
            </div>
        </div>

        <!-- Dimensi Penilaian (Bintang) -->
        <div class="space-y-5 mb-stack-md">
            <p class="font-label-md text-sm text-on-surface-variant font-bold uppercase tracking-wider">Dimensi Pengajaran (klik bintang)</p>
            
            <?php foreach ($dimensi as $idx => $dim): ?>
            <div class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/20">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3 flex-1">
                        <div class="w-10 h-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-xl"><?= $dim['icon'] ?></span>
                        </div>
                        <div>
                            <p class="font-label-md font-bold text-on-surface"><?= $dim['label'] ?></p>
                            <p class="font-body-sm text-on-surface-variant text-xs mt-0.5"><?= $dim['desc'] ?></p>
                        </div>
                    </div>
                    <!-- Star Rating -->
                    <div class="flex gap-1 flex-shrink-0 star-rating" data-krs="<?= $kelas['krs_id'] ?>" data-dim="<?= $dim['kode'] ?>">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <label class="cursor-pointer" title="<?= $s ?> - <?= ['', 'Sangat Kurang', 'Kurang', 'Cukup', 'Baik', 'Sangat Baik'][$s] ?>">
                            <input type="radio" 
                                   name="edom[<?= $kelas['krs_id'] ?>][dimensi][<?= $dim['kode'] ?>]" 
                                   value="<?= $s ?>" 
                                   class="hidden star-input" 
                                   required>
                            <span class="material-symbols-outlined text-3xl text-on-surface-variant/30 hover:text-secondary transition-all star-icon" 
                                  data-val="<?= $s ?>"
                                  style="font-variation-settings: 'FILL' 0;">star</span>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Komentar Opsional -->
        <div>
            <label class="block font-label-md text-sm text-on-surface-variant font-bold uppercase tracking-wider mb-2">
                Komentar & Saran (Opsional)
            </label>
            <textarea 
                name="edom[<?= $kelas['krs_id'] ?>][komentar]" 
                rows="3"
                class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant dark:bg-white/5"
                placeholder="Tuliskan masukan atau saran Anda untuk dosen ini... (identitas Anda tidak akan diungkapkan)"></textarea>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Tombol Submit -->
    <div class="mt-4 flex justify-end gap-4">
        <a href="mahasiswa_dashboard.php" class="px-6 py-3 rounded-xl font-label-md text-on-surface-variant hover:bg-surface-container-low border border-outline-variant/30 transition-all">
            Batalkan
        </a>
        <button type="submit" 
                class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-8 py-3 rounded-xl shadow-lg transition-all active:scale-95 flex items-center gap-2">
            <span class="material-symbols-outlined">send</span>
            Kirim Evaluasi (<?= count($belumEdom) ?> MK)
        </button>
    </div>
</form>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Star Rating Interactive
    document.querySelectorAll('.star-rating').forEach(function(group) {
        const stars = group.querySelectorAll('.star-icon');
        const inputs = group.querySelectorAll('.star-input');
        
        stars.forEach(function(star, idx) {
            star.addEventListener('mouseover', function() {
                stars.forEach(function(s, i) {
                    s.style.fontVariationSettings = i <= idx ? "'FILL' 1" : "'FILL' 0";
                    s.classList.toggle('text-secondary', i <= idx);
                    s.classList.toggle('text-on-surface-variant/30', i > idx);
                });
            });
            
            star.addEventListener('mouseout', function() {
                const checkedInput = group.querySelector('.star-input:checked');
                const checkedVal = checkedInput ? parseInt(checkedInput.value) - 1 : -1;
                stars.forEach(function(s, i) {
                    s.style.fontVariationSettings = i <= checkedVal ? "'FILL' 1" : "'FILL' 0";
                    s.classList.toggle('text-secondary', i <= checkedVal);
                    s.classList.toggle('text-on-surface-variant/30', i > checkedVal);
                });
            });
            
            star.addEventListener('click', function() {
                inputs[idx].checked = true;
                stars.forEach(function(s, i) {
                    s.style.fontVariationSettings = i <= idx ? "'FILL' 1" : "'FILL' 0";
                    s.classList.toggle('text-secondary', i <= idx);
                    s.classList.toggle('text-on-surface-variant/30', i > idx);
                });
            });
        });
    });
});
</script>

<?php include 'components/footer.php'; ?>
