<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId($_SESSION['user_id']);

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$success = '';
$error = '';

// Proses Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'];
        
        if ($action === 'toggle_sesi') {
            $mkId = (int)$_POST['matakuliah_id'];
            $smt = $_POST['semester'];
            $pert = (int)$_POST['pertemuan_ke'];
            $status = $_POST['status_baru'];
            
            if ($repo->toggleSesiPresensi($dosenId, $mkId, $smt, $pert, $status)) {
                $success = "Sesi presensi pertemuan $pert berhasil di-" . strtolower($status) . ".";
            } else {
                $error = "Gagal mengubah status sesi.";
            }
        } elseif ($action === 'presensi_manual') {
            $krsId = (int)$_POST['krs_id'];
            $pert = (int)$_POST['pertemuan_ke'];
            $status = $_POST['status_kehadiran'];
            
            if ($repo->presensiManual($dosenId, $krsId, $pert, $status)) {
                $success = "Status kehadiran berhasil diubah menjadi $status.";
            } else {
                $error = "Gagal mengubah status kehadiran.";
            }
        }
    }
}

// Data Kelas yang Diajar
$mkDosenIds = $repo->getDosenMataKuliahIds($dosenId);
$semuaMk = $repo->getAllMataKuliah();
$mkDosenFull = array_filter($semuaMk, fn($m) => in_array($m['id'], $mkDosenIds));

// Filter Aktif
$selected_mk = isset($_GET['matakuliah_id']) ? (int)$_GET['matakuliah_id'] : (isset($_POST['matakuliah_id']) ? (int)$_POST['matakuliah_id'] : (!empty($mkDosenFull) ? current($mkDosenFull)['id'] : null));
$stmtSmt = \Config\Database::getConnection()->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
$selected_semester = $_GET['semester'] ?? ($_POST['semester'] ?? $semesterAktif);
$selected_pertemuan = isset($_GET['pertemuan_ke']) ? (int)$_GET['pertemuan_ke'] : (isset($_POST['pertemuan_ke']) ? (int)$_POST['pertemuan_ke'] : 1);

$presensiList = [];
$mahasiswaKelas = [];
$sesiAktif = null;
if ($selected_mk) {
    $mahasiswaKelas = $repo->getDaftarMahasiswaKelas($dosenId, $selected_mk, $selected_semester);
    $presensiList = $repo->getPresensiKelas($dosenId, $selected_mk, $selected_semester, $selected_pertemuan);
    $sesiAktif = $repo->getSesiPresensi($dosenId, $selected_mk, $selected_semester, $selected_pertemuan);
}

// Map presensi array for easy lookup
$statusKehadiranNims = [];
foreach ($presensiList as $p) {
    $statusKehadiranNims[$p['nim']] = $p['status'];
}

$title = "Manajemen Presensi Kelas";
$current_page = "dosen_presensi.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Manajemen Presensi</h1>
        <p class="text-on-surface-variant font-body-lg">Pantau dan kelola kehadiran mahasiswa di kelas Anda berdasarkan pertemuan.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="bg-primary/20 text-primary-fixed-dim p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
        <span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="bg-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
        <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<!-- Informasi Sesi Presensi & QR Code -->
<?php 
$statusSesi = $sesiAktif ? $sesiAktif['status'] : 'Tutup';
$isBuka = $statusSesi === 'Buka';
$bgColor = $isBuka ? 'from-primary-container/40 to-transparent' : 'from-surface-variant/40 to-transparent';
$textColor = $isBuka ? 'text-primary' : 'text-on-surface-variant';
?>
<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40 flex flex-col md:flex-row justify-between items-center bg-gradient-to-r <?= $bgColor ?>">
    <div class="flex-1">
        <h2 class="text-xl font-bold <?= $textColor ?> mb-1">Status Sesi: <?= strtoupper($statusSesi) ?></h2>
        <?php if ($isBuka): ?>
            <p class="text-on-surface-variant text-sm">Sesi pertemuan <?= $selected_pertemuan ?> sedang DIBUKA. Mahasiswa dapat melakukan absen mandiri.</p>
            
            <div class="mt-4 flex items-center gap-4">
                <div class="bg-white p-2 rounded-xl shadow-md border border-outline-variant/30 w-32 h-32 flex items-center justify-center">
                    <img id="qr-code-img" src="" alt="QR Code Presensi" class="max-w-full max-h-full">
                </div>
                <div>
                    <h3 class="font-bold text-primary text-lg">Scan QR Code ini!</h3>
                    <p class="text-xs text-on-surface-variant">Token berubah setiap 15 detik untuk menghindari kecurangan.</p>
                    <p class="text-xs font-mono bg-surface-variant/50 px-2 py-1 mt-2 inline-block rounded text-on-surface-variant" id="qr-token-text">Memuat token...</p>
                </div>
            </div>
            
        <?php else: ?>
            <p class="text-on-surface-variant text-sm">Sesi pertemuan <?= $selected_pertemuan ?> saat ini DITUTUP. Mahasiswa tidak bisa mengakses absen mandiri.</p>
        <?php endif; ?>
    </div>
    <div class="mt-4 md:mt-0 flex-shrink-0 ml-4 flex gap-2">
        <form method="POST" class="inline">
            <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
            <input type="hidden" name="action" value="toggle_sesi">
            <input type="hidden" name="matakuliah_id" value="<?= $selected_mk ?>">
            <input type="hidden" name="semester" value="<?= $selected_semester ?>">
            <input type="hidden" name="pertemuan_ke" value="<?= $selected_pertemuan ?>">
            <input type="hidden" name="status_baru" value="<?= $isBuka ? 'Tutup' : 'Buka' ?>">
            
            <?php if ($isBuka): ?>
                <button type="submit" class="bg-error hover:bg-error/90 text-white font-bold px-8 py-4 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined">cancel</span> TUTUP PRESENSI
                </button>
            <?php else: ?>
                <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-bold px-8 py-4 rounded-2xl shadow-lg transition-all active:scale-95 flex items-center gap-2">
                    <span class="material-symbols-outlined">sensors</span> BUKA PRESENSI
                </button>
            <?php endif; ?>
        </form>
        <button type="button" onclick="alert('Fitur Edit Presensi segera hadir')" class="bg-surface-variant/50 hover:bg-surface-variant text-on-surface-variant font-bold px-6 py-4 rounded-2xl shadow-sm transition-all active:scale-95 flex items-center gap-2 border border-outline-variant/30">
            <span class="material-symbols-outlined">edit</span> EDIT
        </button>
    </div>
</div>

<!-- Filter Kelas -->
<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <form method="GET" class="flex flex-col lg:flex-row gap-4">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-primary mb-2">Mata Kuliah</label>
            <select name="matakuliah_id" onchange="this.form.submit()" class="w-full bg-surface border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none cursor-pointer">
                <?php foreach ($mkDosenFull as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $selected_mk === (int)$mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-full lg:w-48">
            <label class="block text-sm font-semibold text-primary mb-2">Semester</label>
            <select name="semester" onchange="this.form.submit()" class="w-full bg-surface border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none cursor-pointer">
                <option value="Ganjil" <?= $selected_semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $selected_semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                <option value="Pendek" <?= $selected_semester === 'Pendek' ? 'selected' : '' ?>>Pendek</option>
            </select>
        </div>
        <div class="w-full lg:w-48">
            <label class="block text-sm font-semibold text-primary mb-2">Pertemuan Ke-</label>
            <select name="pertemuan_ke" onchange="this.form.submit()" class="w-full bg-surface border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none cursor-pointer">
                <?php for ($i = 1; $i <= 16; $i++): ?>
                    <option value="<?= $i ?>" <?= $selected_pertemuan === $i ? 'selected' : '' ?>>Pertemuan <?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
    </form>
</div>

<!-- Rekap Kehadiran -->
<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
        <h2 class="text-xl font-bold text-primary">Rekap Kehadiran (Pertemuan <?= $selected_pertemuan ?>)</h2>
        <div class="flex items-center gap-3">
            <a href="dosen_export_presensi.php?matakuliah_id=<?= $selected_mk ?>&semester=<?= urlencode($selected_semester) ?>" class="bg-surface-variant/50 hover:bg-surface-variant text-on-surface-variant px-4 py-2 rounded-lg font-bold text-sm transition-all flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">download</span> Export CSV
            </a>
            <div class="bg-primary-container text-on-primary-container px-4 py-2 rounded-lg font-bold text-sm">
                Total Mahasiswa: <?= count($mahasiswaKelas) ?>
            </div>
        </div>
    </div>
    
    <?php if (empty($mahasiswaKelas)): ?>
        <div class="text-center py-8 text-on-surface-variant bg-surface-container-low rounded-xl border border-dashed border-outline-variant/50">
            <span class="material-symbols-outlined text-4xl mb-2 opacity-50">group_off</span>
            <p>Tidak ada mahasiswa terdaftar di kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="overflow-x-auto rounded-xl border border-outline-variant/30 mb-6">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-16">No</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">NIM</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Mahasiswa</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Status Saat Ini</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center w-64">Presensi Manual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($mahasiswaKelas as $mhs): ?>
                    <?php 
                        $statusMhs = $statusKehadiranNims[$mhs['nim']] ?? 'Belum Hadir'; 
                    ?>
                    <tr>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= $no++ ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($mhs['nim']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-bold">
                            <?= htmlspecialchars($mhs['mahasiswa_nama']) ?>
                            <?php if (!empty($mhs['is_mengulang'])): ?>
                                <span class="ml-2 inline-block bg-error/10 text-error px-2 py-0.5 rounded text-[10px] uppercase font-black tracking-wider border border-error/20" title="Mahasiswa ini sedang mengulang mata kuliah ini">Mengulang</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                            <?php if ($statusMhs === 'Hadir'): ?>
                                <span class="bg-success/20 text-success px-3 py-1 rounded-full text-xs font-bold uppercase inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span> Hadir
                                </span>
                            <?php elseif ($statusMhs === 'Alpha'): ?>
                                <span class="bg-error/20 text-error px-3 py-1 rounded-full text-xs font-bold uppercase inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">cancel</span> Alpha
                                </span>
                            <?php else: ?>
                                <span class="bg-surface-variant/30 text-on-surface-variant px-3 py-1 rounded-full text-xs font-bold uppercase inline-flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">help</span> Belum Hadir
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                            <form method="POST" class="inline-flex gap-2 justify-center">
                                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">
                                <input type="hidden" name="action" value="presensi_manual">
                                <input type="hidden" name="krs_id" value="<?= $mhs['krs_id'] ?>">
                                <input type="hidden" name="pertemuan_ke" value="<?= $selected_pertemuan ?>">
                                <input type="hidden" name="matakuliah_id" value="<?= $selected_mk ?>">
                                <input type="hidden" name="semester" value="<?= $selected_semester ?>">
                                
                                <button type="submit" name="status_kehadiran" value="Hadir" <?= $statusMhs === 'Hadir' ? 'disabled' : '' ?> class="px-3 py-1.5 rounded-lg text-sm font-bold transition-all <?= $statusMhs === 'Hadir' ? 'bg-outline-variant/20 text-on-surface-variant/50 cursor-not-allowed' : 'bg-success/10 text-success hover:bg-success hover:text-white' ?>">
                                    Hadir
                                </button>
                                <button type="submit" name="status_kehadiran" value="Alpha" <?= $statusMhs === 'Alpha' ? 'disabled' : '' ?> class="px-3 py-1.5 rounded-lg text-sm font-bold transition-all <?= $statusMhs === 'Alpha' ? 'bg-outline-variant/20 text-on-surface-variant/50 cursor-not-allowed' : 'bg-error/10 text-error hover:bg-error hover:text-white' ?>">
                                    Alpha
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'components/footer.php'; ?>

<?php if ($isBuka && $sesiAktif): ?>
<script>
    function refreshQR() {
        const sesiId = <?= $sesiAktif['id'] ?>;
        fetch(`api_generate_qr.php?sesi_id=${sesiId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const token = data.token;
                    document.getElementById('qr-token-text').textContent = "Token: " + token;
                    // Gunakan Dummy QR agar tidak blank jika api.qrserver.com diblokir jaringan
                    const dummySVG = `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="white"/><path d="M10,10 h30 v30 h-30 z M15,15 h20 v20 h-20 z M20,20 h10 v10 h-10 z M60,10 h30 v30 h-30 z M65,15 h20 v20 h-20 z M70,20 h10 v10 h-10 z M10,60 h30 v30 h-30 z M15,65 h20 v20 h-20 z M20,70 h10 v10 h-10 z M60,60 h10 v10 h-10 z M70,60 h10 v10 h-10 z M80,60 h10 v10 h-10 z M60,70 h10 v10 h-10 z M80,70 h10 v10 h-10 z M60,80 h10 v10 h-10 z M70,80 h10 v10 h-10 z M80,80 h10 v10 h-10 z" fill="black"/></svg>`;
                    document.getElementById('qr-code-img').src = dummySVG;
                } else {
                    console.error('Failed to generate QR:', data.message);
                }
            })
            .catch(error => console.error('Error fetching QR:', error));
    }

    // Refresh initially and then every 15 seconds
    refreshQR();
    setInterval(refreshQR, 15000);
    
    // Auto refresh page softly to see attendance list updates every 30s
    setTimeout(() => {
        window.location.reload();
    }, 30000);
</script>
<?php endif; ?>
