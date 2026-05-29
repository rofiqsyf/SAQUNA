<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireOperator();

$pdo = Database::getConnection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $mahasiswa_ids = $_POST['mahasiswa_ids'] ?? [];
        $dosen_wali_id = (int)($_POST['dosen_wali_id'] ?? 0);
        
        if ($dosen_wali_id > 0 && !empty($mahasiswa_ids)) {
            $placeholders = implode(',', array_fill(0, count($mahasiswa_ids), '?'));
            $params = array_merge([$dosen_wali_id], $mahasiswa_ids);
            
            $stmt = $pdo->prepare("UPDATE mahasiswa SET dosen_wali_id = ? WHERE id IN ($placeholders)");
            if ($stmt->execute($params)) {
                $count = count($mahasiswa_ids);
                $success = "$count mahasiswa berhasil di-assign ke Dosen Wali yang dipilih.";
            } else {
                $error = "Terjadi kesalahan sistem saat mengupdate data.";
            }
        } else {
            $error = "Pilih Dosen Wali dan minimal satu Mahasiswa.";
        }
    }
}

// Fetch Data Dosen
$stmtDosen = $pdo->query("SELECT id, nidn, nama, program_studi FROM dosen WHERE status = 'aktif' AND deleted_at IS NULL ORDER BY nama ASC");
$dosenList = $stmtDosen->fetchAll();

// Fetch Data Mahasiswa (dengan Dosen Walinya)
$stmtMhs = $pdo->query("SELECT m.id, m.nim, m.nama, m.program_studi, d.nama as dosen_wali_nama 
                        FROM mahasiswa m 
                        LEFT JOIN dosen d ON m.dosen_wali_id = d.id 
                        ORDER BY m.nim ASC");
$mahasiswaList = $stmtMhs->fetchAll();

$title = "Penugasan Dosen Wali";
$current_page = "operator_perwalian.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col justify-between items-start">
    <h1>Manajemen Dosen Wali</h1>
    <p class="text-on-surface-variant font-body-lg">Pilih mahasiswa dan assign ke Dosen Wali yang sesuai.</p>
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

<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <form method="POST">
        <?= Auth::csrfField() ?>
        
        <div class="mb-6 max-w-md">
            <label class="block text-sm font-semibold text-primary mb-2">Pilih Dosen Wali Tujuan</label>
            <select name="dosen_wali_id" required class="w-full bg-surface border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Pilih Dosen Wali --</option>
                <?php foreach ($dosenList as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama'] . ' (' . $d['program_studi'] . ')') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-primary">Daftar Mahasiswa</h2>
            <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary px-6 py-2 rounded-xl font-label-md transition-all shadow-md flex items-center gap-2">
                <span class="material-symbols-outlined">assignment_ind</span> Assign Terpilih
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-outline-variant/30 max-h-[500px] overflow-y-auto relative">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-surface z-10 shadow-sm">
                    <tr>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-12 text-center">
                            <input type="checkbox" id="checkAll" onchange="document.querySelectorAll('.mhs-check').forEach(c => c.checked = this.checked)">
                        </th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">NIM</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Mahasiswa</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Program Studi</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Dosen Wali Saat Ini</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mahasiswaList as $m): ?>
                    <tr class="hover:bg-surface-variant/10">
                        <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                            <input type="checkbox" name="mahasiswa_ids[]" value="<?= $m['id'] ?>" class="mhs-check">
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($m['nim']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-bold"><?= htmlspecialchars($m['nama']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($m['program_studi']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20">
                            <?php if ($m['dosen_wali_nama']): ?>
                                <span class="bg-secondary/20 text-secondary-fixed-dim px-2 py-1 rounded text-sm"><?= htmlspecialchars($m['dosen_wali_nama']) ?></span>
                            <?php else: ?>
                                <span class="text-error italic text-sm">Belum Ada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<?php include 'components/footer.php'; ?>
