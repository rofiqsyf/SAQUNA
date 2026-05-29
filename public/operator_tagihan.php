<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'tambah') {
            $mahasiswaId = (int)$_POST['mahasiswa_id'];
            $semester = $_POST['semester'];
            $tahunAjaran = trim($_POST['tahun_ajaran']);
            $nominal = (float)$_POST['nominal'];
            $status = $_POST['status'];
            
            if ($repo->createTagihan($mahasiswaId, $semester, $tahunAjaran, $nominal, $status)) {
                $success = "Tagihan berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan tagihan.";
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['tagihan_id'];
            $semester = $_POST['semester'];
            $tahunAjaran = trim($_POST['tahun_ajaran']);
            $nominal = (float)$_POST['nominal'];
            $status = $_POST['status'];
            
            if ($repo->updateTagihan($id, $semester, $tahunAjaran, $nominal, $status)) {
                $success = "Data tagihan berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui data tagihan.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)$_POST['tagihan_id'];
            if ($repo->deleteTagihan($id)) {
                $success = "Data tagihan berhasil dihapus.";
            } else {
                $error = "Gagal menghapus data tagihan.";
            }
        } elseif ($action === 'validasi') {
            $tagihanId = (int)$_POST['tagihan_id'];
            if ($repo->validasiPembayaran($tagihanId)) {
                $success = "Pembayaran berhasil divalidasi! Status berubah menjadi LUNAS.";
            } else {
                $error = "Gagal memvalidasi pembayaran.";
            }
        }
    }
}

// Prepare filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'semester' => $_GET['semester'] ?? '',
    'tahun_ajaran' => $_GET['tahun_ajaran'] ?? ''
];

$tagihan = $repo->getAllTagihan($filters);
$mahasiswaList = $repo->getAllMahasiswa();

$title = "Manajemen Keuangan";
$current_page = "operator_tagihan.php";
include 'components/header.php';
?>

<div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-on-surface mb-2">Manajemen Keuangan & UKT</h1>
        <p class="text-on-surface-variant opacity-80">Pantau status pembayaran mahasiswa, buat tagihan baru, dan lakukan validasi.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-4 py-2 rounded-xl shadow-lg transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">add</span> Tambah Tagihan
    </button>
</div>

<!-- Filter Panel -->
<div class="bg-surface-container-lowest rounded-2xl p-4 mb-6 shadow-sm border border-outline-variant/30">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Cari NIM atau Nama..." class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div>
            <select name="status" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Status --</option>
                <option value="Lunas" <?= $filters['status'] === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                <option value="Belum Lunas" <?= $filters['status'] === 'Belum Lunas' ? 'selected' : '' ?>>Belum Lunas</option>
            </select>
        </div>
        <div>
            <select name="semester" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Semester --</option>
                <option value="Ganjil" <?= $filters['semester'] === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $filters['semester'] === 'Genap' ? 'selected' : '' ?>>Genap</option>
                <option value="Pendek" <?= $filters['semester'] === 'Pendek' ? 'selected' : '' ?>>Pendek</option>
            </select>
        </div>
        <div class="flex gap-2">
            <input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($filters['tahun_ajaran']) ?>" placeholder="Tahun Ajaran" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
            <button type="submit" class="bg-secondary-container text-on-secondary-container px-3 py-2 rounded-lg hover:bg-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
            <a href="operator_tagihan.php" class="bg-surface-variant text-on-surface px-3 py-2 rounded-lg hover:bg-outline-variant transition-colors flex items-center">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </a>
        </div>
    </form>
</div>

<?php if ($success): ?>
    <div class="p-4 mb-6 rounded-xl border bg-green-50 border-green-200 text-green-800 flex items-center gap-3">
        <span class="material-symbols-outlined">check_circle</span>
        <p><?= htmlspecialchars($success) ?></p>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="p-4 mb-6 rounded-xl border bg-red-50 border-red-200 text-red-800 flex items-center gap-3">
        <span class="material-symbols-outlined">error</span>
        <p><?= htmlspecialchars($error) ?></p>
    </div>
<?php endif; ?>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Mahasiswa</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Semester / TA</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nominal Tagihan</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Status</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Waktu Bayar</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tagihan)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-on-surface-variant">Tidak ada tagihan ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($tagihan as $t): ?>
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($t['mhs_nama']) ?></strong><br><small><?= htmlspecialchars($t['nim']) ?></small></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($t['semester'] . ' ' . $t['tahun_ajaran']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-primary font-bold">Rp <?= number_format((float)$t['nominal'], 0, ',', '.') ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <?php if ($t['status'] === 'Lunas'): ?>
                                <span class="bg-primary/20 text-primary px-3 py-1 rounded-full font-label-md text-xs">Lunas</span>
                            <?php else: ?>
                                <span class="bg-error/20 text-error px-3 py-1 rounded-full font-label-md text-xs">Belum Lunas</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-xs text-on-surface-variant"><?= $t['waktu_bayar'] ? date('d M Y, H:i', strtotime($t['waktu_bayar'])) : '-' ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <div class="flex items-center justify-center gap-2">
                                <?php if ($t['status'] !== 'Lunas'): ?>
                                    <form method="POST" class="inline">
                                        <?= Auth::csrfField() ?>
                                        <input type="hidden" name="action" value="validasi">
                                        <input type="hidden" name="tagihan_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="bg-secondary-container text-on-secondary-container p-2 rounded-lg hover:bg-secondary hover:text-white transition-colors" title="Validasi Lunas" onclick="return confirm('Validasi pembayaran menjadi LUNAS untuk mahasiswa ini?')">
                                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button onclick="openEditModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['semester']) ?>', '<?= htmlspecialchars($t['tahun_ajaran'], ENT_QUOTES) ?>', <?= $t['nominal'] ?>, '<?= htmlspecialchars($t['status']) ?>')" class="bg-primary-container text-primary p-2 rounded-lg hover:bg-primary hover:text-white transition-colors" title="Edit Tagihan">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus data tagihan ini?');">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="tagihan_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="bg-error-container text-error p-2 rounded-lg hover:bg-error hover:text-white transition-colors" title="Hapus Tagihan">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Buat Tagihan Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Mahasiswa</label>
                    <select name="mahasiswa_id" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Mahasiswa --</option>
                        <?php foreach($mahasiswaList as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nim'] . ' - ' . $m['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Semester</label>
                        <select name="semester" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" placeholder="2024/2025" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" min="0" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Status Pembayaran</label>
                    <select name="status" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Belum Lunas">Belum Lunas</option>
                        <option value="Lunas">Lunas</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span> Simpan Tagihan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Data Tagihan</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="tagihan_id" id="edit_tagihan_id">
            
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Semester</label>
                        <select name="semester" id="edit_semester" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" id="edit_nominal" min="0" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Status Pembayaran</label>
                    <select name="status" id="edit_status" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Belum Lunas">Belum Lunas</option>
                        <option value="Lunas">Lunas</option>
                    </select>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, semester, tahunAjaran, nominal, status) {
    document.getElementById('edit_tagihan_id').value = id;
    document.getElementById('edit_semester').value = semester;
    document.getElementById('edit_tahun_ajaran').value = tahunAjaran;
    document.getElementById('edit_nominal').value = nominal;
    document.getElementById('edit_status').value = status;
    document.getElementById('modalEdit').classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalEdit) document.body.appendChild(modalEdit);
});
</script>

<?php include 'components/footer.php'; ?>
