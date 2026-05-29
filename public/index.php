<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireLogin();
$repo = new DosenRepository();
$operatorRepo = new \Src\OperatorRepository();
$role = Auth::getRole();

$prodiList = $operatorRepo->getAllProdi();
$fakultasList = $operatorRepo->getAllFakultas();

// Ambil parameter filter & paginasi dari query string
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = $_GET['search'] ?? '';
$fakultas = $_GET['fakultas'] ?? '';
$prodi = $_GET['prodi'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$dir = $_GET['dir'] ?? 'DESC';

$result = $repo->paginate($page, 5, $search, $prodi, $status, $sort, $dir);
$dosens = $result['data'];
$lastPage = $result['last_page'];

// Fungsi pembantu untuk membuat query string yang mempertahankan state saat ini
function buildQueryString(array $mergeParams = []): string {
    $params = array_merge($_GET, $mergeParams);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Dosen - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="container animate-fade-in">
    <div class="d-flex justify-between align-center mb-4">
        <h1>Manajemen Data Dosen</h1>
        <div class="d-flex gap-2">
            <a href="export.php<?= buildQueryString() ?>" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center">Export CSV</a>
            <?php if ($role === 'operator'): ?>
                <a href="create.php" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center">+ Tambah Dosen</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <form method="GET" action="index.php" class="grid grid-cols-1 md:grid-cols-4 gap-stack-md">
            <div class="form-group mb-0">
                <input type="text" name="search" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" placeholder="Cari nama atau NIDN..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="form-group mb-0">
                <select id="fakultas_filter" name="fakultas" onchange="updateProdiOptions()" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant">
                    <option value="">-- Semua Fakultas --</option>
                    <?php foreach($fakultasList as $fak): ?>
                        <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" <?= $fakultas === $fak['nama_fakultas'] ? 'selected' : '' ?> data-id="<?= $fak['id'] ?>">
                            <?= htmlspecialchars($fak['nama_fakultas']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <select id="prodi_filter" name="prodi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" data-selected="<?= htmlspecialchars($prodi) ?>">
                    <option value="">-- Semua Prodi --</option>
                </select>
            </div>
            <div class="form-group mb-0 flex gap-2">
                <select name="status" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 pr-8 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant text-ellipsis">
                    <option value="">-- Semua --</option>
                    <option value="aktif" <?= $status === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                    <option value="nonaktif" <?= $status === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                </select>
                <button type="submit" class="bg-secondary-container text-on-secondary-container px-4 py-2 rounded-xl hover:bg-secondary hover:text-white transition-colors flex items-center justify-center shadow-sm" title="Filter">
                    <span class="material-symbols-outlined text-[20px]">search</span>
                </button>
                <a href="index.php" class="bg-surface-variant text-on-surface px-4 py-2 rounded-xl hover:bg-outline-variant transition-colors flex items-center justify-center shadow-sm" title="Reset Filter">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </a>
            </div>
            <!-- Preserve sorting parameters -->
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
            <input type="hidden" name="dir" value="<?= htmlspecialchars($dir) ?>">
        </form>
    </div>

    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30"><a href="index.php<?= buildQueryString(['sort' => 'nidn', 'dir' => $dir === 'ASC' ? 'DESC' : 'ASC']) ?>">NIDN</a></th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30"><a href="index.php<?= buildQueryString(['sort' => 'nama', 'dir' => $dir === 'ASC' ? 'DESC' : 'ASC']) ?>">Nama Dosen</a></th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Fakultas</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30"><a href="index.php<?= buildQueryString(['sort' => 'program_studi', 'dir' => $dir === 'ASC' ? 'DESC' : 'ASC']) ?>">Program Studi</a></th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Jml MK Diampu</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30"><a href="index.php<?= buildQueryString(['sort' => 'status', 'dir' => $dir === 'ASC' ? 'DESC' : 'ASC']) ?>">Status</a></th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dosens)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Tidak ada data dosen yang ditemukan.</td></tr>
                <?php else: ?>
                    <?php foreach ($dosens as $d): ?>
                    <tr>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($d['nidn']) ?></strong></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <div class="d-flex align-center gap-2">
                                <?php if ($d['foto']): ?>
                                    <img src="<?= htmlspecialchars($d['foto']) ?>" class="avatar" alt="Foto">
                                <?php else: ?>
                                    <div class="avatar d-flex align-center justify-center" style="background: var(--bg-light); color: var(--text-muted); font-size: 0.8rem;">NA</div>
                                <?php endif; ?>
                                <?= htmlspecialchars($d['nama']) ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-on-surface-variant text-sm"><?= htmlspecialchars($d['fakultas'] ?? '-') ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($d['program_studi']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <span class="bg-secondary-fixed text-on-secondary-fixed px-3 py-1 rounded-full font-label-md text-xs"><?= (int)$d['jumlah_mk'] ?> MK</span>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <?php if ($d['status'] === 'aktif'): ?>
                                <span class="bg-secondary-fixed text-on-secondary-fixed px-3 py-1 rounded-full font-label-md text-xs">Aktif</span>
                            <?php else: ?>
                                <span class="bg-tertiary-container text-on-tertiary-container px-3 py-1 rounded-full font-label-md text-xs">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-right">
                            <a href="edit.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                            <?php if ($role === 'operator'): ?>
                                <a href="delete.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin ingin menghapus dosen ini ke sampah?');">Hapus</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Paginasi -->
        <?php if ($lastPage > 1): ?>
        <div class="d-flex justify-between align-center mt-4">
            <div>
                Menampilkan halaman <?= $page ?> dari <?= $lastPage ?> (Total: <?= $result['total'] ?> data)
            </div>
            <div class="d-flex gap-2">
                <?php if ($page > 1): ?>
                    <a href="index.php<?= buildQueryString(['page' => $page - 1]) ?>" class="btn btn-sm btn-secondary">« Prev</a>
                <?php endif; ?>
                
                <?php if ($page < $lastPage): ?>
                    <a href="index.php<?= buildQueryString(['page' => $page + 1]) ?>" class="btn btn-sm btn-secondary">Next »</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer">
    <p>&copy; 2026 SAQUNA UNSIQ - Ujian Tengah Semester</p>
</footer>

<script>
const prodiMasterData = <?= json_encode($prodiList) ?>;

function updateProdiOptions() {
    const fakSelect = document.getElementById('fakultas_filter');
    const prodiSelect = document.getElementById('prodi_filter');
    
    if(!fakSelect || !prodiSelect) return;

    const selectedFakOption = fakSelect.options[fakSelect.selectedIndex];
    const fakId = selectedFakOption && selectedFakOption.value !== '' ? selectedFakOption.getAttribute('data-id') : null;
    
    const currentProdi = prodiSelect.getAttribute('data-selected') || '';
    
    prodiSelect.innerHTML = '<option value="">-- Semua Prodi --</option>';
    
    // Jika ada fakultas dipilih, filter prodinya. Jika tidak, tampilkan semua prodi.
    let targetProdi = prodiMasterData;
    if (fakId) {
        targetProdi = prodiMasterData.filter(p => p.fakultas_id == fakId);
    }

    targetProdi.forEach(p => {
        const option = document.createElement('option');
        option.value = p.nama_prodi;
        option.textContent = p.jenjang + ' ' + p.nama_prodi;
        if (p.nama_prodi === currentProdi) {
            option.selected = true;
        }
        prodiSelect.appendChild(option);
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    updateProdiOptions();
});
</script>

</body>
</html>
