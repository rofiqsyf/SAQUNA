<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_mahasiswa') {
        $data = [
            'nim' => $_POST['nim'] ?? '',
            'nama' => $_POST['nama'] ?? '',
            'fakultas' => $_POST['fakultas'] ?? '',
            'program_studi' => $_POST['program_studi'] ?? '',
            'semester' => !empty($_POST['semester']) ? (int)$_POST['semester'] : 1
        ];
        if ($repo->createMahasiswa($data)) {
            $alertMessage = "Mahasiswa berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan mahasiswa. NIM mungkin sudah terdaftar.";
            $alertType = "error";
        }
    } elseif ($_POST['action'] === 'import_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $result = $repo->importMahasiswaFromCSV($_FILES['csv_file']['tmp_name']);
            if ($result['success'] > 0) {
                $alertMessage = "Berhasil mengimpor " . $result['success'] . " data mahasiswa.";
                $alertType = "success";
            } else {
                $alertMessage = "Gagal mengimpor data. " . implode(" ", $result['errors']);
                $alertType = "error";
            }
        } else {
            $alertMessage = "Upload file CSV gagal.";
            $alertType = "error";
        }
    } elseif ($_POST['action'] === 'edit_akademik') {
    $mahasiswaId = (int)$_POST['mahasiswa_id'];
    $dataUpdate = [
        'nama' => $_POST['nama'] ?? '',
        'nim' => $_POST['nim'] ?? '',
        'program_studi' => $_POST['program_studi'] ?? '',
        'fakultas' => $_POST['fakultas'] ?? '',
        'semester' => $_POST['semester'] ?? '',
        'dosen_wali_id' => $_POST['dosen_wali_id'] ?? ''
    ];

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['foto']['tmp_name'];
        $name = basename($_FILES['foto']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        if (in_array($ext, $allowed)) {
            $uploadDir = __DIR__ . '/uploads/foto/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            $newFileName = 'mhs_op_' . time() . '_' . rand(1000,9999) . '.' . $ext;
            if (move_uploaded_file($tmp_name, $uploadDir . $newFileName)) {
                $dataUpdate['foto'] = 'uploads/foto/' . $newFileName;
            }
        }
    }
    
    if ($repo->updateDataAkademikMahasiswa($mahasiswaId, $dataUpdate)) {
        $alertMessage = "Data akademik mahasiswa berhasil diperbarui.";
        $alertType = "success";
    } else {
        $alertMessage = "Gagal memperbarui data akademik.";
        $alertType = "error";
    }
    } elseif ($_POST['action'] === 'delete_mahasiswa') {
        $mahasiswaId = (int)$_POST['mahasiswa_id'];
        if ($repo->deleteMahasiswa($mahasiswaId)) {
            $alertMessage = "Data mahasiswa berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus data mahasiswa.";
            $alertType = "error";
        }
    }
}

$filters = [
    'search' => $_GET['search'] ?? '',
    'fakultas' => $_GET['fakultas'] ?? '',
    'program_studi' => $_GET['program_studi'] ?? '',
    'semester' => $_GET['semester'] ?? ''
];

$mahasiswa = $repo->getAllMahasiswa($filters);
$dosenList = $repo->getAllDosen();
$fakultasList = $repo->getAllFakultas();
$prodiList = $repo->getAllProdi();

$title = "Master Data Mahasiswa";
$current_page = "master_mahasiswa.php";
include 'components/header.php';
?>

<div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-on-surface mb-2">Master Data Mahasiswa</h1>
        <p class="text-on-surface-variant opacity-80">Kelola data induk mahasiswa di sistem SAQUNA.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="btn-primary">
            <span class="material-symbols-outlined text-[18px]">add</span> Tambah Manual
        </button>
        <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="btn-secondary">
            <span class="material-symbols-outlined text-[18px]">upload_file</span> Import CSV
        </button>
        <a href="export_mahasiswa.php?<?= http_build_query($filters) ?>" class="btn-success">
            <span class="material-symbols-outlined text-[18px]">download</span> Export Data
        </a>
    </div>
</div>

<!-- Filter Panel -->
<div class="bg-surface-container-lowest rounded-2xl p-4 mb-6 shadow-sm border border-outline-variant/30">
    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <div class="md:col-span-2">
            <input type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Cari NIM atau Nama..." class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
        </div>
        <div>
            <select name="fakultas" id="filter_fakultas" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none" onchange="updateFilterProdi()">
                <option value="">-- Semua Fakultas --</option>
                <?php foreach($fakultasList as $fak): ?>
                    <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" <?= $filters['fakultas'] === $fak['nama_fakultas'] ? 'selected' : '' ?> data-id="<?= $fak['id'] ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <select name="program_studi" id="filter_prodi" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none" data-selected="<?= htmlspecialchars($filters['program_studi']) ?>">
                <option value="">-- Semua Prodi --</option>
            </select>
        </div>
        <div class="flex gap-2">
            <input type="number" name="semester" value="<?= htmlspecialchars($filters['semester']) ?>" placeholder="Smt" class="w-full bg-white/60 border border-outline-variant/50 rounded-lg px-3 py-2 text-sm text-on-surface focus:ring-2 focus:ring-primary outline-none">
            <button type="submit" class="bg-secondary-container text-on-secondary-container px-3 py-2 rounded-lg hover:bg-secondary hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
            <a href="master_mahasiswa.php" class="bg-surface-variant text-on-surface px-3 py-2 rounded-lg hover:bg-outline-variant transition-colors flex items-center">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </a>
        </div>
    </form>
</div>

<?php if (isset($alertMessage)): ?>
    <div class="p-4 mb-6 rounded-xl border <?= $alertType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
        <p><?= $alertMessage ?></p>
    </div>
<?php endif; ?>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">No</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">NIM / Username</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Lengkap</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Fakultas & Prodi</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Dosen Wali</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($mahasiswa as $m): ?>
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= $no++ ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($m['nim']) ?></strong></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <div class="flex items-center gap-2">
                                <?php if (!empty($m['foto']) && $m['foto'] !== 'assets/default_mhs.png'): ?>
                                    <div class="w-8 h-8 rounded-full overflow-hidden border border-outline-variant/30 flex-shrink-0">
                                        <img src="<?= htmlspecialchars($m['foto']) ?>" alt="Foto" class="w-full h-full object-cover">
                                    </div>
                                <?php else: ?>
                                    <div class="w-8 h-8 rounded-full bg-surface-variant text-on-surface-variant flex items-center justify-center text-xs border border-outline-variant/30 flex-shrink-0">NA</div>
                                <?php endif; ?>
                                <?= htmlspecialchars($m['nama']) ?>
                            </div>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <span class="bg-primary-container text-on-primary-container px-3 py-1 rounded-full font-label-md text-xs"><?= htmlspecialchars($m['program_studi']) ?></span>
                            <div class="mt-1 text-xs text-on-surface-variant">Fak: <?= htmlspecialchars($m['fakultas'] ?? '-') ?></div>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                            <?= htmlspecialchars($m['dosen_wali_nama'] ?? 'Belum ada') ?>
                        </td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openEditModal(<?= $m['id'] ?>, '<?= htmlspecialchars($m['nim'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($m['fakultas'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($m['program_studi'] ?? '', ENT_QUOTES) ?>', <?= $m['semester'] ?? 1 ?>, <?= $m['dosen_wali_id'] ?? 'null' ?>)" class="bg-primary-container text-primary p-2 rounded-lg hover:bg-primary hover:text-white transition-colors" title="Edit Data Akademik">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">edit_square</span>
                                </button>
                                <form method="POST" action="" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data mahasiswa ini? Tindakan ini tidak dapat dibatalkan dan akun login mahasiswa akan terhapus.');">
                                    <input type="hidden" name="action" value="delete_mahasiswa">
                                    <input type="hidden" name="mahasiswa_id" value="<?= $m['id'] ?>">
                                    <button type="submit" class="bg-error-container text-error p-2 rounded-lg hover:bg-error hover:text-white transition-colors" title="Hapus Data">
                                        <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const prodiMasterData = <?= json_encode($prodiList) ?>;

function updateProdiOptions(mhsId) {
    const fakSelect = document.getElementById('fak_' + mhsId);
    const prodiSelect = document.getElementById('prodi_' + mhsId);
    
    // Get the selected faculty option and its data-id
    const selectedFakOption = fakSelect.options[fakSelect.selectedIndex];
    const fakId = selectedFakOption ? selectedFakOption.getAttribute('data-id') : null;
    
    const currentProdi = prodiSelect.getAttribute('data-selected') || '';
    
    // Clear current options
    prodiSelect.innerHTML = '<option value="">-- Pilih Prodi --</option>';
    
    if (fakId) {
        // Filter prodi based on fakultas_id
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

function openEditModal(id, nim, nama, fakultas, prodi, semester, dosenWaliId) {
    document.getElementById('edit_mahasiswa_id').value = id;
    document.getElementById('edit_nim').value = nim;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_semester').value = semester;
    document.getElementById('edit_dosen_wali_id').value = dosenWaliId || '';
    
    // Set fakultas and trigger prodi update
    const fakSelect = document.getElementById('fak_edit');
    for (let i = 0; i < fakSelect.options.length; i++) {
        if (fakSelect.options[i].value === fakultas) {
            fakSelect.selectedIndex = i;
            break;
        }
    }
    
    // Set prodi_selected value
    document.getElementById('prodi_edit').setAttribute('data-selected', prodi);
    
    updateProdiOptions('edit');
    
    document.getElementById('modalEdit').classList.remove('hidden');
}

function updateFilterProdi() {
    const fakSelect = document.getElementById('filter_fakultas');
    const prodiSelect = document.getElementById('filter_prodi');
    
    const selectedFakOption = fakSelect.options[fakSelect.selectedIndex];
    const fakId = selectedFakOption ? selectedFakOption.getAttribute('data-id') : null;
    const currentProdi = prodiSelect.getAttribute('data-selected') || '';
    
    prodiSelect.innerHTML = '<option value="">-- Semua Prodi --</option>';
    
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

// Inisialisasi prodi filter saat halaman dimuat
document.addEventListener('DOMContentLoaded', () => {
    updateFilterProdi();
    
    // Pindahkan modal ke document.body agar tidak terpengaruh stacking context
    const modalTambah = document.getElementById('modalTambah');
    const modalImport = document.getElementById('modalImport');
    const modalEdit = document.getElementById('modalEdit');
    
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalImport) document.body.appendChild(modalImport);
    if (modalEdit) document.body.appendChild(modalEdit);
});
</script>

<!-- Modal Tambah Mahasiswa Manual -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Tambah Mahasiswa Manual</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="add_mahasiswa">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">NIM</label>
                    <input type="text" name="nim" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                    <p class="text-[11px] text-on-surface-variant mt-1">Digunakan sebagai username & password awal.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Fakultas</label>
                    <select name="fakultas" id="fak_add" onchange="updateProdiOptions('add')" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach($fakultasList as $fak): ?>
                            <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" data-id="<?= $fak['id'] ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Program Studi</label>
                    <select name="program_studi" id="prodi_add" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Prodi --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Semester Aktif</label>
                    <input type="number" name="semester" required value="1" min="1" max="14" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import CSV -->
<div id="modalImport" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Import Data Mahasiswa (CSV)</h3>
            <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="import_csv">
            <div class="mb-4">
                <div class="bg-tertiary-container/30 text-on-surface p-4 rounded-xl mb-4 text-sm border border-tertiary-container">
                    <strong>Format CSV yang didukung:</strong><br>
                    Baris 1 (Header): <code>nim, nama, fakultas, program_studi, semester</code><br>
                    Baris 2+: <code>10123, Budi, Fakultas Teknik, Teknik Informatika, 3</code><br>
                    <br>
                    <em>* NIM akan otomatis menjadi password bawaan akun.</em>
                </div>
                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Pilih File (.csv)</label>
                <input type="file" name="csv_file" accept=".csv" required class="w-full file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-primary-container file:text-on-primary-container hover:file:bg-primary hover:file:text-on-primary transition-all bg-surface-container-lowest border border-outline-variant/50 rounded-xl focus:outline-none">
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-tertiary text-on-tertiary hover:bg-tertiary-container hover:text-on-tertiary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[20px]">upload</span> Mulai Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Mahasiswa -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Data Akademik Mahasiswa</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" enctype="multipart/form-data" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="edit_akademik">
            <input type="hidden" name="mahasiswa_id" id="edit_mahasiswa_id">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">NIM / Username</label>
                    <input type="text" name="nim" id="edit_nim" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Nama Lengkap</label>
                    <input type="text" name="nama" id="edit_nama" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Ganti Foto Profil</label>
                    <input type="file" name="foto" accept="image/jpeg, image/png, image/jpg" class="w-full bg-surface border border-outline-variant rounded-xl px-3 py-1.5 text-sm focus:ring-2 focus:ring-primary outline-none">
                    <p class="text-[10px] text-on-surface-variant mt-1">Kosongi jika tidak diubah.</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Fakultas</label>
                    <select name="fakultas" id="fak_edit" onchange="updateProdiOptions('edit')" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach($fakultasList as $fak): ?>
                            <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" data-id="<?= $fak['id'] ?>"><?= htmlspecialchars($fak['nama_fakultas']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Program Studi</label>
                    <select name="program_studi" id="prodi_edit" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Pilih Prodi --</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Semester Aktif</label>
                    <input type="number" name="semester" id="edit_semester" required value="1" min="1" max="14" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Dosen Wali</label>
                    <select name="dosen_wali_id" id="edit_dosen_wali_id" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                        <option value="">-- Kosong --</option>
                        <?php foreach($dosenList as $dsn): ?>
                            <option value="<?= $dsn['id'] ?>"><?= htmlspecialchars($dsn['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">save</span> Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php include 'components/footer.php'; ?>
