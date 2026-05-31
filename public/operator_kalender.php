<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\KalenderRepository;

Auth::requireOperator();

$repo = new KalenderRepository();
$success = '';
$error = '';

// Proses form tambah/edit/hapus
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        if ($action === 'tambah') {
            $data = [
                'nama_event' => $_POST['nama_event'] ?? '',
                'jenis_event' => $_POST['jenis_event'] ?? '',
                'tanggal_mulai' => $_POST['tanggal_mulai'] ?? '',
                'tanggal_akhir' => $_POST['tanggal_akhir'] ?? '',
                'semester' => $_POST['semester'] ?? '',
                'tahun_ajaran' => $_POST['tahun_ajaran'] ?? ''
            ];
            if ($repo->createEvent($data, $userId)) {
                $success = "Event kalender akademik berhasil ditambahkan.";
            } else {
                $error = "Gagal menambahkan event.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            if ($repo->deleteEvent($id, $userId)) {
                $success = "Event berhasil dihapus.";
            } else {
                $error = "Gagal menghapus event.";
            }
        }
    }
}

$events = $repo->getAllEvents();

$title = "Manajemen Kalender Akademik";
$current_page = "operator_kalender.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Kalender Akademik</h1>
        <p class="text-on-surface-variant font-body-lg">Kelola periode KRS, UTS, UAS, dan libur institusi.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md flex items-center gap-2">
        <span class="material-symbols-outlined">add_circle</span> Tambah Event
    </button>
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
    <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Event</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Jenis</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Periode</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Semester</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">T.A.</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $e): ?>
                <tr>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-bold"><?= htmlspecialchars($e['nama_event']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <span class="px-2 py-1 bg-secondary/20 text-secondary-fixed-dim rounded-md font-label-sm">
                            <?= htmlspecialchars($e['jenis_event']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <?= date('d M Y', strtotime($e['tanggal_mulai'])) ?> - <?= date('d M Y', strtotime($e['tanggal_akhir'])) ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20"><?= htmlspecialchars($e['semester']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20"><?= htmlspecialchars($e['tahun_ajaran']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <form method="POST" class="inline" onsubmit="return confirm('Hapus event ini?');">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            <button type="submit" class="text-error hover:text-error-container">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($events)): ?>
                <tr>
                    <td colspan="6" class="text-center py-6 text-on-surface-variant italic">Belum ada data kalender akademik.</td>
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
            <h2 class="text-xl font-bold text-primary">Tambah Event Kalender</h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Nama Event</label>
                <input type="text" name="nama_event" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Jenis Event</label>
                <select name="jenis_event" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                    <option value="Periode KRS">Periode KRS</option>
                    <option value="Perubahan KRS">Perubahan KRS</option>
                    <option value="UTS">UTS</option>
                    <option value="UAS">UAS</option>
                    <option value="Wisuda">Wisuda</option>
                    <option value="Libur">Libur</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tanggal Mulai</label>
                    <input type="date" name="tanggal_mulai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Semester</label>
                    <select name="semester" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                        <option value="Pendek">Pendek</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" placeholder="Ex: 2024/2025" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Simpan Event</button>
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
