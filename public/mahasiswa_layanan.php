<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireMahasiswa();

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;
$error = '';
$success = '';

// Ambil data mahasiswa
$stmt = $pdo->prepare("SELECT id, nim, nama, program_studi FROM mahasiswa WHERE user_id = ?");
$stmt->execute([$userId]);
$mhs = $stmt->fetch();
$mahasiswaId = $mhs['id'];

// Proses Pengajuan Surat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajukan') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $jenisSurat = $_POST['jenis_surat'] ?? '';
        $keperluan = trim($_POST['keperluan'] ?? '');

        if (empty($jenisSurat) || empty($keperluan)) {
            $error = "Jenis surat dan keperluan wajib diisi.";
        } else {
            $stmtInsert = $pdo->prepare("INSERT INTO layanan_surat (mahasiswa_id, jenis_surat, keperluan, status) VALUES (?, ?, ?, 'Pending')");
            if ($stmtInsert->execute([$mahasiswaId, $jenisSurat, $keperluan])) {
                Auth::logActivity($userId, 'create', 'layanan_surat', $pdo->lastInsertId(), "Mengajukan surat $jenisSurat", $pdo);
                $success = "Pengajuan surat berhasil dikirim. Menunggu proses admin.";
            } else {
                $error = "Gagal mengajukan surat.";
            }
        }
    }
}

// Ambil riwayat pengajuan surat
$stmtRiwayat = $pdo->prepare("SELECT * FROM layanan_surat WHERE mahasiswa_id = ? ORDER BY created_at DESC");
$stmtRiwayat->execute([$mahasiswaId]);
$riwayat = $stmtRiwayat->fetchAll();

$current_page = 'mahasiswa_layanan.php';
$page_title = 'Layanan Administrasi';
$username = $mhs['nama'] ?? 'Mahasiswa';
$role = 'mahasiswa';
$unreadCount = 0; // Simplified for this context

require_once __DIR__ . '/components/header.php';
?>

<div class="grid grid-cols-12 gap-stack-lg items-start">
    <!-- Header Section -->
    <header class="col-span-12 flex items-center justify-between mb-2">
        <div>
            <h2 class="font-headline-lg text-headline-lg text-primary font-black">Layanan Administrasi</h2>
            <p class="font-body-md text-on-surface-variant mt-1">Ajukan dokumen akademik dengan cepat melalui sistem satu pintu.</p>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="document.getElementById('modalAjukan').classList.remove('hidden')" class="bg-primary text-on-primary px-6 py-3 rounded-xl font-title-lg flex items-center gap-2 shadow-lg shadow-primary/20 hover:shadow-xl hover:scale-[1.02] active:scale-95 transition-all">
                <span class="material-symbols-outlined">add</span>
                Pengajuan Baru
            </button>
        </div>
    </header>

    <!-- Alerts -->
    <?php if ($success || $error): ?>
    <section class="col-span-12">
        <?php if ($success): ?>
        <div class="bg-success-container text-on-success-container p-4 rounded-2xl font-bold flex items-center gap-3 shadow-sm border border-success/20 mb-4">
            <span class="material-symbols-outlined text-2xl">check_circle</span> <?= $success ?>
        </div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="bg-error-container text-on-error-container p-4 rounded-2xl font-bold flex items-center gap-3 shadow-sm border border-error/20 mb-4">
            <span class="material-symbols-outlined text-2xl">error</span> <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Services Column -->
    <section class="col-span-12 lg:col-span-7 space-y-stack-md">
        <div class="flex justify-between items-center px-2">
            <h3 class="font-title-lg text-title-lg text-primary">Daftar Layanan Tersedia</h3>
        </div>
        <div class="grid grid-cols-1 gap-4">
            <!-- Service Card 1 -->
            <div class="glass-panel p-6 rounded-xl flex flex-col sm:flex-row items-center justify-between group hover:bg-white/80 transition-all duration-300 gap-4">
                <div class="flex items-center gap-5 w-full">
                    <div class="w-14 h-14 bg-secondary-container rounded-xl flex items-center justify-center text-on-secondary-container shadow-inner flex-shrink-0">
                        <span class="material-symbols-outlined text-[28px]">description</span>
                    </div>
                    <div>
                        <p class="font-body-lg font-bold text-on-surface leading-tight">Surat Keterangan Aktif Kuliah</p>
                        <p class="font-body-sm text-on-surface-variant mt-1 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">schedule</span> Estimasi: 1-2 Hari Kerja
                        </p>
                    </div>
                </div>
                <button onclick="openAjukanModal('Surat Keterangan Aktif Kuliah')" class="w-full sm:w-auto bg-primary-container text-on-primary-container px-6 py-2.5 rounded-lg font-label-md hover:bg-primary hover:text-on-primary transition-all shadow-sm whitespace-nowrap">
                    Ajukan
                </button>
            </div>
            
            <!-- Service Card 2 -->
            <div class="glass-panel p-6 rounded-xl flex flex-col sm:flex-row items-center justify-between group hover:bg-white/80 transition-all duration-300 gap-4">
                <div class="flex items-center gap-5 w-full">
                    <div class="w-14 h-14 bg-secondary-container rounded-xl flex items-center justify-center text-on-secondary-container shadow-inner flex-shrink-0">
                        <span class="material-symbols-outlined text-[28px]">event_busy</span>
                    </div>
                    <div>
                        <p class="font-body-lg font-bold text-on-surface leading-tight">Surat Keterangan Cuti</p>
                        <p class="font-body-sm text-on-surface-variant mt-1 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">schedule</span> Estimasi: 3-5 Hari Kerja
                        </p>
                    </div>
                </div>
                <button onclick="openAjukanModal('Surat Keterangan Cuti')" class="w-full sm:w-auto bg-primary-container text-on-primary-container px-6 py-2.5 rounded-lg font-label-md hover:bg-primary hover:text-on-primary transition-all shadow-sm whitespace-nowrap">
                    Ajukan
                </button>
            </div>
            
            <!-- Service Card 3 -->
            <div class="glass-panel p-6 rounded-xl flex flex-col sm:flex-row items-center justify-between group hover:bg-white/80 transition-all duration-300 gap-4">
                <div class="flex items-center gap-5 w-full">
                    <div class="w-14 h-14 bg-secondary-container rounded-xl flex items-center justify-center text-on-secondary-container shadow-inner flex-shrink-0">
                        <span class="material-symbols-outlined text-[28px]">work</span>
                    </div>
                    <div>
                        <p class="font-body-lg font-bold text-on-surface leading-tight">Surat Pengantar Penelitian</p>
                        <p class="font-body-sm text-on-surface-variant mt-1 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[14px]">schedule</span> Estimasi: 2 Hari Kerja
                        </p>
                    </div>
                </div>
                <button onclick="openAjukanModal('Surat Pengantar Penelitian')" class="w-full sm:w-auto bg-primary-container text-on-primary-container px-6 py-2.5 rounded-lg font-label-md hover:bg-primary hover:text-on-primary transition-all shadow-sm whitespace-nowrap">
                    Ajukan
                </button>
            </div>
        </div>
    </section>

    <!-- History Column -->
    <aside class="col-span-12 lg:col-span-5 space-y-stack-md">
        <div class="flex justify-between items-center px-2">
            <h3 class="font-title-lg text-title-lg text-primary">Riwayat Pengajuan</h3>
        </div>
        
        <?php
        $stats = ['Pending' => 0, 'Diproses' => 0, 'Selesai' => 0, 'Ditolak' => 0];
        foreach ($riwayat as $r) {
            if (isset($stats[$r['status']])) $stats[$r['status']]++;
        }
        ?>
        <!-- Stats Summary -->
        <div class="glass-panel p-6 rounded-xl mb-6 bg-primary-container/10 border-primary/20">
            <h4 class="font-title-lg text-primary mb-4 text-[16px]">Ringkasan Pengajuan</h4>
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center">
                    <p class="text-[24px] font-bold text-primary"><?= $stats['Selesai'] ?></p>
                    <p class="font-label-md text-on-surface-variant">Selesai</p>
                </div>
                <div class="text-center border-l border-primary/10">
                    <p class="text-[24px] font-bold text-tertiary"><?= $stats['Pending'] + $stats['Diproses'] ?></p>
                    <p class="font-label-md text-on-surface-variant">Sedang Berjalan</p>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <?php if (empty($riwayat)): ?>
                <div class="p-6 text-center text-on-surface-variant glass-panel rounded-xl">
                    Belum ada riwayat pengajuan surat.
                </div>
            <?php else: ?>
                <?php foreach ($riwayat as $r): 
                    $borderColor = 'bg-surface-variant';
                    $statusColor = 'bg-surface-variant text-on-surface-variant';
                    $icon = 'hourglass_empty';
                    
                    if ($r['status'] === 'Pending') {
                        $borderColor = 'bg-tertiary-container';
                        $statusColor = 'bg-tertiary-fixed text-on-tertiary-fixed';
                        $icon = 'hourglass_empty';
                    } elseif ($r['status'] === 'Diproses') {
                        $borderColor = 'bg-secondary-container';
                        $statusColor = 'bg-secondary-fixed text-on-secondary-fixed';
                        $icon = 'sync';
                    } elseif ($r['status'] === 'Selesai') {
                        $borderColor = 'bg-success-badge';
                        $statusColor = 'bg-success-badge text-on-primary-fixed-variant';
                        $icon = 'check_circle';
                    } elseif ($r['status'] === 'Ditolak') {
                        $borderColor = 'bg-error';
                        $statusColor = 'bg-error text-white';
                        $icon = 'cancel';
                    }
                ?>
                <div class="glass-panel p-5 rounded-xl relative overflow-hidden group hover:border-primary/30 transition-all">
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $borderColor ?>"></div>
                    <div class="flex justify-between items-start mb-3 ml-2">
                        <div class="flex flex-col">
                            <span class="font-body-md font-bold text-on-surface"><?= htmlspecialchars($r['jenis_surat']) ?></span>
                            <span class="font-label-md text-on-surface-variant mt-0.5">ID: #SQN-<?= $r['id'] ?></span>
                        </div>
                        <div class="px-3 py-1 rounded-full <?= $statusColor ?> font-label-md flex items-center gap-1">
                            <?php if ($r['status'] === 'Pending'): ?>
                                <span class="w-2 h-2 rounded-full bg-tertiary-container animate-pulse"></span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-[14px]"><?= $icon ?></span>
                            <?php endif; ?>
                            <?= $r['status'] ?>
                        </div>
                    </div>
                    <div class="flex justify-between items-center pt-3 ml-2 border-t border-outline-variant/30 text-on-surface-variant font-label-md">
                        <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">calendar_today</span> <?= date('d M Y', strtotime($r['created_at'])) ?></span>
                        
                        <?php if ($r['status'] === 'Selesai' && !empty($r['file_surat'])): ?>
                            <a href="<?= htmlspecialchars($r['file_surat']) ?>" target="_blank" class="text-primary font-bold hover:underline cursor-pointer flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">download</span> Unduh Dokumen</a>
                        <?php elseif ($r['status'] === 'Ditolak'): ?>
                            <span class="text-error" title="<?= htmlspecialchars($r['catatan_operator'] ?? 'Ditolak') ?>">Lihat Catatan</span>
                        <?php else: ?>
                            <span class="text-on-surface-variant/50">Proses...</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
</div>

<script>
function openAjukanModal(jenis) {
    document.getElementById('modalAjukan').classList.remove('hidden');
    const select = document.querySelector('select[name="jenis_surat"]');
    if (select) {
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value === jenis) {
                select.selectedIndex = i;
                break;
            }
        }
    }
}
</script>

<!-- Modal Ajukan Surat -->
<div id="modalAjukan" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg overflow-hidden border border-outline-variant/30">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
            <h2 class="text-xl font-bold text-primary flex items-center gap-2">
                <span class="material-symbols-outlined">post_add</span> Ajukan Surat Baru
            </h2>
            <button onclick="document.getElementById('modalAjukan').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="ajukan">
            
            <div class="bg-surface-container-low p-4 rounded-xl mb-4 border border-outline-variant/30">
                <p class="text-sm font-bold text-on-surface mb-1">Data Pemohon:</p>
                <div class="text-sm text-on-surface-variant">
                    <?= htmlspecialchars($mhs['nim']) ?> - <?= htmlspecialchars($mhs['nama']) ?><br>
                    <?= htmlspecialchars($mhs['program_studi']) ?>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Jenis Surat <span class="text-error">*</span></label>
                <select name="jenis_surat" required class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all">
                    <option value="">-- Pilih Jenis Surat --</option>
                    <option value="Surat Keterangan Aktif Kuliah">Surat Keterangan Aktif Kuliah</option>
                    <option value="Surat Pengantar Penelitian">Surat Pengantar Penelitian (Skripsi)</option>
                    <option value="Surat Keterangan Cuti">Surat Keterangan Cuti Akademik</option>
                    <option value="Surat Keterangan Berkelakuan Baik">Surat Keterangan Berkelakuan Baik</option>
                    <option value="Surat Rekomendasi Beasiswa">Surat Rekomendasi Beasiswa</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-primary mb-2">Keperluan <span class="text-error">*</span></label>
                <textarea name="keperluan" required rows="3" placeholder="Jelaskan secara detail untuk keperluan apa surat ini diajukan (misal: syarat pengajuan beasiswa, izin penelitian di instansi X, dll)" class="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus:ring-2 focus:ring-primary outline-none text-sm transition-all"></textarea>
            </div>
            
            <div class="flex justify-end gap-3 mt-8">
                <button type="button" onclick="document.getElementById('modalAjukan').classList.add('hidden')" class="px-6 py-3 rounded-xl text-on-surface-variant hover:bg-surface-container-low font-bold transition-all">
                    Batal
                </button>
                <button type="submit" class="btn-primary flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px]">send</span> Kirim Pengajuan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
