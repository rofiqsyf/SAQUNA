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
$krs = $repo->getKrsMahasiswa($mhs['id'], $semesterAktif);

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Token CSRF tidak valid.';
    } else {
        $krsId = (int)($_POST['krs_id'] ?? 0);
        $pertemuan = (int)($_POST['pertemuan_ke'] ?? 0);
        $tokenQr = $_POST['token_qr'] ?? '';

        if ($krsId > 0 && $pertemuan > 0 && $pertemuan <= 16) {
            $result = $repo->simpanPresensi($krsId, $pertemuan, $tokenQr);
            if ($result['success']) {
                $successMsg = $result['message'];
            } else {
                $errorMsg = $result['message'];
            }
        } else {
            $errorMsg = "Mohon lengkapi pilihan Mata Kuliah dan Pertemuan Ke- dengan benar.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presensi Mandiri - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <section class="lg:col-span-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary font-black">Presensi Mandiri</h1>
            <p class="text-on-surface-variant opacity-80 font-body-md mt-1">Pilih Mata Kuliah dan Pertemuan, lalu klik tombol Hadir. <b>Opsi Token/Scan QR hanya diperlukan jika dosen mengaktifkannya.</b></p>
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

    <section class="lg:col-span-12 flex justify-center">
        <div class="glass-panel rounded-3xl p-8 shadow-sm border border-white/40 w-full max-w-2xl relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full -mr-32 -mt-32 blur-3xl group-hover:bg-primary/20 transition-colors"></div>
            
            <form method="POST" action="" class="relative z-10">
                <?= Auth::csrfField() ?>
                
                <div class="space-y-6">
                    <div>
                        <label class="block font-bold text-on-surface-variant mb-2 uppercase tracking-wider text-sm" for="krs_id">Pilih Mata Kuliah Hari Ini</label>
                        <select name="krs_id" id="krs_id" class="w-full bg-surface/50 border border-white/20 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all font-medium text-on-surface cursor-pointer shadow-sm" required>
                            <option value="">-- Pilih Mata Kuliah --</option>
                            <?php foreach ($krs as $mk): ?>
                                <option value="<?= $mk['id'] ?>"><?= htmlspecialchars($mk['kode'] . ' - ' . $mk['mk_nama'] . ' (Dosen: ' . $mk['dosen_nama'] . ')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-on-surface-variant mb-2 uppercase tracking-wider text-sm" for="pertemuan_ke">Pertemuan Ke-</label>
                        <select name="pertemuan_ke" id="pertemuan_ke" class="w-full bg-surface/50 border border-white/20 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all font-medium text-on-surface cursor-pointer shadow-sm" required>
                            <option value="">-- Pilih --</option>
                            <?php for ($i = 1; $i <= 16; $i++): ?>
                                <option value="<?= $i ?>">Pertemuan <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-on-surface-variant mb-2 uppercase tracking-wider text-sm" for="token_qr">Token QR Presensi (Opsional)</label>
                        <input type="text" name="token_qr" id="token_qr" class="w-full bg-surface/50 border border-white/20 rounded-xl px-4 py-3.5 focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all font-medium text-on-surface placeholder:text-on-surface-variant/50 shadow-sm" placeholder="Scan QR atau masukkan manual (Bila ada)">
                        
                        <div class="mt-4">
                            <button type="button" id="start-scan-btn" class="bg-secondary/10 text-secondary border border-secondary/20 hover:bg-secondary hover:text-white px-4 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2 w-full shadow-sm">
                                <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span> Scan QR Menggunakan Kamera
                            </button>
                        </div>
                        <div id="reader" class="mt-4 hidden border border-white/30 rounded-2xl overflow-hidden bg-black/5"></div>
                    </div>

                    <div class="pt-4 border-t border-white/20 mt-6">
                        <button type="submit" class="w-full bg-primary hover:bg-primary/90 text-on-primary font-black text-lg px-6 py-4 rounded-2xl shadow-lg shadow-primary/20 transition-all active:scale-95 flex items-center justify-center gap-3">
                            <span class="material-symbols-outlined text-[24px]">how_to_reg</span> HADIR & SIMPAN PRESENSI
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>
</div>

<script src="assets/js/html5-qrcode.min.js"></script>
<script>
    const startScanBtn = document.getElementById('start-scan-btn');
    const readerDiv = document.getElementById('reader');
    const tokenInput = document.getElementById('token_qr');
    let html5QrcodeScanner = null;

    startScanBtn.addEventListener('click', function() {
        if (readerDiv.classList.contains('hidden')) {
            readerDiv.classList.remove('hidden');
            startScanBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">stop_circle</span> Hentikan Scan';
            startScanBtn.classList.add('bg-error/10', 'text-error', 'border-error/20');
            startScanBtn.classList.remove('bg-secondary/10', 'text-secondary', 'border-secondary/20');
            
            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 250} },
                /* verbose= */ false);
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        } else {
            stopScan();
        }
    });

    function onScanSuccess(decodedText, decodedResult) {
        tokenInput.value = decodedText;
        stopScan();
        // Optional: langsung submit form
        // document.querySelector('form').submit();
    }

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning.
    }
    
    function stopScan() {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear();
        }
        readerDiv.classList.add('hidden');
        startScanBtn.innerHTML = '<span class="material-symbols-outlined text-[20px]">qr_code_scanner</span> Scan QR Menggunakan Kamera';
        startScanBtn.classList.remove('bg-error/10', 'text-error', 'border-error/20');
        startScanBtn.classList.add('bg-secondary/10', 'text-secondary', 'border-secondary/20');
    }
</script>

<?php include 'components/footer.php'; ?>

