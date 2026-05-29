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
        
        if ($action === 'buat') {
            $judul = trim($_POST['judul']);
            $isi = trim($_POST['isi']);
            $target = $_POST['target_role'];
            $kategori = $_POST['kategori'] ?? 'Umum';
            
            if ($repo->buatPengumuman($judul, $isi, $target, $kategori)) {
                $success = "Pengumuman berhasil diterbitkan!";
            } else {
                $error = "Gagal menerbitkan pengumuman.";
            }
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $judul = trim($_POST['judul']);
            $isi = trim($_POST['isi']);
            $target = $_POST['target_role'];
            $kategori = $_POST['kategori'] ?? 'Umum';
            
            if ($repo->updatePengumuman($id, $judul, $isi, $target, $kategori)) {
                $success = "Pengumuman berhasil diperbarui!";
            } else {
                $error = "Gagal memperbarui pengumuman.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)$_POST['id'];
            if ($repo->hapusPengumuman($id)) {
                $success = "Pengumuman berhasil dihapus.";
            } else {
                $error = "Gagal menghapus pengumuman.";
            }
        }
    }
}

// Prepare filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'target_role' => $_GET['target_role'] ?? '',
    'kategori' => $_GET['kategori'] ?? ''
];

$pengumuman = $repo->getSemuaPengumuman($filters);

$title = "Manajemen Pengumuman";
$current_page = "operator_pengumuman.php";
include 'components/header.php';
?>

<div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-on-surface mb-2">Manajemen Pengumuman</h1>
        <p class="text-on-surface-variant opacity-80">Siarkan berita dan informasi penting ke Dasbor Dosen dan Mahasiswa.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-4 py-2 rounded-xl shadow-lg transition-all flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">add</span> Buat Pengumuman
    </button>
</div>

<!-- Filter Panel -->
<div class="bg-surface-container-lowest rounded-2xl p-4 mb-6 shadow-sm border border-outline-variant/30">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Cari judul atau isi pengumuman..." class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div>
            <select name="target_role" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Target --</option>
                <option value="semua" <?= $filters['target_role'] === 'semua' ? 'selected' : '' ?>>Semua (Dosen & Mahasiswa)</option>
                <option value="dosen" <?= $filters['target_role'] === 'dosen' ? 'selected' : '' ?>>Khusus Dosen</option>
                <option value="mahasiswa" <?= $filters['target_role'] === 'mahasiswa' ? 'selected' : '' ?>>Khusus Mahasiswa</option>
            </select>
        </div>
        <div class="flex gap-2">
            <select name="kategori" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
                <option value="">-- Semua Kategori --</option>
                <option value="Umum" <?= $filters['kategori'] === 'Umum' ? 'selected' : '' ?>>Umum / Akademik</option>
                <option value="Event" <?= $filters['kategori'] === 'Event' ? 'selected' : '' ?>>Event Kemahasiswaan</option>
                <option value="Beasiswa" <?= $filters['kategori'] === 'Beasiswa' ? 'selected' : '' ?>>Info Beasiswa</option>
            </select>
            <button type="submit" class="bg-secondary-container text-on-secondary-container px-3 py-2 rounded-lg hover:bg-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
            <a href="operator_pengumuman.php" class="bg-surface-variant text-on-surface px-3 py-2 rounded-lg hover:bg-outline-variant transition-colors flex items-center">
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

<?php if (empty($pengumuman)): ?>
    <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center justify-center gap-2 py-8">
        Belum ada pengumuman yang sesuai dengan filter Anda.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 gap-4">
        <?php foreach ($pengumuman as $p): ?>
        <div class="bg-surface-container-lowest rounded-3xl p-6 shadow-sm border border-outline-variant/30 relative hover:shadow-md transition-shadow">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h4 class="text-xl font-bold text-on-surface m-0 mb-2"><?= htmlspecialchars($p['judul']) ?></h4>
                    <div class="flex flex-wrap gap-2 items-center">
                        <span class="bg-primary/20 text-primary px-3 py-1 rounded-full font-label-md text-xs font-bold">Target: <?= strtoupper(htmlspecialchars($p['target_role'])) ?></span>
                        <span class="bg-secondary/20 text-secondary px-3 py-1 rounded-full font-label-md text-xs font-bold">Kategori: <?= htmlspecialchars($p['kategori'] ?? 'Umum') ?></span>
                        <small class="text-on-surface-variant opacity-80 text-xs flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">calendar_today</span>
                            <?= date('d M Y, H:i', strtotime($p['created_at'])) ?>
                        </small>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button onclick="openEditModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['judul'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['target_role'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['kategori'] ?? 'Umum', ENT_QUOTES) ?>', `<?= htmlspecialchars($p['isi'], ENT_QUOTES) ?>`)" class="bg-primary-container text-primary p-2 rounded-lg hover:bg-primary hover:text-white transition-colors" title="Edit Pengumuman">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                    </button>
                    <form method="POST" class="inline" onsubmit="return confirm('Hapus pengumuman ini secara permanen?');">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="bg-error-container text-error p-2 rounded-lg hover:bg-error hover:text-white transition-colors" title="Hapus Pengumuman">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                        </button>
                    </form>
                </div>
            </div>
            <div class="bg-surface p-4 rounded-xl border border-outline-variant/10 text-on-surface-variant text-sm whitespace-pre-wrap leading-relaxed">
                <?= htmlspecialchars($p['isi']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Tulis Pengumuman Baru</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="buat">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Judul Pengumuman</label>
                    <input type="text" name="judul" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required placeholder="Contoh: Jadwal Ujian Akhir Semester">
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tujuan Penyiaran (Target Role)</label>
                        <select name="target_role" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required>
                            <option value="semua">Semua (Dosen & Mahasiswa)</option>
                            <option value="dosen">Khusus Dosen</option>
                            <option value="mahasiswa">Khusus Mahasiswa</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Kategori Pengumuman</label>
                        <select name="kategori" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required>
                            <option value="Umum">Umum / Akademik</option>
                            <option value="Event">Event Kemahasiswaan</option>
                            <option value="Beasiswa">Info Beasiswa</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Isi Pengumuman</label>
                    <textarea name="isi" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" rows="5" required placeholder="Tulis rincian pengumuman di sini..."></textarea>
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">send</span> Terbitkan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Pengumuman</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Judul Pengumuman</label>
                    <input type="text" name="judul" id="edit_judul" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Tujuan Penyiaran (Target Role)</label>
                        <select name="target_role" id="edit_target" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required>
                            <option value="semua">Semua (Dosen & Mahasiswa)</option>
                            <option value="dosen">Khusus Dosen</option>
                            <option value="mahasiswa">Khusus Mahasiswa</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-1">Kategori Pengumuman</label>
                        <select name="kategori" id="edit_kategori" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" required>
                            <option value="Umum">Umum / Akademik</option>
                            <option value="Event">Event Kemahasiswaan</option>
                            <option value="Beasiswa">Info Beasiswa</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Isi Pengumuman</label>
                    <textarea name="isi" id="edit_isi" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none" rows="5" required></textarea>
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
function openEditModal(id, judul, target, kategori, isi) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_judul').value = judul;
    document.getElementById('edit_target').value = target;
    document.getElementById('edit_kategori').value = kategori;
    document.getElementById('edit_isi').value = isi;
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
