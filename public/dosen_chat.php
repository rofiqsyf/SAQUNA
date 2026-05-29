<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

$repo = new DosenRepository();
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

        if ($penerimaId > 0 && $subjek !== '' && $pesan !== '') {
            if ($repo->kirimPesan($userId, $penerimaId, $subjek, $pesan)) {
                $success = "Pesan berhasil dikirim!";
            } else {
                $error = "Gagal mengirim pesan.";
            }
        } else {
            $error = "Semua field harus diisi dengan benar.";
        }
    }
}

$pesanMasuk = $repo->getPesanMasuk($userId);
$daftarKontak = $repo->getKontakPenerima();

$title = "Pesan & Tanya Jawab";
$current_page = "dosen_chat.php";
include 'components/header.php';
?>

<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h1>Kotak Masuk (Inbox)</h1>
        <p class="text-on-surface-variant opacity-80">Baca pesan dari Mahasiswa atau Operator, dan kirimkan balasan.</p>
    </div>
    <button class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center" onclick="document.getElementById('formPesanBaru').style.display = 'block'">+ Tulis Pesan</button>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Formulir Pesan Baru -->
<div id="formPesanBaru" class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40" style="display: none; border-top: 4px solid var(--primary);">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <h3 class="mt-0">Tulis Pesan Baru</h3>
        <form method="POST">
            <?= Auth::csrfField() ?>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Kirim Ke (Operator / Mahasiswa)</label>
                <select name="penerima_id" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
                    <option value="">-- Pilih Penerima --</option>
                    <?php foreach ($daftarKontak as $kontak): ?>
                        <option value="<?= $kontak['id'] ?>">
                            [<?= strtoupper(htmlspecialchars($kontak['role'])) ?>] <?= htmlspecialchars($kontak['username']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Subjek</label>
                <input type="text" name="subjek" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" placeholder="Contoh: Balasan Tugas / Konfirmasi Nilai" required>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Isi Pesan</label>
                <textarea name="pesan" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" rows="5" placeholder="Tuliskan pesan Anda di sini..." required></textarea>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center">Kirim Pesan</button>
                <button type="button" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center" onclick="document.getElementById('formPesanBaru').style.display = 'none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Daftar Pesan Masuk -->
<?php if (empty($pesanMasuk)): ?>
    <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Kotak masuk Anda kosong. Belum ada pesan masuk.</div>
<?php else: ?>
    <div class="row" style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
        <?php foreach ($pesanMasuk as $p): ?>
        <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40" style="border-left: 4px solid <?= $p['is_read'] ? 'var(--text-muted)' : 'var(--primary)' ?>;">
            <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
                <div class="d-flex justify-content-between mb-2">
                    <h4 style="margin: 0;"><?= htmlspecialchars($p['subjek']) ?></h4>
                    <span class="text-on-surface-variant opacity-80" style="font-size: 0.85rem;"><?= date('d M Y, H:i', strtotime($p['waktu_kirim'])) ?></span>
                </div>
                <div class="mb-3">
                    <span class="badge badge-<?= $p['pengirim_role'] === 'operator' ? 'warning' : 'primary' ?>">
                        Dari: <?= strtoupper(htmlspecialchars($p['pengirim_role'])) ?>
                    </span>
                    <strong style="margin-left: 8px;"><?= htmlspecialchars($p['pengirim_nama']) ?></strong>
                </div>
                <p style="background: var(--bg-light); padding: 1rem; border-radius: 6px; margin: 0; font-family: monospace;">
                    <?= nl2br(htmlspecialchars($p['pesan'])) ?>
                </p>
                
                <div class="mt-3">
                    <button class="btn btn-sm btn-secondary" onclick="
                        document.getElementById('formPesanBaru').style.display = 'block';
                        document.querySelector('[name=penerima_id]').value = '<?= $p['pengirim_user_id'] ?>';
                        document.querySelector('[name=subjek]').value = 'Re: <?= addslashes(htmlspecialchars($p['subjek'])) ?>';
                        window.scrollTo(0, 0);
                    ">Balas Pesan</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include 'components/footer.php'; ?>
