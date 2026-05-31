<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireLogin();
if (Auth::getRole() !== 'dosen') {
    die("Akses ditolak.");
}

$repo = new DosenRepository();
$dosenId = $_SESSION['user_id']; // This is actually user_id, I need dosen_id. Wait!
// Auth doesn't store dosen_id in session, it stores user_id. Let's fetch dosen_id first.
$pdo = \Config\Database::getConnection();
$stmt = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$dosenRealId = $stmt->fetchColumn();

if (!$dosenRealId) {
    die("Data dosen tidak valid.");
}

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'add_penelitian') {
            $data = [
                'judul' => $_POST['judul'],
                'tahun' => (int)$_POST['tahun'],
                'link_publikasi' => $_POST['link_publikasi'] ?? '',
                'jenis' => $_POST['jenis']
            ];
            if ($repo->addPenelitian($dosenRealId, $data)) {
                $successMsg = "Penelitian berhasil ditambahkan.";
            } else {
                $errorMsg = "Gagal menambahkan penelitian.";
            }
        } elseif ($action === 'add_pengabdian') {
            $data = [
                'judul' => $_POST['judul'],
                'tahun' => (int)$_POST['tahun'],
                'lokasi' => $_POST['lokasi'] ?? '',
                'deskripsi' => $_POST['deskripsi'] ?? ''
            ];
            if ($repo->addPengabdian($dosenRealId, $data)) {
                $successMsg = "Pengabdian berhasil ditambahkan.";
            } else {
                $errorMsg = "Gagal menambahkan pengabdian.";
            }
        } elseif ($action === 'delete_penelitian') {
            if ($repo->deletePenelitian((int)$_POST['id'], $dosenRealId)) {
                $successMsg = "Penelitian berhasil dihapus.";
            }
        } elseif ($action === 'delete_pengabdian') {
            if ($repo->deletePengabdian((int)$_POST['id'], $dosenRealId)) {
                $successMsg = "Pengabdian berhasil dihapus.";
            }
        }
    }
}

$penelitian = $repo->getPenelitianDosen($dosenRealId);
$pengabdian = $repo->getPengabdianDosen($dosenRealId);

$title = "Portofolio Tridharma";
$current_page = "dosen_portofolio.php";
require_once __DIR__ . '/components/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Portofolio Tridharma</h1>
        <p class="text-on-surface/70 mt-1">Kelola rekam jejak Penelitian dan Pengabdian kepada Masyarakat.</p>
    </div>
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Kolom Penelitian -->
    <div class="card p-0 overflow-hidden flex flex-col h-full">
        <div class="bg-primary/5 p-5 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">science</span> Penelitian & Jurnal
            </h2>
            <button onclick="document.getElementById('modalPenelitian').classList.remove('hidden')" class="btn-primary py-2 px-4 text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">add</span> Tambah
            </button>
        </div>
        <div class="p-5 flex-1 overflow-y-auto">
            <?php if (empty($penelitian)): ?>
                <div class="text-center py-10 text-on-surface-variant/50">
                    <span class="material-symbols-outlined text-4xl mb-2">article</span>
                    <p>Belum ada data penelitian.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($penelitian as $p): ?>
                        <div class="p-4 border border-outline-variant/30 rounded-xl hover:border-primary/50 transition-colors bg-surface relative group">
                            <form method="POST" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="delete_penelitian">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-error hover:bg-error/10 p-1.5 rounded-lg" onclick="return confirm('Hapus penelitian ini?')">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </form>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-0.5 text-xs font-bold rounded-md <?= $p['jenis'] === 'Internasional' ? 'bg-tertiary-container text-on-tertiary-container' : 'bg-primary-container text-on-primary-container' ?>">
                                    <?= htmlspecialchars($p['jenis']) ?>
                                </span>
                                <span class="text-sm font-medium text-on-surface-variant"><span class="material-symbols-outlined text-[14px] align-middle">calendar_today</span> <?= $p['tahun'] ?></span>
                            </div>
                            <h3 class="font-bold text-lg leading-tight mb-2 pr-8"><?= htmlspecialchars($p['judul']) ?></h3>
                            <?php if (!empty($p['link_publikasi'])): ?>
                                <a href="<?= htmlspecialchars($p['link_publikasi']) ?>" target="_blank" class="text-primary text-sm font-medium hover:underline flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px]">link</span> Lihat Publikasi
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Pengabdian -->
    <div class="card p-0 overflow-hidden flex flex-col h-full">
        <div class="bg-secondary/5 p-5 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-secondary">diversity_3</span> Pengabdian Masyarakat
            </h2>
            <button onclick="document.getElementById('modalPengabdian').classList.remove('hidden')" class="bg-secondary text-on-secondary hover:bg-secondary/90 px-4 py-2 rounded-xl font-bold shadow-sm transition-all text-sm flex items-center gap-1">
                <span class="material-symbols-outlined text-[18px]">add</span> Tambah
            </button>
        </div>
        <div class="p-5 flex-1 overflow-y-auto">
            <?php if (empty($pengabdian)): ?>
                <div class="text-center py-10 text-on-surface-variant/50">
                    <span class="material-symbols-outlined text-4xl mb-2">volunteer_activism</span>
                    <p>Belum ada data pengabdian.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pengabdian as $p): ?>
                        <div class="p-4 border border-outline-variant/30 rounded-xl hover:border-secondary/50 transition-colors bg-surface relative group">
                            <form method="POST" class="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="delete_pengabdian">
                                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-error hover:bg-error/10 p-1.5 rounded-lg" onclick="return confirm('Hapus pengabdian ini?')">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </form>
                            
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-medium text-on-surface-variant bg-surface-container px-2 py-0.5 rounded flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px]">calendar_today</span> <?= $p['tahun'] ?>
                                </span>
                            </div>
                            <h3 class="font-bold text-lg leading-tight mb-2 pr-8"><?= htmlspecialchars($p['judul']) ?></h3>
                            <?php if (!empty($p['lokasi'])): ?>
                                <p class="text-sm text-on-surface-variant flex items-start gap-1 mt-2">
                                    <span class="material-symbols-outlined text-[16px] text-secondary mt-0.5">location_on</span>
                                    <?= htmlspecialchars($p['lokasi']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($p['deskripsi'])): ?>
                                <p class="text-sm text-on-surface/80 mt-2 line-clamp-2"><?= htmlspecialchars($p['deskripsi']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Penelitian -->
<div id="modalPenelitian" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-black">Tambah Penelitian</h3>
            <button type="button" onclick="document.getElementById('modalPenelitian').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-on-surface/10 text-on-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form method="POST" id="formPenelitian" class="space-y-4">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="add_penelitian">
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Judul Penelitian <span class="text-error">*</span></label>
                    <textarea name="judul" required class="input-text" rows="3" placeholder="Masukkan judul penelitian lengkap..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="block text-sm font-bold mb-1">Tahun <span class="text-error">*</span></label>
                        <input type="number" name="tahun" required class="input-text" value="<?= date('Y') ?>" min="2000" max="<?= date('Y') + 1 ?>">
                    </div>
                    <div class="form-group">
                        <label class="block text-sm font-bold mb-1">Jenis <span class="text-error">*</span></label>
                        <select name="jenis" class="input-text" required>
                            <option value="Nasional">Nasional</option>
                            <option value="Internasional">Internasional</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Link Publikasi (Opsional)</label>
                    <input type="url" name="link_publikasi" class="input-text" placeholder="https://doi.org/...">
                </div>
            </form>
        </div>
        <div class="p-5 border-t border-outline-variant/30 flex justify-end gap-3 bg-surface-container-low mt-auto">
            <button type="button" onclick="document.getElementById('modalPenelitian').classList.add('hidden')" class="btn-outline">Batal</button>
            <button type="submit" form="formPenelitian" class="btn-primary shadow-md">Simpan Penelitian</button>
        </div>
    </div>
</div>

<!-- Modal Tambah Pengabdian -->
<div id="modalPengabdian" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[100] flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-black">Tambah Pengabdian</h3>
            <button type="button" onclick="document.getElementById('modalPengabdian').classList.add('hidden')" class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-on-surface/10 text-on-surface-variant transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto">
            <form method="POST" id="formPengabdian" class="space-y-4">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="add_pengabdian">
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Judul PkM <span class="text-error">*</span></label>
                    <textarea name="judul" required class="input-text" rows="2" placeholder="Judul kegiatan..."></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="form-group">
                        <label class="block text-sm font-bold mb-1">Tahun <span class="text-error">*</span></label>
                        <input type="number" name="tahun" required class="input-text" value="<?= date('Y') ?>" min="2000" max="<?= date('Y') + 1 ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Lokasi (Opsional)</label>
                    <input type="text" name="lokasi" class="input-text" placeholder="Desa X, Kec. Y...">
                </div>

                <div class="form-group">
                    <label class="block text-sm font-bold mb-1">Deskripsi Singkat (Opsional)</label>
                    <textarea name="deskripsi" class="input-text" rows="3" placeholder="Penjelasan ringkas hasil pengabdian..."></textarea>
                </div>
            </form>
        </div>
        <div class="p-5 border-t border-outline-variant/30 flex justify-end gap-3 bg-surface-container-low mt-auto">
            <button type="button" onclick="document.getElementById('modalPengabdian').classList.add('hidden')" class="btn-outline">Batal</button>
            <button type="submit" form="formPengabdian" class="bg-secondary text-on-secondary hover:bg-secondary/90 px-6 py-2.5 rounded-xl font-bold shadow-md transition-all">Simpan PkM</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Pindahkan modal ke document.body agar tidak terpengaruh stacking context dari <main>
    const modalPenelitian = document.getElementById('modalPenelitian');
    const modalPengabdian = document.getElementById('modalPengabdian');
    
    if (modalPenelitian) document.body.appendChild(modalPenelitian);
    if (modalPengabdian) document.body.appendChild(modalPengabdian);
});
</script>

<?php include 'components/footer.php'; ?>
