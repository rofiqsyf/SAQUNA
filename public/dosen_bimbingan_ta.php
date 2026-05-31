<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireLogin();
if (Auth::getRole() !== 'dosen') {
    die("Akses ditolak.");
}

$repo = new DosenRepository();
$pdo = \Config\Database::getConnection();
$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosenRealId = $stmt->fetchColumn();

if (!$dosenRealId) die("Data dosen tidak valid.");

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_status_ta') {
            $taId = (int)$_POST['ta_id'];
            $status = $_POST['status']; // Diterima, Ditolak, Lulus
            
            if ($repo->updateStatusTABimbingan($taId, $dosenRealId, $status)) {
                $successMsg = "Status pengajuan berhasil diubah menjadi: $status";
            }
        } elseif ($action === 'update_logbook') {
            $logbookId = (int)$_POST['logbook_id'];
            $taId = (int)$_POST['ta_id'];
            $status = $_POST['status']; // Disetujui, Revisi
            $catatan = $_POST['catatan_dosen'] ?? '';
            
            if ($repo->updateLogbookTABimbingan($logbookId, $taId, $dosenRealId, $status, $catatan)) {
                $successMsg = "Logbook berhasil diverifikasi.";
            }
        }
    }
}

$daftarBimbingan = $repo->getBimbinganTA($dosenRealId);

// Filter by status if needed, defaults to showing active ones first
usort($daftarBimbingan, function($a, $b) {
    $order = ['Diajukan' => 1, 'Revisi' => 2, 'Diterima' => 3, 'Lulus' => 4, 'Ditolak' => 5];
    return $order[$a['status']] <=> $order[$b['status']];
});

$title = "Bimbingan Tugas Akhir";
$current_page = "dosen_bimbingan_ta.php";
require_once __DIR__ . '/components/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Bimbingan Tugas Akhir</h1>
        <p class="text-on-surface/70 mt-1">Review pengajuan skripsi dan validasi logbook mahasiswa bimbingan.</p>
    </div>
</div>

<?php if ($successMsg): ?>
    <div class="bg-success-container text-on-success-container p-4 rounded-xl mb-6 shadow-sm border border-success/20 flex items-center gap-3">
        <span class="material-symbols-outlined">check_circle</span>
        <?= htmlspecialchars($successMsg) ?>
    </div>
<?php endif; ?>

<?php if (empty($daftarBimbingan)): ?>
    <div class="card p-10 text-center flex flex-col items-center justify-center">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant/30 mb-4">group_off</span>
        <h2 class="text-xl font-bold mb-2">Belum Ada Mahasiswa Bimbingan</h2>
        <p class="text-on-surface-variant">Saat ini belum ada mahasiswa yang mengajukan Tugas Akhir kepada Anda.</p>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($daftarBimbingan as $ta): ?>
            <div class="card p-0 overflow-hidden shadow-sm border border-outline-variant/30">
                <div class="p-6 border-b border-outline-variant/20 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-surface-container-lowest">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider
                                <?= match($ta['status']) {
                                    'Diajukan' => 'bg-secondary-container text-on-secondary-container animate-pulse',
                                    'Diterima' => 'bg-primary/20 text-primary',
                                    'Revisi'   => 'bg-tertiary-container text-on-tertiary-container',
                                    'Lulus'    => 'bg-success/20 text-success',
                                    default    => 'bg-error/20 text-error'
                                } ?>">
                                <?= $ta['status'] ?>
                            </span>
                            <span class="text-sm font-medium text-on-surface-variant"><?= date('d M Y', strtotime($ta['created_at'])) ?></span>
                        </div>
                        <h3 class="text-xl font-black mb-1"><?= htmlspecialchars($ta['judul']) ?></h3>
                        <p class="text-sm text-on-surface/80 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]">person</span>
                            <span class="font-bold"><?= htmlspecialchars($ta['mahasiswa_nama']) ?></span> 
                            (<?= htmlspecialchars($ta['nim']) ?>)
                        </p>
                    </div>
                    
                    <?php if ($ta['status'] === 'Diajukan'): ?>
                        <div class="flex gap-2 w-full md:w-auto">
                            <form method="POST" class="flex-1 md:flex-none">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="update_status_ta">
                                <input type="hidden" name="ta_id" value="<?= $ta['id'] ?>">
                                <input type="hidden" name="status" value="Ditolak">
                                <button type="submit" class="btn-outline text-error border-error/50 hover:bg-error/10 w-full" onclick="return confirm('Tolak pengajuan ini?')">Tolak</button>
                            </form>
                            <form method="POST" class="flex-1 md:flex-none">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="update_status_ta">
                                <input type="hidden" name="ta_id" value="<?= $ta['id'] ?>">
                                <input type="hidden" name="status" value="Diterima">
                                <button type="submit" class="btn-primary w-full shadow-md shadow-primary/20" onclick="return confirm('Terima pengajuan ini?')">Terima TA</button>
                            </form>
                        </div>
                    <?php elseif (in_array($ta['status'], ['Diterima', 'Revisi'])): ?>
                        <div class="w-full md:w-auto text-right">
                            <form method="POST" class="inline-block">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="update_status_ta">
                                <input type="hidden" name="ta_id" value="<?= $ta['id'] ?>">
                                <input type="hidden" name="status" value="Lulus">
                                <button type="submit" class="bg-success text-white px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:bg-success/90" onclick="return confirm('Tandai sebagai Lulus?')">Set Lulus</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (in_array($ta['status'], ['Diterima', 'Revisi', 'Lulus'])): ?>
                    <div class="p-6 bg-surface-container-lowest">
                        <details class="group cursor-pointer">
                            <summary class="flex items-center gap-2 font-bold text-primary list-none">
                                <span class="material-symbols-outlined transition-transform group-open:rotate-90">chevron_right</span>
                                Lihat Logbook Bimbingan
                            </summary>
                            
                            <div class="mt-4 pl-8 border-l-2 border-outline-variant/30 space-y-4">
                                <?php $logbooks = $repo->getLogbookTABimbingan($ta['id'], $dosenRealId); ?>
                                <?php if (empty($logbooks)): ?>
                                    <p class="text-sm text-on-surface-variant italic">Belum ada catatan logbook dari mahasiswa.</p>
                                <?php else: ?>
                                    <?php foreach ($logbooks as $log): ?>
                                        <div class="bg-surface p-4 rounded-xl shadow-sm border border-outline-variant/20 relative">
                                            <div class="flex justify-between items-start mb-2">
                                                <div class="font-bold text-sm"><?= date('d F Y', strtotime($log['tanggal'])) ?></div>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $log['status'] === 'Disetujui' ? 'bg-success/10 text-success' : ($log['status'] === 'Revisi' ? 'bg-error/10 text-error' : 'bg-surface-variant text-on-surface') ?>">
                                                    <?= $log['status'] ?>
                                                </span>
                                            </div>
                                            <p class="text-sm mb-3"><?= nl2br(htmlspecialchars($log['kegiatan'])) ?></p>
                                            
                                            <?php if ($log['status'] === 'Menunggu'): ?>
                                                <form method="POST" class="bg-surface-container-low p-3 rounded-lg border border-outline-variant/30">
                                                    <?= Auth::csrfField() ?>
                                                    <input type="hidden" name="action" value="update_logbook">
                                                    <input type="hidden" name="ta_id" value="<?= $ta['id'] ?>">
                                                    <input type="hidden" name="logbook_id" value="<?= $log['id'] ?>">
                                                    
                                                    <label class="block text-xs font-bold mb-1">Berikan Catatan/Revisi:</label>
                                                    <textarea name="catatan_dosen" class="input-text text-sm p-2 mb-2" rows="2" placeholder="Tulis masukan di sini..."></textarea>
                                                    
                                                    <div class="flex justify-end gap-2">
                                                        <button type="submit" name="status" value="Revisi" class="btn-outline text-error border-error/50 hover:bg-error/10 text-xs py-1.5 px-3">Revisi</button>
                                                        <button type="submit" name="status" value="Disetujui" class="bg-success text-white px-3 py-1.5 rounded-lg text-xs font-bold hover:bg-success/90">Setujui Log</button>
                                                    </div>
                                                </form>
                                            <?php elseif (!empty($log['catatan_dosen'])): ?>
                                                <div class="bg-primary/5 p-3 rounded-lg border border-primary/10 mt-2">
                                                    <p class="text-xs font-bold text-primary mb-1">Catatan Anda:</p>
                                                    <p class="text-sm italic text-on-surface/80"><?= nl2br(htmlspecialchars($log['catatan_dosen'])) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                    </div>
                <?php else: ?>
                    <div class="p-6 bg-surface-container-low text-sm">
                        <p class="font-bold mb-1">Latar Belakang / Deskripsi:</p>
                        <p class="text-on-surface/80 leading-relaxed"><?= nl2br(htmlspecialchars($ta['deskripsi'])) ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
