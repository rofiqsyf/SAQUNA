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
        $action = $_POST['action'] ?? '';
        
        if ($action === 'tambah') {
            $kode = trim($_POST['kode_ruangan'] ?? '');
            $nama = trim($_POST['nama_ruangan'] ?? '');
            $gedungId = (int)($_POST['gedung_id'] ?? 0);
            $kapasitas = (int)($_POST['kapasitas'] ?? 0);
            $jenis = $_POST['jenis'] ?? 'Teori';
            
            try {
                $stmt = $pdo->prepare("INSERT INTO ruangan (kode_ruangan, nama_ruangan, gedung_id, kapasitas, jenis) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$kode, $nama, $gedungId ?: null, $kapasitas, $jenis]);
                $success = "Ruangan baru berhasil ditambahkan.";
            } catch (Exception $e) {
                $error = "Gagal menambahkan ruangan. Pastikan Kode Ruangan unik.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            try {
                $stmt = $pdo->prepare("DELETE FROM ruangan WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Ruangan berhasil dihapus.";
            } catch (Exception $e) {
                $error = "Ruangan tidak bisa dihapus karena sedang digunakan.";
            }
        }
    }
}

// Fetch Ruangan
$ruanganList = $pdo->query("
    SELECT r.*, g.nama_gedung, k.nama_kampus 
    FROM ruangan r 
    LEFT JOIN master_gedung g ON r.gedung_id = g.id 
    LEFT JOIN master_kampus k ON g.kampus_id = k.id 
    ORDER BY r.kode_ruangan ASC
")->fetchAll();

// Fetch Gedung for form
$gedungList = $pdo->query("SELECT g.id, g.nama_gedung, k.nama_kampus FROM master_gedung g JOIN master_kampus k ON g.kampus_id = k.id ORDER BY k.nama_kampus, g.nama_gedung")->fetchAll();

$title = "Manajemen Ruangan";
$current_page = "operator_ruangan.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Manajemen Ruangan</h1>
        <p class="text-on-surface-variant font-body-lg">Kelola data ruangan fisik untuk keperluan kelas dan praktikum.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md flex items-center gap-2">
        <span class="material-symbols-outlined">add_circle</span> Tambah Ruangan
    </button>
</div>

<?php if ($success): ?><div class="bg-primary/20 text-primary-fixed-dim p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Kode</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Ruangan</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Lokasi / Gedung</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Kapasitas</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Jenis</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ruanganList as $r): ?>
                <tr>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-bold text-primary"><?= htmlspecialchars($r['kode_ruangan']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-bold"><?= htmlspecialchars($r['nama_ruangan']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                        <?= $r['nama_kampus'] ? htmlspecialchars($r['nama_kampus'] . ' - ' . $r['nama_gedung']) : '-' ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= (int)$r['kapasitas'] ?> Kursi</td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <span class="px-2 py-1 bg-secondary/20 text-secondary-fixed-dim rounded-md font-label-sm">
                            <?= htmlspecialchars($r['jenis']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <form method="POST" class="inline" onsubmit="return confirm('Hapus ruangan ini?');">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="text-error hover:text-error-container">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($ruanganList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-6 text-on-surface-variant italic">Belum ada data ruangan.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-primary">Tambah Ruangan</h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Kode Ruangan</label>
                    <input type="text" name="kode_ruangan" required placeholder="Ex: R101" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Gedung Fakultas</label>
                    <select name="gedung_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Gedung --</option>
                        <?php foreach($gedungList as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_kampus'] . ' - ' . $g['nama_gedung']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Nama Lengkap Ruangan</label>
                <input type="text" name="nama_ruangan" required placeholder="Ex: Ruang Teori 101" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Kapasitas (Orang)</label>
                    <input type="number" name="kapasitas" required min="1" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jenis Ruangan</label>
                    <select name="jenis" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Teori">Teori</option>
                        <option value="Praktikum/Lab">Praktikum / Lab</option>
                        <option value="Studio">Studio</option>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Simpan Ruangan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalTambah = document.getElementById('modalTambah');
    if (modalTambah) document.body.appendChild(modalTambah);
});
</script>

<?php include 'components/footer.php'; ?>
