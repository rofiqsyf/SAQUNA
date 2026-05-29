<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();
$repo = new MahasiswaRepository();
$userId = $_SESSION['user_id'];
$mhs = $repo->getMahasiswaProfile($userId);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$_SESSION['nama_lengkap'] = $mhs['nama'];

$alertMessage = '';
$alertType = '';

// Handle Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dataUpdate = [
        'nama' => $_POST['nama'] ?? $mhs['nama'],
        'tempat_tanggal_lahir' => $_POST['tempat_tanggal_lahir'] ?? '',
        'alamat_asal' => $_POST['alamat_asal'] ?? '',
        'domisili' => $_POST['domisili'] ?? '',
        'email' => $_POST['email'] ?? '',
        'no_hp' => $_POST['no_hp'] ?? '',
        'jenis_kelamin' => $_POST['jenis_kelamin'] ?? ''
    ];

    if ($repo->updateBiodata($mhs['id'], $dataUpdate)) {
        
        // Handle upload foto
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['foto']['tmp_name'];
            $name = basename($_FILES['foto']['name']);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/uploads/foto/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $newFileName = 'mhs_' . $mhs['nim'] . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;
                if (move_uploaded_file($tmp_name, $targetPath)) {
                    $repo->updateFoto($mhs['id'], 'uploads/foto/' . $newFileName);
                }
            }
        }

        $alertMessage = "Biodata berhasil diperbarui!";
        $alertType = 'success';
        // Refresh data
        $mhs = $repo->getMahasiswaProfile($userId);
        $_SESSION['nama_lengkap'] = $mhs['nama'];
    } else {
        $alertMessage = "Gagal memperbarui biodata. Silakan coba lagi.";
        $alertType = 'error';
    }
}

// Include header first to get generateSvgAvatar function if needed
$title = "Profil Mahasiswa";
$current_page = "mahasiswa_profile.php";
include 'components/header.php';

$foto = !empty($mhs['foto']) ? $mhs['foto'] : generateSvgAvatar($mhs['nama']);
$title = "Profil Mahasiswa - SAQUNA";
?>

<main class="flex-1 ml-0 lg:ml-72 mt-16 lg:mt-0 min-h-screen bg-surface p-stack-sm md:p-stack-lg transition-all duration-300 relative z-10 font-sans">
    <div class="max-w-7xl mx-auto">
        <header class="mb-stack-lg animate-slide-up">
            <h1 class="font-display-md text-display-md text-on-surface">Profil & Biodata</h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">Kelola data diri dan informasi kontak Anda di sini.</p>
        </header>

        <?php if ($alertMessage): ?>
            <div class="p-4 mb-6 rounded-xl border <?= $alertType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> flex items-center gap-3 animate-fade-in">
                <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
                <p><?= $alertMessage ?></p>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-stack-md">
            
            <!-- Kiri: Informasi Akademik (Readonly) -->
            <section class="lg:col-span-1">
                <div class="bg-white rounded-3xl p-stack-md shadow-sm border border-outline-variant h-full relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-bl-full pointer-events-none group-hover:scale-110 transition-transform"></div>
                    
                    <div class="text-center mb-6 relative z-10">
                        <div class="w-32 h-32 mx-auto rounded-full border-4 border-white shadow-lg overflow-hidden mb-4 bg-gray-100">
                            <img src="<?= htmlspecialchars($foto) ?>" alt="Foto" class="w-full h-full object-cover">
                        </div>
                        <h2 class="font-title-lg text-title-lg text-on-surface font-bold"><?= htmlspecialchars($mhs['nama']) ?></h2>
                        <p class="font-body-md text-primary mt-1 font-mono tracking-wider"><?= htmlspecialchars($mhs['nim']) ?></p>
                        <span class="inline-block mt-3 px-3 py-1 bg-secondary-container text-on-secondary-container rounded-full text-xs font-semibold">Mahasiswa Aktif</span>
                    </div>

                    <hr class="border-outline-variant mb-6">

                    <div class="space-y-4 relative z-10">
                        <h3 class="font-title-md font-semibold text-on-surface flex items-center gap-2 mb-4">
                            <span class="material-symbols-outlined text-primary">school</span> Data Akademik
                        </h3>
                        
                        <!-- Readonly Fields -->
                        <div class="bg-surface p-3 rounded-xl border border-outline-variant/50">
                            <p class="text-xs text-on-surface-variant mb-1 uppercase tracking-wide font-semibold">Program Studi</p>
                            <p class="font-body-md text-on-surface font-medium"><?= htmlspecialchars($mhs['program_studi'] ?? '-') ?></p>
                        </div>
                        
                        <div class="bg-surface p-3 rounded-xl border border-outline-variant/50">
                            <p class="text-xs text-on-surface-variant mb-1 uppercase tracking-wide font-semibold">Fakultas</p>
                            <p class="font-body-md text-on-surface font-medium"><?= htmlspecialchars($mhs['fakultas'] ?? 'Belum diset') ?></p>
                        </div>
                        
                        <div class="bg-surface p-3 rounded-xl border border-outline-variant/50">
                            <p class="text-xs text-on-surface-variant mb-1 uppercase tracking-wide font-semibold">Semester Aktif</p>
                            <p class="font-body-md text-on-surface font-medium"><?= htmlspecialchars($mhs['semester'] ?? 'Belum diset') ?></p>
                        </div>

                        <div class="bg-surface p-3 rounded-xl border border-outline-variant/50 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-primary-container text-primary flex items-center justify-center flex-shrink-0">
                                <span class="material-symbols-outlined">person_pin</span>
                            </div>
                            <div>
                                <p class="text-xs text-on-surface-variant mb-1 uppercase tracking-wide font-semibold">Dosen Wali</p>
                                <p class="font-body-md text-on-surface font-bold"><?= htmlspecialchars($mhs['dosen_wali_nama'] ?? 'Belum ditunjuk') ?></p>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-secondary/10 rounded-lg text-xs text-secondary-container-on flex gap-2 items-start">
                            <span class="material-symbols-outlined text-[16px]">info</span>
                            <p>Data akademik di atas hanya dapat diubah oleh Operator atau Bagian Akademik.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Kanan: Form Biodata -->
            <section class="lg:col-span-2">
                <div class="bg-white rounded-3xl p-stack-md shadow-sm border border-outline-variant h-full">
                    <form method="POST" action="" enctype="multipart/form-data" class="h-full flex flex-col">
                        <h3 class="font-title-lg font-bold text-on-surface flex items-center gap-2 mb-6">
                            <span class="material-symbols-outlined text-primary">manage_accounts</span> Edit Biodata Diri
                        </h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 flex-1">
                            
                            <!-- Foto Profil -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Ganti Foto Profil</label>
                                <input type="file" name="foto" accept="image/jpeg, image/png, image/jpg" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                <p class="text-xs text-on-surface-variant mt-1">Format: JPG, JPEG, PNG.</p>
                            </div>

                            <!-- Nama Lengkap -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Nama Lengkap</label>
                                <input type="text" name="nama" value="<?= htmlspecialchars($mhs['nama']) ?>" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" required>
                            </div>

                            <!-- Tempat, Tanggal Lahir -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Tempat, Tanggal Lahir</label>
                                <input type="text" name="tempat_tanggal_lahir" value="<?= htmlspecialchars($mhs['tempat_tanggal_lahir'] ?? '') ?>" placeholder="Cth: Malang, 17 Agustus 2002" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                            </div>

                            <!-- Jenis Kelamin -->
                            <div>
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Jenis Kelamin</label>
                                <select name="jenis_kelamin" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                                    <option value="" <?= empty($mhs['jenis_kelamin']) ? 'selected' : '' ?>>-- Pilih --</option>
                                    <option value="Laki-laki" <?= ($mhs['jenis_kelamin'] ?? '') == 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="Perempuan" <?= ($mhs['jenis_kelamin'] ?? '') == 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>

                            <!-- No HP -->
                            <div>
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Nomor HP (WhatsApp)</label>
                                <input type="text" name="no_hp" value="<?= htmlspecialchars($mhs['no_hp'] ?? '') ?>" placeholder="Cth: 081234567890" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                            </div>

                            <!-- Email -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Alamat Email Pribadi</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($mhs['email'] ?? '') ?>" placeholder="Cth: nama@gmail.com" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all">
                            </div>

                            <!-- Alamat Asal -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Alamat Asal (Sesuai KTP)</label>
                                <textarea name="alamat_asal" rows="2" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"><?= htmlspecialchars($mhs['alamat_asal'] ?? '') ?></textarea>
                            </div>

                            <!-- Domisili -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-semibold text-on-surface-variant mb-2">Alamat Domisili (Kos / Tinggal saat ini)</label>
                                <textarea name="domisili" rows="2" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all"><?= htmlspecialchars($mhs['domisili'] ?? '') ?></textarea>
                            </div>

                        </div>

                        <div class="mt-8 pt-6 border-t border-outline-variant flex justify-end">
                            <button type="submit" class="bg-primary text-on-primary hover:bg-on-primary-fixed-variant px-8 py-3 rounded-xl font-label-lg shadow-md transition-all flex items-center gap-2">
                                <span class="material-symbols-outlined">save</span> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
