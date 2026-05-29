<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$pdo = Database::getConnection();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'tambah_catatan') {
            $mahasiswa_id = (int)($_POST['mahasiswa_id'] ?? 0);
            $catatan = trim($_POST['catatan'] ?? '');
            $stmtSmt = $pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
            $semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
            $semester = $_POST['semester'] ?? $semesterAktif;
            $tahun_ajaran = $_POST['tahun_ajaran'] ?? date('Y') . '/' . (date('Y')+1);
            
            if ($mahasiswa_id > 0 && $catatan !== '') {
                $stmt = $pdo->prepare("INSERT INTO catatan_perwalian (mahasiswa_id, dosen_wali_id, semester, tahun_ajaran, catatan) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$mahasiswa_id, $dosenId, $semester, $tahun_ajaran, $catatan])) {
                    $success = "Catatan perwalian berhasil ditambahkan.";
                } else {
                    $error = "Gagal menyimpan catatan.";
                }
            }
        }
    }
}

// Ambil Mahasiswa Binaan
$stmtMhs = $pdo->prepare("SELECT id, nim, nama, program_studi, no_telp, email FROM mahasiswa WHERE dosen_wali_id = ? ORDER BY nim ASC");
$stmtMhs->execute([$dosenId]);
$mahasiswaBinaan = $stmtMhs->fetchAll();

// Ambil Catatan Perwalian
$stmtCatatan = $pdo->prepare("SELECT c.*, m.nama as mhs_nama, m.nim FROM catatan_perwalian c JOIN mahasiswa m ON c.mahasiswa_id = m.id WHERE c.dosen_wali_id = ? ORDER BY c.waktu_bimbingan DESC");
$stmtCatatan->execute([$dosenId]);
$catatanList = $stmtCatatan->fetchAll();

$title = "Bimbingan Perwalian";
$current_page = "dosen_perwalian.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col justify-between items-start">
    <h1>Bimbingan Akademik (Perwalian)</h1>
    <p class="text-on-surface-variant font-body-lg">Kelola mahasiswa binaan dan rekam catatan bimbingan pra-KRS.</p>
</div>

<?php if ($success): ?><div class="bg-primary/20 text-primary-fixed-dim p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error/20 text-error p-4 rounded-xl mb-6 font-medium flex items-center gap-2"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Panel Daftar Mahasiswa Binaan -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 max-h-[600px] flex flex-col">
        <h2 class="text-xl font-bold text-primary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">groups</span> Mahasiswa Binaan (<?= count($mahasiswaBinaan) ?>)
        </h2>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar space-y-3">
            <?php if (empty($mahasiswaBinaan)): ?>
                <div class="text-center py-8 text-on-surface-variant bg-surface-container-low rounded-xl border border-dashed border-outline-variant/50">
                    <span class="material-symbols-outlined text-4xl mb-2 opacity-50">group_off</span>
                    <p>Anda belum ditugaskan sebagai Dosen Wali untuk mahasiswa manapun.</p>
                </div>
            <?php else: foreach ($mahasiswaBinaan as $m): ?>
                <div class="bg-surface-container-lowest p-4 rounded-xl border border-outline-variant/30 hover:border-primary/30 transition-all cursor-pointer flex justify-between items-center group" onclick="bukaModalCatatan(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nama']) ?>', '<?= htmlspecialchars($m['nim']) ?>')">
                    <div>
                        <h3 class="font-bold text-primary group-hover:text-primary-fixed-dim line-clamp-1"><?= htmlspecialchars($m['nama']) ?></h3>
                        <p class="text-sm text-on-surface-variant mt-1"><?= htmlspecialchars($m['nim']) ?> • <?= htmlspecialchars($m['program_studi']) ?></p>
                        <p class="text-xs text-on-surface-variant opacity-70 mt-1">
                            <span class="material-symbols-outlined text-[14px] align-middle">call</span> <?= htmlspecialchars($m['no_telp'] ?: '-') ?>
                        </p>
                    </div>
                    <button class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all">
                        <span class="material-symbols-outlined">edit_note</span>
                    </button>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Panel Riwayat Catatan -->
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 max-h-[600px] flex flex-col">
        <h2 class="text-xl font-bold text-secondary mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined">history</span> Riwayat Catatan Perwalian
        </h2>
        
        <div class="overflow-y-auto flex-1 pr-2 custom-scrollbar space-y-4">
            <?php if (empty($catatanList)): ?>
                <div class="text-center py-8 text-on-surface-variant opacity-70">Belum ada catatan perwalian yang direkam.</div>
            <?php else: foreach ($catatanList as $c): ?>
                <div class="bg-secondary/5 p-4 rounded-xl border border-secondary/20 relative">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <span class="bg-secondary/20 text-secondary-fixed-dim px-2 py-0.5 rounded text-xs font-bold mb-1 inline-block"><?= htmlspecialchars($c['semester'] . ' ' . $c['tahun_ajaran']) ?></span>
                            <h4 class="font-bold text-secondary text-sm"><?= htmlspecialchars($c['mhs_nama']) ?> (<?= htmlspecialchars($c['nim']) ?>)</h4>
                        </div>
                        <span class="text-xs text-on-surface-variant opacity-60"><?= date('d M Y H:i', strtotime($c['waktu_bimbingan'])) ?></span>
                    </div>
                    <p class="text-sm text-on-surface-variant whitespace-pre-wrap bg-white/50 p-3 rounded-lg mt-2"><?= htmlspecialchars($c['catatan']) ?></p>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<!-- Modal Tambah Catatan -->
<div id="modalCatatan" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg overflow-hidden border border-outline-variant/30">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold text-primary">Rekam Catatan Perwalian</h2>
            <button onclick="document.getElementById('modalCatatan').classList.add('hidden')" class="text-on-surface-variant hover:text-error">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="tambah_catatan">
            <input type="hidden" name="mahasiswa_id" id="catatan_mhs_id">
            
            <div class="mb-4 bg-primary/10 p-3 rounded-xl border border-primary/20">
                <p class="text-sm text-on-surface-variant">Mahasiswa:</p>
                <p class="font-bold text-primary" id="catatan_mhs_nama_nim"></p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Semester</label>
                    <select name="semester" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                        <option value="Ganjil">Ganjil</option>
                        <option value="Genap">Genap</option>
                        <option value="Pendek">Pendek</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-primary mb-2">Tahun Ajaran</label>
                    <input type="text" name="tahun_ajaran" value="<?= date('Y').'/'.(date('Y')+1) ?>" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-primary mb-2">Catatan Bimbingan / Rekomendasi SKS</label>
                <textarea name="catatan" required rows="4" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none placeholder:opacity-50" placeholder="Contoh: Disarankan mengambil maksimal 20 SKS karena IPK semester lalu menurun..."></textarea>
            </div>
            
            <div class="flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalCatatan').classList.add('hidden')" class="px-6 py-3 rounded-xl font-label-lg text-on-surface-variant hover:bg-surface-container-low transition-all">Batal</button>
                <button type="submit" class="bg-primary hover:bg-primary-fixed-dim text-on-primary px-6 py-3 rounded-xl font-label-lg transition-all shadow-md">Simpan Catatan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalCatatan(id, nama, nim) {
    document.getElementById('catatan_mhs_id').value = id;
    document.getElementById('catatan_mhs_nama_nim').innerText = nama + ' (' + nim + ')';
    document.getElementById('modalCatatan').classList.remove('hidden');
}
</script>

<?php include 'components/footer.php'; ?>
