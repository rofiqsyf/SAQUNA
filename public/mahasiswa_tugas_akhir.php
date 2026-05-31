<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;
use Src\OperatorRepository; // To get list of dosen

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);
if (!$mhs) die("Data mahasiswa tidak valid.");

$opRepo = new OperatorRepository();
$dosenList = $opRepo->getAllDosen(); // List of valid dosen

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'submit_ta') {
            $dosenId = (int)$_POST['dosen_id'];
            $judul = $_POST['judul'];
            $deskripsi = $_POST['deskripsi'];
            
            if ($repo->submitTugasAkhir($mhs['id'], $dosenId, $judul, $deskripsi)) {
                $successMsg = "Pengajuan Tugas Akhir berhasil dikirim.";
            } else {
                $errorMsg = "Gagal mengajukan. Anda mungkin sudah memiliki pengajuan yang aktif.";
            }
        } elseif ($action === 'add_logbook') {
            $taId = (int)$_POST['ta_id'];
            $tanggal = $_POST['tanggal'];
            $kegiatan = $_POST['kegiatan'];
            
            if ($repo->addLogbookTA($taId, $mhs['id'], $tanggal, $kegiatan)) {
                $successMsg = "Logbook berhasil ditambahkan.";
            } else {
                $errorMsg = "Gagal menyimpan logbook.";
            }
        }
    }
}

$taAktif = $repo->getTugasAkhir($mhs['id']);
$logbooks = [];
if ($taAktif) {
    $logbooks = $repo->getLogbookTA($taAktif['id']);
}

$title = "Tugas Akhir / Skripsi";
$current_page = "mahasiswa_tugas_akhir.php";
require_once __DIR__ . '/components/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Tugas Akhir (Skripsi)</h1>
        <p class="text-on-surface/70 mt-1">Ajukan judul dan catat logbook bimbingan secara rutin.</p>
    </div>
    <?php if (!$taAktif || $taAktif['status'] === 'Ditolak'): ?>
        <button onclick="document.getElementById('modalPengajuan').classList.remove('hidden')" class="btn-primary flex items-center gap-2">
            <span class="material-symbols-outlined">post_add</span> Ajukan TA Baru
        </button>
    <?php endif; ?>
</div>

<?php if ($successMsg): ?>
    <div class="bg-success-container text-on-success-container p-4 rounded-xl mb-6 shadow-sm border border-success/20 flex items-center gap-3">
        <span class="material-symbols-outlined">check_circle</span>
        <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="bg-error-container text-on-error-container p-4 rounded-xl mb-6 shadow-sm border border-error/20 flex items-center gap-3">
        <span class="material-symbols-outlined">error</span>
        <?= htmlspecialchars($errorMsg) ?>
    </div>
<?php endif; ?>

<?php if ($taAktif && $taAktif['status'] !== 'Ditolak'): ?>
    <!-- Status TA -->
    <div class="card p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between gap-4">
            <div class="flex-1">
                <div class="flex items-center gap-3 mb-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                        <?= match($taAktif['status']) {
                            'Diajukan' => 'bg-secondary-container text-on-secondary-container',
                            'Diterima' => 'bg-primary/20 text-primary',
                            'Revisi'   => 'bg-tertiary-container text-on-tertiary-container',
                            'Lulus'    => 'bg-success/20 text-success',
                            default    => 'bg-surface-variant text-on-surface'
                        } ?>">
                        <?= $taAktif['status'] ?>
                    </span>
                    <span class="text-sm text-on-surface-variant font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">calendar_today</span> 
                        Diajukan: <?= date('d M Y', strtotime($taAktif['created_at'])) ?>
                    </span>
                </div>
                <h2 class="text-xl md:text-2xl font-black mb-2"><?= htmlspecialchars($taAktif['judul']) ?></h2>
                <p class="text-on-surface/80 text-sm leading-relaxed mb-4"><?= nl2br(htmlspecialchars($taAktif['deskripsi'])) ?></p>
                
                <div class="inline-flex items-center gap-2 bg-surface-container-low px-4 py-2 rounded-lg border border-outline-variant/30">
                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary text-[18px]">person</span>
                    </div>
                    <div>
                        <p class="text-xs text-on-surface-variant font-medium">Dosen Pembimbing</p>
                        <p class="font-bold text-sm"><?= htmlspecialchars($taAktif['dosen_nama']) ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (in_array($taAktif['status'], ['Diterima', 'Revisi'])): ?>
                <div class="flex items-center md:items-start">
                    <button onclick="document.getElementById('modalLogbook').classList.remove('hidden')" class="btn-primary shadow-lg shadow-primary/20 flex items-center gap-2 px-6 py-3">
                        <span class="material-symbols-outlined">edit_note</span> Tulis Logbook
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Logbook -->
    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">menu_book</span> Riwayat Logbook Bimbingan
    </h3>
    
    <?php if (empty($logbooks)): ?>
        <div class="card p-10 text-center">
            <span class="material-symbols-outlined text-5xl text-on-surface-variant/30 mb-3">history</span>
            <p class="text-on-surface-variant font-medium">Belum ada catatan logbook yang ditambahkan.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($logbooks as $log): ?>
                <div class="card p-5 border-l-4 <?= $log['status'] === 'Disetujui' ? 'border-l-success' : ($log['status'] === 'Revisi' ? 'border-l-error' : 'border-l-secondary') ?>">
                    <div class="flex justify-between items-start mb-3">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined <?= $log['status'] === 'Disetujui' ? 'text-success' : 'text-on-surface-variant' ?>">
                                <?= $log['status'] === 'Disetujui' ? 'check_circle' : 'pending' ?>
                            </span>
                            <span class="font-bold text-sm"><?= date('d F Y', strtotime($log['tanggal'])) ?></span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-xs font-bold <?= $log['status'] === 'Disetujui' ? 'bg-success/10 text-success' : ($log['status'] === 'Revisi' ? 'bg-error/10 text-error' : 'bg-surface-variant text-on-surface') ?>">
                            <?= $log['status'] ?>
                        </span>
                    </div>
                    
                    <div class="bg-surface-container-low p-4 rounded-xl border border-outline-variant/30 mb-3">
                        <p class="text-sm font-bold text-on-surface-variant mb-1">Kegiatan Bimbingan:</p>
                        <p class="text-sm"><?= nl2br(htmlspecialchars($log['kegiatan'])) ?></p>
                    </div>
                    
                    <?php if (!empty($log['catatan_dosen'])): ?>
                        <div class="bg-primary/5 p-4 rounded-xl border border-primary/20">
                            <p class="text-sm font-bold text-primary mb-1 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">record_voice_over</span> Catatan Dosen Pembimbing:
                            </p>
                            <p class="text-sm italic text-on-surface/90"><?= nl2br(htmlspecialchars($log['catatan_dosen'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
<?php else: ?>
    <div class="card p-10 text-center flex flex-col items-center justify-center min-h-[40vh]">
        <div class="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center mb-4">
            <span class="material-symbols-outlined text-4xl text-primary">school</span>
        </div>
        <h2 class="text-2xl font-black mb-2">Mulai Tugas Akhir Anda</h2>
        <p class="text-on-surface-variant max-w-md mx-auto mb-6">Anda belum memiliki pengajuan Tugas Akhir/Skripsi yang aktif. Silakan ajukan judul kepada Dosen Pembimbing yang Anda pilih.</p>
        <button onclick="document.getElementById('modalPengajuan').classList.remove('hidden')" class="btn-primary px-8 py-3 rounded-full text-lg shadow-lg shadow-primary/20">
            Mulai Pengajuan
        </button>
    </div>
<?php endif; ?>

<!-- Modal Pengajuan TA -->
<div id="modalPengajuan" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-surface w-full max-w-2xl rounded-3xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low rounded-t-3xl">
            <h3 class="text-xl font-black flex items-center gap-2"><span class="material-symbols-outlined text-primary">post_add</span> Ajukan Judul TA</h3>
            <button type="button" onclick="document.getElementById('modalPengajuan').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-on-surface/10 text-on-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form method="POST" id="formPengajuan" class="space-y-5">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="submit_ta">
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Dosen Pembimbing <span class="text-error">*</span></label>
                    <select name="dosen_id" required class="input-text">
                        <option value="">-- Pilih Dosen Pembimbing --</option>
                        <?php foreach($dosenList as $dosen): ?>
                            <option value="<?= $dosen['id'] ?>"><?= htmlspecialchars($dosen['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Usulan Judul <span class="text-error">*</span></label>
                    <textarea name="judul" required class="input-text text-lg font-bold" rows="2" placeholder="Ketikkan judul tugas akhir..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Latar Belakang & Deskripsi Singkat <span class="text-error">*</span></label>
                    <textarea name="deskripsi" required class="input-text" rows="5" placeholder="Jelaskan secara singkat apa yang akan Anda teliti..."></textarea>
                </div>
            </form>
        </div>
        <div class="p-6 border-t border-outline-variant/30 bg-surface-container-low rounded-b-3xl flex justify-end gap-3 mt-auto">
            <button type="button" onclick="document.getElementById('modalPengajuan').classList.add('hidden')" class="btn-outline px-6">Batal</button>
            <button type="submit" form="formPengajuan" class="btn-primary px-8 shadow-md">Kirim Pengajuan</button>
        </div>
    </div>
</div>

<!-- Modal Logbook -->
<?php if ($taAktif): ?>
<div id="modalLogbook" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] hidden flex items-center justify-center p-4">
    <div class="bg-surface w-full max-w-lg rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low">
            <h3 class="text-xl font-black">Tambah Logbook Bimbingan</h3>
            <button type="button" onclick="document.getElementById('modalLogbook').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-on-surface/10 text-on-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form method="POST" id="formLogbook" class="space-y-4">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="add_logbook">
                <input type="hidden" name="ta_id" value="<?= $taAktif['id'] ?>">
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Tanggal Bimbingan <span class="text-error">*</span></label>
                    <input type="date" name="tanggal" required class="input-text" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Deskripsi Kegiatan <span class="text-error">*</span></label>
                    <textarea name="kegiatan" required class="input-text" rows="4" placeholder="Misal: Konsultasi Bab 1 dan perbaikan rumusan masalah..."></textarea>
                </div>
            </form>
        </div>
        <div class="p-5 border-t border-outline-variant/30 flex justify-end gap-3 bg-surface-container-low mt-auto">
            <button type="button" onclick="document.getElementById('modalLogbook').classList.add('hidden')" class="btn-outline">Batal</button>
            <button type="submit" form="formLogbook" class="btn-primary shadow-md">Simpan Logbook</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
