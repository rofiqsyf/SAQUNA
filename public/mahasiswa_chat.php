<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();

$repo = new MahasiswaRepository();
$userId = $_SESSION['user_id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $penerimaId = (int)$_POST['penerima_id'];
        $subjek = trim($_POST['subjek']);
        $pesan = trim($_POST['pesan']);
        
        if ($repo->kirimPesan($userId, $penerimaId, $subjek, $pesan)) {
            $success = "Pesan berhasil dikirim!";
        } else {
            $error = "Gagal mengirim pesan.";
        }
    }
}

$inbox = $repo->getPesanMasuk($userId);
$kontak = $repo->getKontakPenerima();

$title = "Tanya Jawab / Pesan";
$current_page = "mahasiswa_chat.php";
include 'components/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1>Layanan Tanya Jawab</h1>
        <p class="text-on-surface-variant opacity-80">Kirim tiket pertanyaan ke Dosen atau Operator.</p>
    </div>
    <button class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center" onclick="document.getElementById('formPesan').style.display = 'block'">+ Buat Pesan Baru</button>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div id="formPesan" class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40" style="display: none; border-top: 4px solid var(--primary);">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <h3 class="mt-0">Tulis Pesan Baru</h3>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Tujuan (Penerima)</label>
                <select name="penerima_id" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
                    <option value="">-- Pilih Dosen / Operator --</option>
                    <?php foreach ($kontak as $k): ?>
                        <option value="<?= $k['id'] ?>">[<?= strtoupper($k['role']) ?>] <?= htmlspecialchars($k['username']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Subjek</label>
                <input type="text" name="subjek" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required placeholder="Contoh: Pertanyaan seputar KRS">
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Isi Pesan</label>
                <textarea name="pesan" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" rows="4" required placeholder="Tuliskan pertanyaan Anda..."></textarea>
            </div>
            <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center">Kirim Pesan</button>
            <button type="button" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center" onclick="document.getElementById('formPesan').style.display = 'none'">Batal</button>
        </form>
    </div>
</div>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <h3 style="margin: 0;">Kotak Masuk (Inbox)</h3>
    </div>
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <?php if (empty($inbox)): ?>
            <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Kotak masuk Anda kosong.</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($inbox as $msg): ?>
                <div style="border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; background: <?= $msg['is_read'] ? '#f9f9f9' : '#fff' ?>; border-left: 4px solid <?= $msg['is_read'] ? '#ccc' : 'var(--primary)' ?>;">
                    <div class="flex justify-between items-center mb-2">
                        <span style="font-weight: 600; font-size: 1.1rem;"><?= htmlspecialchars($msg['pengirim_nama']) ?> <span class="badge badge-secondary" style="font-size: 0.75rem;"><?= strtoupper($msg['pengirim_role']) ?></span></span>
                        <span class="text-on-surface-variant opacity-80" style="font-size: 0.85rem;"><?= date('d M Y, H:i', strtotime($msg['waktu_kirim'])) ?></span>
                    </div>
                    <h4 style="margin: 0 0 0.5rem 0;"><?= htmlspecialchars($msg['subjek']) ?></h4>
                    <p style="margin: 0; color: #555;"><?= nl2br(htmlspecialchars($msg['pesan'])) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'components/footer.php'; ?>
