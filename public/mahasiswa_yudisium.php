<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();

$pdo = Database::getConnection();
$repo = new MahasiswaRepository();
$userId = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Ambil data mahasiswa
$mhs = $repo->getMahasiswaByUserId($userId);
if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}
$mahasiswaId = $mhs['id'];

// Kalkulasi Syarat Yudisium
// 1. SKS Lulus
$progress = $repo->getProgressStudi($mahasiswaId);
$sksLulus = $progress['sks_lulus'] ?? 0;
$syaratSks = $sksLulus >= 144;

// 2. IPK
$ipkStats = $repo->getStatistikIPK($mahasiswaId);
$ipk = $ipkStats['ipk_kumulatif'] ?? 0;
$syaratIpk = $ipk >= 2.0;

// 3. Tugas Akhir
$ta = $repo->getTugasAkhir($mahasiswaId);
$taSelesai = ($ta && $ta['status'] === 'Selesai');
$syaratTa = $taSelesai;

// 4. Tagihan (Tidak ada yang 'Belum Lunas' & tipe 'UKT')
$tagihan = $repo->getTagihan($mahasiswaId);
$bebasTanggungan = true;
foreach ($tagihan as $t) {
    if ($t['status'] === 'Belum Lunas') {
        $bebasTanggungan = false;
        break;
    }
}
$syaratTanggungan = $bebasTanggungan;

$isEligible = $syaratSks && $syaratIpk && $syaratTa && $syaratTanggungan;

// Cek status yudisium saat ini
$stmtYudisium = $pdo->prepare("SELECT * FROM yudisium WHERE mahasiswa_id = ?");
$stmtYudisium->execute([$mahasiswaId]);
$yudisium = $stmtYudisium->fetch();

// Proses Pengajuan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } elseif (!$isEligible) {
        $error = "Anda belum memenuhi syarat pendaftaran yudisium.";
    } elseif ($yudisium) {
        $error = "Anda sudah pernah mengajukan yudisium.";
    } else {
        $stmtInsert = $pdo->prepare("INSERT INTO yudisium (mahasiswa_id, status_pengajuan) VALUES (?, 'Diajukan')");
        if ($stmtInsert->execute([$mahasiswaId])) {
            Auth::logActivity($userId, 'create', 'yudisium', $pdo->lastInsertId(), "Mengajukan Yudisium", $pdo);
            $success = "Pendaftaran yudisium berhasil diajukan. Silakan tunggu proses verifikasi.";
            $yudisium = ['status_pengajuan' => 'Diajukan', 'catatan' => null, 'tanggal_lulus' => null, 'no_sk' => null];
        } else {
            $error = "Gagal mengajukan yudisium.";
        }
    }
}

$current_page = 'mahasiswa_yudisium.php';
$page_title = 'Pendaftaran Yudisium';
$username = $mhs['nama'];
$role = 'mahasiswa';
$unreadCount = 0;

require_once __DIR__ . '/components/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Pendaftaran Yudisium</h1>
        <p class="text-on-surface/70 mt-1">Verifikasi syarat kelulusan dan pendaftaran yudisium akhir.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="bg-success/10 border border-success/20 text-success p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span> <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-error/10 border border-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Status Pengajuan -->
<?php if ($yudisium): 
    $st = $yudisium['status_pengajuan'];
    if ($st === 'Disetujui') {
        $boxCls = 'bg-success/10 border-success/30';
        $icon = 'verified';
        $iconCls = 'text-success bg-success/20';
        $title = 'Yudisium Disetujui! 🎉';
        $desc = "Selamat! Anda telah resmi dinyatakan lulus pada tanggal " . date('d F Y', strtotime($yudisium['tanggal_lulus'])) . ".";
    } elseif ($st === 'Ditolak') {
        $boxCls = 'bg-error/10 border-error/30';
        $icon = 'cancel';
        $iconCls = 'text-error bg-error/20';
        $title = 'Pengajuan Ditolak';
        $desc = "Pengajuan yudisium Anda ditolak. Silakan cek catatan dan perbaiki kekurangan Anda.";
    } else {
        $boxCls = 'bg-secondary/10 border-secondary/30';
        $icon = 'hourglass_top';
        $iconCls = 'text-secondary bg-secondary/20';
        $title = 'Sedang Diproses';
        $desc = "Pengajuan yudisium Anda sedang diverifikasi oleh BAAK. Mohon cek halaman ini secara berkala.";
    }
?>
<div class="<?= $boxCls ?> border rounded-2xl p-6 mb-8 flex flex-col md:flex-row items-center gap-6">
    <div class="w-20 h-20 rounded-full <?= $iconCls ?> flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-4xl" style="font-variation-settings: 'FILL' 1"><?= $icon ?></span>
    </div>
    <div class="flex-1 text-center md:text-left">
        <h2 class="text-xl font-black mb-1"><?= $title ?></h2>
        <p class="text-sm opacity-80 mb-3"><?= $desc ?></p>
        
        <?php if ($st === 'Disetujui' && !empty($yudisium['no_sk'])): ?>
            <div class="inline-block bg-white/50 dark:bg-black/20 px-4 py-2 rounded-lg font-mono text-sm border border-current/10">
                <strong>No. SK Yudisium:</strong> <?= htmlspecialchars($yudisium['no_sk']) ?>
            </div>
        <?php endif; ?>
        <?php if ($st === 'Ditolak' && !empty($yudisium['catatan'])): ?>
            <div class="inline-block bg-error/20 text-error px-4 py-2 rounded-lg text-sm border border-error/30">
                <strong>Catatan Admin:</strong> <?= htmlspecialchars($yudisium['catatan']) ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Syarat Kelulusan -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden mb-8">
    <div class="p-5 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low">
        <h2 class="text-lg font-bold text-primary flex items-center gap-2">
            <span class="material-symbols-outlined">checklist</span> Checklist Syarat Yudisium
        </h2>
        <?php if ($isEligible): ?>
            <span class="px-3 py-1 bg-success/10 text-success rounded-full text-xs font-bold border border-success/20">Memenuhi Syarat</span>
        <?php else: ?>
            <span class="px-3 py-1 bg-error/10 text-error rounded-full text-xs font-bold border border-error/20">Belum Memenuhi Syarat</span>
        <?php endif; ?>
    </div>
    <div class="p-2">
        <!-- SKS -->
        <div class="flex items-center gap-4 p-4 border-b border-outline-variant/10">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $syaratSks ? 'bg-success/20 text-success' : 'bg-surface-variant text-on-surface-variant' ?>">
                <span class="material-symbols-outlined" style="<?= $syaratSks ? "font-variation-settings: 'FILL' 1" : "" ?>"><?= $syaratSks ? 'check_circle' : 'radio_button_unchecked' ?></span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-on-surface">Minimal 144 SKS Lulus</p>
                <p class="text-sm text-on-surface-variant mt-0.5">Total SKS Anda saat ini: <strong><?= $sksLulus ?> SKS</strong></p>
            </div>
        </div>
        
        <!-- IPK -->
        <div class="flex items-center gap-4 p-4 border-b border-outline-variant/10">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $syaratIpk ? 'bg-success/20 text-success' : 'bg-surface-variant text-on-surface-variant' ?>">
                <span class="material-symbols-outlined" style="<?= $syaratIpk ? "font-variation-settings: 'FILL' 1" : "" ?>"><?= $syaratIpk ? 'check_circle' : 'radio_button_unchecked' ?></span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-on-surface">IPK Minimal 2.00</p>
                <p class="text-sm text-on-surface-variant mt-0.5">IPK Kumulatif Anda saat ini: <strong><?= number_format($ipk, 2) ?></strong></p>
            </div>
        </div>
        
        <!-- TA -->
        <div class="flex items-center gap-4 p-4 border-b border-outline-variant/10">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $syaratTa ? 'bg-success/20 text-success' : 'bg-surface-variant text-on-surface-variant' ?>">
                <span class="material-symbols-outlined" style="<?= $syaratTa ? "font-variation-settings: 'FILL' 1" : "" ?>"><?= $syaratTa ? 'check_circle' : 'radio_button_unchecked' ?></span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-on-surface">Lulus Tugas Akhir / Skripsi</p>
                <p class="text-sm text-on-surface-variant mt-0.5">Status TA Anda: <strong><?= $ta ? $ta['status'] : 'Belum mengambil' ?></strong></p>
            </div>
        </div>
        
        <!-- Keuangan -->
        <div class="flex items-center gap-4 p-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 <?= $syaratTanggungan ? 'bg-success/20 text-success' : 'bg-surface-variant text-on-surface-variant' ?>">
                <span class="material-symbols-outlined" style="<?= $syaratTanggungan ? "font-variation-settings: 'FILL' 1" : "" ?>"><?= $syaratTanggungan ? 'check_circle' : 'radio_button_unchecked' ?></span>
            </div>
            <div class="flex-1">
                <p class="font-bold text-on-surface">Bebas Tanggungan Keuangan (UKT)</p>
                <p class="text-sm text-on-surface-variant mt-0.5"><?= $syaratTanggungan ? 'Semua tagihan lunas.' : 'Masih ada tagihan yang belum lunas.' ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (!$yudisium || $yudisium['status_pengajuan'] === 'Ditolak'): ?>
<div class="text-center">
    <?php if ($isEligible): ?>
        <form method="POST" onsubmit="return confirm('Apakah Anda yakin data Anda sudah benar dan siap mengajukan Yudisium? Proses ini tidak dapat dibatalkan secara mandiri.')">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="ajukan">
            <button type="submit" class="btn-primary shadow-lg shadow-primary/30 px-8 py-4 text-lg">
                <span class="material-symbols-outlined mr-2">how_to_reg</span> Ajukan Pendaftaran Yudisium
            </button>
        </form>
    <?php else: ?>
        <button class="bg-surface-variant text-on-surface-variant px-8 py-4 rounded-xl font-bold cursor-not-allowed text-lg opacity-70" disabled>
            <span class="material-symbols-outlined mr-2 inline-block align-middle">lock</span> Syarat Belum Terpenuhi
        </button>
        <p class="text-sm text-on-surface-variant mt-3">Anda harus memenuhi semua checklist di atas untuk dapat mendaftar yudisium.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/components/footer.php'; ?>
