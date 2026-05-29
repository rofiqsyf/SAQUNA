<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireLogin();
$userId = (int)$_SESSION['user_id'];
$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validasi CSRF
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $alertMessage = "Token keamanan tidak valid. Silakan muat ulang halaman.";
        $alertType = "error";
    } else {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $alertMessage = "Semua kolom wajib diisi.";
        $alertType = "error";
    } elseif ($newPassword !== $confirmPassword) {
        $alertMessage = "Konfirmasi password baru tidak cocok.";
        $alertType = "error";
    } elseif (strlen($newPassword) < 6) {
        $alertMessage = "Password baru minimal 6 karakter.";
        $alertType = "error";
    } else {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($oldPassword, $user['password_hash'])) {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($update->execute([$newHash, $userId])) {
                $alertMessage = "Password berhasil diubah. Silakan gunakan password baru pada login berikutnya.";
                $alertType = "success";
                Auth::logActivity($userId, 'update', 'system', $userId, 'User mengganti password', $pdo);
            } else {
                $alertMessage = "Terjadi kesalahan sistem, gagal mengubah password.";
                $alertType = "error";
            }
        } else {
            $alertMessage = "Password lama yang Anda masukkan salah.";
            $alertType = "error";
        }
        }
    } // end CSRF check
}

$title = "Ganti Password - SAQUNA";
include 'components/header.php';
?>

<main class="flex-1 ml-0 lg:ml-72 mt-16 lg:mt-0 min-h-screen bg-surface p-stack-sm md:p-stack-lg transition-all duration-300 relative z-10 font-sans">
    <div class="max-w-2xl mx-auto">
        <header class="mb-stack-lg animate-slide-up">
            <h1 class="font-display-md text-display-md text-on-surface">Ganti Password</h1>
            <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">Perbarui kata sandi akun Anda secara berkala untuk menjaga keamanan data.</p>
        </header>

        <?php if ($alertMessage): ?>
            <div class="p-4 mb-6 rounded-xl border <?= $alertType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> flex items-center gap-3 animate-fade-in">
                <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
                <p><?= $alertMessage ?></p>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-3xl p-stack-md shadow-sm border border-outline-variant">
            <form method="POST" action="">
                <?= Auth::csrfField() ?>
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-2">Password Lama</label>
                        <input type="password" name="old_password" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" required>
                    </div>
                    <hr class="border-outline-variant/30">
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-2">Password Baru</label>
                        <input type="password" name="new_password" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" required>
                        <p class="text-xs text-on-surface-variant mt-1">Minimal 6 karakter.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-on-surface-variant mb-2">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password" class="w-full bg-surface border border-outline-variant rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all" required>
                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-outline-variant flex justify-end">
                    <button type="submit" class="bg-primary text-on-primary hover:bg-on-primary-fixed-variant px-8 py-3 rounded-xl font-label-lg shadow-md transition-all flex items-center gap-2">
                        <span class="material-symbols-outlined">key</span> Simpan Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
