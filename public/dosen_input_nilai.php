<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireDosen();

$repo = new DosenRepository();
$pdo = Database::getConnection();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$success = '';
$error = '';

// Proses simpan nilai sekarang ditangani oleh api_input_nilai.php via AJAX Auto-Save

// Data Kelas yang Diajar
$mkDosenIds = $repo->getDosenMataKuliahIds($dosenId);
$semuaMk = $repo->getAllMataKuliah();
$mkDosenFull = array_filter($semuaMk, fn(array $m) => in_array($m['id'], $mkDosenIds));

// Filter Aktif
$firstMk = reset($mkDosenFull);
$selected_mk = isset($_GET['matakuliah_id']) ? (int)$_GET['matakuliah_id'] : ($firstMk ? (int)$firstMk['id'] : null);
$stmtSmt = $pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
$selected_semester = $_GET['semester'] ?? $semesterAktif;

$mahasiswaKelas = [];
if ($selected_mk) {
    // Ambil daftar mahasiswa beserta komponen nilainya
    $sqlMhs = "SELECT k.id as krs_id, k.nilai_huruf, m.nama as mahasiswa_nama, m.nim, m.program_studi,
                      kn.nilai_tugas, kn.nilai_uts, kn.nilai_uas, kn.nilai_praktikum
               FROM krs k
               JOIN mahasiswa m ON k.mahasiswa_id = m.id
               LEFT JOIN komponen_nilai kn ON k.id = kn.krs_id
               WHERE k.dosen_id = ? AND k.matakuliah_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'
               ORDER BY m.nim ASC";
    $stmtMhs = $pdo->prepare($sqlMhs);
    $stmtMhs->execute([$dosenId, $selected_mk, $selected_semester]);
    $mahasiswaKelas = $stmtMhs->fetchAll();
}

$title = "Input Nilai Akhir (KHS)";
$current_page = "dosen_input_nilai.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Input Nilai Komponen</h1>
        <p class="text-on-surface/70 mt-1">Masukkan komponen nilai (0-100) untuk kalkulasi nilai akhir mahasiswa.</p>
    </div>
</div>

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

<!-- Filter Kelas -->
<div class="card p-5 rounded-2xl bg-surface shadow-sm border border-outline-variant/30 mb-6">
    <form method="GET" class="flex flex-col md:flex-row gap-4">
        <div class="flex-1">
            <label class="block text-sm font-semibold text-primary mb-2">Mata Kuliah</label>
            <select name="matakuliah_id" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                <?php foreach ($mkDosenFull as $mk): ?>
                    <option value="<?= $mk['id'] ?>" <?= $selected_mk === (int)$mk['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:w-64">
            <label class="block text-sm font-semibold text-primary mb-2">Semester Aktif</label>
            <select name="semester" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm">
                <option value="Ganjil" <?= $selected_semester === 'Ganjil' ? 'selected' : '' ?>>Ganjil</option>
                <option value="Genap" <?= $selected_semester === 'Genap' ? 'selected' : '' ?>>Genap</option>
                <option value="Pendek" <?= $selected_semester === 'Pendek' ? 'selected' : '' ?>>Pendek</option>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="btn-primary py-3 px-6 h-[46px] flex items-center justify-center">Tampilkan</button>
        </div>
    </form>
</div>

<!-- Informasi Bobot -->
<div class="bg-primary/5 border border-primary/20 rounded-2xl p-4 mb-6 flex items-center gap-4 text-sm text-on-surface-variant">
    <span class="material-symbols-outlined text-primary text-2xl">info</span>
    <p>Bobot otomatis: <strong>Tugas (20%) + UTS (30%) + UAS (40%) + Praktikum (10%)</strong>. Nilai Huruf akan dikalkulasi otomatis saat Anda menyimpan nilai.</p>
</div>

<!-- Daftar Mahasiswa -->
<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <div class="p-5 border-b border-outline-variant/30">
        <h2 class="text-lg font-bold text-primary flex items-center gap-2">
            <span class="material-symbols-outlined">spellcheck</span> Form Pengisian Nilai
        </h2>
    </div>
    
    <?php if (empty($mahasiswaKelas)): ?>
        <div class="p-10 text-center text-on-surface-variant">
            <span class="material-symbols-outlined text-5xl opacity-30 mb-2">group_off</span>
            <p>Tidak ada mahasiswa yang disetujui KRS-nya untuk kelas ini.</p>
        </div>
    <?php else: ?>
        <div class="glass-panel p-stack-md rounded-3xl shadow-sm border border-white/40 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-10 -mt-10 blur-2xl transition-colors"></div>
            
            <div class="mb-4 bg-primary/5 p-4 rounded-xl border border-primary/20">
                <p class="font-bold text-primary mb-1">Informasi Fitur Auto-Save</p>
                <p class="text-sm text-on-surface-variant">Sistem kini dilengkapi dengan penyimpanan otomatis (Auto-Save). Anda tidak perlu menekan tombol simpan massal. Nilai akan otomatis tersimpan setiap kali Anda mengetik dan berpindah kolom.</p>
            </div>

            <div class="table-responsive-wrapper relative z-10 custom-scrollbar rounded-xl border border-outline-variant/30">
                <input type="hidden" id="csrf_token" value="<?= Auth::generateCsrf() ?>">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-12 text-center">No</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 min-w-[200px]">Mahasiswa</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-24 text-center">Tugas (20%)</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-24 text-center">UTS (30%)</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-24 text-center">UAS (40%)</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-24 text-center">Praktek (10%)</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 w-24 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($mahasiswaKelas as $mhs): 
                        ?>
                        <tr class="hover:bg-surface-container-lowest transition-colors row-nilai" data-krs="<?= $mhs['krs_id'] ?>">
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center font-body-md text-on-surface-variant"><?= $no++ ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                <div class="font-bold text-on-surface"><?= htmlspecialchars($mhs['mahasiswa_nama']) ?>
                                    <?php if(!empty($mhs['is_mengulang'])): ?>
                                        <span class="inline-flex items-center justify-center bg-error/10 text-error px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider ml-2 border border-error/20" title="Mahasiswa ini sedang mengulang mata kuliah">Mengulang</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-on-surface-variant"><?= htmlspecialchars($mhs['nim']) ?> - <?= htmlspecialchars($mhs['program_studi']) ?></div>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <input type="number" min="0" max="100" class="w-full bg-surface border border-outline-variant/50 rounded-lg px-2 py-1.5 text-center focus:ring-2 focus:ring-primary input-nilai" data-type="tugas" value="<?= $mhs['nilai_tugas'] ?? '' ?>">
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <input type="number" min="0" max="100" class="w-full bg-surface border border-outline-variant/50 rounded-lg px-2 py-1.5 text-center focus:ring-2 focus:ring-primary input-nilai" data-type="uts" value="<?= $mhs['nilai_uts'] ?? '' ?>">
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <input type="number" min="0" max="100" class="w-full bg-surface border border-outline-variant/50 rounded-lg px-2 py-1.5 text-center focus:ring-2 focus:ring-primary input-nilai" data-type="uas" value="<?= $mhs['nilai_uas'] ?? '' ?>">
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20">
                                <input type="number" min="0" max="100" class="w-full bg-surface border border-outline-variant/50 rounded-lg px-2 py-1.5 text-center focus:ring-2 focus:ring-primary input-nilai" data-type="praktikum" value="<?= $mhs['nilai_praktikum'] ?? '' ?>">
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 text-center status-cell">
                                <?php if ($mhs['nilai_huruf']): ?>
                                    <span class="nilai-huruf inline-flex items-center justify-center w-8 h-8 rounded-full font-black shadow-sm <?= $mhs['nilai_huruf'] === 'E' ? 'bg-error-container text-on-error-container' : 'bg-success-container text-on-success-container' ?>"><?= $mhs['nilai_huruf'] ?></span>
                                <?php else: ?>
                                    <span class="nilai-huruf text-on-surface-variant opacity-50">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'components/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('.input-nilai');
    const csrf = document.getElementById('csrf_token') ? document.getElementById('csrf_token').value : '';

    let timeoutId;

    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            saveRowData(this.closest('.row-nilai'));
        });
        
        // save after 1 second of inactivity
        input.addEventListener('input', function() {
            clearTimeout(timeoutId);
            const row = this.closest('.row-nilai');
            timeoutId = setTimeout(() => saveRowData(row), 1000);
        });
    });

    function saveRowData(row) {
        if (!row) return;
        const krsId = row.getAttribute('data-krs');
        const tugas = row.querySelector('[data-type="tugas"]').value;
        const uts = row.querySelector('[data-type="uts"]').value;
        const uas = row.querySelector('[data-type="uas"]').value;
        const praktikum = row.querySelector('[data-type="praktikum"]').value;
        const statusCell = row.querySelector('.status-cell');
        
        // Tampilkan indikator loading
        statusCell.innerHTML = '<span class="material-symbols-outlined animate-spin text-primary">sync</span>';

        fetch('api_input_nilai.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                csrf_token: csrf,
                krs_id: krsId,
                tugas: tugas,
                uts: uts,
                uas: uas,
                praktikum: praktikum
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const isE = data.huruf === 'E';
                const classes = isE ? 'bg-error-container text-on-error-container' : 'bg-success-container text-on-success-container';
                statusCell.innerHTML = `<span class="nilai-huruf inline-flex items-center justify-center w-8 h-8 rounded-full font-black shadow-sm ${classes}" title="Tersimpan">${data.huruf}</span>`;
            } else {
                statusCell.innerHTML = `<span class="text-error" title="${data.message}"><span class="material-symbols-outlined">error</span></span>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            statusCell.innerHTML = `<span class="text-error" title="Gagal koneksi"><span class="material-symbols-outlined">wifi_off</span></span>`;
        });
    }
});
</script>
