<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireOperator();

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Proses form admin (update status / upload file)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        
        if ($action === 'update_status') {
            $newStatus = $_POST['status'] ?? '';
            $catatan = trim($_POST['catatan_operator'] ?? '');
            
            if ($newStatus === 'Selesai') {
                // Handle file upload jika status selesai
                $fileUrl = null;
                if (isset($_FILES['file_surat']) && $_FILES['file_surat']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['file_surat']['name'], PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $uploadDir = __DIR__ . '/../uploads/surat/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                        
                        $filename = 'surat_' . $id . '_' . time() . '.pdf';
                        $targetPath = $uploadDir . $filename;
                        
                        if (move_uploaded_file($_FILES['file_surat']['tmp_name'], $targetPath)) {
                            $fileUrl = 'uploads/surat/' . $filename;
                        } else {
                            $error = "Gagal mengunggah file surat.";
                        }
                    } else {
                        $error = "File surat harus berformat PDF.";
                    }
                }
                
                if (empty($error)) {
                    $stmt = $pdo->prepare("UPDATE layanan_surat SET status = 'Selesai', catatan_operator = ?, file_surat = COALESCE(?, file_surat) WHERE id = ?");
                    if ($stmt->execute([$catatan, $fileUrl, $id])) {
                        Auth::logActivity($userId, 'update', 'layanan_surat', $id, "Menyelesaikan surat ID $id", $pdo);
                        $success = "Surat berhasil ditandai selesai.";
                    } else {
                        $error = "Gagal memperbarui status surat.";
                    }
                }
            } else {
                $stmt = $pdo->prepare("UPDATE layanan_surat SET status = ?, catatan_operator = ? WHERE id = ?");
                if ($stmt->execute([$newStatus, $catatan, $id])) {
                    Auth::logActivity($userId, 'update', 'layanan_surat', $id, "Mengubah status surat ID $id ke $newStatus", $pdo);
                    $success = "Status pengajuan surat diperbarui menjadi $newStatus.";
                } else {
                    $error = "Gagal memperbarui status.";
                }
            }
        } elseif ($action === 'hapus') {
            $stmt = $pdo->prepare("DELETE FROM layanan_surat WHERE id = ?");
            if ($stmt->execute([$id])) {
                Auth::logActivity($userId, 'delete', 'layanan_surat', $id, "Hapus pengajuan surat ID $id", $pdo);
                $success = "Data pengajuan berhasil dihapus.";
            } else {
                $error = "Gagal menghapus data.";
            }
        }
        
        if (empty($error)) {
            header("Location: operator_layanan.php?msg=" . urlencode($success));
            exit;
        }
    }
}

if (isset($_GET['msg'])) $success = htmlspecialchars($_GET['msg']);

// Filter & Pagination (Sederhana)
$filterStatus = $_GET['status'] ?? 'Pending';
$whereClause = "";
$params = [];
if ($filterStatus !== 'Semua') {
    $whereClause = "WHERE ls.status = ?";
    $params[] = $filterStatus;
}

// Ambil data pengajuan
$sql = "SELECT ls.*, m.nim, m.nama as nama_mahasiswa, m.program_studi 
        FROM layanan_surat ls 
        JOIN mahasiswa m ON ls.mahasiswa_id = m.id 
        $whereClause 
        ORDER BY ls.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$suratList = $stmt->fetchAll();

$current_page = 'operator_layanan.php';
$page_title = 'Layanan Administrasi';
require_once __DIR__ . '/components/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Manajemen Layanan Surat</h1>
        <p class="text-on-surface/70 mt-1">Verifikasi, proses, dan terbitkan surat keterangan digital mahasiswa.</p>
    </div>
</div>

<!-- Filter Box -->
<div class="card p-4 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 mb-6 flex flex-wrap gap-2">
    <?php
    $statuses = ['Semua', 'Pending', 'Diproses', 'Selesai', 'Ditolak'];
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

<!-- Tabel Pengajuan -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <?php if (empty($suratList)): ?>
    <div class="p-10 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30 mb-2">mark_email_read</span>
        <p>Tidak ada pengajuan surat dengan status ini.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive-wrapper">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 w-12">No</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Pemohon</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Jenis & Keperluan</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Tanggal</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Status</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                <?php 
                $no = 1;
                $statusColors = [
                    'Pending' => 'bg-tertiary/10 text-tertiary border-tertiary/20',
                    'Diproses' => 'bg-secondary/10 text-secondary border-secondary/20',
                    'Selesai' => 'bg-success/10 text-success border-success/20',
                    'Ditolak' => 'bg-error/10 text-error border-error/20'
                ];
                foreach ($suratList as $r): 
                    $badgeCls = $statusColors[$r['status']] ?? 'bg-surface-variant text-on-surface-variant';
                ?>
                <tr class="hover:bg-surface-variant/10 transition-colors">
                    <td class="px-5 py-4 text-sm text-on-surface-variant text-center"><?= $no++ ?></td>
                    <td class="px-5 py-4">
                        <p class="font-bold text-on-surface text-sm"><?= htmlspecialchars($r['nama_mahasiswa']) ?></p>
                        <p class="text-xs text-on-surface-variant mt-0.5"><?= htmlspecialchars($r['nim']) ?></p>
                        <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($r['program_studi']) ?></p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="font-bold text-primary text-sm"><?= htmlspecialchars($r['jenis_surat']) ?></p>
                        <p class="text-xs text-on-surface-variant line-clamp-2 mt-0.5 max-w-xs" title="<?= htmlspecialchars($r['keperluan']) ?>">
                            <?= htmlspecialchars($r['keperluan']) ?>
                        </p>
                        <?php if (!empty($r['catatan_operator'])): ?>
                            <p class="text-[11px] text-error mt-1 p-1 bg-error/5 rounded border border-error/10 break-words">Catatan: <?= htmlspecialchars($r['catatan_operator']) ?></p>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-sm text-on-surface-variant">
                        <?= date('d M Y', strtotime($r['created_at'])) ?><br>
                        <span class="text-xs opacity-70"><?= date('H:i', strtotime($r['created_at'])) ?></span>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <span class="px-3 py-1 rounded-full text-xs font-bold border <?= $badgeCls ?>">
                            <?= $r['status'] ?>
                        </span>
                        <?php if ($r['status'] === 'Selesai' && !empty($r['file_surat'])): ?>
                        <br>
                        <a href="../<?= htmlspecialchars($r['file_surat']) ?>" target="_blank" class="inline-block mt-2 text-[10px] bg-primary/10 text-primary hover:bg-primary/20 px-2 py-1 rounded font-bold transition-colors">Lihat File</a>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4 text-center">
                        <div class="flex gap-2 justify-center items-center">
                            <button onclick='bukaModalProses(<?= json_encode($r) ?>)'
                                    class="bg-primary/10 text-primary hover:bg-primary hover:text-white p-2 rounded-lg transition-all" title="Proses Surat">
                                <span class="material-symbols-outlined text-[20px]"><?= $r['status'] === 'Selesai' ? 'visibility' : 'edit_document' ?></span>
                            </button>
                            
                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus pengajuan ini secara permanen?')">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="bg-error/10 text-error hover:bg-error hover:text-white p-2 rounded-lg transition-all" title="Hapus">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Proses -->
<div id="modalProses" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">edit_document</span> Proses Surat
            </h2>
            <button onclick="document.getElementById('modalProses').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/30">
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-bold text-on-surface-variant uppercase">Pemohon</span>
                    <span class="text-sm font-bold text-primary" id="v_nama"></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-bold text-on-surface-variant uppercase">NIM</span>
                    <span class="text-sm font-bold text-on-surface" id="v_nim"></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-xs font-bold text-on-surface-variant uppercase">Jenis Surat</span>
                    <span class="text-sm font-bold text-on-surface" id="v_jenis"></span>
                </div>
                <div>
                    <span class="text-xs font-bold text-on-surface-variant uppercase block mb-1">Keperluan</span>
                    <p class="text-sm text-on-surface bg-surface p-2 rounded-lg border border-outline-variant/20 italic" id="v_keperluan"></p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Ubah Status</label>
                <select name="status" id="edit_status" onchange="toggleFileUpload()" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all">
                    <option value="Pending">Pending (Baru Masuk)</option>
                    <option value="Diproses">Sedang Diproses</option>
                    <option value="Selesai">Selesai & Terbitkan</option>
                    <option value="Ditolak">Ditolak</option>
                </select>
            </div>
            
            <div id="fileUploadContainer" class="hidden">
                <label class="block text-sm font-semibold text-success mb-2">Upload File Surat (PDF)</label>
                <div class="border-2 border-dashed border-success/30 rounded-xl p-4 text-center hover:bg-success/5 transition-colors cursor-pointer relative">
                    <input type="file" name="file_surat" accept="application/pdf" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer">
                    <span class="material-symbols-outlined text-success text-3xl mb-1">upload_file</span>
                    <p class="text-sm font-bold text-success">Klik atau Seret PDF ke sini</p>
                    <p class="text-xs text-on-surface-variant">Batas maksimal: 2MB. Hanya format .pdf</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Catatan (Opsional / Wajib jika ditolak)</label>
                <textarea name="catatan_operator" id="edit_catatan" rows="2" placeholder="Alasan penolakan atau catatan tambahan untuk mahasiswa..." class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('modalProses').classList.add('hidden')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-container-low font-bold transition-all">
                    Batal
                </button>
                <button type="submit" class="btn-primary">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalProses(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('v_nama').innerText = data.nama_mahasiswa;
    document.getElementById('v_nim').innerText = data.nim;
    document.getElementById('v_jenis').innerText = data.jenis_surat;
    document.getElementById('v_keperluan').innerText = data.keperluan;
    document.getElementById('edit_status').value = data.status;
    document.getElementById('edit_catatan').value = data.catatan_operator || '';
    
    toggleFileUpload();
    document.getElementById('modalProses').classList.remove('hidden');
}

function toggleFileUpload() {
    const status = document.getElementById('edit_status').value;
    const uploadContainer = document.getElementById('fileUploadContainer');
    
    if (status === 'Selesai') {
        uploadContainer.classList.remove('hidden');
    } else {
        uploadContainer.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const modalProses = document.getElementById('modalProses');
    if (modalProses) document.body.appendChild(modalProses);
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
