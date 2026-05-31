<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();
$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_univ') {
        $namaUniv = $_POST['nama_universitas'] ?? '';
        if ($repo->setPengaturan('nama_universitas', $namaUniv)) {
            $alertMessage = "Nama universitas berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui nama universitas.";
            $alertType = "error";
        }
    } elseif ($action === 'add_fakultas') {
        $namaFak = $_POST['nama_fakultas'] ?? '';
        $singkatan = $_POST['singkatan'] ?? '';
        if ($repo->addFakultas($namaFak, $singkatan)) {
            $alertMessage = "Fakultas berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan fakultas.";
            $alertType = "error";
        }
    } elseif ($action === 'edit_fakultas') {
        $id = (int)($_POST['id'] ?? 0);
        $namaFak = $_POST['nama_fakultas'] ?? '';
        $singkatan = $_POST['singkatan'] ?? '';
        if ($repo->updateFakultas($id, $namaFak, $singkatan)) {
            $alertMessage = "Fakultas berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui fakultas.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_fakultas') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteFakultas($id)) {
            $alertMessage = "Fakultas berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus fakultas (pastikan tidak ada prodi terkait).";
            $alertType = "error";
        }
    } elseif ($action === 'add_prodi') {
        $fakId = (int)($_POST['fakultas_id'] ?? 0);
        $namaProdi = $_POST['nama_prodi'] ?? '';
        $jenjang = $_POST['jenjang'] ?? 'S1';
        if ($repo->addProdi($fakId, $namaProdi, $jenjang)) {
            $alertMessage = "Program studi berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan program studi.";
            $alertType = "error";
        }
    } elseif ($action === 'edit_prodi') {
        $id = (int)($_POST['id'] ?? 0);
        $fakId = (int)($_POST['fakultas_id'] ?? 0);
        $namaProdi = $_POST['nama_prodi'] ?? '';
        $jenjang = $_POST['jenjang'] ?? 'S1';
        if ($repo->updateProdi($id, $fakId, $namaProdi, $jenjang)) {
            $alertMessage = "Program studi berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui program studi.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_prodi') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteProdi($id)) {
            $alertMessage = "Program studi berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus program studi.";
            $alertType = "error";
        }
    } elseif ($action === 'add_kampus') {
        $namaKampus = $_POST['nama_kampus'] ?? '';
        $alamatKampus = $_POST['alamat_kampus'] ?? '';
        if ($repo->addKampus($namaKampus, $alamatKampus)) {
            $alertMessage = "Kampus berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan kampus.";
            $alertType = "error";
        }
    } elseif ($action === 'edit_kampus') {
        $id = (int)($_POST['id'] ?? 0);
        $namaKampus = $_POST['nama_kampus'] ?? '';
        $alamatKampus = $_POST['alamat_kampus'] ?? '';
        if ($repo->updateKampus($id, $namaKampus, $alamatKampus)) {
            $alertMessage = "Kampus berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui kampus.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_kampus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteKampus($id)) {
            $alertMessage = "Kampus berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus kampus (pastikan tidak ada gedung terkait).";
            $alertType = "error";
        }
    } elseif ($action === 'add_gedung') {
        $kampusId = (int)($_POST['kampus_id'] ?? 0);
        $namaGedung = $_POST['nama_gedung'] ?? '';
        if ($repo->addGedung($kampusId, $namaGedung)) {
            $alertMessage = "Gedung berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan gedung.";
            $alertType = "error";
        }
    } elseif ($action === 'edit_gedung') {
        $id = (int)($_POST['id'] ?? 0);
        $kampusId = (int)($_POST['kampus_id'] ?? 0);
        $namaGedung = $_POST['nama_gedung'] ?? '';
        if ($repo->updateGedung($id, $kampusId, $namaGedung)) {
            $alertMessage = "Gedung berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui gedung.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_gedung') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteGedung($id)) {
            $alertMessage = "Gedung berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus gedung (pastikan tidak ada ruangan terkait).";
            $alertType = "error";
        }
    } elseif ($action === 'add_ruangan') {
        $kode = trim($_POST['kode_ruangan'] ?? '');
        $nama = trim($_POST['nama_ruangan'] ?? '');
        $gedungId = (int)($_POST['gedung_id'] ?? 0);
        $kapasitas = (int)($_POST['kapasitas'] ?? 0);
        $jenis = $_POST['jenis'] ?? 'Teori';
        if ($repo->addRuangan($kode, $nama, $gedungId, $kapasitas, $jenis)) {
            $alertMessage = "Ruangan berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan ruangan. Pastikan Kode Ruangan unik.";
            $alertType = "error";
        }
    } elseif ($action === 'edit_ruangan') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode_ruangan'] ?? '');
        $nama = trim($_POST['nama_ruangan'] ?? '');
        $gedungId = (int)($_POST['gedung_id'] ?? 0);
        $kapasitas = (int)($_POST['kapasitas'] ?? 0);
        $jenis = $_POST['jenis'] ?? 'Teori';
        if ($repo->updateRuangan($id, $kode, $nama, $gedungId, $kapasitas, $jenis)) {
            $alertMessage = "Ruangan berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui ruangan.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_ruangan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteRuangan($id)) {
            $alertMessage = "Ruangan berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus ruangan (pastikan tidak ada jadwal yang menggunakannya).";
            $alertType = "error";
        }
    }
}

$namaUniv = $repo->getPengaturan('nama_universitas') ?? 'Universitas Teknologi SAQUNA';
$fakultasList = $repo->getAllFakultas();
$prodiList = $repo->getAllProdi();
$kampusList = $repo->getAllKampus();
$gedungList = $repo->getAllGedung();
$ruanganList = $repo->getAllRuangan();

$title = "Master Institusi";
$current_page = "master_institusi.php";
include 'components/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1>Master Data Institusi</h1>
        <p class="text-on-surface-variant opacity-80">Kelola profil perguruan tinggi, fakultas, dan program studi.</p>
    </div>
</div>

<?php if ($alertMessage): ?>
    <div class="p-4 mb-6 rounded-xl border <?= $alertType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> flex items-center gap-3 animate-fade-in">
        <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
        <p><?= $alertMessage ?></p>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    
    <!-- Bagian Nama Univ -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">account_balance</span> Profil Kampus</h3>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_univ">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Nama Universitas</label>
                <input type="text" name="nama_universitas" value="<?= htmlspecialchars($namaUniv) ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none" required>
            </div>
            <button type="submit" class="w-full bg-primary text-white rounded-xl py-3 font-label-md shadow-sm hover:bg-on-primary-fixed-variant transition-colors flex items-center justify-center gap-2">
                <span class="material-symbols-outlined" style="font-size: 18px;">save</span> Simpan Perubahan
            </button>
        </form>
    </div>

    <!-- Bagian Kampus (Lokasi) -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">pin_drop</span> Lokasi Kampus</h3>
        
        <form method="POST" action="" class="mb-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/30 flex gap-4 items-end">
            <input type="hidden" name="action" value="add_kampus">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Nama Kampus</label>
                <input type="text" name="nama_kampus" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Contoh: Kampus 1" required>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Alamat (Opsional)</label>
                <input type="text" name="alamat_kampus" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Jalan Raya No.123">
            </div>
            <button type="submit" class="bg-secondary text-on-secondary px-5 py-2.5 rounded-xl font-label-md hover:bg-secondary/90 transition-colors flex items-center gap-1 shadow-sm"><span class="material-symbols-outlined" style="font-size: 18px;">add</span> Tambah</button>
        </form>

        <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Nama Kampus</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Alamat</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($kampusList)): ?>
                        <tr><td colspan="3" class="px-4 py-4 text-center text-sm text-on-surface-variant">Belum ada data kampus.</td></tr>
                    <?php else: ?>
                        <?php foreach($kampusList as $k): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-semibold"><?= htmlspecialchars($k['nama_kampus']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><?= htmlspecialchars($k['alamat'] ?? '-') ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-center flex justify-center items-center gap-2">
                                <button onclick="toggleEditKampus(<?= $k['id'] ?>)" class="text-primary hover:text-primary-fixed-variant" title="Edit Kampus"><span class="material-symbols-outlined text-[18px]">edit_square</span></button>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus kampus ini?');">
                                    <input type="hidden" name="action" value="delete_kampus">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <button type="submit" class="text-error hover:text-red-700" title="Hapus Kampus"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                </form>
                            </td>
                        </tr>
                        <!-- Inline Edit Kampus -->
                        <tr id="edit_kampus_<?= $k['id'] ?>" class="hidden bg-surface-container-low/50">
                            <td colspan="3" class="px-4 py-3 border-b border-outline-variant/30">
                                <form method="POST" action="" class="flex flex-wrap md:flex-nowrap gap-3 items-end bg-white p-3 rounded-xl border border-outline-variant/50 shadow-inner">
                                    <input type="hidden" name="action" value="edit_kampus">
                                    <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Nama Kampus</label>
                                        <input type="text" name="nama_kampus" value="<?= htmlspecialchars($k['nama_kampus']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Alamat</label>
                                        <input type="text" name="alamat_kampus" value="<?= htmlspecialchars($k['alamat'] ?? '') ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="toggleEditKampus(<?= $k['id'] ?>)" class="px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface-variant text-xs hover:bg-surface-container">Batal</button>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">save</span> Simpan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Bagian Gedung Fakultas -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit lg:col-span-1">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">apartment</span> Gedung Fakultas</h3>
        
        <form method="POST" action="" class="mb-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/30">
            <input type="hidden" name="action" value="add_gedung">
            <div class="mb-3">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Pilih Kampus</label>
                <select name="kampus_id" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" required>
                    <option value="">-- Pilih Kampus --</option>
                    <?php foreach($kampusList as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kampus']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Nama Gedung</label>
                <input type="text" name="nama_gedung" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Ex: Gedung Fakultas Teknik" required>
            </div>
            <button type="submit" class="w-full bg-secondary text-on-secondary py-2.5 rounded-xl font-label-md hover:bg-secondary/90 transition-colors flex items-center justify-center gap-1 shadow-sm"><span class="material-symbols-outlined" style="font-size: 18px;">add</span> Tambah Gedung</button>
        </form>

        <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Lokasi</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Gedung</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-20 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($gedungList)): ?>
                        <tr><td colspan="3" class="px-4 py-4 text-center text-sm text-on-surface-variant">Belum ada data gedung.</td></tr>
                    <?php else: ?>
                        <?php foreach($gedungList as $g): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-xs text-on-surface-variant"><?= htmlspecialchars($g['nama_kampus']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-semibold"><?= htmlspecialchars($g['nama_gedung']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-center flex justify-center items-center gap-1">
                                <button onclick="toggleEditGedung(<?= $g['id'] ?>)" class="text-primary hover:text-primary-fixed-variant" title="Edit Gedung"><span class="material-symbols-outlined text-[16px]">edit_square</span></button>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus gedung ini?');">
                                    <input type="hidden" name="action" value="delete_gedung">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <button type="submit" class="text-error hover:text-red-700" title="Hapus Gedung"><span class="material-symbols-outlined text-[16px]">delete</span></button>
                                </form>
                            </td>
                        </tr>
                        <!-- Inline Edit Gedung -->
                        <tr id="edit_gedung_<?= $g['id'] ?>" class="hidden bg-surface-container-low/50">
                            <td colspan="3" class="px-4 py-3 border-b border-outline-variant/30">
                                <form method="POST" action="" class="flex flex-col gap-2 bg-white p-3 rounded-xl border border-outline-variant/50 shadow-inner">
                                    <input type="hidden" name="action" value="edit_gedung">
                                    <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                    <div>
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Pilih Kampus</label>
                                        <select name="kampus_id" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                            <?php foreach($kampusList as $k): ?>
                                                <option value="<?= $k['id'] ?>" <?= $k['id'] == $g['kampus_id'] ? 'selected' : '' ?>><?= htmlspecialchars($k['nama_kampus']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Nama Gedung</label>
                                        <input type="text" name="nama_gedung" value="<?= htmlspecialchars($g['nama_gedung']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="flex gap-2 mt-1">
                                        <button type="button" onclick="toggleEditGedung(<?= $g['id'] ?>)" class="flex-1 px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface-variant text-xs hover:bg-surface-container">Batal</button>
                                        <button type="submit" class="flex-1 px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs flex items-center justify-center gap-1"><span class="material-symbols-outlined text-[14px]">save</span> Simpan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bagian Ruangan -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit lg:col-span-2">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">meeting_room</span> Manajemen Ruangan</h3>
        
        <form method="POST" action="" class="mb-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/30 flex flex-wrap md:flex-nowrap gap-4 items-end">
            <input type="hidden" name="action" value="add_ruangan">
            <div class="w-full md:w-1/3">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Gedung Fakultas</label>
                <select name="gedung_id" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($gedungList as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['nama_kampus'] . ' - ' . $g['nama_gedung']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-24">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Kode</label>
                <input type="text" name="kode_ruangan" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Ex: R101" required>
            </div>
            <div class="flex-1">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Nama Ruangan</label>
                <input type="text" name="nama_ruangan" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Ex: Ruang Teori 1" required>
            </div>
            <div class="w-20">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Kapasitas</label>
                <input type="number" name="kapasitas" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="40" required>
            </div>
            <div class="w-32">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Jenis</label>
                <select name="jenis" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <option value="Teori">Teori</option>
                    <option value="Praktikum">Praktikum</option>
                    <option value="Auditorium">Auditorium</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
            </div>
            <button type="submit" class="bg-secondary text-on-secondary px-5 py-2.5 rounded-xl font-label-md hover:bg-secondary/90 transition-colors flex items-center gap-1 shadow-sm"><span class="material-symbols-outlined" style="font-size: 18px;">add</span> Tambah</button>
        </form>
        <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Lokasi / Gedung</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Kode</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Nama Ruangan</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Kapasitas</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Jenis</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ruanganList)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-sm text-on-surface-variant">Belum ada data ruangan.</td></tr>
                    <?php else: ?>
                        <?php foreach($ruanganList as $r): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-xs text-on-surface-variant">
                                <?= $r['nama_kampus'] ? htmlspecialchars($r['nama_kampus'] . ' - ' . $r['nama_gedung']) : '-' ?>
                            </td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-semibold text-primary"><?= htmlspecialchars($r['kode_ruangan']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-bold"><?= htmlspecialchars($r['nama_ruangan']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><?= (int)$r['kapasitas'] ?> Kursi</td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm">
                                <span class="px-2 py-0.5 bg-secondary/20 text-secondary-fixed-dim rounded text-xs"><?= htmlspecialchars($r['jenis']) ?></span>
                            </td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-center flex justify-center items-center gap-2">
                                <button onclick="toggleEditRuangan(<?= $r['id'] ?>)" class="text-primary hover:text-primary-fixed-variant" title="Edit Ruangan"><span class="material-symbols-outlined text-[18px]">edit_square</span></button>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus ruangan ini?');">
                                    <input type="hidden" name="action" value="delete_ruangan">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="text-error hover:text-red-700" title="Hapus Ruangan"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                </form>
                            </td>
                        </tr>
                        <!-- Inline Edit Ruangan -->
                        <tr id="edit_ruangan_<?= $r['id'] ?>" class="hidden bg-surface-container-low/50">
                            <td colspan="6" class="px-4 py-3 border-b border-outline-variant/30">
                                <form method="POST" action="" class="flex flex-wrap md:flex-nowrap gap-3 items-end bg-white p-3 rounded-xl border border-outline-variant/50 shadow-inner">
                                    <input type="hidden" name="action" value="edit_ruangan">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <div class="w-full md:w-1/3">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Gedung Fakultas</label>
                                        <select name="gedung_id" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                            <option value="">-- Pilih --</option>
                                            <?php foreach($gedungList as $g): ?>
                                                <option value="<?= $g['id'] ?>" <?= $g['id'] == $r['gedung_id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['nama_kampus'] . ' - ' . $g['nama_gedung']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="w-20">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Kode</label>
                                        <input type="text" name="kode_ruangan" value="<?= htmlspecialchars($r['kode_ruangan']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Nama Ruangan</label>
                                        <input type="text" name="nama_ruangan" value="<?= htmlspecialchars($r['nama_ruangan']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="w-16">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Kapasitas</label>
                                        <input type="number" name="kapasitas" value="<?= (int)$r['kapasitas'] ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="w-24">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Jenis</label>
                                        <select name="jenis" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none">
                                            <option value="Teori" <?= $r['jenis'] === 'Teori' ? 'selected' : '' ?>>Teori</option>
                                            <option value="Praktikum" <?= $r['jenis'] === 'Praktikum' ? 'selected' : '' ?>>Praktikum</option>
                                            <option value="Auditorium" <?= $r['jenis'] === 'Auditorium' ? 'selected' : '' ?>>Auditorium</option>
                                            <option value="Lainnya" <?= $r['jenis'] === 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="toggleEditRuangan(<?= $r['id'] ?>)" class="px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface-variant text-xs hover:bg-surface-container">Batal</button>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs hover:bg-on-primary-fixed-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">save</span> Simpan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <!-- Bagian Fakultas -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit lg:col-span-3">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">domain</span> Manajemen Fakultas</h3>
        
        <form method="POST" action="" class="mb-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/30 flex flex-wrap md:flex-nowrap gap-4 items-end">
            <input type="hidden" name="action" value="add_fakultas">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Nama Fakultas</label>
                <input type="text" name="nama_fakultas" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Contoh: Fakultas Ilmu Komputer" required>
            </div>
            <div class="w-32">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Singkatan</label>
                <input type="text" name="singkatan" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Contoh: FIK">
            </div>
            <button type="submit" class="bg-secondary text-on-secondary px-5 py-2.5 rounded-xl font-label-md hover:bg-secondary/90 transition-colors flex items-center gap-1 shadow-sm"><span class="material-symbols-outlined" style="font-size: 18px;">add</span> Tambah</button>
        </form>

        <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-12">No</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Nama Fakultas</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-32">Singkatan</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($fakultasList)): ?>
                        <tr><td colspan="4" class="px-4 py-4 text-center text-sm text-on-surface-variant">Belum ada data fakultas.</td></tr>
                    <?php else: ?>
                        <?php $no=1; foreach($fakultasList as $f): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><?= $no++ ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-semibold"><?= htmlspecialchars($f['nama_fakultas']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><span class="bg-secondary-container text-on-secondary-container px-2 py-0.5 rounded text-xs"><?= htmlspecialchars($f['singkatan'] ?? '-') ?></span></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-center flex justify-center items-center gap-2">
                                <button onclick="toggleEditFakultas(<?= $f['id'] ?>)" class="text-primary hover:text-primary-fixed-variant" title="Edit Fakultas"><span class="material-symbols-outlined text-[18px]">edit_square</span></button>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Fakultas ini? Semua Prodi di bawahnya juga dapat terhapus!');">
                                    <input type="hidden" name="action" value="delete_fakultas">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="text-error hover:text-red-700" title="Hapus Fakultas"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                </form>
                            </td>
                        </tr>
                        <!-- Inline Edit Fakultas -->
                        <tr id="edit_fak_<?= $f['id'] ?>" class="hidden bg-surface-container-low/50">
                            <td colspan="4" class="px-4 py-3 border-b border-outline-variant/30">
                                <form method="POST" action="" class="flex flex-wrap md:flex-nowrap gap-3 items-end bg-white p-3 rounded-xl border border-outline-variant/50 shadow-inner">
                                    <input type="hidden" name="action" value="edit_fakultas">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <div class="flex-1 min-w-[200px]">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Nama Fakultas</label>
                                        <input type="text" name="nama_fakultas" value="<?= htmlspecialchars($f['nama_fakultas']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="w-24">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Singkatan</label>
                                        <input type="text" name="singkatan" value="<?= htmlspecialchars($f['singkatan'] ?? '') ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none">
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="toggleEditFakultas(<?= $f['id'] ?>)" class="px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface-variant text-xs hover:bg-surface-container">Batal</button>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs hover:bg-on-primary-fixed-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">save</span> Simpan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bagian Program Studi -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 h-fit lg:col-span-3">
        <h3 class="font-title-lg font-bold text-primary flex items-center gap-2 mb-4"><span class="material-symbols-outlined">menu_book</span> Manajemen Program Studi</h3>
        
        <form method="POST" action="" class="mb-6 p-4 bg-surface-container-low rounded-xl border border-outline-variant/30 flex flex-wrap md:flex-nowrap gap-4 items-end">
            <input type="hidden" name="action" value="add_prodi">
            <div class="w-full md:w-1/3">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Pilih Fakultas</label>
                <select name="fakultas_id" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach($fakultasList as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nama_fakultas']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="w-full md:w-1/2">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Nama Program Studi</label>
                <input type="text" name="nama_prodi" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none" placeholder="Contoh: S1 Teknik Informatika" required>
            </div>
            <div class="w-32">
                <label class="block text-xs font-semibold text-on-surface-variant mb-1">Jenjang</label>
                <select name="jenjang" class="w-full bg-white border border-outline-variant rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <option value="D3">D3</option>
                    <option value="D4">D4</option>
                    <option value="S1" selected>S1</option>
                    <option value="S2">S2</option>
                    <option value="S3">S3</option>
                </select>
            </div>
            <button type="submit" class="bg-secondary text-on-secondary px-5 py-2.5 rounded-xl font-label-md hover:bg-secondary/90 transition-colors flex items-center gap-1 shadow-sm"><span class="material-symbols-outlined" style="font-size: 18px;">add</span> Tambah</button>
        </form>

        <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse bg-white">
                <thead>
                    <tr>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-12">No</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Fakultas</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30">Program Studi</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-24">Jenjang</th>
                        <th class="px-4 py-2 bg-surface-container-low text-xs text-on-surface-variant font-semibold border-b border-outline-variant/30 w-24 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($prodiList)): ?>
                        <tr><td colspan="5" class="px-4 py-4 text-center text-sm text-on-surface-variant">Belum ada data program studi.</td></tr>
                    <?php else: ?>
                        <?php $no=1; foreach($prodiList as $p): ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors">
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><?= $no++ ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-on-surface-variant"><?= htmlspecialchars($p['nama_fakultas']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm font-semibold"><?= htmlspecialchars($p['nama_prodi']) ?></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm"><span class="bg-primary-container text-on-primary-container px-2 py-0.5 rounded text-xs font-bold"><?= htmlspecialchars($p['jenjang']) ?></span></td>
                            <td class="px-4 py-2 border-b border-outline-variant/10 text-sm text-center flex justify-center items-center gap-2">
                                <button onclick="toggleEditProdi(<?= $p['id'] ?>)" class="text-primary hover:text-primary-fixed-variant" title="Edit Prodi"><span class="material-symbols-outlined text-[18px]">edit_square</span></button>
                                <form method="POST" action="" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus Program Studi ini?');">
                                    <input type="hidden" name="action" value="delete_prodi">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="text-error hover:text-red-700" title="Hapus Prodi"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                </form>
                            </td>
                        </tr>
                        <!-- Inline Edit Prodi -->
                        <tr id="edit_prodi_<?= $p['id'] ?>" class="hidden bg-surface-container-low/50">
                            <td colspan="5" class="px-4 py-3 border-b border-outline-variant/30">
                                <form method="POST" action="" class="flex flex-wrap md:flex-nowrap gap-3 items-end bg-white p-3 rounded-xl border border-outline-variant/50 shadow-inner">
                                    <input type="hidden" name="action" value="edit_prodi">
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    
                                    <div class="w-full md:w-1/3">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Fakultas</label>
                                        <select name="fakultas_id" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                            <?php foreach($fakultasList as $fak): ?>
                                                <option value="<?= $fak['id'] ?>" <?= ($p['fakultas_id'] == $fak['id']) ? 'selected' : '' ?>><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="flex-1 min-w-[150px]">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Nama Program Studi</label>
                                        <input type="text" name="nama_prodi" value="<?= htmlspecialchars($p['nama_prodi']) ?>" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none" required>
                                    </div>
                                    <div class="w-24">
                                        <label class="block text-[10px] font-semibold text-on-surface-variant mb-1">Jenjang</label>
                                        <select name="jenjang" class="w-full bg-surface border border-outline-variant rounded-lg px-2 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none">
                                            <option value="D3" <?= $p['jenjang'] == 'D3' ? 'selected' : '' ?>>D3</option>
                                            <option value="D4" <?= $p['jenjang'] == 'D4' ? 'selected' : '' ?>>D4</option>
                                            <option value="S1" <?= $p['jenjang'] == 'S1' ? 'selected' : '' ?>>S1</option>
                                            <option value="S2" <?= $p['jenjang'] == 'S2' ? 'selected' : '' ?>>S2</option>
                                            <option value="S3" <?= $p['jenjang'] == 'S3' ? 'selected' : '' ?>>S3</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" onclick="toggleEditProdi(<?= $p['id'] ?>)" class="px-3 py-1.5 rounded-lg border border-outline-variant text-on-surface-variant text-xs hover:bg-surface-container">Batal</button>
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-primary text-on-primary text-xs hover:bg-on-primary-fixed-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">save</span> Simpan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
function toggleEditFakultas(id) {
    document.querySelectorAll('tr[id^="edit_fak_"]').forEach(row => {
        if (row.id !== 'edit_fak_' + id) row.classList.add('hidden');
    });
    const editRow = document.getElementById('edit_fak_' + id);
    if (editRow.classList.contains('hidden')) editRow.classList.remove('hidden');
    else editRow.classList.add('hidden');
}

function toggleEditProdi(id) {
    document.querySelectorAll('tr[id^="edit_prodi_"]').forEach(row => {
        if (row.id !== 'edit_prodi_' + id) row.classList.add('hidden');
    });
    const editRow = document.getElementById('edit_prodi_' + id);
    if (editRow.classList.contains('hidden')) editRow.classList.remove('hidden');
    else editRow.classList.add('hidden');
}
function toggleEditKampus(id) {
    document.querySelectorAll('tr[id^="edit_kampus_"]').forEach(row => {
        if (row.id !== 'edit_kampus_' + id) row.classList.add('hidden');
    });
    const editRow = document.getElementById('edit_kampus_' + id);
    if (editRow.classList.contains('hidden')) editRow.classList.remove('hidden');
    else editRow.classList.add('hidden');
}
function toggleEditRuangan(id) {
    document.querySelectorAll('tr[id^="edit_ruangan_"]').forEach(row => {
        if (row.id !== 'edit_ruangan_' + id) row.classList.add('hidden');
    });
    const editRow = document.getElementById('edit_ruangan_' + id);
    if (editRow.classList.contains('hidden')) editRow.classList.remove('hidden');
    else editRow.classList.add('hidden');
}
function toggleEditGedung(id) {
    document.querySelectorAll('tr[id^="edit_gedung_"]').forEach(row => {
        if (row.id !== 'edit_gedung_' + id) row.classList.add('hidden');
    });
    const editRow = document.getElementById('edit_gedung_' + id);
    if (editRow.classList.contains('hidden')) editRow.classList.remove('hidden');
    else editRow.classList.add('hidden');
}
</script>

<?php include 'components/footer.php'; ?>
