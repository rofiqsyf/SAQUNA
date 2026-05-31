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
            $namaBeasiswa = trim($_POST['nama_beasiswa']);
            $tahun = trim($_POST['tahun']);
            $status = $_POST['status'];
            
            if ($repo->tambahBeasiswaPenerima($mahasiswaId, $namaBeasiswa, $tahun, $status)) {
                $success = "Data penerima beasiswa berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan data penerima beasiswa.";
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['beasiswa_id'];
            $namaBeasiswa = trim($_POST['nama_beasiswa']);
            $tahun = trim($_POST['tahun']);
            $status = $_POST['status'];
            
            if ($repo->updateBeasiswaPenerima($id, $namaBeasiswa, $tahun, $status)) {
                $success = "Data penerima beasiswa berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui data penerima beasiswa.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)$_POST['id'];
            if ($repo->hapusBeasiswaPenerima($id)) {
                $success = "Data penerima beasiswa berhasil dihapus.";
            } else {
                $error = "Gagal menghapus data beasiswa.";
            }
        }
    }
}

// Prepare filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? '',
    'program_studi' => $_GET['program_studi'] ?? '',
    'tahun' => $_GET['tahun'] ?? ''
];

$penerima = $repo->getAllBeasiswaPenerima($filters);
$mahasiswaList = $repo->getAllMahasiswa();
$prodiList = $repo->getAllProdi();

$title = "Manajemen Penerima Beasiswa";
$current_page = "operator_beasiswa.php";
include 'components/header.php';
?>

<div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-on-surface mb-2">Manajemen Penerima Beasiswa</h1>
        <p class="text-on-surface-variant opacity-80">Kontrol daftar mahasiswa yang menerima beasiswa untuk ditampilkan pada dasbor mereka.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-4 py-2 rounded-xl shadow-lg transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">add</span> Tambah Penerima
    </button>
</div>

<!-- Filter Panel -->
<div class="bg-surface-container-lowest rounded-2xl p-4 mb-6 shadow-sm border border-outline-variant/30">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Cari NIM, Nama Mahasiswa, atau Nama Beasiswa..." class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div>
            <select name="program_studi" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Prodi --</option>
                <?php foreach($prodiList as $prodi): ?>
                    <option value="<?= htmlspecialchars($prodi['nama_prodi']) ?>" <?= $filters['program_studi'] === $prodi['nama_prodi'] ? 'selected' : '' ?>><?= htmlspecialchars($prodi['nama_prodi']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select name="status" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Status --</option>
                <option value="Aktif" <?= $filters['status'] === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="Selesai" <?= $filters['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                <option value="Dibatalkan" <?= $filters['status'] === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
            </select>
        </div>
        <div class="flex gap-2">
            <input type="text" name="tahun" value="<?= htmlspecialchars($filters['tahun']) ?>" placeholder="Tahun Akademik" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
            <button type="submit" class="bg-secondary-container text-on-secondary-container px-3 py-2 rounded-lg hover:bg-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
            <a href="operator_beasiswa.php" class="bg-surface-variant text-on-surface px-3 py-2 rounded-lg hover:bg-outline-variant transition-colors flex items-center">
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
    <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Mahasiswa</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Program Studi</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Program Beasiswa</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Tahun Akademik</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Status</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($penerima)): ?>
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-on-surface-variant opacity-60">Belum ada data penerima beasiswa yang cocok.</td>
                    </tr>
                <?php else: foreach ($penerima as $p): ?>
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($p['mhs_nama']) ?></strong><br><small><?= htmlspecialchars($p['nim']) ?></small></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($p['program_studi'] ?? '-') ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md font-bold text-tertiary"><?= htmlspecialchars($p['nama_beasiswa']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-center"><?= htmlspecialchars($p['tahun']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <span class="px-3 py-1 rounded-full font-label-md text-xs <?= $p['status'] === 'Aktif' ? 'bg-primary/20 text-primary' : ($p['status'] === 'Selesai' ? 'bg-surface-variant text-on-surface-variant' : 'bg-error/20 text-error') ?>">
                                <?= htmlspecialchars($p['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openEditModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['nama_beasiswa'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['tahun'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['status']) ?>')" class="bg-primary-container text-primary p-2 rounded-lg hover:bg-primary hover:text-white transition-colors" title="Edit">
                                    <span class="material-symbols-outlined text-[18px]">edit</span>
                                </button>
                                
                                <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus data beasiswa ini?');">
                                    <?= Auth::csrfField() ?>
                                    <input type="hidden" name="action" value="hapus">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="bg-error-container text-error p-2 rounded-lg hover:bg-error hover:text-white transition-colors" title="Hapus">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Input Penerima Beasiswa Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Pilih Mahasiswa</label>
                    <select name="mahasiswa_id" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Mahasiswa --</option>
                        <?php foreach($mahasiswaList as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nim'] . ' - ' . $m['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Program Beasiswa</label>
                    <input type="text" name="nama_beasiswa" placeholder="Contoh: Beasiswa Djarum Plus" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tahun Akademik</label>
                        <input type="text" name="tahun" placeholder="Contoh: 2025/2026" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Status Beasiswa</label>
                        <select name="status" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                            <option value="Aktif">Aktif</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span> Simpan Data</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Data Beasiswa</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="beasiswa_id" id="edit_beasiswa_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Program Beasiswa</label>
                    <input type="text" name="nama_beasiswa" id="edit_nama_beasiswa" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tahun Akademik</label>
                        <input type="text" name="tahun" id="edit_tahun" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Status Beasiswa</label>
                        <select name="status" id="edit_status" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                            <option value="Aktif">Aktif</option>
                            <option value="Selesai">Selesai</option>
                            <option value="Dibatalkan">Dibatalkan</option>
                        </select>
                    </div>
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
function openEditModal(id, namaBeasiswa, tahun, status) {
    document.getElementById('edit_beasiswa_id').value = id;
    document.getElementById('edit_nama_beasiswa').value = namaBeasiswa;
    document.getElementById('edit_tahun').value = tahun;
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
