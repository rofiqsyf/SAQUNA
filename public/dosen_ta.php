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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_status_ta') {
            $taId = (int)$_POST['ta_id'];
            $status = $_POST['status'];
            if ($repo->updateStatusTA($dosenId, $taId, $status)) {
                $success = "Status pengajuan Tugas Akhir berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui status TA.";
            }
        } elseif ($action === 'update_logbook') {
            $logId = (int)$_POST['log_id'];
            $status = $_POST['status'];
            $catatan = trim($_POST['catatan']);
            if ($repo->updateStatusLogbook($dosenId, $logId, $status, $catatan)) {
                $success = "Logbook berhasil diverifikasi!";
            } else {
                $error = "Gagal memverifikasi logbook.";
            }
        }
    }
}

$daftarTA = $repo->getTugasAkhirBimbingan($dosenId);

$title = "Bimbingan Tugas Akhir";
$current_page = "dosen_ta.php";
include 'components/header.php';
?>

<div class="mb-4">
    <h1>Bimbingan Tugas Akhir & Skripsi</h1>
    <p class="text-on-surface-variant opacity-80">Kelola pengajuan judul dan periksa logbook bimbingan mahasiswa Anda.</p>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (empty($daftarTA)): ?>
    <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Belum ada mahasiswa bimbingan yang mengajukan Tugas Akhir.</div>
<?php else: ?>
    <div class="row" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        <?php foreach ($daftarTA as $ta): 
            $logbooks = $repo->getLogbookTA((int)$ta['id']);
        ?>
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40" style="border-top: 5px solid var(--primary);">
            <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
                <div class="flex justify-between items-center mb-2">
                    <h3 style="margin: 0;"><?= htmlspecialchars($ta['judul']) ?></h3>
                    <span class="badge badge-<?= $ta['status'] === 'Diterima' || $ta['status'] === 'Lulus' ? 'success' : 'warning' ?>"><?= htmlspecialchars($ta['status']) ?></span>
                </div>
                <p style="margin-bottom: 0.5rem;"><strong>Mahasiswa:</strong> <?= htmlspecialchars($ta['mhs_nama']) ?> (<?= htmlspecialchars($ta['nim']) ?>)</p>
                <p class="text-on-surface-variant opacity-80" style="font-size: 0.95rem; margin-bottom: 1.5rem;"><?= nl2br(htmlspecialchars($ta['deskripsi'])) ?></p>

                <!-- Form Ubah Status TA -->
                <form method="POST" class="d-flex gap-2 align-items-center mb-4" style="background: var(--bg-light); padding: 1rem; border-radius: 8px;">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="update_status_ta">
                    <input type="hidden" name="ta_id" value="<?= $ta['id'] ?>">
                    <strong>Ubah Status Pengajuan:</strong>
                    <select name="status" class="form-control form-control-sm" style="width: auto;">
                        <option value="Diajukan" <?= $ta['status'] === 'Diajukan' ? 'selected' : '' ?>>Diajukan (Pending)</option>
                        <option value="Diterima" <?= $ta['status'] === 'Diterima' ? 'selected' : '' ?>>Diterima</option>
                        <option value="Revisi" <?= $ta['status'] === 'Revisi' ? 'selected' : '' ?>>Revisi</option>
                        <option value="Ditolak" <?= $ta['status'] === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        <option value="Lulus" <?= $ta['status'] === 'Lulus' ? 'selected' : '' ?>>Lulus Ujian Akhir</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Simpan Status</button>
                </form>

                <hr>
                <h4>Logbook Bimbingan (<?= count($logbooks) ?> Catatan)</h4>
                <?php if (empty($logbooks)): ?>
                    <p class="text-on-surface-variant opacity-80">Mahasiswa belum mengisi logbook bimbingan.</p>
                <?php else: ?>
                    <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Tanggal</th>
                                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Laporan Progres / Kegiatan</th>
                                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Status & Catatan Dosen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logbooks as $log): ?>
                                <tr>
                                    <td style="width: 15%;"><?= date('d/m/Y', strtotime($log['tanggal'])) ?></td>
                                    <td style="width: 45%;"><?= nl2br(htmlspecialchars($log['kegiatan'])) ?></td>
                                    <td style="width: 40%;">
                                        <form method="POST" style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <?= Auth::csrfField() ?>
                                            <input type="hidden" name="action" value="update_logbook">
                                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                            
                                            <div class="d-flex gap-2">
                                                <select name="status" class="form-control form-control-sm">
                                                    <option value="Menunggu" <?= $log['status'] === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                                    <option value="Disetujui" <?= $log['status'] === 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                                                    <option value="Revisi" <?= $log['status'] === 'Revisi' ? 'selected' : '' ?>>Revisi</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-secondary">Simpan</button>
                                            </div>
                                            <textarea name="catatan" class="form-control form-control-sm" rows="2" placeholder="Catatan untuk mahasiswa..."><?= htmlspecialchars($log['catatan_dosen'] ?? '') ?></textarea>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
