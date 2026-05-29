<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Src\Validator;

Auth::requireLogin();
Auth::requireOperator(); // Hanya operator yang bisa tambah dosen

$repo = new DosenRepository();
$operatorRepo = new \Src\OperatorRepository();
$allMk = $repo->getAllMataKuliah();
$fakultasList = $operatorRepo->getAllFakultas();
$prodiList = $operatorRepo->getAllProdi();

$errors = [];
$old = [
    'nidn' => '', 'nama' => '', 'email' => '', 
    'program_studi' => '', 'status' => 'aktif', 'matakuliah' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $errors['csrf'] = 'Token CSRF tidak valid.';
    } else {
        $validator = new Validator($_POST);
        
        $validator->validateRequired('nidn', 'NIDN wajib diisi.');
        $validator->validateLength('nidn', 10, 10, 'NIDN harus 10 karakter.');
        
        $validator->validateRequired('nama', 'Nama wajib diisi.');
        $validator->validateLength('nama', 3, 100, 'Nama harus 3-100 karakter.');
        
        $validator->validateEmail('email', 'Format email tidak valid.');
        
        // $validator->validateInArray('program_studi', ['Teknik Informatika', 'Sistem Informasi', 'Teknik Elektro'], 'Program Studi tidak valid.');
        
        $validator->validateInArray('status', ['aktif', 'nonaktif'], 'Status tidak valid.');

        $fotoName = $validator->validateImageUpload('foto', false); // Foto opsional

        if (!$validator->hasErrors()) {
            $data = [
                'nidn' => $validator->get('nidn'),
                'nama' => $validator->get('nama'),
                'email' => $validator->get('email'),
                'fakultas' => $validator->get('fakultas'),
                'program_studi' => $validator->get('program_studi'),
                'status' => $validator->get('status'),
                'foto' => $fotoName
            ];
            $matakuliahIds = $validator->get('matakuliah') ?? [];

            if ($fotoName) {
                // Gunakan folder public/uploads/foto
                $uploadSuccess = $validator->handleUpload('foto', $fotoName, __DIR__ . '/uploads/foto');
                if (!$uploadSuccess) {
                    $errors['foto'] = 'Gagal menyimpan file foto.';
                } else {
                    // Beri prefix agar konsisten dengan mahasiswa_profile & dosen_profile
                    $data['foto'] = 'uploads/foto/' . $fotoName;
                }
            }

            if (empty($errors)) {
                $success = $repo->create($data, (array)$matakuliahIds, $_SESSION['user_id']);
                if ($success) {
                    header("Location: index.php?msg=created");
                    exit;
                } else {
                    $errors['db'] = 'Gagal menyimpan ke database. NIDN/Email mungkin sudah terdaftar.';
                }
            }
        } else {
            $errors = array_merge($errors, $validator->getErrors());
        }

        // Isi old data
        $old = array_merge($old, $_POST);
        $old['matakuliah'] = $_POST['matakuliah'] ?? [];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Dosen - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="container animate-fade-in">
    <div class="d-flex justify-between align-center mb-4">
        <h1>Tambah Dosen Baru</h1>
        <a href="index.php" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center">Kembali</a>
    </div>

    <?php if (isset($errors['csrf'])): ?>
        <div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= $errors['csrf'] ?></div>
    <?php endif; ?>
    <?php if (isset($errors['db'])): ?>
        <div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= $errors['db'] ?></div>
    <?php endif; ?>

    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <form method="POST" action="" enctype="multipart/form-data">
            <?= Auth::csrfField() ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="nidn">NIDN (10 digit)</label>
                    <input type="text" id="nidn" name="nidn" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars((string)$old['nidn']) ?>">
                    <?php if (isset($errors['nidn'])): ?><div class="text-danger mt-2"><?= $errors['nidn'] ?></div><?php endif; ?>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="nama">Nama Lengkap</label>
                    <input type="text" id="nama" name="nama" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars((string)$old['nama']) ?>">
                    <?php if (isset($errors['nama'])): ?><div class="text-danger mt-2"><?= $errors['nama'] ?></div><?php endif; ?>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="email">Email</label>
                    <input type="email" id="email" name="email" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars((string)$old['email']) ?>">
                    <?php if (isset($errors['email'])): ?><div class="text-danger mt-2"><?= $errors['email'] ?></div><?php endif; ?>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="fakultas">Fakultas</label>
                    <select id="fakultas" name="fakultas" onchange="updateProdiOptions()" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant">
                        <option value="">-- Pilih Fakultas --</option>
                        <?php foreach($fakultasList as $fak): ?>
                            <option value="<?= htmlspecialchars($fak['nama_fakultas']) ?>" <?= ($old['fakultas'] ?? '') == $fak['nama_fakultas'] ? 'selected' : '' ?> data-id="<?= $fak['id'] ?>">
                                <?= htmlspecialchars($fak['nama_fakultas']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="program_studi">Program Studi</label>
                    <select id="program_studi" name="program_studi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" data-selected="<?= htmlspecialchars($old['program_studi'] ?? '') ?>">
                        <option value="">-- Pilih Prodi --</option>
                    </select>
                    <?php if (isset($errors['program_studi'])): ?><div class="text-danger mt-2"><?= $errors['program_studi'] ?></div><?php endif; ?>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="status">Status</label>
                    <select id="status" name="status" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant">
                        <option value="aktif" <?= $old['status'] === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= $old['status'] === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <?php if (isset($errors['status'])): ?><div class="text-danger mt-2"><?= $errors['status'] ?></div><?php endif; ?>
                </div>
                <div class="mb-stack-sm">
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="foto">Foto Profil (JPG/PNG/WebP, max 2MB)</label>
                    <input type="file" id="foto" name="foto" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" accept="image/jpeg,image/png,image/webp">
                    <?php if (isset($errors['foto'])): ?><div class="text-danger mt-2"><?= $errors['foto'] ?></div><?php endif; ?>
                </div>
            </div>

            <div class="form-group mt-4">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="matakuliah">Mata Kuliah yang Diampu (Tahan CTRL/CMD untuk multi-select)</label>
                <select id="matakuliah" name="matakuliah[]" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" multiple style="min-height: 150px;">
                    <?php foreach ($allMk as $mk): ?>
                        <option value="<?= $mk['id'] ?>" <?= in_array($mk['id'], (array)$old['matakuliah']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama'] . ' (' . $mk['sks'] . ' SKS)') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mt-4">
                <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center">Simpan Dosen</button>
            </div>
        </form>
    </div>
</div>

<script>
const prodiMasterData = <?= json_encode($prodiList) ?>;

function updateProdiOptions() {
    const fakSelect = document.getElementById('fakultas');
    const prodiSelect = document.getElementById('program_studi');
    
    if(!fakSelect || !prodiSelect) return;

    const selectedFakOption = fakSelect.options[fakSelect.selectedIndex];
    const fakId = selectedFakOption && selectedFakOption.value !== '' ? selectedFakOption.getAttribute('data-id') : null;
    
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

document.addEventListener('DOMContentLoaded', () => {
    updateProdiOptions();
});
</script>

<?php include 'components/footer.php'; ?>

