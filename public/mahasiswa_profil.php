<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$mhs = $repo->getMahasiswaByUserId($_SESSION['user_id']);

if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $alamat = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');
        $domisili = trim($_POST['domisili'] ?? '');
        
        if ($repo->updateProfil((int)$mhs['id'], $alamat, $no_telp, $domisili)) {
            $success = "Profil berhasil diperbarui!";
            // Update local variable
            $mhs['alamat'] = $alamat;
            $mhs['no_telp'] = $no_telp;
            $mhs['domisili'] = $domisili;
        } else {
            $error = "Gagal memperbarui profil.";
        }
    }
}

$title = "Profil Mahasiswa";
$current_page = "mahasiswa_profil.php";
include 'components/header.php';
?>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <h2 style="margin: 0;">Pengaturan Profil Mahasiswa</h2>
    </div>
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST" action="">
            <?= Auth::csrfField() ?>
            
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">NIM</label>
                <input type="text" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars($mhs['nim']) ?>" disabled>
            </div>
            
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Nama Lengkap</label>
                <input type="text" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars($mhs['nama']) ?>" disabled>
            </div>

            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Program Studi</label>
                <input type="text" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars($mhs['program_studi']) ?>" disabled>
            </div>
            
            <hr style="margin: 2rem 0; border: none; border-top: 1px solid #ddd;">
            <h4>Data Kontak Pribadi</h4>
            <p class="text-on-surface-variant opacity-80">Perbarui data kontak Anda di bawah ini agar mudah dihubungi oleh dosen/staf kampus.</p>

            <div class="form-group mt-3">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="no_telp">Nomor Telepon / WhatsApp</label>
                <input type="text" id="no_telp" name="no_telp" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars($mhs['no_telp'] ?? '') ?>" placeholder="081234567890">
            </div>

            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="domisili">Domisili Saat Ini (Kota/Kabupaten)</label>
                <input type="text" id="domisili" name="domisili" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="<?= htmlspecialchars($mhs['domisili'] ?? '') ?>" placeholder="Kota Bandung">
            </div>

            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2" for="alamat">Alamat Lengkap</label>
                <textarea id="alamat" name="alamat" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" rows="3" placeholder="Jl. Contoh No. 123..."><?= htmlspecialchars($mhs['alamat'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-lg mt-2">Simpan Perubahan</button>
        </form>
    </div>
</div>

<?php include 'components/footer.php'; ?>
