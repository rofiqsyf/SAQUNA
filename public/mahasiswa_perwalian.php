<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;
use Config\Database;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaProfile($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$pdo = Database::getConnection();

// Ambil info dosen wali
$dosenWali = null;
if (!empty($mhs['dosen_wali_id'])) {
    $stmtDosen = $pdo->prepare("SELECT * FROM dosen WHERE id = ?");
    $stmtDosen->execute([$mhs['dosen_wali_id']]);
    $dosenWali = $stmtDosen->fetch();
}

// Ambil riwayat catatan perwalian
$stmtCatatan = $pdo->prepare("
    SELECT c.*, d.nama as dosen_nama 
    FROM catatan_perwalian c 
    JOIN dosen d ON c.dosen_wali_id = d.id
    WHERE c.mahasiswa_id = ? 
    ORDER BY c.waktu_bimbingan DESC
");
$stmtCatatan->execute([$mhs['id']]);
$catatanList = $stmtCatatan->fetchAll();

// Ambil riwayat KRS per semester (semua, dengan status)
$stmtKrs = $pdo->prepare("
    SELECT k.semester_aktif, k.status, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama
    FROM krs k
    JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
    JOIN dosen d ON k.dosen_id = d.id
    WHERE k.mahasiswa_id = ?
    ORDER BY k.semester_aktif DESC, mk.nama ASC
");
$stmtKrs->execute([$mhs['id']]);
$allKrs = $stmtKrs->fetchAll();

// Group KRS by semester
$krsBySemester = [];
foreach ($allKrs as $k) {
    $krsBySemester[$k['semester_aktif']][] = $k;
}

$title = "Riwayat Perwalian — SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md">
    <h2 class="font-display-sm text-display-sm text-primary">Riwayat Perwalian</h2>
    <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">Catatan bimbingan dan riwayat KRS dari Dosen Wali Anda.</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- Kolom Kiri: Info Dosen Wali -->
    <div class="lg:col-span-4 space-y-gutter">
        <!-- Kartu Dosen Wali -->
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">person_pin</span> Dosen Wali Akademik
            </h3>
            <?php if ($dosenWali): ?>
            <div class="flex flex-col items-center text-center">
                <?php if (!empty($dosenWali['foto'])): ?>
                <img src="<?= htmlspecialchars($dosenWali['foto']) ?>" 
                     class="w-24 h-24 rounded-full object-cover border-4 border-primary/20 mb-4" alt="Foto Dosen Wali">
                <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center mb-4">
                    <span class="font-display-md text-on-primary text-3xl font-black">
                        <?= strtoupper(substr($dosenWali['nama'], 0, 1)) ?>
                    </span>
                </div>
                <?php endif; ?>
                <h4 class="font-headline-sm text-on-surface font-bold"><?= htmlspecialchars($dosenWali['nama']) ?></h4>
                <p class="text-on-surface-variant text-sm mt-1"><?= htmlspecialchars($dosenWali['nidn'] ?? '-') ?></p>
                <span class="bg-primary/10 text-primary px-3 py-1 rounded-full text-xs font-bold mt-2">
                    <?= htmlspecialchars($dosenWali['program_studi'] ?? '-') ?>
                </span>
                <div class="mt-4 w-full space-y-2 text-left">
                    <?php if (!empty($dosenWali['email'])): ?>
                    <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">mail</span>
                        <?= htmlspecialchars($dosenWali['email']) ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($dosenWali['no_hp'])): ?>
                    <div class="flex items-center gap-2 text-sm text-on-surface-variant">
                        <span class="material-symbols-outlined text-sm">phone</span>
                        <?= htmlspecialchars($dosenWali['no_hp']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-8 text-on-surface-variant opacity-60">
                <span class="material-symbols-outlined text-5xl mb-2">person_off</span>
                <p>Belum ditugaskan Dosen Wali.</p>
                <p class="text-xs mt-1">Hubungi Operator/BAK untuk info lebih lanjut.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Statistik Singkat -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary font-bold mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">analytics</span> Ringkasan Studi
            </h3>
            <?php
            $totalSks = 0;
            $totalMkLulus = 0;
            $totalBobot = 0;
            $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
            foreach ($allKrs as $k) {
                if ($k['status'] === 'Disetujui') {
                    // Fetch nilai
                }
            }
            $progres = $repo->getProgressStudi($mhs['id']);
            ?>
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-primary/5 rounded-xl p-3 text-center">
                    <p class="font-headline-md text-primary font-black"><?= $progres['sks_lulus'] ?? 0 ?></p>
                    <p class="text-xs text-on-surface-variant">SKS Lulus</p>
                </div>
                <div class="bg-secondary/5 rounded-xl p-3 text-center">
                    <p class="font-headline-md text-secondary font-black"><?= count($krsBySemester) ?></p>
                    <p class="text-xs text-on-surface-variant">Semester Dijalani</p>
                </div>
                <div class="bg-tertiary/5 rounded-xl p-3 text-center">
                    <p class="font-headline-md text-tertiary font-black"><?= count($catatanList) ?></p>
                    <p class="text-xs text-on-surface-variant">Catatan Bimbingan</p>
                </div>
                <div class="bg-success/5 rounded-xl p-3 text-center">
                    <p class="font-headline-md text-success font-black"><?= $progres['mk_lulus'] ?? 0 ?></p>
                    <p class="text-xs text-on-surface-variant">MK Lulus</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kolom Kanan: Catatan & Riwayat KRS -->
    <div class="lg:col-span-8 space-y-gutter">
        <!-- Catatan Perwalian -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">edit_note</span> Catatan Bimbingan Dosen Wali
            </h3>
            
            <?php if (empty($catatanList)): ?>
            <div class="text-center py-10 text-on-surface-variant opacity-60">
                <span class="material-symbols-outlined text-5xl mb-3">speaker_notes_off</span>
                <p>Belum ada catatan bimbingan yang direkam.</p>
                <p class="text-sm mt-1">Catatan akan muncul setelah Anda melakukan konsultasi dengan Dosen Wali.</p>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($catatanList as $c): ?>
                <div class="bg-secondary/5 border border-secondary/20 rounded-2xl p-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <span class="bg-secondary/20 text-secondary px-2 py-0.5 rounded-md text-xs font-bold">
                                <?= htmlspecialchars($c['semester'] . ' ' . ($c['tahun_ajaran'] ?? '')) ?>
                            </span>
                            <p class="text-xs text-on-surface-variant mt-1">
                                oleh <strong><?= htmlspecialchars($c['dosen_nama']) ?></strong>
                            </p>
                        </div>
                        <span class="text-xs text-on-surface-variant opacity-60">
                            <?= date('d M Y, H:i', strtotime($c['waktu_bimbingan'])) ?>
                        </span>
                    </div>
                    <p class="text-sm text-on-surface-variant whitespace-pre-wrap bg-white/50 p-3 rounded-xl">
                        <?= htmlspecialchars($c['catatan']) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Riwayat KRS per Semester -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined">history_edu</span> Riwayat Persetujuan KRS per Semester
            </h3>

            <?php if (empty($krsBySemester)): ?>
            <p class="text-center text-on-surface-variant opacity-60 py-6">Belum ada riwayat KRS.</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($krsBySemester as $semester => $krsItems): 
                    $disetujui = array_filter($krsItems, fn($k) => $k['status'] === 'Disetujui');
                    $menunggu = array_filter($krsItems, fn($k) => $k['status'] === 'Menunggu');
                    $ditolak = array_filter($krsItems, fn($k) => $k['status'] === 'Ditolak');
                    $totalSksPerSem = array_sum(array_column(array_filter($krsItems, fn($k) => $k['status'] === 'Disetujui'), 'sks'));
                ?>
                <details class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 overflow-hidden group">
                    <summary class="flex items-center justify-between p-4 cursor-pointer hover:bg-surface-variant/20 transition-colors list-none">
                        <div class="flex items-center gap-3">
                            <span class="material-symbols-outlined text-primary group-open:rotate-90 transition-transform">chevron_right</span>
                            <div>
                                <h4 class="font-bold text-on-surface">Semester <?= htmlspecialchars($semester) ?></h4>
                                <p class="text-xs text-on-surface-variant"><?= count($krsItems) ?> MK · <?= $totalSksPerSem ?> SKS Disetujui</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <?php if (count($disetujui) > 0): ?>
                            <span class="px-2 py-1 bg-success/10 text-success border border-success/20 rounded-md text-xs font-bold"><?= count($disetujui) ?> Disetujui</span>
                            <?php endif; ?>
                            <?php if (count($menunggu) > 0): ?>
                            <span class="px-2 py-1 bg-tertiary/10 text-tertiary border border-tertiary/20 rounded-md text-xs font-bold"><?= count($menunggu) ?> Menunggu</span>
                            <?php endif; ?>
                            <?php if (count($ditolak) > 0): ?>
                            <span class="px-2 py-1 bg-error/10 text-error border border-error/20 rounded-md text-xs font-bold"><?= count($ditolak) ?> Ditolak</span>
                            <?php endif; ?>
                        </div>
                    </summary>
                    <div class="px-4 pb-4 space-y-2">
                        <?php foreach ($krsItems as $k): 
                            $statusColor = $k['status'] === 'Disetujui' ? 'text-success' : ($k['status'] === 'Ditolak' ? 'text-error' : 'text-tertiary');
                            $bgColor = $k['status'] === 'Disetujui' ? 'bg-success/5' : ($k['status'] === 'Ditolak' ? 'bg-error/5' : 'bg-tertiary/5');
                        ?>
                        <div class="flex justify-between items-center p-3 <?= $bgColor ?> rounded-xl border border-outline-variant/20">
                            <div>
                                <span class="font-bold text-sm text-on-surface"><?= htmlspecialchars($k['mk_nama']) ?></span>
                                <span class="text-xs text-on-surface-variant ml-2"><?= htmlspecialchars($k['kode']) ?> · <?= $k['sks'] ?> SKS</span>
                            </div>
                            <span class="text-xs font-bold <?= $statusColor ?> flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">
                                    <?= $k['status'] === 'Disetujui' ? 'check_circle' : ($k['status'] === 'Ditolak' ? 'cancel' : 'pending') ?>
                                </span>
                                <?= htmlspecialchars($k['status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
