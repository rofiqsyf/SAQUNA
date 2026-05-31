<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;
use Src\DosenRepository;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$dosenRepo = new DosenRepository();

$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);
if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$ta = $repo->getTugasAkhir((int)$mhs['id']);
$allDosen = $dosenRepo->getAll();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'ajukan_ta' && !$ta) {
            $dosenId = (int)$_POST['dosen_id'];
            $judul = trim($_POST['judul']);
            $deskripsi = trim($_POST['deskripsi']);
            
            if ($repo->ajukanTugasAkhir((int)$mhs['id'], $dosenId, $judul, $deskripsi)) {
                $success = "Judul Tugas Akhir berhasil diajukan!";
                $ta = $repo->getTugasAkhir((int)$mhs['id']); // Refresh data
            } else {
                $error = "Gagal mengajukan Tugas Akhir.";
            }
        }
    }
}

$logbooks = [];
if ($ta) {
    $logbooks = $repo->getLogbook((int)$ta['id']);
}

$title = "Tugas Akhir / Skripsi";
$current_page = "mahasiswa_ta.php";
include 'components/header.php';
?>

<div class="mb-4">
    <h1>Tugas Akhir & Bimbingan</h1>
    <p class="text-on-surface-variant opacity-80">Administrasi pengajuan judul dan logbook skripsi.</p>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (!$ta): ?>
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <h3 style="margin: 0;">Pengajuan Judul Skripsi</h3>
        </div>
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <form method="POST">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="ajukan_ta">
                
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Pilih Dosen Pembimbing</label>
                    <select name="dosen_id" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
                        <option value="">-- Pilih Dosen --</option>
                        <?php foreach ($allDosen as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama']) ?> (<?= htmlspecialchars($d['nidn']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Rencana Judul Penelitian</label>
                    <input type="text" name="judul" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required placeholder="Contoh: Implementasi Algoritma X untuk Y...">
                </div>

                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Deskripsi Singkat / Latar Belakang</label>
                    <textarea name="deskripsi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" rows="5" required placeholder="Jelaskan secara singkat apa yang ingin Anda teliti..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary btn-lg mt-3">Ajukan Judul</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40" style="border-left: 5px solid var(--primary);">
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <div class="flex justify-between items-center">
                <h3 style="margin:0;"><?= htmlspecialchars($ta['judul']) ?></h3>
                <span class="badge badge-<?= $ta['status'] === 'Diterima' ? 'success' : ($ta['status'] === 'Lulus' ? 'primary' : 'warning') ?>" style="font-size: 1rem;"><?= htmlspecialchars($ta['status']) ?></span>
            </div>
            <p class="text-muted mt-2"><strong>Dosen Pembimbing:</strong> <?= htmlspecialchars($ta['dosen_nama']) ?></p>
            <hr>
            <p style="margin-bottom: 0; font-size: 0.95rem;"><?= nl2br(htmlspecialchars($ta['deskripsi'])) ?></p>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <h3 style="margin: 0;">Logbook Bimbingan</h3>
            <button class="btn btn-sm btn-primary" onclick="alert('Fitur tambah logbook sedang dikembangkan.')">+ Tambah Bimbingan</button>
        </div>
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <?php if (empty($logbooks)): ?>
                <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Belum ada catatan bimbingan. Segera hubungi dosen pembimbing Anda.</div>
            <?php else: ?>
                <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Tanggal</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Kegiatan / Progres</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Catatan Dosen</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logbooks as $log): ?>
                                <tr>
                                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= date('d/m/Y', strtotime($log['tanggal'])) ?></td>
                                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= nl2br(htmlspecialchars($log['kegiatan'])) ?></td>
                                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= nl2br(htmlspecialchars($log['catatan_dosen'] ?: '-')) ?></td>
                                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><span class="badge badge-<?= $log['status'] === 'Disetujui' ? 'success' : 'warning' ?>"><?= htmlspecialchars($log['status']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
