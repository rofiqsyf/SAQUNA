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

$semesterAktif = $repo->getSemesterAktif();

// --- BLOCKER EDOM ---
if (!$repo->cekEdomLengkap($mhs['id'], $semesterAktif)) {
    // Redirect paksa ke halaman EDOM
    header("Location: mahasiswa_edom.php");
    exit;
}
// --------------------

$krs = $repo->getKrsMahasiswa($mhs['id'], $semesterAktif);
$msg = $_GET['msg'] ?? '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Hasil Studi (KHS) - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <section class="lg:col-span-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary font-black">Kartu Hasil Studi (KHS)</h1>
            <p class="text-on-surface-variant opacity-80 font-body-md mt-1">Semester Aktif: <span class="font-bold text-on-surface"><?= $semesterAktif ?></span></p>
        </div>
        <button onclick="window.print()" class="btn-primary">
            <span class="material-symbols-outlined text-[20px]">print</span> Cetak KHS
        </button>
    </section>

    <!-- Alerts -->
    <?php if ($msg === 'edom_success'): ?>
    <section class="lg:col-span-12">
        <div class="bg-success-container text-on-success-container p-4 rounded-2xl mb-4 font-bold flex items-center gap-3 shadow-sm border border-success/20">
            <span class="material-symbols-outlined text-2xl">check_circle</span> Terima kasih telah mengisi EDOM. KHS Anda sekarang dapat diakses.
        </div>
    </section>
    <?php endif; ?>

    <section class="lg:col-span-12">
        <div class="glass-panel rounded-3xl shadow-sm border border-white/40 overflow-hidden relative mb-stack-md">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-10 -mt-10 blur-2xl transition-colors"></div>
            <div class="overflow-x-auto custom-scrollbar relative z-10">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-surface/50 text-on-surface font-label-md text-sm">
                            <th class="px-6 py-4 font-bold border-b border-white/20">Kode MK</th>
                            <th class="px-6 py-4 font-bold border-b border-white/20">Mata Kuliah</th>
                            <th class="px-6 py-4 font-bold border-b border-white/20 text-center">SKS</th>
                            <th class="px-6 py-4 font-bold border-b border-white/20">Dosen Pengampu</th>
                            <th class="px-6 py-4 font-bold border-b border-white/20 text-center">Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10">
                        <?php 
                        $totalSks = 0;
                        if (empty($krs)): 
                        ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-on-surface-variant font-bold">Belum ada data KRS/KHS untuk semester ini.</td></tr>
                        <?php else: ?>
                            <?php foreach ($krs as $row): 
                                $totalSks += (int)$row['sks'];
                            ?>
                            <tr class="hover:bg-white/20 transition-colors">
                                <td class="px-6 py-4 font-bold text-on-surface-variant text-sm"><?= htmlspecialchars($row['kode']) ?></td>
                                <td class="px-6 py-4 font-bold text-on-surface text-base"><?= htmlspecialchars($row['mk_nama']) ?></td>
                                <td class="px-6 py-4 font-black text-center"><?= (int)$row['sks'] ?></td>
                                <td class="px-6 py-4 text-sm text-on-surface-variant font-medium"><?= htmlspecialchars($row['dosen_nama']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($row['nilai_huruf']): ?>
                                        <span class="px-3 py-1.5 rounded-xl text-sm font-black shadow-sm inline-flex items-center justify-center min-w-[40px] <?= $row['nilai_huruf'] === 'E' ? 'bg-error-container text-on-error-container' : 'bg-success-container text-on-success-container' ?>">
                                            <?= htmlspecialchars($row['nilai_huruf']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-surface-variant/50 text-on-surface-variant px-3 py-1 rounded-xl text-xs font-bold">Belum Dinilai</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($krs)): ?>
                    <tfoot class="bg-surface/30">
                        <tr>
                            <td colspan="2" class="px-6 py-4 text-right font-bold text-on-surface-variant">Total SKS:</td>
                            <td class="px-6 py-4 text-center font-black text-primary text-lg"><?= $totalSks ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </section>
</div>

<?php include 'components/footer.php'; ?>

