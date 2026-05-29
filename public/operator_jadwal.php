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
        
        if ($action === 'assign_jadwal') {
            // Identifier kelas berasal dari dosen_matakuliah
            $kelas_str = $_POST['kelas_id'] ?? ''; // Format: dosen_id|matakuliah_id|semester
            $ruangan_id = (int)($_POST['ruangan_id'] ?? 0);
            $hari = $_POST['hari'] ?? '';
            $jam_mulai = $_POST['jam_mulai'] ?? '';
            $jam_selesai = $_POST['jam_selesai'] ?? '';
            
            if ($kelas_str && $ruangan_id && $hari && $jam_mulai && $jam_selesai) {
                list($dosen_id, $matakuliah_id, $semester) = explode('|', $kelas_str);
                
                // Validasi bentrok ruangan
                $stmtCek = $pdo->prepare("SELECT id FROM jadwal_kelas WHERE ruangan_id = ? AND hari = ? AND semester = ? AND (jam_mulai < ? AND jam_selesai > ?)");
                $stmtCek->execute([$ruangan_id, $hari, $semester, $jam_selesai, $jam_mulai]);
                
                if ($stmtCek->fetch()) {
                    $error = "Bentrok! Ruangan sudah dipakai pada hari dan jam tersebut.";
                } else {
                    // Validasi bentrok dosen
                    $stmtCekDosen = $pdo->prepare("SELECT id FROM jadwal_kelas WHERE dosen_id = ? AND hari = ? AND semester = ? AND (jam_mulai < ? AND jam_selesai > ?)");
                    $stmtCekDosen->execute([$dosen_id, $hari, $semester, $jam_selesai, $jam_mulai]);
                    
                    if ($stmtCekDosen->fetch()) {
                        $error = "Bentrok! Dosen sudah memiliki jadwal mengajar di tempat lain pada waktu tersebut.";
                    } else {
                        // Hapus jadwal lama jika ada (1 kelas 1 jadwal untuk saat ini)
                        $pdo->prepare("DELETE FROM jadwal_kelas WHERE dosen_id = ? AND matakuliah_id = ? AND semester = ?")->execute([$dosen_id, $matakuliah_id, $semester]);
                        
                        $stmt = $pdo->prepare("INSERT INTO jadwal_kelas (dosen_id, matakuliah_id, semester, ruangan_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$dosen_id, $matakuliah_id, $semester, $ruangan_id, $hari, $jam_mulai, $jam_selesai])) {
                            $success = "Jadwal kelas berhasil diatur.";
                        } else {
                            $error = "Gagal menyimpan jadwal.";
                        }
                    }
                }
            } else {
                $error = "Harap lengkapi semua field.";
            }
        } elseif ($action === 'edit_jadwal') {
            $id = (int)($_POST['jadwal_id'] ?? 0);
            $ruangan_id = (int)($_POST['ruangan_id'] ?? 0);
            $hari = $_POST['hari'] ?? '';
            $jam_mulai = $_POST['jam_mulai'] ?? '';
            $jam_selesai = $_POST['jam_selesai'] ?? '';
            
            if ($id && $ruangan_id && $hari && $jam_mulai && $jam_selesai) {
                // Ambil dosen_id & semester
                $jadwal_lama = $pdo->prepare("SELECT dosen_id, semester FROM jadwal_kelas WHERE id = ?");
                $jadwal_lama->execute([$id]);
                $old = $jadwal_lama->fetch();
                if ($old) {
                    $dosen_id = $old['dosen_id'];
                    $semester = $old['semester'];
                    
                    // Cek bentrok ruangan
                    $stmtCek = $pdo->prepare("SELECT id FROM jadwal_kelas WHERE ruangan_id = ? AND hari = ? AND semester = ? AND (jam_mulai < ? AND jam_selesai > ?) AND id != ?");
                    $stmtCek->execute([$ruangan_id, $hari, $semester, $jam_selesai, $jam_mulai, $id]);
                    if ($stmtCek->fetch()) {
                        $error = "Bentrok! Ruangan sudah dipakai pada hari dan jam tersebut.";
                    } else {
                        // Cek bentrok dosen
                        $stmtCekDosen = $pdo->prepare("SELECT id FROM jadwal_kelas WHERE dosen_id = ? AND hari = ? AND semester = ? AND (jam_mulai < ? AND jam_selesai > ?) AND id != ?");
                        $stmtCekDosen->execute([$dosen_id, $hari, $semester, $jam_selesai, $jam_mulai, $id]);
                        if ($stmtCekDosen->fetch()) {
                            $error = "Bentrok! Dosen sudah memiliki jadwal di tempat lain.";
                        } else {
                            $stmt = $pdo->prepare("UPDATE jadwal_kelas SET ruangan_id=?, hari=?, jam_mulai=?, jam_selesai=? WHERE id=?");
                            if ($stmt->execute([$ruangan_id, $hari, $jam_mulai, $jam_selesai, $id])) {
                                $success = "Jadwal kelas berhasil diperbarui.";
                            } else {
                                $error = "Gagal memperbarui jadwal.";
                            }
                        }
                    }
                } else {
                    $error = "Jadwal tidak ditemukan.";
                }
            } else {
                $error = "Lengkapi form edit.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare("DELETE FROM jadwal_kelas WHERE id = ?")->execute([$id]);
            $success = "Jadwal berhasil dihapus.";
        }
    }
}

// Fetch Master Data
$ruanganList = $pdo->query("SELECT * FROM ruangan ORDER BY kode_ruangan ASC")->fetchAll();
$kelasList = $pdo->query("
    SELECT dm.dosen_id, dm.matakuliah_id, dm.semester, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama 
    FROM dosen_matakuliah dm
    JOIN mata_kuliah mk ON dm.matakuliah_id = mk.id
    JOIN dosen d ON dm.dosen_id = d.id
    ORDER BY mk.nama ASC
")->fetchAll();

$fakultasList = $pdo->query("SELECT * FROM master_fakultas ORDER BY nama_fakultas ASC")->fetchAll();
$prodiList = $pdo->query("SELECT mp.*, mf.nama_fakultas FROM master_prodi mp JOIN master_fakultas mf ON mp.fakultas_id = mf.id ORDER BY mp.nama_prodi ASC")->fetchAll();

// Fetch Assigned Jadwal
$jadwalList = $pdo->query("
    SELECT jk.*, mk.kode, mk.nama as mk_nama, mk.sks, mk.prodi as mk_prodi,
           (SELECT mf.nama_fakultas FROM master_prodi mp JOIN master_fakultas mf ON mp.fakultas_id = mf.id WHERE mp.nama_prodi = mk.prodi COLLATE utf8mb4_unicode_ci LIMIT 1) as mk_fakultas,
           d.nama as dosen_nama, r.nama_ruangan, r.kode_ruangan
    FROM jadwal_kelas jk
    JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
    JOIN dosen d ON jk.dosen_id = d.id
    JOIN ruangan r ON jk.ruangan_id = r.id
    ORDER BY jk.semester, FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jk.jam_mulai ASC
")->fetchAll();

$title = "Manajemen Jadwal Kelas";
$current_page = "operator_jadwal.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Manajemen Jadwal Kelas</h1>
        <p class="text-on-surface-variant font-body-lg">Atur jadwal pertemuan fisik dan plot ruangan untuk kelas yang tersedia.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md flex items-center gap-2">
        <span class="material-symbols-outlined">edit_calendar</span> Set Jadwal Baru
    </button>
</div>

<?php if ($success): ?><div class="bg-primary/20 text-primary-fixed-dim p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="mb-6 flex flex-col lg:flex-row gap-4 items-center justify-between bg-surface-container-low p-4 rounded-2xl border border-outline-variant/30 shadow-sm">
    <div class="relative w-full lg:w-1/3">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
        <input type="text" id="searchJadwal" placeholder="Cari MK, Ruangan, atau Dosen..." class="w-full pl-12 pr-4 py-3 bg-surface border border-outline-variant/50 rounded-xl focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all font-medium text-sm">
    </div>
    <div class="flex flex-wrap lg:flex-nowrap gap-3 w-full lg:w-2/3">
        <div class="relative flex-1 min-w-[140px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">account_balance</span>
            <select id="filterFakultas" class="w-full bg-surface border border-outline-variant/50 rounded-xl pl-10 pr-8 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all cursor-pointer appearance-none text-sm font-medium">
                <option value="">Semua Fakultas</option>
                <?php foreach ($fakultasList as $f): ?>
                    <option value="<?= htmlspecialchars($f['nama_fakultas']) ?>"><?= htmlspecialchars($f['nama_fakultas']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">arrow_drop_down</span>
        </div>
        
        <div class="relative flex-1 min-w-[140px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">school</span>
            <select id="filterProdi" class="w-full bg-surface border border-outline-variant/50 rounded-xl pl-10 pr-8 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all cursor-pointer appearance-none text-sm font-medium">
                <option value="">Semua Prodi</option>
                <?php foreach ($prodiList as $p): ?>
                    <option value="<?= htmlspecialchars($p['nama_prodi']) ?>" data-fakultas="<?= htmlspecialchars($p['nama_fakultas']) ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">arrow_drop_down</span>
        </div>

        <div class="relative flex-1 min-w-[140px]">
            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">calendar_today</span>
            <select id="filterHari" class="w-full bg-surface border border-outline-variant/50 rounded-xl pl-10 pr-8 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-all cursor-pointer appearance-none text-sm font-medium">
                <option value="">Semua Hari</option>
                <option value="Senin">Senin</option>
                <option value="Selasa">Selasa</option>
                <option value="Rabu">Rabu</option>
                <option value="Kamis">Kamis</option>
                <option value="Jumat">Jumat</option>
                <option value="Sabtu">Sabtu</option>
            </select>
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">arrow_drop_down</span>
        </div>
    </div>
</div>

<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse" id="tableJadwal">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Hari & Waktu</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Ruangan</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Mata Kuliah</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Dosen Pengampu</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Smt</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jadwalList as $j): ?>
                <tr class="hover:bg-surface-variant/10 cursor-pointer transition-colors jadwal-row" data-hari="<?= htmlspecialchars($j['hari']) ?>" data-fakultas="<?= htmlspecialchars($j['mk_fakultas'] ?? '') ?>" data-prodi="<?= htmlspecialchars($j['mk_prodi'] ?? '') ?>" onclick="openEditModal(<?= $j['id'] ?>)">
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <span class="bg-primary-container/30 text-primary px-2 py-1 rounded text-sm font-bold inline-block mb-1 search-target"><?= htmlspecialchars($j['hari']) ?></span><br>
                        <span class="text-xs font-bold text-on-surface-variant search-target"><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?></span>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-bold text-primary search-target">
                        <?= htmlspecialchars($j['kode_ruangan']) ?>
                        <div class="text-xs text-on-surface-variant font-normal"><?= htmlspecialchars($j['nama_ruangan']) ?></div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <strong class="search-target"><?= htmlspecialchars($j['mk_nama']) ?></strong>
                        <div class="text-xs opacity-70 search-target"><?= htmlspecialchars($j['kode']) ?> (<?= $j['sks'] ?> SKS)</div>
                        <div class="text-[10px] bg-surface-variant text-on-surface-variant px-1.5 py-0.5 rounded-md inline-block mt-1"><?= htmlspecialchars($j['mk_prodi'] ?? 'Umum') ?></div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md search-target"><?= htmlspecialchars($j['dosen_nama']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 text-center font-bold text-primary bg-primary/5"><?= htmlspecialchars($j['semester']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 whitespace-nowrap">
                        <button type="button" onclick="event.stopPropagation(); openEditModal(<?= $j['id'] ?>)" class="text-primary hover:text-primary-container mr-2 bg-primary/10 p-2 rounded-xl transition-colors" title="Edit Jadwal">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </button>
                        <form method="POST" class="inline" onsubmit="event.stopPropagation(); return confirm('Hapus jadwal ini?');">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $j['id'] ?>">
                            <button type="submit" class="text-error hover:text-error-container bg-error/10 p-2 rounded-xl transition-colors" onclick="event.stopPropagation()" title="Hapus Jadwal">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($jadwalList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-6 text-on-surface-variant italic">Belum ada jadwal yang diplot.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Set Jadwal -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-xl overflow-hidden border border-outline-variant/30">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold text-primary">Set Jadwal Kelas</h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="assign_jadwal">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Pilih Kelas (Dosen - MK - Smt)</label>
                <select name="kelas_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($kelasList as $k): 
                        $val = $k['dosen_id'].'|'.$k['matakuliah_id'].'|'.$k['semester'];
                    ?>
                        <option value="<?= $val ?>"><?= htmlspecialchars($k['mk_nama'].' ('.$k['dosen_nama'].') - Smt '.$k['semester']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Pilih Ruangan</label>
                <select name="ruangan_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                    <option value="">-- Pilih Ruangan --</option>
                    <?php foreach ($ruanganList as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_ruangan'].' (Kapasitas: '.$r['kapasitas'].')') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Hari</label>
                    <select name="hari" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jam Mulai</label>
                    <input type="time" name="jam_mulai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jam Selesai</label>
                    <input type="time" name="jam_selesai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Simpan Jadwal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit & Detail Jadwal -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-4xl overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">edit_calendar</span>
                Detail & Edit Jadwal
            </h2>
            <button onclick="closeEditModal()" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div id="modalEditLoading" class="p-10 flex flex-col items-center justify-center text-on-surface-variant">
            <span class="material-symbols-outlined animate-spin text-4xl text-primary mb-2">progress_activity</span>
            <p>Memuat informasi...</p>
        </div>

        <div id="modalEditContent" class="hidden flex-1 overflow-y-auto custom-scrollbar flex-col">
            <!-- Tabs -->
            <div class="flex border-b border-outline-variant/30 bg-surface-container-lowest flex-shrink-0">
                <button onclick="switchTab('tab-edit')" id="btn-tab-edit" class="flex-1 py-3 font-bold text-primary border-b-2 border-primary transition-all">Informasi & Edit</button>
                <button onclick="switchTab('tab-mhs')" id="btn-tab-mhs" class="flex-1 py-3 font-medium text-on-surface-variant border-b-2 border-transparent transition-all">Mahasiswa Terdaftar <span id="badge-mhs" class="ml-2 bg-primary text-white text-xs px-2 py-0.5 rounded-full">0</span></button>
            </div>
            
            <!-- Tab Edit -->
            <div id="tab-edit" class="p-6">
                <form method="POST">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="edit_jadwal">
                    <input type="hidden" name="jadwal_id" id="edit_jadwal_id">
                    
                    <div class="bg-primary/5 p-4 rounded-xl mb-6 flex gap-4">
                        <div class="flex-1">
                            <h3 id="edit_mk_nama" class="font-bold text-lg text-primary">Nama MK</h3>
                            <p id="edit_dosen" class="text-sm text-on-surface-variant">Dosen</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-primary mb-2">Pilih Ruangan</label>
                        <select name="ruangan_id" id="edit_ruangan_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                            <option value="">-- Pilih Ruangan --</option>
                            <?php foreach ($ruanganList as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_ruangan'].' (Kapasitas: '.$r['kapasitas'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-primary mb-2">Hari</label>
                            <select name="hari" id="edit_hari" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                                <option value="Senin">Senin</option>
                                <option value="Selasa">Selasa</option>
                                <option value="Rabu">Rabu</option>
                                <option value="Kamis">Kamis</option>
                                <option value="Jumat">Jumat</option>
                                <option value="Sabtu">Sabtu</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-primary mb-2">Jam Mulai</label>
                            <input type="time" name="jam_mulai" id="edit_jam_mulai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-primary mb-2">Jam Selesai</label>
                            <input type="time" name="jam_selesai" id="edit_jam_selesai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-outline-variant/20">
                        <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-2.5 rounded-xl font-label-lg transition-all shadow-md">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
            
            <!-- Tab Mhs -->
            <div id="tab-mhs" class="p-6 hidden max-h-[60vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-bold text-primary font-title-lg">Daftar Mahasiswa (Terdaftar)</h3>
                    <a href="#" id="edit_export_btn" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-4 py-2 rounded-xl text-sm font-bold transition-all flex items-center gap-2 shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">download</span> Export CSV
                    </a>
                </div>
                <table class="w-full text-left border-collapse border border-outline-variant/30 rounded-xl overflow-hidden">
                    <thead class="bg-surface-container-low text-on-surface-variant text-sm font-bold border-b border-outline-variant/30">
                        <tr>
                            <th class="py-2 px-4 w-12 text-center">No</th>
                            <th class="py-2 px-4">NIM</th>
                            <th class="py-2 px-4">Nama Mahasiswa</th>
                            <th class="py-2 px-4">Program Studi</th>
                        </tr>
                    </thead>
                    <tbody id="edit_mhs_body" class="text-sm divide-y divide-outline-variant/10 text-on-surface">
                        <!-- Diisi oleh JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Logic Search & Filter
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchJadwal');
    const filterHari = document.getElementById('filterHari');
    const filterFakultas = document.getElementById('filterFakultas');
    const filterProdi = document.getElementById('filterProdi');
    const rows = document.querySelectorAll('.jadwal-row');

    function applyFilters() {
        const query = searchInput.value.toLowerCase();
        const hari = filterHari.value;
        const fakultas = filterFakultas.value;
        const prodi = filterProdi.value;

        rows.forEach(row => {
            const rowHari = row.getAttribute('data-hari');
            const rowFakultas = row.getAttribute('data-fakultas');
            const rowProdi = row.getAttribute('data-prodi');
            
            const targets = row.querySelectorAll('.search-target');
            let textMatch = false;
            
            targets.forEach(t => {
                if (t.textContent.toLowerCase().includes(query)) textMatch = true;
            });
            
            const hariMatch = (hari === '' || rowHari === hari);
            const fakultasMatch = (fakultas === '' || rowFakultas === fakultas);
            const prodiMatch = (prodi === '' || rowProdi === prodi);

            if (textMatch && hariMatch && fakultasMatch && prodiMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput.addEventListener('input', applyFilters);
    filterHari.addEventListener('change', applyFilters);
    filterProdi.addEventListener('change', applyFilters);
    
    // Dynamic Prodi Options based on Fakultas
    filterFakultas.addEventListener('change', () => {
        const selectedFakultas = filterFakultas.value;
        const prodiOptions = filterProdi.querySelectorAll('option');
        
        filterProdi.value = ''; // Reset prodi selection
        
        prodiOptions.forEach(opt => {
            if (opt.value === '') {
                opt.style.display = '';
                return;
            }
            const optFakultas = opt.getAttribute('data-fakultas');
            if (selectedFakultas === '' || optFakultas === selectedFakultas) {
                opt.style.display = '';
            } else {
                opt.style.display = 'none';
            }
        });
        
        applyFilters();
    });
    
    // Pindahkan modal ke document.body agar tidak terpengaruh stacking context
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalEdit) document.body.appendChild(modalEdit);
});

// Logic Modal Edit & Tab
function openEditModal(id) {
    const modal = document.getElementById('modalEdit');
    const loading = document.getElementById('modalEditLoading');
    const content = document.getElementById('modalEditContent');
    
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    switchTab('tab-edit'); // Reset tab

    fetch('ajax_get_kelas_info.php?id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const k = data.data.kelas;
                const m = data.data.mahasiswa;
                
                // Populate Form
                document.getElementById('edit_jadwal_id').value = k.id;
                document.getElementById('edit_export_btn').href = 'export_kelas_peserta.php?id=' + k.id;
                document.getElementById('edit_mk_nama').textContent = k.mk_nama + ' (' + k.mk_kode + ')';
                document.getElementById('edit_dosen').textContent = 'Dosen: ' + k.dosen_nama;
                
                // Select values
                const rSelect = document.getElementById('edit_ruangan_id');
                for(let i=0; i<rSelect.options.length; i++) {
                    if(rSelect.options[i].text.includes(k.nama_ruangan)) {
                        rSelect.selectedIndex = i; break;
                    }
                }
                
                document.getElementById('edit_hari').value = k.hari;
                document.getElementById('edit_jam_mulai').value = k.jam_mulai;
                document.getElementById('edit_jam_selesai').value = k.jam_selesai;
                
                // Populate Mhs
                document.getElementById('badge-mhs').textContent = m.length;
                const tbody = document.getElementById('edit_mhs_body');
                tbody.innerHTML = '';
                if(m.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="py-6 px-4 text-center italic text-on-surface-variant">Belum ada mahasiswa.</td></tr>';
                } else {
                    m.forEach((mhs, idx) => {
                        tbody.innerHTML += `
                            <tr class="hover:bg-surface-variant/10">
                                <td class="py-2 px-4 text-center">${idx + 1}</td>
                                <td class="py-2 px-4 font-bold text-primary">${mhs.nim}</td>
                                <td class="py-2 px-4">${mhs.nama}</td>
                                <td class="py-2 px-4 text-xs">${mhs.program_studi}</td>
                            </tr>
                        `;
                    });
                }
                
                loading.classList.add('hidden');
                content.classList.remove('hidden');
            } else {
                alert('Gagal memuat: ' + (data.error || 'Unknown'));
                closeEditModal();
            }
        })
        .catch(e => {
            alert('Kesalahan jaringan.');
            closeEditModal();
        });
}

function closeEditModal() {
    document.getElementById('modalEdit').classList.add('hidden');
}

function switchTab(tabId) {
    document.getElementById('tab-edit').classList.add('hidden');
    document.getElementById('tab-mhs').classList.add('hidden');
    document.getElementById(tabId).classList.remove('hidden');
    
    document.getElementById('btn-tab-edit').className = 'flex-1 py-3 font-medium text-on-surface-variant border-b-2 border-transparent transition-all';
    document.getElementById('btn-tab-mhs').className = 'flex-1 py-3 font-medium text-on-surface-variant border-b-2 border-transparent transition-all';
    
    document.getElementById('btn-' + tabId).className = 'flex-1 py-3 font-bold text-primary border-b-2 border-primary transition-all';
}
</script>

<?php include 'components/footer.php'; ?>
