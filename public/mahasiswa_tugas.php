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

// Default semester ganjil
$semester = $_GET['semester'] ?? $repo->getSemesterAktif();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $tugasId = (int)($_POST['tugas_id'] ?? 0);
        $tautan = trim($_POST['tautan'] ?? '');
        $keterangan = trim($_POST['keterangan'] ?? '');
        
        $filePath = '';
        $uploadError = '';
        if (isset($_FILES['tugas_file']) && $_FILES['tugas_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['tugas_file']['name'], PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($ext, $allowedExt)) {
                $uploadError = "Format file tidak diizinkan! (Gunakan PDF, DOCX, ZIP, JPG, dsb)";
            } else {
                $uploadDir = __DIR__ . '/uploads/tugas/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                // Buat nama file aman murni random
                $fileName = time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['tugas_file']['tmp_name'], $targetPath)) {
                    $filePath = 'uploads/tugas/' . $fileName;
                } else {
                    $uploadError = "Gagal memindahkan file yang diunggah.";
                }
            }
        }
        
        if ($uploadError) {
            $error = $uploadError;
        } else {
        
        // Cek file_path lama jika ini adalah re-upload dan user tidak memilih file baru
        if (empty($filePath) && $tugasId > 0) {
            $cek = \Config\Database::getConnection()->prepare("SELECT file_path FROM pengumpulan_tugas WHERE tugas_id = ? AND mahasiswa_id = ?");
            $cek->execute([$tugasId, $mhs['id']]);
            $filePath = $cek->fetchColumn() ?: '';
        }
        
        if ($tugasId && ($filePath || $tautan || $keterangan)) {
            if ($repo->uploadTugas($tugasId, (int)$mhs['id'], $filePath, $keterangan, $tautan)) {
                $success = "Tugas berhasil diunggah!";
            } else {
                $error = "Gagal mengunggah tugas.";
            }
        } else {
            $error = "Harap isi minimal satu inputan (File, Tautan, atau Keterangan).";
        }
        } // tutup else uploadError
    }
}

$listTugas = $repo->getTugasKuliah((int)$mhs['id'], $semester);

$title = "Tugas Kuliah";
$current_page = "mahasiswa_tugas.php";
include 'components/header.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- Header Section -->
    <section class="lg:col-span-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary font-black">Manajemen Tugas Kuliah</h1>
            <p class="text-on-surface-variant opacity-80 font-body-md mt-1">Kumpulkan tugas Anda tepat waktu.</p>
        </div>
        <form method="GET" class="flex gap-2 m-0">
            <div class="bg-surface border border-outline-variant/30 rounded-xl overflow-hidden flex items-center focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all shadow-sm">
                <span class="material-symbols-outlined text-on-surface-variant pl-4">event</span>
                <select name="semester" class="bg-transparent border-none text-on-surface font-bold text-sm pl-3 pr-10 py-3 focus:ring-0 outline-none cursor-pointer" onchange="this.form.submit()">
                    <option value="Ganjil" <?= $semester === 'Ganjil' ? 'selected' : '' ?>>Semester Ganjil</option>
                    <option value="Genap" <?= $semester === 'Genap' ? 'selected' : '' ?>>Semester Genap</option>
                    <option value="Pendek" <?= $semester === 'Pendek' ? 'selected' : '' ?>>Semester Pendek</option>
                </select>
            </div>
        </form>
    </section>

    <!-- Alerts -->
    <?php if ($success || $error): ?>
    <section class="lg:col-span-12">
        <?php if ($success): ?>
            <div class="bg-success-container text-on-success-container p-4 rounded-2xl mb-4 font-bold flex items-center gap-3 shadow-sm border border-success/20">
                <span class="material-symbols-outlined text-2xl">check_circle</span> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-error-container text-on-error-container p-4 rounded-2xl mb-4 font-bold flex items-center gap-3 shadow-sm border border-error/20">
                <span class="material-symbols-outlined text-2xl">error</span> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Main Content -->
    <section class="lg:col-span-12">
        <?php if (empty($listTugas)): ?>
            <div class="glass-panel p-12 rounded-3xl text-center shadow-sm border border-white/40 flex flex-col items-center justify-center">
                <div class="w-20 h-20 bg-primary/10 text-primary rounded-full flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined text-4xl">celebration</span>
                </div>
                <h2 class="text-xl font-black text-on-surface mb-2">Belum ada tugas pada semester ini.</h2>
                <p class="text-sm font-medium text-on-surface-variant">Selamat bersantai dan nikmati waktu luang Anda!</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($listTugas as $tugas): 
                    $dueDate = new DateTime($tugas['due_date']);
                    $now = new DateTime();
                    $isLate = $now > $dueDate;
                    $statusColorClass = $isLate ? 'bg-error-container text-on-error-container' : 'bg-primary-container text-on-primary-container';
                    $hasSubmitted = isset($tugas['waktu_kumpul']) && $tugas['waktu_kumpul'] !== null;
                ?>
                <div class="glass-panel rounded-3xl p-6 shadow-sm border border-white/40 flex flex-col relative overflow-hidden group">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -mr-16 -mt-16 blur-2xl group-hover:bg-primary/10 transition-colors"></div>
                    
                    <div class="flex justify-between items-start mb-4 relative z-10">
                        <span class="bg-secondary/10 text-secondary border border-secondary/20 px-3 py-1 rounded-full font-bold text-xs"><?= htmlspecialchars($tugas['mk_nama']) ?></span>
                        <span class="<?= $statusColorClass ?> px-3 py-1 rounded-full text-xs font-black shadow-sm flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">schedule</span> <?= $dueDate->format('d M, H:i') ?>
                        </span>
                    </div>
                    
                    <h3 class="font-title-lg text-primary font-black mb-2 relative z-10 line-clamp-2"><?= htmlspecialchars($tugas['judul_tugas']) ?></h3>
                    <p class="text-on-surface-variant text-sm mb-4 line-clamp-3 relative z-10"><?= nl2br(htmlspecialchars($tugas['deskripsi'])) ?></p>
                    
                    <div class="bg-surface/50 border border-white/20 p-4 rounded-2xl mb-4 text-sm text-on-surface-variant space-y-1 relative z-10">
                        <div class="flex justify-between"><span>Dosen:</span> <span class="font-bold text-on-surface"><?= htmlspecialchars($tugas['dosen_nama']) ?></span></div>
                        <div class="flex justify-between"><span>Bobot Nilai:</span> <span class="font-bold text-primary"><?= htmlspecialchars((string)$tugas['bobot_nilai']) ?>%</span></div>
                        <div class="flex justify-between"><span>Toleransi:</span> <span class="font-bold text-on-surface"><?= htmlspecialchars((string)$tugas['toleransi_keterlambatan_menit']) ?> mnt</span></div>
                    </div>

                    <?php if ($hasSubmitted): ?>
                        <div class="bg-success/10 border border-success/20 p-4 rounded-2xl mb-4 font-body-sm space-y-3 relative z-10">
                            <div class="font-bold text-success flex items-center gap-2">
                                <span class="material-symbols-outlined text-[20px]">task_alt</span> 
                                Dikumpulkan: <?= date('d M, H:i', strtotime($tugas['waktu_kumpul'])) ?>
                            </div>
                            
                            <div class="space-y-2">
                                <?php if (!empty($tugas['file_path'])): ?>
                                    <div class="flex items-center gap-2 text-on-surface">
                                        <span class="material-symbols-outlined text-[18px] text-primary">description</span> 
                                        <a href="<?= htmlspecialchars($tugas['file_path']) ?>" target="_blank" class="font-medium hover:text-primary transition-colors truncate">Lihat File Tersimpan</a>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($tugas['tautan'])): ?>
                                    <div class="flex items-center gap-2 text-on-surface">
                                        <span class="material-symbols-outlined text-[18px] text-tertiary">link</span> 
                                        <a href="<?= htmlspecialchars($tugas['tautan']) ?>" target="_blank" class="font-medium hover:text-tertiary transition-colors truncate">Buka Tautan</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($tugas['keterangan'])): ?>
                                <div class="mt-2 text-on-surface-variant text-sm italic border-t border-success/10 pt-2 break-words">
                                    "<?= htmlspecialchars($tugas['keterangan']) ?>"
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($tugas['nilai'] !== null): ?>
                            <div class="bg-tertiary/10 text-tertiary p-4 rounded-2xl border border-tertiary/20 mb-4 mt-auto relative z-10 flex justify-between items-center">
                                <div>
                                    <div class="font-bold text-xs uppercase tracking-wider mb-0.5 text-on-surface-variant">Nilai Akhir</div>
                                    <div class="text-2xl font-black"><?= htmlspecialchars((string)$tugas['nilai']) ?>/100</div>
                                </div>
                                <?php if ($tugas['feedback_dosen']): ?>
                                <div class="bg-white/50 p-2 rounded-xl text-xs max-w-[50%] text-right font-medium italic text-on-surface">
                                    <?= htmlspecialchars($tugas['feedback_dosen']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($tugas['nilai'] === null): ?>
                        <div class="mt-auto pt-4 border-t border-white/20 relative z-10">
                            <form method="POST" enctype="multipart/form-data" class="space-y-4" onsubmit="return confirm('Apakah Anda yakin ingin mengumpulkan tugas ini?')">
                                <?= Auth::csrfField() ?>
                                <input type="hidden" name="tugas_id" value="<?= $tugas['id'] ?>">
                                
                                <div class="space-y-3">
                                    <div>
                                        <label class="block text-xs font-bold text-on-surface-variant mb-1 uppercase tracking-wider">Upload File</label>
                                        <input type="file" name="tugas_file" class="w-full bg-surface/50 border border-white/20 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-primary file:mr-4 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-primary file:text-on-primary hover:file:bg-primary/90 cursor-pointer text-on-surface transition-all">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-on-surface-variant mb-1 uppercase tracking-wider">Tautan Tambahan (Opsional)</label>
                                        <input type="url" name="tautan" class="w-full bg-surface/50 border border-white/20 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-primary placeholder:text-outline-variant text-on-surface transition-all" placeholder="Link Google Drive, dll" value="<?= htmlspecialchars($tugas['tautan'] ?? '') ?>">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-xs font-bold text-on-surface-variant mb-1 uppercase tracking-wider">Keterangan (Opsional)</label>
                                        <textarea name="keterangan" rows="2" class="w-full bg-surface/50 border border-white/20 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-primary placeholder:text-outline-variant text-on-surface transition-all resize-none" placeholder="Pesan singkat..."><?= htmlspecialchars($tugas['keterangan'] ?? '') ?></textarea>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full py-3 rounded-xl font-bold transition-all shadow-sm flex items-center justify-center gap-2 <?= $hasSubmitted ? 'bg-secondary text-on-secondary hover:bg-secondary/90' : 'bg-primary text-on-primary hover:bg-primary/90' ?>">
                                    <span class="material-symbols-outlined text-[20px]"><?= $hasSubmitted ? 'update' : 'send' ?></span>
                                    <?= $hasSubmitted ? 'Re-Upload Tugas' : 'Kumpulkan Tugas' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include 'components/footer.php'; ?>
