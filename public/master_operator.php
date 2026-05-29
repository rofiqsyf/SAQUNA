<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();
$currentUser = [
    'id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Validasi CSRF untuk semua aksi POST
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $alertMessage = "Token keamanan tidak valid. Silakan muat ulang halaman.";
        $alertType = "error";
    } else {
        if ($_POST['action'] === 'add_operator') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username && $password) {
                if ($repo->createOperator($username, $password)) {
                    $alertMessage = "Operator berhasil ditambahkan.";
                    $alertType = "success";
                } else {
                    $alertMessage = "Gagal menambahkan operator. Username mungkin sudah digunakan.";
                    $alertType = "error";
                }
            } else {
                $alertMessage = "Username dan Password wajib diisi.";
                $alertType = "error";
            }
        } elseif ($_POST['action'] === 'edit_operator') {
            $id = (int)$_POST['operator_id'];
            $username = trim($_POST['username'] ?? '');
            $password = !empty($_POST['password']) ? $_POST['password'] : null;

            if ($username) {
                if ($repo->updateOperator($id, $username, $password)) {
                    $alertMessage = "Operator berhasil diperbarui.";
                    $alertType = "success";
                } else {
                    $alertMessage = "Gagal memperbarui operator. Username mungkin sudah digunakan.";
                    $alertType = "error";
                }
            } else {
                $alertMessage = "Username wajib diisi.";
                $alertType = "error";
            }
        } elseif ($_POST['action'] === 'delete_operator') {
            $id = (int)$_POST['operator_id'];
            if ($id === (int)$currentUser['id']) {
                $alertMessage = "Anda tidak dapat menghapus akun Anda sendiri.";
                $alertType = "error";
            } else {
                if ($repo->deleteOperator($id)) {
                    $alertMessage = "Operator berhasil dihapus.";
                    $alertType = "success";
                } else {
                    $alertMessage = "Gagal menghapus operator.";
                    $alertType = "error";
                }
            }
        }
    } // end CSRF validation
}

$operators = $repo->getAllOperators();

$title = "Manajemen Operator";
$current_page = "master_operator.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1 class="text-3xl font-bold text-on-surface mb-2">Manajemen Operator</h1>
        <p class="text-on-surface-variant opacity-80">Kelola akun akses tingkat admin/operator.</p>
    </div>
    <div>
        <button onclick="document.getElementById('modalTambah').classList.remove('hidden')" class="btn-primary">
            <span class="material-symbols-outlined text-[18px]">add_circle</span> Tambah Operator
        </button>
    </div>
</div>

<?php if (isset($alertMessage)): ?>
    <div class="p-4 mb-6 rounded-xl border <?= $alertType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800' ?> flex items-center gap-3">
        <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
        <p><?= $alertMessage ?></p>
    </div>
<?php endif; ?>

<div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
    <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">No</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Username</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Tanggal Dibuat</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($operators as $op): ?>
                <tr class="hover:bg-surface-container-low/50 transition-colors">
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= $no++ ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">admin_panel_settings</span>
                            <strong><?= htmlspecialchars($op['username']) ?></strong>
                            <?php if ($op['id'] == $currentUser['id']): ?>
                                <span class="bg-primary-container text-on-primary-container px-2 py-0.5 rounded text-[10px] ml-2 font-bold uppercase">Anda</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= date('d M Y H:i', strtotime($op['created_at'])) ?></td>
                    <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="openEditModal(<?= $op['id'] ?>, '<?= htmlspecialchars($op['username'], ENT_QUOTES) ?>')" class="bg-primary-container text-primary p-2 rounded-lg hover:bg-primary hover:text-white transition-colors" title="Edit Operator">
                                <span class="material-symbols-outlined" style="font-size: 18px;">edit_square</span>
                            </button>
                            <?php if ($op['id'] != $currentUser['id']): ?>
                            <form method="POST" action="" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun operator ini? Tindakan ini bersifat permanen.');">
                <input type="hidden" name="action" value="delete_operator">
                <input type="hidden" name="operator_id" value="<?= $op['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrf() ?>">

                                <button type="submit" class="bg-error-container text-error p-2 rounded-lg hover:bg-error hover:text-white transition-colors" title="Hapus Operator">
                                    <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                                </button>
                            </form>
                            <?php else: ?>
                            <button disabled class="bg-surface-variant text-on-surface-variant p-2 rounded-lg opacity-50 cursor-not-allowed" title="Tidak dapat menghapus diri sendiri">
                                <span class="material-symbols-outlined" style="font-size: 18px;">delete</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openEditModal(id, username) {
    document.getElementById('edit_operator_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('modalEdit').classList.remove('hidden');
}
</script>

<!-- Modal Tambah -->
<div id="modalTambah" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-md mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Tambah Operator</h3>
            <button onclick="document.getElementById('modalTambah').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="add_operator">
            <?= Auth::csrfField() ?>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Username</label>
                    <input type="text" name="username" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Password</label>
                    <input type="password" name="password" required minlength="4" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalTambah').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-md mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Operator</h3>
            <button onclick="document.getElementById('modalEdit').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="edit_operator">
            <?= Auth::csrfField() ?>

            <input type="hidden" name="operator_id" id="edit_operator_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Username</label>
                    <input type="text" name="username" id="edit_username" required class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-on-surface-variant mb-1">Password Baru (Opsional)</label>
                    <input type="password" name="password" minlength="4" placeholder="Kosongi jika tidak diubah" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 focus:ring-2 focus:ring-primary outline-none">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalEdit').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalTambah = document.getElementById('modalTambah');
    const modalEdit = document.getElementById('modalEdit');
    if (modalTambah) document.body.appendChild(modalTambah);
    if (modalEdit) document.body.appendChild(modalEdit);
});
</script>

<?php include 'components/footer.php'; ?>
