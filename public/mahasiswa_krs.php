<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;
use Src\KalenderRepository;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan. Hubungi administrator.");
}

$semesterAktif = $repo->getSemesterAktif(); // Asumsi semester yang sedang berjalan
$semuaMK = $repo->getPenawaranMK($mhs['id'], $semesterAktif);
$krsSaatIni = $repo->getKrsMahasiswa($mhs['id'], $semesterAktif);
$krsIds = array_map(function($k) { return $k['dosen_id'] . '|' . $k['matakuliah_id']; }, $krsSaatIni);

$krsStatusMap = [];
foreach ($krsSaatIni as $k) {
    $krsStatusMap[$k['dosen_id'] . '|' . $k['matakuliah_id']] = $k['status'];
}

$kalenderRepo = new KalenderRepository();
$isKrsOpen = $kalenderRepo->isKrsPeriodOpen();

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isKrsOpen) {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Token CSRF tidak valid.';
    } else {
        $pilihan = $_POST['krs'] ?? [];
        $hasilSimpan = $repo->simpanKRS($mhs['id'], $pilihan, $semesterAktif);
        if ($hasilSimpan['status']) {
            $successMsg = 'KRS berhasil disimpan!';
            // Refresh krs ids
            $krsSaatIni = $repo->getKrsMahasiswa($mhs['id'], $semesterAktif);
            $krsIds = array_map(function($k) { return $k['dosen_id'] . '|' . $k['matakuliah_id']; }, $krsSaatIni);
        } else {
            $errorMsg = $hasilSimpan['error_msg'] ?: 'Gagal menyimpan KRS. Terjadi kesalahan pada server.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Isi KRS - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <section class="lg:col-span-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary font-black">Kartu Rencana Studi (KRS)</h1>
            <p class="text-on-surface-variant opacity-80 font-body-md mt-1">Semester Aktif: <span class="font-bold text-on-surface"><?= $semesterAktif ?></span></p>
        </div>
    </section>

    <!-- Alerts -->
    <?php if ($successMsg || $errorMsg): ?>
    <section class="lg:col-span-12">
        <?php if ($successMsg): ?>
            <div class="bg-success-container text-on-success-container p-4 rounded-2xl mb-4 font-bold flex items-center gap-3 shadow-sm border border-success/20">
                <span class="material-symbols-outlined text-2xl">check_circle</span> <?= htmlspecialchars($successMsg) ?>
            </div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="bg-error-container text-on-error-container p-4 rounded-2xl mb-4 font-bold flex items-center gap-3 shadow-sm border border-error/20">
                <span class="material-symbols-outlined text-2xl">error</span> <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (!$isKrsOpen): ?>
    <section class="lg:col-span-12">
        <div class="glass-panel p-8 rounded-3xl text-center shadow-sm border border-white/40 flex flex-col items-center justify-center">
            <div class="w-20 h-20 bg-error/10 text-error rounded-full flex items-center justify-center mb-4">
                <span class="material-symbols-outlined text-4xl">event_busy</span>
            </div>
            <h2 class="text-xl font-black text-on-surface mb-2">Periode Pengisian KRS saat ini sedang DITUTUP.</h2>
            <p class="text-sm font-medium text-on-surface-variant">Silakan cek Kalender Akademik untuk informasi lebih lanjut tentang jadwal pengisian KRS.</p>
        </div>
    </section>
    <?php else: ?>
    
    <section class="lg:col-span-12">
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex justify-between items-center relative overflow-hidden group" id="krs-summary-card">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/10 rounded-full -mr-10 -mt-10 blur-2xl group-hover:bg-primary/20 transition-colors"></div>
            <div class="relative z-10 flex items-center gap-3">
                <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-2xl">calculate</span>
                </div>
                <div>
                    <h3 class="font-bold text-on-surface-variant text-sm uppercase tracking-wider mb-0.5">Total SKS Diambil</h3>
                    <p class="font-display-md text-2xl font-black text-on-surface"><span id="total-sks" class="text-primary transition-colors">0</span> / <span class="text-on-surface-variant">24</span></p>
                </div>
            </div>
            <div class="relative z-10">
                <span id="sks-warning" class="bg-error/10 border border-error/20 text-error px-4 py-2 rounded-xl font-bold text-sm items-center gap-2" style="display: none;">
                    <span class="material-symbols-outlined text-[18px]">warning</span> SKS Melebihi Batas!
                </span>
            </div>
        </div>
    </section>

    <section class="lg:col-span-12">
        <form method="POST" action="">
            <?= Auth::csrfField() ?>
            
            <div class="glass-panel rounded-3xl shadow-sm border border-white/40 overflow-hidden relative mb-stack-md">
                <div class="overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-surface/50 text-on-surface font-label-md text-sm">
                                <th class="px-6 py-4 font-bold w-16 text-center border-b border-white/20">Pilih</th>
                                <th class="px-6 py-4 font-bold border-b border-white/20">Kode MK</th>
                                <th class="px-6 py-4 font-bold border-b border-white/20">Mata Kuliah</th>
                                <th class="px-6 py-4 font-bold border-b border-white/20">SKS</th>
                                <th class="px-6 py-4 font-bold border-b border-white/20">Dosen Pengampu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/10">
                            <?php if (empty($semuaMK)): ?>
                                <tr><td colspan="5" class="px-6 py-12 text-center text-on-surface-variant font-bold">Belum ada penawaran mata kuliah semester ini.</td></tr>
                            <?php else: ?>
                                <?php foreach ($semuaMK as $mk): ?>
                                <?php 
                                    $val = $mk['dosen_id'] . '|' . $mk['matakuliah_id'];
                                    $isChecked = isset($krsStatusMap[$val]);
                                    $statusKrs = $isChecked ? $krsStatusMap[$val] : '';
                                    $isDisabled = ($statusKrs === 'Disetujui');
                                ?>
                                <tr class="hover:bg-white/20 transition-colors">
                                    <td class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center">
                                            <input type="checkbox" name="krs[]" class="krs-checkbox w-5 h-5 rounded-md border-outline-variant/50 text-primary focus:ring-primary bg-surface transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" 
                                                   value="<?= htmlspecialchars($val) ?>" 
                                                   data-sks="<?= (int)$mk['sks'] ?>" 
                                                   <?= $isChecked ? 'checked' : '' ?>
                                                   <?= $isDisabled ? 'disabled' : '' ?>>
                                            <?php if ($isDisabled): ?>
                                                <input type="hidden" name="krs[]" value="<?= htmlspecialchars($val) ?>">
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-on-surface-variant text-sm"><?= htmlspecialchars($mk['kode']) ?></td>
                                    <td class="px-6 py-4 font-bold text-on-surface text-base">
                                        <?= htmlspecialchars($mk['mk_nama']) ?>
                                        <?php if ($statusKrs === 'Disetujui'): ?>
                                            <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-success/10 text-success border border-success/20">
                                                <span class="material-symbols-outlined text-[12px]">check_circle</span> Disetujui
                                            </span>
                                        <?php elseif ($statusKrs === 'Menunggu'): ?>
                                            <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-secondary/10 text-secondary border border-secondary/20">
                                                <span class="material-symbols-outlined text-[12px]">schedule</span> Menunggu
                                            </span>
                                        <?php elseif ($statusKrs === 'Ditolak'): ?>
                                            <span class="ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-error/10 text-error border border-error/20">
                                                <span class="material-symbols-outlined text-[12px]">cancel</span> Ditolak
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-primary font-black"><?= (int)$mk['sks'] ?> SKS</td>
                                    <td class="px-6 py-4 text-sm text-on-surface-variant font-medium"><?= htmlspecialchars($mk['dosen_nama']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end mt-4">
                <button type="submit" id="btn-submit-krs" class="btn-primary text-base">
                    <span class="material-symbols-outlined">save</span> Simpan KRS
                </button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.krs-checkbox');
    const totalSksEl = document.getElementById('total-sks');
    const warningEl = document.getElementById('sks-warning');
    const btnSubmit = document.getElementById('btn-submit-krs');
    const summaryCard = document.getElementById('krs-summary-card');

    function hitungSKS() {
        let total = 0;
        checkboxes.forEach(cb => {
            if (cb.checked) {
                total += parseInt(cb.getAttribute('data-sks'));
            }
        });

        totalSksEl.innerText = total;

        if (total > 24) {
            warningEl.style.display = 'inline-block';
            btnSubmit.disabled = true;
            summaryCard.style.border = '2px solid var(--danger)';
            totalSksEl.style.color = 'var(--danger)';
        } else {
            warningEl.style.display = 'none';
            btnSubmit.disabled = false;
            summaryCard.style.border = 'none';
            totalSksEl.style.color = 'var(--primary)';
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', hitungSKS);
    });

    // Hitung SKS awal saat halaman dimuat
    hitungSKS();
});
</script>

