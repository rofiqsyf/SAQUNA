<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireOperator();

$pdo = Database::getConnection();
$success = '';
$error = '';
$current_page = 'operator_periode_krs.php';
$page_title = 'Periode KRS';

// Cek tabel ada
try {
    $pdo->query("SELECT 1 FROM periode_krs LIMIT 1");
} catch (\PDOException $e) {
    die("<h3>Tabel periode_krs belum ada. Jalankan <a href='../migrate_new_features.php'>migrate_new_features.php</a> terlebih dahulu.</h3>");
}

// --- HANDLE POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        if ($action === 'tambah' || $action === 'edit') {
            $data = [
                'nama_periode'  => trim($_POST['nama_periode'] ?? ''),
                'semester'      => $_POST['semester'] ?? '',
                'tahun_ajaran'  => trim($_POST['tahun_ajaran'] ?? ''),
                'tanggal_buka'  => $_POST['tanggal_buka'] ?? '',
                'tanggal_tutup' => $_POST['tanggal_tutup'] ?? '',
                'status'        => $_POST['status'] ?? 'Tutup',
                'catatan'       => trim($_POST['catatan'] ?? ''),
            ];
            
            if (empty($data['nama_periode']) || empty($data['tanggal_buka']) || empty($data['tanggal_tutup'])) {
                $error = "Nama periode, tanggal buka, dan tanggal tutup wajib diisi.";
            } elseif (strtotime($data['tanggal_tutup']) <= strtotime($data['tanggal_buka'])) {
                $error = "Tanggal tutup harus setelah tanggal buka.";
            } else {
                if ($action === 'tambah') {
                    $stmt = $pdo->prepare("INSERT INTO periode_krs (nama_periode, semester, tahun_ajaran, tanggal_buka, tanggal_tutup, status, catatan, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$data['nama_periode'], $data['semester'], $data['tahun_ajaran'], $data['tanggal_buka'], $data['tanggal_tutup'], $data['status'], $data['catatan'], $userId])) {
                        Auth::logActivity($userId, 'create', 'periode_krs', null, "Membuat periode KRS: {$data['nama_periode']}", $pdo);
                        $success = "Periode KRS berhasil ditambahkan.";
                    } else {
                        $error = "Gagal menyimpan periode KRS.";
                    }
                } else {
                    $id = (int)($_POST['periode_id'] ?? 0);
                    $stmt = $pdo->prepare("UPDATE periode_krs SET nama_periode=?, semester=?, tahun_ajaran=?, tanggal_buka=?, tanggal_tutup=?, status=?, catatan=? WHERE id=?");
                    if ($stmt->execute([$data['nama_periode'], $data['semester'], $data['tahun_ajaran'], $data['tanggal_buka'], $data['tanggal_tutup'], $data['status'], $data['catatan'], $id])) {
                        Auth::logActivity($userId, 'update', 'periode_krs', $id, "Update periode KRS ID $id", $pdo);
                        $success = "Periode KRS berhasil diperbarui.";
                    } else {
                        $error = "Gagal memperbarui periode KRS.";
                    }
                }
            }
        } elseif ($action === 'toggle') {
            $id = (int)($_POST['periode_id'] ?? 0);
            $newStatus = $_POST['new_status'] ?? 'Tutup';
            $stmt = $pdo->prepare("UPDATE periode_krs SET status = ? WHERE id = ?");
            if ($stmt->execute([$newStatus, $id])) {
                Auth::logActivity($userId, 'update', 'periode_krs', $id, "Toggle status periode KRS ID $id → $newStatus", $pdo);
                $success = "Status periode berhasil diubah menjadi '$newStatus'.";
            } else {
                $error = "Gagal mengubah status.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['periode_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM periode_krs WHERE id = ?");
            if ($stmt->execute([$id])) {
                Auth::logActivity($userId, 'delete', 'periode_krs', $id, "Hapus periode KRS ID $id", $pdo);
                $success = "Periode KRS berhasil dihapus.";
            }
        }

        if (empty($error)) {
            header("Location: operator_periode_krs.php?msg=" . urlencode($success));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);

// Ambil semua periode KRS
$periodeList = $pdo->query("SELECT * FROM periode_krs ORDER BY tanggal_buka DESC")->fetchAll();

// Cek apakah ada periode yang sedang aktif
$now = date('Y-m-d H:i:s');
$periodeAktif = null;
foreach ($periodeList as $p) {
    if ($p['status'] === 'Buka' && $p['tanggal_buka'] <= $now && $p['tanggal_tutup'] >= $now) {
        $periodeAktif = $p;
        break;
    }
}

require_once __DIR__ . '/components/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Manajemen Periode KRS</h1>
        <p class="text-on-surface/70 mt-1">Buka dan tutup periode pengisian KRS per semester secara terkontrol.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="btn-primary">
        <span class="material-symbols-outlined text-[18px]">add</span> Buat Periode KRS
    </button>
</div>

<!-- Status Banner -->
<?php if ($periodeAktif): ?>
<div class="bg-success/10 border border-success/30 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 rounded-full bg-success/20 flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-success text-2xl" style="font-variation-settings: 'FILL' 1;">lock_open</span>
    </div>
    <div class="flex-1">
        <p class="font-bold text-success text-lg">KRS Sedang Buka 🟢</p>
        <p class="text-success/80 text-sm"><?= htmlspecialchars($periodeAktif['nama_periode']) ?> | Tutup: <?= date('d M Y, H:i', strtotime($periodeAktif['tanggal_tutup'])) ?></p>
    </div>
    <form method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="periode_id" value="<?= $periodeAktif['id'] ?>">
        <input type="hidden" name="new_status" value="Tutup">
        <button type="submit" class="bg-error text-white px-5 py-2.5 rounded-xl font-bold hover:bg-error/80 transition-all flex items-center gap-2" onclick="return confirm('Tutup periode KRS ini sekarang?')">
            <span class="material-symbols-outlined">lock</span> Tutup Sekarang
        </button>
    </form>
</div>
<?php else: ?>
<div class="bg-error/5 border border-error/20 rounded-2xl p-5 mb-6 flex items-center gap-4">
    <div class="w-12 h-12 rounded-full bg-error/10 flex items-center justify-center flex-shrink-0">
        <span class="material-symbols-outlined text-error text-2xl" style="font-variation-settings: 'FILL' 1;">lock</span>
    </div>
    <div>
        <p class="font-bold text-error text-lg">KRS Ditutup 🔴</p>
        <p class="text-error/70 text-sm">Tidak ada periode KRS yang sedang aktif. Mahasiswa tidak dapat mengisi KRS.</p>
    </div>
</div>
<?php endif; ?>

<!-- Alert -->
<?php if ($success): ?>
<div class="bg-primary/10 border border-primary/20 text-primary p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span> <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-error/10 border border-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Tabel Periode KRS -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <div class="table-responsive-wrapper">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Nama Periode</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Semester / T.A.</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Tanggal Buka</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Tanggal Tutup</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Status</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($periodeList as $p): 
                    $isOpen = $p['status'] === 'Buka';
                    $isPast = $p['tanggal_tutup'] < $now;
                    $isCurrent = $p['tanggal_buka'] <= $now && $p['tanggal_tutup'] >= $now;
                ?>
                <tr class="hover:bg-surface-variant/10 transition-colors">
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <p class="font-bold text-on-surface text-sm"><?= htmlspecialchars($p['nama_periode']) ?></p>
                        <?php if (!empty($p['catatan'])): ?>
                        <p class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($p['catatan']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-sm text-on-surface-variant">
                        <?= htmlspecialchars($p['semester']) ?> / <?= htmlspecialchars($p['tahun_ajaran']) ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-sm">
                        <?= date('d M Y, H:i', strtotime($p['tanggal_buka'])) ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-sm">
                        <?= date('d M Y, H:i', strtotime($p['tanggal_tutup'])) ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                        <?php if ($isOpen && $isCurrent): ?>
                        <span class="px-3 py-1 bg-success/10 text-success border border-success/20 rounded-full text-xs font-bold uppercase animate-pulse">🟢 Buka</span>
                        <?php elseif ($isOpen): ?>
                        <span class="px-3 py-1 bg-tertiary/10 text-tertiary border border-tertiary/20 rounded-full text-xs font-bold uppercase">🟡 Dijadwalkan</span>
                        <?php elseif ($isPast): ?>
                        <span class="px-3 py-1 bg-surface-variant/30 text-on-surface-variant border border-outline-variant/30 rounded-full text-xs font-bold uppercase">⬜ Selesai</span>
                        <?php else: ?>
                        <span class="px-3 py-1 bg-error/10 text-error border border-error/20 rounded-full text-xs font-bold uppercase">🔴 Tutup</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                        <div class="flex gap-2 justify-center items-center">
                            <!-- Toggle Status -->
                            <?php if (!$isPast): ?>
                            <form method="POST">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="new_status" value="<?= $isOpen ? 'Tutup' : 'Buka' ?>">
                                <button type="submit" 
                                        class="<?= $isOpen ? 'text-error hover:text-error/70 hover:bg-error/10' : 'text-success hover:text-success/70 hover:bg-success/10' ?> p-1 w-8 h-8 rounded-lg transition-all"
                                        title="<?= $isOpen ? 'Tutup Periode' : 'Buka Periode' ?>"
                                        onclick="return confirm('<?= $isOpen ? 'Tutup' : 'Buka' ?> periode KRS ini?')">
                                    <span class="material-symbols-outlined"><?= $isOpen ? 'lock' : 'lock_open' ?></span>
                                </button>
                            </form>
                            <?php endif; ?>
                            
                            <!-- Edit -->
                            <button onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($p)) ?>)"
                                    class="text-primary hover:text-primary/70 p-1 w-8 h-8 rounded-lg hover:bg-primary/10 transition-all" title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            
                            <!-- Hapus -->
                            <form method="POST">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="periode_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="text-error hover:text-error/70 p-1 w-8 h-8 rounded-lg hover:bg-error/10 transition-all" title="Hapus" onclick="return confirm('Hapus periode ini?')">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($periodeList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-10 text-on-surface-variant italic">
                        <span class="material-symbols-outlined text-4xl block mb-2 opacity-30">event_upcoming</span>
                        Belum ada periode KRS yang dibuat.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Panduan Singkat -->
<div class="card p-5 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 mt-6">
    <h3 class="font-bold text-primary mb-3 flex items-center gap-2">
        <span class="material-symbols-outlined">help_outline</span> Panduan Periode KRS
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-on-surface-variant">
        <div class="flex items-start gap-2">
            <span class="text-success font-bold">①</span>
            <p>Buat periode KRS baru dengan menentukan tanggal buka dan tutup.</p>
        </div>
        <div class="flex items-start gap-2">
            <span class="text-primary font-bold">②</span>
            <p>Ubah status menjadi <strong>Buka</strong> saat periode dimulai agar mahasiswa dapat mengisi KRS.</p>
        </div>
        <div class="flex items-start gap-2">
            <span class="text-error font-bold">③</span>
            <p>Ubah status menjadi <strong>Tutup</strong> setelah deadline untuk mencegah pengisian KRS baru.</p>
        </div>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg overflow-hidden border border-outline-variant/30">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">event_available</span> Buat Periode KRS
            </h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Nama Periode <span class="text-error">*</span></label>
                    <input type="text" name="nama_periode" required 
                           class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm"
                           placeholder="Contoh: KRS Semester Ganjil 2025/2026">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Semester</label>
                        <select name="semester" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" placeholder="2025/2026"
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tanggal Buka <span class="text-error">*</span></label>
                        <input type="datetime-local" name="tanggal_buka" required
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tanggal Tutup <span class="text-error">*</span></label>
                        <input type="datetime-local" name="tanggal_tutup" required
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Status Awal</label>
                    <select name="status" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="Tutup">Tutup (buka nanti)</option>
                        <option value="Buka">Buka Sekarang</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Catatan (Opsional)</label>
                    <textarea name="catatan" rows="2"
                              class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm"
                              placeholder="Catatan tambahan tentang periode ini..."></textarea>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" 
                        class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="btn-primary">Simpan Periode</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg overflow-hidden border border-outline-variant/30">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold text-secondary flex items-center gap-2">
                <span class="material-symbols-outlined">edit_calendar</span> Edit Periode KRS
            </h2>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="periode_id" id="edit_periode_id">
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Nama Periode</label>
                    <input type="text" name="nama_periode" id="edit_nama_periode" required
                           class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Semester</label>
                        <select name="semester" id="edit_semester" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                            <option value="Pendek">Pendek</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" id="edit_tahun_ajaran"
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tanggal Buka</label>
                        <input type="datetime-local" name="tanggal_buka" id="edit_tanggal_buka" required
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-primary mb-2">Tanggal Tutup</label>
                        <input type="datetime-local" name="tanggal_tutup" id="edit_tanggal_tutup" required
                               class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Status</label>
                    <select name="status" id="edit_status" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="Tutup">Tutup</option>
                        <option value="Buka">Buka</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Catatan</label>
                    <textarea name="catatan" id="edit_catatan" rows="2"
                              class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm"></textarea>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" 
                        class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-secondary hover:bg-secondary/80 text-on-secondary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Perbarui</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalEdit(data) {
    document.getElementById('edit_periode_id').value = data.id;
    document.getElementById('edit_nama_periode').value = data.nama_periode || '';
    document.getElementById('edit_semester').value = data.semester || 'Ganjil';
    document.getElementById('edit_tahun_ajaran').value = data.tahun_ajaran || '';
    document.getElementById('edit_tanggal_buka').value = data.tanggal_buka ? data.tanggal_buka.slice(0, 16) : '';
    document.getElementById('edit_tanggal_tutup').value = data.tanggal_tutup ? data.tanggal_tutup.slice(0, 16) : '';
    document.getElementById('edit_status').value = data.status || 'Tutup';
    document.getElementById('edit_catatan').value = data.catatan || '';
    document.getElementById('modalEdit').classList.remove('hidden');
}
document.addEventListener('DOMContentLoaded', () => {
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalEdit) document.body.appendChild(modalEdit);
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
