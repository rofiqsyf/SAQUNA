<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireOperator();

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'proses_yudisium') {
            $status = $_POST['status'] ?? '';
            $catatan = trim($_POST['catatan'] ?? '');
            
            if ($status === 'Disetujui') {
                $tglLulus = $_POST['tanggal_lulus'] ?? '';
                $noSk = trim($_POST['no_sk'] ?? '');
                
                if (empty($tglLulus) || empty($noSk)) {
                    $error = "Tanggal Lulus dan No. SK wajib diisi jika Disetujui.";
                } else {
                    $stmt = $pdo->prepare("UPDATE yudisium SET status_pengajuan = 'Disetujui', tanggal_lulus = ?, no_sk = ?, catatan = ? WHERE id = ?");
                    if ($stmt->execute([$tglLulus, $noSk, $catatan, $id])) {
                        Auth::logActivity($userId, 'update', 'yudisium', $id, "Menyetujui yudisium ID $id", $pdo);
                        $success = "Yudisium berhasil disetujui.";
                    } else {
                        $error = "Gagal menyetujui yudisium.";
                    }
                }
            } else {
                $stmt = $pdo->prepare("UPDATE yudisium SET status_pengajuan = ?, catatan = ? WHERE id = ?");
                if ($stmt->execute([$status, $catatan, $id])) {
                    Auth::logActivity($userId, 'update', 'yudisium', $id, "Mengubah status yudisium ID $id ke $status", $pdo);
                    $success = "Status yudisium berhasil diubah menjadi $status.";
                } else {
                    $error = "Gagal mengubah status yudisium.";
                }
            }
        }
        
        if (empty($error)) {
            header("Location: operator_yudisium.php?msg=" . urlencode($success));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);

// Filter status
$filterStatus = $_GET['status'] ?? 'Semua';
$whereClause = "";
$params = [];
if ($filterStatus !== 'Semua') {
    $whereClause = "WHERE y.status_pengajuan = ?";
    $params[] = $filterStatus;
}

// Ambil data yudisium
$sql = "SELECT y.*, m.nim, m.nama as nama_mahasiswa, m.program_studi 
        FROM yudisium y 
        JOIN mahasiswa m ON y.mahasiswa_id = m.id 
        $whereClause 
        ORDER BY y.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$yudisiumList = $stmt->fetchAll();

$current_page = 'operator_yudisium.php';
$page_title = 'Manajemen Yudisium';
require_once __DIR__ . '/components/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Manajemen Yudisium</h1>
        <p class="text-on-surface/70 mt-1">Verifikasi pengajuan yudisium dan penerbitan kelulusan mahasiswa.</p>
    </div>
</div>

<!-- Filter Box -->
<div class="card p-4 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 mb-6 flex flex-wrap gap-2">
    <?php
    $statuses = ['Semua', 'Diajukan', 'Disetujui', 'Ditolak'];
    foreach ($statuses as $st):
        $active = $filterStatus === $st;
        $cls = $active ? 'bg-primary text-white font-bold border-primary' : 'bg-surface text-on-surface-variant hover:bg-surface-variant/30 border-outline-variant/30';
    ?>
    <a href="?status=<?= urlencode($st) ?>" class="px-4 py-2 border rounded-full text-sm transition-all <?= $cls ?>">
        <?= $st ?>
    </a>
    <?php endforeach; ?>
</div>

<!-- Alerts -->
<?php if ($success): ?>
<div class="bg-success/10 border border-success/20 text-success p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">check_circle</span> <?= $success ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-error/10 border border-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2">
    <span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Tabel Yudisium -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <?php if (empty($yudisiumList)): ?>
    <div class="p-10 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30 mb-2">school</span>
        <p>Tidak ada data pengajuan yudisium.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive-wrapper">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 w-12">No</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Mahasiswa</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Tanggal Pengajuan</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">SK Lulus</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Status</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                <?php 
                $no = 1;
                $statusColors = [
                    'Diajukan' => 'bg-tertiary/10 text-tertiary border-tertiary/20',
                    'Disetujui' => 'bg-success/10 text-success border-success/20',
                    'Ditolak' => 'bg-error/10 text-error border-error/20'
                ];
                foreach ($yudisiumList as $r): 
                    $badgeCls = $statusColors[$r['status_pengajuan']] ?? 'bg-surface-variant text-on-surface-variant';
                ?>
                <tr class="hover:bg-surface-variant/10 transition-colors">
                    <td class="px-5 py-4 text-sm text-on-surface-variant text-center"><?= $no++ ?></td>
                    <td class="px-5 py-4">
                        <p class="font-bold text-on-surface text-sm"><?= htmlspecialchars($r['nama_mahasiswa']) ?></p>
                        <p class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($r['nim']) ?> - <?= htmlspecialchars($r['program_studi']) ?></p>
                    </td>
                    <td class="px-5 py-4 text-sm text-on-surface-variant">
                        <?= date('d M Y, H:i', strtotime($r['created_at'])) ?>
                    </td>
                    <td class="px-5 py-4 text-sm">
                        <?php if ($r['status_pengajuan'] === 'Disetujui'): ?>
                            <p class="font-bold text-primary"><?= htmlspecialchars($r['no_sk'] ?? '-') ?></p>
                            <p class="text-xs text-on-surface-variant mt-0.5">Tgl: <?= $r['tanggal_lulus'] ? date('d M Y', strtotime($r['tanggal_lulus'])) : '-' ?></p>
                        <?php else: ?>
                            <span class="text-on-surface-variant opacity-50">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-bold border <?= $badgeCls ?>">
                            <?= $r['status_pengajuan'] ?>
                        </span>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <button onclick='bukaModalYudisium(<?= json_encode($r) ?>)'
                                class="bg-primary/10 text-primary hover:bg-primary hover:text-white px-4 py-2 rounded-lg transition-all text-sm font-bold flex items-center gap-1 mx-auto" title="Verifikasi">
                            <span class="material-symbols-outlined text-[18px]">verified_user</span> Proses
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Proses Yudisium -->
<div id="modalYudisium" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">school</span> Proses Kelulusan
            </h2>
            <button onclick="document.getElementById('modalYudisium').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="proses_yudisium">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/30 text-center">
                <span class="material-symbols-outlined text-4xl text-on-surface-variant mb-2">person</span>
                <h3 class="font-bold text-lg text-on-surface" id="v_nama"></h3>
                <p class="text-sm text-on-surface-variant" id="v_nim_prodi"></p>
            </div>

            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Keputusan Yudisium</label>
                <select name="status" id="edit_status" onchange="toggleSkFields()" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all">
                    <option value="Diajukan">Masih Diajukan (Belum diproses)</option>
                    <option value="Disetujui">Lulus (Disetujui)</option>
                    <option value="Ditolak">Ditolak (Ada Syarat Belum Terpenuhi)</option>
                </select>
            </div>
            
            <div id="skFields" class="hidden space-y-4 border-l-4 border-success pl-4 ml-1">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Nomor SK Yudisium <span class="text-error">*</span></label>
                    <input type="text" name="no_sk" id="edit_no_sk" placeholder="Misal: SK/001/YUD/2026" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tanggal Lulus <span class="text-error">*</span></label>
                    <input type="date" name="tanggal_lulus" id="edit_tgl_lulus" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Catatan Tambahan (Opsional)</label>
                <textarea name="catatan" id="edit_catatan" rows="2" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('modalYudisium').classList.add('hidden')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-container-low font-bold transition-all">
                    Batal
                </button>
                <button type="submit" class="btn-primary">
                    Simpan Keputusan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalYudisium(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('v_nama').innerText = data.nama_mahasiswa;
    document.getElementById('v_nim_prodi').innerText = data.nim + ' — ' + data.program_studi;
    
    document.getElementById('edit_status').value = data.status_pengajuan;
    document.getElementById('edit_no_sk').value = data.no_sk || '';
    document.getElementById('edit_tgl_lulus').value = data.tanggal_lulus || '';
    document.getElementById('edit_catatan').value = data.catatan || '';
    
    toggleSkFields();
    document.getElementById('modalYudisium').classList.remove('hidden');
}

function toggleSkFields() {
    const status = document.getElementById('edit_status').value;
    const skFields = document.getElementById('skFields');
    
    if (status === 'Disetujui') {
        skFields.classList.remove('hidden');
        document.getElementById('edit_no_sk').required = true;
        document.getElementById('edit_tgl_lulus').required = true;
    } else {
        skFields.classList.add('hidden');
        document.getElementById('edit_no_sk').required = false;
        document.getElementById('edit_tgl_lulus').required = false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalYudisium = document.getElementById('modalYudisium');
    if (modalYudisium) document.body.appendChild(modalYudisium);
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
