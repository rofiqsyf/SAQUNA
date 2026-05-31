<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireOperator();

$repo = new DosenRepository();
$pdo = Database::getConnection();

$success = '';
$error = '';
$current_page = 'master_dosen.php';
$page_title = 'Master Data Dosen';

// --- HANDLE POST ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        if ($action === 'tambah' || $action === 'edit') {
            $data = [
                'nidn' => trim($_POST['nidn'] ?? ''),
                'nama' => trim($_POST['nama'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'fakultas' => trim($_POST['fakultas'] ?? ''),
                'program_studi' => trim($_POST['program_studi'] ?? ''),
                'status' => $_POST['status'] ?? 'aktif',
                'tempat_tanggal_lahir' => trim($_POST['tempat_tanggal_lahir'] ?? ''),
                'jenis_kelamin' => trim($_POST['jenis_kelamin'] ?? ''),
                'no_hp' => trim($_POST['no_hp'] ?? ''),
                'alamat_asal' => trim($_POST['alamat_asal'] ?? ''),
                'domisili' => trim($_POST['domisili'] ?? ''),
                'foto' => null,
            ];
            
            // Handle foto upload
            if (!empty($_FILES['foto']['name']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
                $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                if (in_array($ext, $allowedExt)) {
                    $uploadDir = __DIR__ . '/uploads/foto/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $fileName = 'dosen_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $uploadDir . $fileName)) {
                        $data['foto'] = 'uploads/foto/' . $fileName;
                    }
                }
            }

            $mkIds = $_POST['matakuliah_ids'] ?? [];
            
            if ($action === 'tambah') {
                if (empty($data['nidn']) || empty($data['nama'])) {
                    $error = "NIDN dan Nama wajib diisi.";
                } else {
                    // Buat user juga untuk login dosen
                    $stmtCheckNidn = $pdo->prepare("SELECT id FROM dosen WHERE nidn = ?");
                    $stmtCheckNidn->execute([$data['nidn']]);
                    if ($stmtCheckNidn->fetch()) {
                        $error = "NIDN sudah terdaftar.";
                    } else {
                        // Create user account
                        $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'dosen')");
                        $password = password_hash($data['nidn'], PASSWORD_DEFAULT);
                        $stmtUser->execute([$data['nidn'], $password]);
                        $userId2 = (int)$pdo->lastInsertId();

                        // Insert dosen dengan user_id
                        $sql = "INSERT INTO dosen (nidn, nama, email, fakultas, program_studi, foto, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        if ($stmt->execute([$data['nidn'], $data['nama'], $data['email'], $data['fakultas'], $data['program_studi'], $data['foto'], $data['status'], $userId2])) {
                            $dosenIdNew = (int)$pdo->lastInsertId();
                            if (!empty($mkIds)) {
                                $stmtSmt = $pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
                                $semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
                                foreach ($mkIds as $mkId) {
                                    $pdo->prepare("INSERT IGNORE INTO dosen_matakuliah (dosen_id, matakuliah_id, semester) VALUES (?, ?, ?)")->execute([$dosenIdNew, (int)$mkId, $semesterAktif]);
                                }
                            }
                            Auth::logActivity($userId, 'create', 'dosen', $dosenIdNew, "Tambah dosen: {$data['nama']}", $pdo);
                            $success = "Dosen berhasil ditambahkan. Password default: {$data['nidn']}";
                        } else {
                            $error = "Gagal menambahkan dosen.";
                        }
                    }
                }
            } elseif ($action === 'edit') {
                $dosenId = (int)($_POST['dosen_id'] ?? 0);
                if ($repo->update($dosenId, $data, $mkIds, $userId)) {
                    $success = "Data dosen berhasil diperbarui.";
                } else {
                    $error = "Gagal memperbarui data dosen.";
                }
            }
        } elseif ($action === 'hapus') {
            $dosenId = (int)($_POST['dosen_id'] ?? 0);
            if ($repo->softDelete($dosenId, $userId)) {
                $success = "Dosen berhasil dinonaktifkan (soft delete).";
            } else {
                $error = "Gagal menghapus data dosen.";
            }
        } elseif ($action === 'pulihkan') {
            $dosenId = (int)($_POST['dosen_id'] ?? 0);
            if ($repo->restore($dosenId, $userId)) {
                $success = "Dosen berhasil dipulihkan.";
            } else {
                $error = "Gagal memulihkan dosen.";
            }
        }
        
        if (empty($error) && !empty($success)) {
            header("Location: master_dosen.php?msg=" . urlencode($success));
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    $success = htmlspecialchars($_GET['msg']);
}

// --- FILTER & PAGINATION ---
$search = trim($_GET['search'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$filterProdi = $_GET['prodi'] ?? '';
$showTrashed = isset($_GET['trash']);
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

// Fetch dosen (paginated)
if ($showTrashed) {
    $trashedList = $repo->getTrashed();
    $dosenList = ['data' => $trashedList, 'total' => count($trashedList), 'last_page' => 1];
} else {
    $dosenList = $repo->paginate($page, $perPage, $search, $filterProdi, $filterStatus);
}

// Fetch all MK for multi-select
$allMK = $repo->getAllMataKuliah();

// Fetch prodi & fakultas for dynamic dropdown
$fakultasList = $pdo->query("SELECT * FROM master_fakultas ORDER BY nama_fakultas ASC")->fetchAll(PDO::FETCH_ASSOC);
$prodiList = $pdo->query("SELECT * FROM master_prodi ORDER BY nama_prodi ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/components/header.php';
?>

<!-- Header -->
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Master Data Dosen</h1>
        <p class="text-on-surface/70 mt-1">Kelola seluruh data dosen — CRUD lengkap, penugasan MK, dan arsip.</p>
    </div>
    <div class="flex gap-3">
        <a href="?trash=1" class="btn-secondary <?= $showTrashed ? 'btn-danger' : '' ?>">
            <span class="material-symbols-outlined text-[18px]">delete_history</span> Arsip
        </a>
        <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="btn-primary">
            <span class="material-symbols-outlined text-[18px]">person_add</span> Tambah Dosen
        </button>
    </div>
</div>

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

<!-- Filter -->
<?php if (!$showTrashed): ?>
<div class="card p-4 bg-surface rounded-2xl shadow-sm border border-outline-variant/30 mb-6">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-semibold text-on-surface-variant mb-1">Cari Nama / NIDN</label>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Ketik nama atau NIDN..."
                   class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div class="min-w-[160px]">
            <label class="block text-xs font-semibold text-on-surface-variant mb-1">Status</label>
            <select name="status" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                <option value="">Semua Status</option>
                <option value="aktif" <?= $filterStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Non-Aktif</option>
            </select>
        </div>
        <div class="min-w-[180px]">
            <label class="block text-xs font-semibold text-on-surface-variant mb-1">Program Studi</label>
            <select name="prodi" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                <option value="">Semua Prodi</option>
                <?php foreach ($prodiList as $prodi): ?>
                <option value="<?= htmlspecialchars($prodi['nama_prodi']) ?>" <?= $filterProdi === $prodi['nama_prodi'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($prodi['nama_prodi']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="bg-primary text-on-primary px-5 py-2.5 rounded-xl text-sm font-bold hover:bg-primary/80 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">filter_list</span> Filter
        </button>
        <a href="master_dosen.php" class="px-5 py-2.5 rounded-xl border border-outline-variant/30 text-on-surface-variant text-sm hover:bg-surface-container-low transition-all">Reset</a>
    </form>
</div>
<?php endif; ?>

<!-- Tabel Dosen -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <?php if ($showTrashed): ?>
    <div class="bg-error/5 border-b border-error/20 px-6 py-3 text-sm text-error font-bold flex items-center gap-2">
        <span class="material-symbols-outlined">delete_history</span> 
        Menampilkan Dosen yang Telah Dihapus (Arsip) — <a href="master_dosen.php" class="underline">Kembali ke Aktif</a>
    </div>
    <?php endif; ?>
    
    <div class="table-responsive-wrapper">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Dosen</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">NIDN</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Program Studi</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">MK</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Status</th>
                    <th class="px-4 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dosenList['data'] as $d): 
                    $dosenMkIds = $repo->getDosenMataKuliahIds((int)$d['id']);
                ?>
                <tr class="hover:bg-surface-variant/10 transition-colors">
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($d['foto'])): ?>
                            <img src="<?= htmlspecialchars($d['foto']) ?>" class="w-10 h-10 rounded-full object-cover" alt="">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-on-primary font-black text-sm flex-shrink-0">
                                <?= strtoupper(substr($d['nama'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <p class="font-bold text-on-surface text-sm"><?= htmlspecialchars($d['nama']) ?></p>
                                <p class="text-xs text-on-surface-variant"><?= htmlspecialchars($d['email'] ?? '-') ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-mono text-sm text-on-surface-variant">
                        <?= htmlspecialchars($d['nidn'] ?? '-') ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-sm text-on-surface-variant">
                        <?= htmlspecialchars($d['program_studi'] ?? '-') ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                        <span class="bg-secondary/10 text-secondary font-bold px-2 py-1 rounded-lg text-xs">
                            <?= $d['jumlah_mk'] ?? count($dosenMkIds) ?> MK
                        </span>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                        <?php if ($showTrashed): ?>
                        <span class="px-2 py-1 bg-error/10 text-error border border-error/20 rounded-md text-xs font-bold uppercase">Dihapus</span>
                        <?php else: ?>
                        <span class="px-2 py-1 <?= $d['status'] === 'aktif' ? 'bg-success/10 text-success border-success/20' : 'bg-error/10 text-error border-error/20' ?> border rounded-md text-xs font-bold uppercase">
                            <?= htmlspecialchars($d['status']) ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center">
                        <div class="flex gap-2 justify-center">
                            <?php if ($showTrashed): ?>
                            <form method="POST">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="pulihkan">
                                <input type="hidden" name="dosen_id" value="<?= $d['id'] ?>">
                                <button type="submit" class="text-success hover:text-success/70 p-1" title="Pulihkan">
                                    <span class="material-symbols-outlined">restore</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <button onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($d)) ?>, <?= htmlspecialchars(json_encode($dosenMkIds)) ?>)"
                                    class="text-primary hover:text-primary/70 p-1 w-8 h-8 rounded-lg hover:bg-primary/10 transition-all" title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </button>
                            <form method="POST" onsubmit="return confirm('Nonaktifkan dosen ini?');">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="dosen_id" value="<?= $d['id'] ?>">
                                <button type="submit" class="text-error hover:text-error/70 p-1 w-8 h-8 rounded-lg hover:bg-error/10 transition-all" title="Hapus">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                
                <?php if (empty($dosenList['data'])): ?>
                <tr>
                    <td colspan="6" class="text-center py-10 text-on-surface-variant italic">
                        <span class="material-symbols-outlined text-4xl block mb-2 opacity-30">search_off</span>
                        Tidak ada data dosen ditemukan.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if (!$showTrashed && $dosenList['last_page'] > 1): ?>
    <div class="flex justify-between items-center p-4 border-t border-outline-variant/20">
        <p class="text-sm text-on-surface-variant">Total: <?= $dosenList['total'] ?> dosen</p>
        <div class="flex gap-2">
            <?php for ($i = 1; $i <= $dosenList['last_page']; $i++): ?>
            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filterStatus) ?>&prodi=<?= urlencode($filterProdi) ?>"
               class="w-8 h-8 rounded-lg text-sm font-bold flex items-center justify-center transition-all
                      <?= $i === $page ? 'bg-primary text-on-primary' : 'text-on-surface-variant hover:bg-surface-container-low' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">person_add</span> Tambah Dosen Baru
            </h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 overflow-y-auto flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">NIDN <span class="text-error">*</span></label>
                    <input type="text" name="nidn" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm" placeholder="Contoh: 0412345678">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Nama Lengkap <span class="text-error">*</span></label>
                    <input type="text" name="nama" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm" placeholder="Dr. Nama Dosen, M.Kom">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Email</label>
                    <input type="email" name="email" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Fakultas</label>
                    <select name="fakultas" id="add_fak" onchange="updateProdiOptions('add')" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach($fakultasList as $fak): ?>
                            <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" data-id="<?= $fak['id'] ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Program Studi</label>
                    <select name="program_studi" id="prodi_add" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="">-- Pilih Prodi --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Status</label>
                    <select name="status" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Non-Aktif</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Foto Profil</label>
                    <div class="relative group cursor-pointer">
                        <input type="file" name="foto" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name-add').textContent = this.files[0] ? this.files[0].name : 'Pilih file atau drag & drop ke sini';">
                        <div class="w-full bg-surface-container-lowest border-2 border-dashed border-outline-variant/50 rounded-xl px-4 py-6 flex flex-col items-center justify-center gap-2 group-hover:border-primary group-hover:bg-primary-container/10 transition-all text-center">
                            <span class="material-symbols-outlined text-3xl text-primary/50 group-hover:text-primary transition-colors">cloud_upload</span>
                            <p class="text-sm font-semibold text-primary" id="file-name-add">Pilih file atau drag & drop ke sini</p>
                            <p class="text-xs text-on-surface-variant">Format didukung: JPG, PNG, WEBP</p>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Penugasan Mata Kuliah</label>
                    <div class="flex gap-2 mb-3 bg-surface-variant/20 p-2 rounded-xl">
                        <select id="filter_fakultas_add" onchange="updateFilterProdi('add')" class="flex-1 bg-surface-container-lowest border border-outline-variant/30 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary outline-none text-xs">
                            <option value="">Semua Fakultas</option>
                            <?php foreach($fakultasList as $fak): ?>
                                <option value="<?= htmlspecialchars($fak['id']) ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filter_prodi_add" onchange="filterMatkul('add')" class="flex-1 bg-surface-container-lowest border border-outline-variant/30 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary outline-none text-xs">
                            <option value="">Semua Prodi</option>
                        </select>
                        <button type="button" onclick="resetFilterMatkul('add')" class="bg-surface border border-outline-variant/30 text-on-surface-variant px-3 py-2 rounded-lg text-xs font-bold hover:bg-surface-variant transition-all flex items-center gap-1" title="Reset Filter"><span class="material-symbols-outlined text-[16px]">refresh</span> Reset</button>
                        <button type="button" onclick="uncheckAllMatkul('add')" class="bg-error/10 border border-error/30 text-error px-3 py-2 rounded-lg text-xs font-bold hover:bg-error/20 transition-all flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">deselect</span> Copot Semua</button>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-3 max-h-60 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-3 custom-scrollbar" id="mk_container_add">
                        <?php foreach ($allMK as $mk): ?>
                        <label class="mk-item flex items-start gap-3 p-3 rounded-xl border border-outline-variant/30 cursor-pointer hover:bg-primary-container/20 hover:border-primary/40 transition-all group has-[:checked]:border-primary has-[:checked]:bg-primary/5" data-prodi="<?= htmlspecialchars($mk['prodi'] ?? '') ?>">
                            <input type="checkbox" name="matakuliah_ids[]" value="<?= $mk['id'] ?>" class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500 border-outline-variant/50 transition-all cursor-pointer mt-0.5">
                            <div class="flex-1">
                                <span class="font-bold text-on-surface text-sm block group-hover:text-primary transition-colors"><?= htmlspecialchars($mk['kode']) ?> — <?= htmlspecialchars($mk['nama']) ?></span>
                                <span class="text-xs text-on-surface-variant block mt-1"><?= $mk['sks'] ?> SKS <?= !empty($mk['prodi']) ? '• ' . htmlspecialchars($mk['prodi']) : '' ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <p class="text-xs text-on-surface-variant mb-4 bg-primary/5 p-3 rounded-xl">
                <span class="material-symbols-outlined text-sm align-middle text-primary">info</span>
                Password default login dosen adalah NIDN. Dosen dapat menggantinya setelah login pertama.
            </p>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" 
                        class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="btn-primary">Simpan Dosen</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-secondary flex items-center gap-2">
                <span class="material-symbols-outlined">edit</span> Edit Data Dosen
            </h2>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error p-1">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" class="p-6 overflow-y-auto flex-1" id="formEdit">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="dosen_id" id="edit_dosen_id">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">NIDN</label>
                    <input type="text" name="nidn" id="edit_nidn" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Email</label>
                    <input type="email" name="email" id="edit_email" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Fakultas</label>
                    <select name="fakultas" id="edit_fakultas" onchange="updateProdiOptions('edit')" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach($fakultasList as $fak): ?>
                            <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" data-id="<?= $fak['id'] ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Program Studi</label>
                    <select name="program_studi" id="edit_prodi" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="">-- Pilih Prodi --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tempat, Tgl Lahir</label>
                    <input type="text" name="tempat_tanggal_lahir" id="edit_tempat_tanggal_lahir" placeholder="Contoh: Jakarta, 01 Januari 1980" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="edit_jenis_kelamin" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="">-- Pilih --</option>
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">No HP</label>
                    <input type="text" name="no_hp" id="edit_no_hp" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Status</label>
                    <select name="status" id="edit_status" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Non-Aktif</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Alamat Asal</label>
                    <textarea name="alamat_asal" id="edit_alamat_asal" rows="2" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Domisili Saat Ini</label>
                    <textarea name="domisili" id="edit_domisili" rows="2" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Foto Profil (kosongkan jika tidak diubah)</label>
                    <div class="relative group cursor-pointer">
                        <input type="file" name="foto" accept="image/jpeg, image/png, image/webp" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name-edit').textContent = this.files[0] ? this.files[0].name : 'Klik untuk ganti foto';">
                        <div class="w-full bg-surface-container-lowest border-2 border-dashed border-outline-variant/50 rounded-xl px-4 py-6 flex flex-col items-center justify-center gap-2 group-hover:border-primary group-hover:bg-primary-container/10 transition-all text-center">
                            <span class="material-symbols-outlined text-3xl text-primary/50 group-hover:text-primary transition-colors">cloud_upload</span>
                            <p class="text-sm font-semibold text-primary" id="file-name-edit">Klik untuk ganti foto</p>
                            <p class="text-xs text-on-surface-variant">Format didukung: JPG, PNG, WEBP</p>
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-primary mb-2">Penugasan Mata Kuliah</label>
                    <div class="flex gap-2 mb-3 bg-surface-variant/20 p-2 rounded-xl">
                        <select id="filter_fakultas_edit" onchange="updateFilterProdi('edit')" class="flex-1 bg-surface-container-lowest border border-outline-variant/30 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary outline-none text-xs">
                            <option value="">Semua Fakultas</option>
                            <?php foreach($fakultasList as $fak): ?>
                                <option value="<?= htmlspecialchars($fak['id']) ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select id="filter_prodi_edit" onchange="filterMatkul('edit')" class="flex-1 bg-surface-container-lowest border border-outline-variant/30 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary outline-none text-xs">
                            <option value="">Semua Prodi</option>
                        </select>
                        <button type="button" onclick="resetFilterMatkul('edit')" class="bg-surface border border-outline-variant/30 text-on-surface-variant px-3 py-2 rounded-lg text-xs font-bold hover:bg-surface-variant transition-all flex items-center gap-1" title="Reset Filter"><span class="material-symbols-outlined text-[16px]">refresh</span> Reset</button>
                        <button type="button" onclick="uncheckAllMatkul('edit')" class="bg-error/10 border border-error/30 text-error px-3 py-2 rounded-lg text-xs font-bold hover:bg-error/20 transition-all flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">deselect</span> Copot Semua</button>
                    </div>
                    <div class="bg-surface-container-lowest border border-outline-variant/30 rounded-xl p-3 max-h-60 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-3 custom-scrollbar" id="edit_matkul_container">
                        <?php foreach ($allMK as $mk): ?>
                        <label class="mk-item flex items-start gap-3 p-3 rounded-xl border border-outline-variant/30 cursor-pointer hover:bg-primary-container/20 hover:border-primary/40 transition-all group has-[:checked]:border-primary has-[:checked]:bg-primary/5" data-prodi="<?= htmlspecialchars($mk['prodi'] ?? '') ?>">
                            <input type="checkbox" name="matakuliah_ids[]" value="<?= $mk['id'] ?>" class="w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500 border-outline-variant/50 transition-all cursor-pointer mt-0.5">
                            <div class="flex-1">
                                <span class="font-bold text-on-surface text-sm block group-hover:text-primary transition-colors"><?= htmlspecialchars($mk['kode']) ?> — <?= htmlspecialchars($mk['nama']) ?></span>
                                <span class="text-xs text-on-surface-variant block mt-1"><?= $mk['sks'] ?> SKS <?= !empty($mk['prodi']) ? '• ' . htmlspecialchars($mk['prodi']) : '' ?></span>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" 
                        class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-secondary hover:bg-secondary/80 text-on-secondary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Perbarui Data</button>
            </div>
        </form>
    </div>
</div>

<script>
const prodiMasterData = <?= json_encode($prodiList) ?>;

// Filter Matkul logic
function updateFilterProdi(prefix) {
    const fakSelect = document.getElementById('filter_fakultas_' + prefix);
    const prodiSelect = document.getElementById('filter_prodi_' + prefix);
    if (!fakSelect || !prodiSelect) return;
    
    const fakId = fakSelect.value;
    prodiSelect.innerHTML = '<option value="">Semua Prodi</option>';
    
    if (fakId) {
        const filteredProdi = prodiMasterData.filter(p => p.fakultas_id == fakId);
        filteredProdi.forEach(p => {
            const option = document.createElement('option');
            option.value = p.nama_prodi;
            option.textContent = p.jenjang + ' ' + p.nama_prodi;
            prodiSelect.appendChild(option);
        });
    }
    filterMatkul(prefix);
}

function filterMatkul(prefix) {
    const fakSelect = document.getElementById('filter_fakultas_' + prefix);
    const prodiSelect = document.getElementById('filter_prodi_' + prefix);
    const container = document.getElementById(prefix === 'add' ? 'mk_container_add' : 'edit_matkul_container');
    
    const selectedProdi = prodiSelect.value;
    const selectedFakId = fakSelect.value;
    
    let validProdiNames = [];
    if (selectedFakId && !selectedProdi) {
        validProdiNames = prodiMasterData.filter(p => p.fakultas_id == selectedFakId).map(p => p.nama_prodi);
    }
    
    const items = container.querySelectorAll('.mk-item');
    items.forEach(item => {
        const itemProdi = item.getAttribute('data-prodi');
        let show = true;
        
        if (selectedProdi) {
            show = itemProdi === selectedProdi;
        } else if (selectedFakId) {
            // Jika mata kuliah tidak punya data prodi tapi difilter per fakultas, maka sembunyikan kecuali mau ditampilkan semua
            show = validProdiNames.includes(itemProdi);
        }
        
        if (show) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function resetFilterMatkul(prefix) {
    document.getElementById('filter_fakultas_' + prefix).value = '';
    updateFilterProdi(prefix);
}

function uncheckAllMatkul(prefix) {
    const container = document.getElementById(prefix === 'add' ? 'mk_container_add' : 'edit_matkul_container');
    if (!container) return;
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
}

// Add/Edit modal logic
function updateProdiOptions(prefix) {
    const fakSelect = document.getElementById(prefix + '_fakultas') || document.getElementById('add_fak');
    const prodiSelect = document.getElementById(prefix + '_prodi') || document.getElementById('prodi_add');
    if (!fakSelect || !prodiSelect) return;
    
    const selectedFakOption = fakSelect.options[fakSelect.selectedIndex];
    const fakId = selectedFakOption ? selectedFakOption.getAttribute('data-id') : null;
    const currentProdi = prodiSelect.getAttribute('data-selected') || '';
    
    prodiSelect.innerHTML = '<option value="">-- Pilih Prodi --</option>';
    
    if (fakId) {
        const filteredProdi = prodiMasterData.filter(p => p.fakultas_id == fakId);
        filteredProdi.forEach(p => {
            const option = document.createElement('option');
            option.value = p.nama_prodi;
            option.textContent = p.jenjang + ' ' + p.nama_prodi;
            if (p.nama_prodi === currentProdi) {
                option.selected = true;
            }
            prodiSelect.appendChild(option);
        });
    }
}

function bukaModalEdit(dosenData, mkIds) {
    document.getElementById('edit_dosen_id').value = dosenData.id;
    document.getElementById('edit_nidn').value = dosenData.nidn || '';
    document.getElementById('edit_nama').value = dosenData.nama || '';
    document.getElementById('edit_email').value = dosenData.email || '';
    document.getElementById('edit_status').value = dosenData.status || 'aktif';
    document.getElementById('edit_tempat_tanggal_lahir').value = dosenData.tempat_tanggal_lahir || '';
    document.getElementById('edit_jenis_kelamin').value = dosenData.jenis_kelamin || '';
    document.getElementById('edit_no_hp').value = dosenData.no_hp || '';
    document.getElementById('edit_alamat_asal').value = dosenData.alamat_asal || '';
    document.getElementById('edit_domisili').value = dosenData.domisili || '';
    
    const fakSelect = document.getElementById('edit_fakultas');
    for (let i = 0; i < fakSelect.options.length; i++) {
        if (fakSelect.options[i].value === dosenData.fakultas) {
            fakSelect.selectedIndex = i;
            break;
        }
    }
    document.getElementById('edit_prodi').setAttribute('data-selected', dosenData.program_studi || '');
    updateProdiOptions('edit');

    // Reset matakuliah checkboxes
    const container = document.getElementById('edit_matkul_container');
    const checkboxes = container.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = false);
    
    // Set checked if mkIds exist
    if (mkIds && mkIds.length > 0) {
        mkIds.forEach(id => {
            const cb = container.querySelector(`input[value="${id}"]`);
            if (cb) cb.checked = true;
        });
    }
    
    document.getElementById('modalEdit').classList.remove('hidden');
}

// Inisialisasi prodi filter saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => {
    updateProdiOptions('add');
    
    // Pindahkan modal ke document.body agar tidak terpengaruh stacking context
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalEdit) document.body.appendChild(modalEdit);
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
