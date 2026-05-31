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
        $krsId = (int)($_POST['krs_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        
        if ($krsId > 0 && in_array($status, ['Menunggu', 'Disetujui', 'Ditolak'])) {
            if ($repo->updateStatusKrs($dosenId, $krsId, $status)) {
                $success = "Status KRS berhasil diubah menjadi: " . htmlspecialchars($status);
            } else {
                $error = "Gagal mengubah status KRS.";
            }
        } elseif (isset($_POST['simpan_catatan'])) {
            $mhsId = (int)$_POST['mahasiswa_id'];
            $catatan = trim($_POST['catatan'] ?? '');
            $semester = $_POST['semester'] ?? '';
            
            if (!empty($catatan) && !empty($semester)) {
                if ($repo->addCatatanPerwalian($dosenId, $mhsId, $semester, $catatan)) {
                    $success = "Catatan perwalian berhasil dikirim.";
                } else {
                    $error = "Gagal mengirim catatan perwalian.";
                }
            }
        }
        
        // Fitur validasi massal per mahasiswa
        $mhsIdValidasi = (int)($_POST['mahasiswa_id_massal'] ?? 0);
        $statusMassal = $_POST['status_massal'] ?? '';
        if ($mhsIdValidasi > 0 && in_array($statusMassal, ['Disetujui', 'Ditolak'])) {
            $allKrs = $repo->getKrsMenunggu($dosenId);
            $count = 0;
            foreach($allKrs as $k) {
                if ($k['mahasiswa_id'] == $mhsIdValidasi && $k['status'] === 'Menunggu') {
                    $repo->updateStatusKrs($dosenId, (int)$k['id'], $statusMassal);
                    $count++;
                }
            }
            $success = "$count mata kuliah berhasil di-$statusMassal sekaligus!";
        }
    }
}

$semuaKrs = $repo->getKrsMenunggu($dosenId);

// Group by Mahasiswa
$groupedKrs = [];
foreach ($semuaKrs as $k) {
    $mId = $k['mahasiswa_id'];
    if (!isset($groupedKrs[$mId])) {
        $groupedKrs[$mId] = [
            'nama' => $k['mhs_nama'],
            'nim' => $k['nim'],
            'semester' => $k['semester_aktif'],
            'mata_kuliah' => []
        ];
    }
    $groupedKrs[$mId]['mata_kuliah'][] = $k;
}

$title = "Persetujuan KRS";
$current_page = "dosen_krs.php";
include 'components/header.php';
?>

<div class="mb-4">
    <h1>Persetujuan Kartu Rencana Studi (KRS)</h1>
    <p class="text-on-surface-variant opacity-80">Validasi daftar mata kuliah yang diambil oleh mahasiswa perwalian Anda.</p>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if (empty($groupedKrs)): ?>
    <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Belum ada pengajuan KRS yang masuk ke akun Anda.</div>
<?php else: ?>
    <div class="row" style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        <?php foreach ($groupedKrs as $mhsId => $data): ?>
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
            <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
                <div class="flex justify-between items-center mb-3">
                    <h3 style="margin: 0;"><?= htmlspecialchars($data['nama']) ?> <small class="text-on-surface-variant opacity-80">(<?= htmlspecialchars($data['nim']) ?>)</small></h3>
                    
                    <form method="POST" class="d-flex gap-2">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="mahasiswa_id_massal" value="<?= $mhsId ?>">
                        <button type="submit" name="status_massal" value="Disetujui" class="btn btn-sm btn-success" onclick="return confirm('ACC semua mata kuliah yang masih Menunggu untuk mahasiswa ini?')">ACC Semua</button>
                        <button type="submit" name="status_massal" value="Ditolak" class="btn btn-sm btn-danger" onclick="return confirm('Tolak semua mata kuliah yang masih Menunggu untuk mahasiswa ini?')">Tolak Semua</button>
                    </form>
                </div>
                
                <!-- Form Catatan Perwalian -->
                <form method="POST" class="mb-4 bg-surface-container-low p-3 rounded-xl border border-outline-variant/30 flex gap-2">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="mahasiswa_id" value="<?= $mhsId ?>">
                    <input type="hidden" name="semester" value="<?= htmlspecialchars($data['semester']) ?>">
                    <textarea name="catatan" class="input-text w-full text-sm p-2" rows="2" placeholder="Tinggalkan catatan untuk mahasiswa (contoh: 'Perbaiki IPK dulu baru ambil 24 SKS')" required></textarea>
                    <button type="submit" name="simpan_catatan" class="btn-primary text-sm whitespace-nowrap self-end"><span class="material-symbols-outlined text-[16px]">send</span> Kirim Catatan</button>
                </form>
                
                <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Mata Kuliah</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">SKS</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Semester Ajuan</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Status</th>
                                <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi (Per MK)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalSks = 0;
                            foreach ($data['mata_kuliah'] as $mk): 
                                $totalSks += (int)$mk['sks'];
                            ?>
                            <tr>
                                <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($mk['mk_nama']) ?></strong></td>
                                <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= $mk['sks'] ?> SKS</td>
                                <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">Semester <?= htmlspecialchars($mk['semester_aktif']) ?></td>
                                <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                    <?php if ($mk['status'] === 'Disetujui'): ?>
                                        <span class="bg-secondary-fixed text-on-secondary-fixed px-3 py-1 rounded-full font-label-md text-xs">Disetujui</span>
                                    <?php elseif ($mk['status'] === 'Ditolak'): ?>
                                        <span class="bg-error-container text-on-error-container px-3 py-1 rounded-full font-label-md text-xs">Ditolak</span>
                                    <?php else: ?>
                                        <span class="bg-tertiary-container text-on-tertiary-container px-3 py-1 rounded-full font-label-md text-xs">Menunggu</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                    <form method="POST" style="display: inline-block;">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="krs_id" value="<?= $mk['id'] ?>">
                                        <select name="status" class="form-control form-control-sm d-inline-block" style="width: auto;" onchange="this.form.submit()">
                                            <option value="Menunggu" <?= $mk['status'] === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                                            <option value="Disetujui" <?= $mk['status'] === 'Disetujui' ? 'selected' : '' ?>>Disetujui</option>
                                            <option value="Ditolak" <?= $mk['status'] === 'Ditolak' ? 'selected' : '' ?>>Ditolak</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="1" class="text-right">Total SKS Diambil:</th>
                                <th colspan="4"><?= $totalSks ?> SKS</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
