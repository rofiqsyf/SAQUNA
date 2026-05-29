<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;
use Src\OperatorRepository;

Auth::requireOperator();

$pdo = Database::getConnection();
$repo = new OperatorRepository();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'tambah') {
            $kelasId = (int)($_POST['kelas_id'] ?? 0);
            $jenis = $_POST['jenis_ujian'] ?? 'UTS';
            $tanggal = $_POST['tanggal'] ?? '';
            $jamMulai = $_POST['jam_mulai'] ?? '';
            $jamSelesai = $_POST['jam_selesai'] ?? '';
            $ruanganId = (int)($_POST['ruangan_id'] ?? 0);
            $pengawasId = (int)($_POST['pengawas_id'] ?? 0);
            
            if ($repo->createJadwalUjian($kelasId, $jenis, $tanggal, $jamMulai, $jamSelesai, $ruanganId, $pengawasId)) {
                $success = "Jadwal Ujian berhasil ditambahkan.";
            } else {
                $error = "Gagal menambahkan! Terdapat bentrok Ruangan atau bentrok jadwal Pengawas di jam tersebut.";
            }
        } elseif ($action === 'hapus') {
            $id = (int)($_POST['id'] ?? 0);
            if ($repo->deleteJadwalUjian($id)) {
                $success = "Jadwal Ujian berhasil dihapus.";
            } else {
                $error = "Gagal menghapus jadwal.";
            }
        }
    }
}

// Fetch Master Data
$ruanganList = $pdo->query("SELECT * FROM ruangan ORDER BY kode_ruangan ASC")->fetchAll();
$dosenList = $pdo->query("SELECT id, nama, nidn FROM dosen WHERE status='aktif' ORDER BY nama ASC")->fetchAll();
$kelasList = $pdo->query("
    SELECT jk.id, jk.semester, mk.kode, mk.nama as mk_nama, d.nama as dosen_nama 
    FROM jadwal_kelas jk
    JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
    JOIN dosen d ON jk.dosen_id = d.id
    ORDER BY mk.nama ASC
")->fetchAll();

$jadwalList = $repo->getJadwalUjian();

$title = "Manajemen Jadwal Ujian";
$current_page = "operator_ujian.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Manajemen Jadwal Ujian (UTS/UAS)</h1>
        <p class="text-on-surface-variant font-body-lg">Atur jadwal ujian terpusat beserta plot ruangan dan dosen pengawas.</p>
    </div>
    <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md flex items-center gap-2">
        <span class="material-symbols-outlined">add_task</span> Plot Jadwal Ujian
    </button>
</div>

<?php if ($success): ?><div class="bg-primary/20 text-primary-fixed-dim p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Tgl & Jam</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Mata Kuliah</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Ruangan</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Dosen Pengawas</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Jenis</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jadwalList as $j): ?>
                <tr>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <div class="font-bold text-primary"><?= date('d M Y', strtotime($j['tanggal'])) ?></div>
                        <div class="text-xs font-bold text-on-surface-variant"><?= substr($j['jam_mulai'], 0, 5) ?> - <?= substr($j['jam_selesai'], 0, 5) ?></div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <strong><?= htmlspecialchars($j['mk_nama']) ?></strong>
                        <div class="text-xs opacity-70"><?= htmlspecialchars($j['kode']) ?> | Pengampu: <?= htmlspecialchars($j['dosen_pengampu']) ?></div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-bold text-secondary">
                        <?= htmlspecialchars($j['kode_ruangan']) ?>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($j['dosen_pengawas']) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <span class="px-2 py-1 <?= $j['jenis_ujian'] === 'UTS' ? 'bg-tertiary/20 text-tertiary-fixed-dim' : 'bg-primary/20 text-primary-fixed-dim' ?> rounded-md font-label-sm">
                            <?= htmlspecialchars($j['jenis_ujian']) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20">
                        <form method="POST" class="inline" onsubmit="return confirm('Hapus jadwal ujian ini?');">
                            <?= Auth::csrfField() ?>
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?= $j['id'] ?>">
                            <button type="submit" class="text-error hover:text-error-container">
                                <span class="material-symbols-outlined">delete</span>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($jadwalList)): ?>
                <tr>
                    <td colspan="6" class="text-center py-6 text-on-surface-variant italic">Belum ada jadwal ujian.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center flex-shrink-0">
            <h2 class="text-xl font-bold text-primary">Plot Jadwal Ujian Baru</h2>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah">
            
            <div class="mb-4">
                <label class="block text-sm font-semibold text-primary mb-2">Pilih Kelas</label>
                <select name="kelas_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                    <option value="">-- Pilih Kelas --</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['mk_nama'].' ('.$k['dosen_nama'].') - Smt '.$k['semester']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jenis Ujian</label>
                    <select name="jenis_ujian" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="UTS">Ujian Tengah Semester (UTS)</option>
                        <option value="UAS">Ujian Akhir Semester (UAS)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tanggal</label>
                    <input type="date" name="tanggal" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jam Mulai</label>
                    <input type="time" name="jam_mulai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Jam Selesai</label>
                    <input type="time" name="jam_selesai" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Ruangan Ujian</label>
                    <select name="ruangan_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Ruangan --</option>
                        <?php foreach ($ruanganList as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nama_ruangan'].' (Kapasitas: '.$r['kapasitas'].')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Dosen Pengawas</label>
                    <select name="pengawas_id" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Pengawas --</option>
                        <?php foreach ($dosenList as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Simpan Jadwal Ujian</button>
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
