<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId($_SESSION['user_id']);

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
        $error = "Token CSRF tidak valid.";
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'buat_tugas') {
            $data = [
                'matakuliah_id' => (int)$_POST['matakuliah_id'],
                'semester' => $_POST['semester'],
                'judul_tugas' => trim($_POST['judul_tugas']),
                'deskripsi' => trim($_POST['deskripsi']),
                'bobot_nilai' => (int)$_POST['bobot_nilai'],
                'due_date' => $_POST['due_date'],
                'toleransi_keterlambatan_menit' => (int)$_POST['toleransi']
            ];
            if ($repo->buatTugas($dosenId, $data)) {
                $success = "Tugas berhasil dibuat!";
            } else {
                $error = "Gagal membuat tugas.";
            }
        } elseif ($action === 'nilai_tugas') {
            $pengumpulanId = (int)$_POST['pengumpulan_id'];
            $nilai = (int)$_POST['nilai'];
            $feedback = trim($_POST['feedback']);
            
            if ($repo->nilaiTugas($dosenId, $pengumpulanId, $nilai, $feedback)) {
                $success = "Nilai berhasil disimpan!";
            } else {
                $error = "Gagal menyimpan nilai.";
            }
        }
    }
}

$daftarTugas = $repo->getDaftarTugas($dosenId);
$mkDosen = $repo->getDosenMataKuliahIds($dosenId);
$semuaMk = $repo->getAllMataKuliah();
$mkDosenFull = array_filter($semuaMk, fn($m) => in_array($m['id'], $mkDosen));

$selectedTugasId = isset($_GET['tugas_id']) ? (int)$_GET['tugas_id'] : (!empty($daftarTugas) ? $daftarTugas[0]['id'] : null);

$pengumpulan = [];
$selectedTugas = null;
if ($selectedTugasId) {
    $pengumpulan = $repo->getPengumpulanTugas($selectedTugasId);
    $selectedTugasArray = array_filter($daftarTugas, fn($t) => $t['id'] == $selectedTugasId);
    $selectedTugas = !empty($selectedTugasArray) ? reset($selectedTugasArray) : null;
}

$title = "Penilaian Tugas Kuliah";
$current_page = "dosen_tugas.php";
include 'components/header.php';
?>
<style>
    .active-tugas-card {
        background: rgba(232, 245, 233, 0.9);
        border-left: 4px solid var(--primary, #196b50);
    }
    .active-row {
        background-color: rgba(25, 107, 80, 0.05);
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.4);
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>

<div class="flex justify-between items-center mb-stack-md">
    <div>
        <h1 class="font-headline-md text-headline-md font-bold text-primary m-0">Manajemen Tugas Kuliah</h1>
        <p class="text-on-surface-variant opacity-80 font-body-md mt-1 mb-0">Buat tugas baru dan berikan penilaian kepada mahasiswa.</p>
    </div>
    <button class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-full shadow-lg shadow-primary-container/30 transition-all active:scale-95 inline-flex items-center gap-2 border-none cursor-pointer" onclick="document.getElementById('formTugasBaru').style.display = 'block'">
        <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">add</span> Buat Tugas Baru
    </button>
</div>

<?php if ($success): ?><div class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2 shadow-sm border border-secondary-fixed-dim"><span class="material-symbols-outlined">check_circle</span> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2 shadow-sm border border-error/20"><span class="material-symbols-outlined">error</span> <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Form Buat Tugas Baru -->
<div id="formTugasBaru" class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40 relative" style="display: none; border-top: 4px solid var(--primary);">
    <div class="flex justify-between items-center mb-6">
        <h3 class="mt-0 font-headline-sm text-headline-sm text-on-surface m-0">Formulir Tugas Baru</h3>
        <button onclick="document.getElementById('formTugasBaru').style.display = 'none'" class="material-symbols-outlined text-outline-variant hover:text-primary bg-transparent border-none cursor-pointer rounded-full p-2 hover:bg-surface-container-low transition-colors">close</button>
    </div>
    <form method="POST">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="buat_tugas">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Mata Kuliah</label>
                <select name="matakuliah_id" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
                    <option value="">-- Pilih Mata Kuliah --</option>
                    <?php foreach ($mkDosenFull as $mk): ?>
                        <option value="<?= $mk['id'] ?>"><?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Semester Aktif</label>
                <select name="semester" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
                    <option value="Ganjil">Ganjil</option>
                    <option value="Genap">Genap</option>
                    <option value="Pendek">Pendek</option>
                </select>
            </div>
        </div>
        
        <div class="mb-stack-sm">
            <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Judul Tugas</label>
            <input type="text" name="judul_tugas" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" placeholder="Contoh: Tugas 1 - Pembuatan ERD" required>
        </div>
        
        <div class="mb-stack-sm">
            <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Deskripsi & Instruksi</label>
            <textarea name="deskripsi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" rows="4" placeholder="Jelaskan instruksi tugas secara detail..." required></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-stack-md">
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Bobot Nilai (%)</label>
                <input type="number" name="bobot_nilai" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" min="1" max="100" placeholder="10" required>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Batas Waktu (Due Date)</label>
                <input type="datetime-local" name="due_date" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" required>
            </div>
            <div class="mb-stack-sm">
                <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Toleransi Telat (Menit)</label>
                <input type="number" name="toleransi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant" value="0" min="0" required>
            </div>
        </div>
        
        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-full shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center border-none cursor-pointer">Simpan Tugas Baru</button>
            <button type="button" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-full transition-all active:scale-95 inline-block text-center border-none cursor-pointer" onclick="document.getElementById('formTugasBaru').style.display = 'none'">Batal</button>
        </div>
    </form>
</div>

<!-- Layout Utama: 3 Kolom -->
<div class="flex flex-col lg:flex-row gap-gutter relative items-start">
    
    <!-- Kolom Kiri: Daftar Tugas -->
    <section class="w-full lg:w-1/4 space-y-4">
        <div class="flex items-center justify-between mb-2 px-2">
            <h2 class="font-headline-sm text-headline-sm m-0">Tugas Aktif</h2>
            <span class="bg-primary-container text-on-primary-container px-2 py-0.5 rounded text-[10px] font-bold"><?= count($daftarTugas) ?> TOTAL</span>
        </div>
        
        <div class="space-y-3 max-h-[75vh] overflow-y-auto no-scrollbar pb-4 px-1" style="-webkit-mask-image: linear-gradient(to bottom, black 95%, transparent 100%); mask-image: linear-gradient(to bottom, black 95%, transparent 100%);">
            <?php if (empty($daftarTugas)): ?>
                <div class="glass-panel p-4 rounded-xl text-center text-on-surface-variant font-body-sm opacity-70">
                    Belum ada tugas yang dibuat.
                </div>
            <?php else: ?>
                <?php foreach ($daftarTugas as $t): 
                    $isActive = ($t['id'] == $selectedTugasId);
                    $cardClass = $isActive ? 'active-tugas-card ring-2 ring-primary/10' : 'glass-card border-l-4 border-outline-variant opacity-80 hover:opacity-100';
                ?>
                    <a href="?tugas_id=<?= $t['id'] ?>" class="block w-full p-4 rounded-xl text-left shadow-sm hover:shadow-md transition-all group no-underline <?= $cardClass ?>">
                        <p class="text-[10px] font-label-md <?= $isActive ? 'text-primary' : 'text-on-surface-variant' ?> uppercase mb-1 truncate"><?= htmlspecialchars($t['mk_nama']) ?> • <?= htmlspecialchars($t['semester']) ?></p>
                        <h3 class="font-label-md text-label-md text-on-surface mb-2 truncate" style="margin-top:0; margin-bottom: 0.5rem; font-size: 14px;"><?= htmlspecialchars($t['judul_tugas']) ?></h3>
                        <div class="flex items-center justify-between text-[11px] text-on-surface-variant">
                            <span class="opacity-80"><span class="material-symbols-outlined text-[12px] align-text-bottom">calendar_today</span> <?= date('d M Y, H:i', strtotime($t['due_date'])) ?></span>
                            <span class="group-hover:translate-x-1 transition-transform <?= $isActive ? 'text-primary' : '' ?>">→</span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Kolom Tengah: Tabel Pengumpulan -->
    <section class="w-full lg:flex-1 space-y-4">
        <div class="glass-panel rounded-2xl shadow-sm overflow-hidden border border-outline-variant/30 flex flex-col h-[75vh]">
            <div class="p-4 border-b border-outline-variant/30 flex justify-between items-center bg-white/40 shrink-0">
                <h2 class="font-headline-sm text-headline-sm m-0">Daftar Pengumpulan</h2>
                <?php if ($selectedTugas): ?>
                <div class="flex gap-2">
                    <span class="px-3 py-1.5 rounded-full bg-surface-container text-on-surface-variant text-label-sm font-label-sm border border-outline-variant/20"><?= count($pengumpulan) ?> Terkumpul</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="flex-1 overflow-auto no-scrollbar">
                <?php if (!$selectedTugas): ?>
                    <div class="flex flex-col items-center justify-center h-full text-on-surface-variant opacity-70 p-8">
                        <span class="material-symbols-outlined text-5xl mb-4 opacity-50">assignment</span>
                        <p class="font-body-md text-center max-w-sm">Pilih salah satu tugas di panel sebelah kiri untuk melihat daftar mahasiswa yang sudah mengumpulkan.</p>
                    </div>
                <?php elseif (empty($pengumpulan)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-on-surface-variant opacity-70 p-8">
                        <span class="material-symbols-outlined text-5xl mb-4 opacity-50">inbox</span>
                        <p class="font-body-md text-center max-w-sm">Belum ada mahasiswa yang mengumpulkan tugas ini.</p>
                    </div>
                <?php else: ?>
                    <table class="w-full text-left border-collapse m-0">
                        <thead class="sticky top-0 bg-surface-container-low/95 backdrop-blur z-10 border-b border-outline-variant/30 shadow-sm">
                            <tr class="text-on-surface-variant">
                                <th class="p-4 font-label-md text-label-md">Nama Mahasiswa</th>
                                <th class="p-4 font-label-md text-label-md">Waktu Kumpul</th>
                                <th class="p-4 font-label-md text-label-md">Status</th>
                                <th class="p-4 font-label-md text-label-md text-right">Nilai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20" id="tableBody">
                            <?php foreach ($pengumpulan as $p): 
                                $isDinilai = ($p['nilai'] !== null);
                                $statusBg = $isDinilai ? 'bg-primary/10 border-primary/20 text-primary' : 'bg-warning-amber/10 border-warning-amber/20 text-warning-amber';
                                $statusText = $isDinilai ? 'Sudah Dinilai' : 'Belum Dinilai';
                                
                                $words = explode(" ", $p['mhs_nama']);
                                $initials = strtoupper(substr($words[0], 0, 1) . (isset($words[1]) ? substr($words[1], 0, 1) : ''));
                            ?>
                            <tr class="hover:bg-primary/5 transition-colors cursor-pointer row-student" 
                                onclick="openGradingPanel(this, <?= $p['id'] ?>, '<?= htmlspecialchars($p['mhs_nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($p['nim'], ENT_QUOTES) ?>', '<?= date('d M Y, H:i', strtotime($p['waktu_kumpul'])) ?>', '<?= htmlspecialchars($p['file_path'], ENT_QUOTES) ?>', '<?= $p['nilai'] ?? '' ?>', '<?= htmlspecialchars($p['feedback_dosen'] ?? '', ENT_QUOTES) ?>')">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-secondary-fixed flex items-center justify-center font-bold text-on-secondary-fixed text-xs shrink-0 shadow-sm"><?= $initials ?></div>
                                        <div>
                                            <div class="font-body-md text-body-md font-medium text-on-surface"><?= htmlspecialchars($p['mhs_nama']) ?></div>
                                            <div class="text-[11px] text-on-surface-variant font-medium"><?= htmlspecialchars($p['nim']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 font-body-sm text-body-sm text-on-surface-variant"><?= date('d M, H:i', strtotime($p['waktu_kumpul'])) ?></td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-full border text-[10px] font-bold uppercase tracking-wider whitespace-nowrap <?= $statusBg ?>"><?= $statusText ?></span>
                                </td>
                                <td class="p-4 text-right font-headline-sm text-headline-sm <?= $isDinilai ? 'text-primary font-bold' : 'text-outline-variant' ?>">
                                    <?= $isDinilai ? $p['nilai'] : '—' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Kolom Kanan: Detail Penilaian (Sticky) -->
    <aside class="w-full lg:w-1/3 lg:block sticky top-28" id="gradingPanelContainer" style="<?= $selectedTugas && count($pengumpulan) > 0 ? 'display: none;' : 'display: none;' ?>">
        <div class="glass-panel rounded-2xl p-6 shadow-md border border-outline-variant/30">
            <div class="flex items-center justify-between mb-6">
                <h2 class="font-headline-sm text-headline-sm m-0">Detail Penilaian</h2>
                <button onclick="closeGradingPanel()" class="material-symbols-outlined text-outline-variant cursor-pointer hover:text-primary bg-transparent border-none rounded-full p-1 hover:bg-surface-container-low transition-colors">close</button>
            </div>
            
            <div class="bg-surface-container-lowest rounded-xl p-4 mb-6 border border-outline-variant/20 shadow-sm">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0 border border-primary/20">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">description</span>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="font-label-md text-label-md truncate m-0 text-on-surface" id="panel_mhs_nama">Nama Mahasiswa</p>
                        <p class="text-[11px] text-on-surface-variant m-0 mt-1 flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">schedule</span> <span id="panel_waktu">Dikumpulkan</span></p>
                    </div>
                </div>
                <a href="#" id="panel_file_link" target="_blank" class="w-full py-2 bg-surface-container-low hover:bg-surface-container-high rounded-full font-label-md text-label-md text-primary flex items-center justify-center gap-2 transition-colors border border-primary/10 no-underline shadow-sm hover:shadow active:scale-95">
                    <span class="material-symbols-outlined text-sm">download</span> Unduh Jawaban
                </a>
            </div>
            
            <form method="POST" id="formPenilaian">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="nilai_tugas">
                <input type="hidden" name="pengumpulan_id" id="panel_peng_id">
                
                <div class="space-y-5">
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Skor Tugas (0-100)</label>
                        <input type="number" name="nilai" id="panel_nilai" class="w-full px-4 py-4 bg-white/80 backdrop-blur border border-outline-variant/50 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-display-lg text-headline-lg text-primary text-center shadow-inner" min="0" max="100" placeholder="0" required>
                    </div>
                    <div>
                        <label class="block font-label-md text-label-md text-on-surface-variant mb-2">Feedback Dosen</label>
                        <textarea name="feedback" id="panel_feedback" class="w-full px-4 py-3 bg-white/80 backdrop-blur border border-outline-variant/50 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all font-body-sm text-body-sm shadow-inner" placeholder="Tuliskan catatan, evaluasi atau masukan untuk mahasiswa..." rows="4"></textarea>
                    </div>
                    
                    <div class="pt-2">
                        <button type="submit" class="w-full py-3 bg-primary hover:bg-on-primary-fixed-variant text-on-primary rounded-full font-label-md text-label-md shadow-lg shadow-primary-container/30 hover:-translate-y-0.5 transition-all border-none cursor-pointer flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1;">save</span> Simpan Penilaian
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-6 p-3 bg-primary/5 rounded-lg flex items-start gap-3 border border-primary/10">
                <span class="material-symbols-outlined text-primary text-sm mt-0.5" style="font-variation-settings: 'FILL' 1;">info</span>
                <p class="text-[11px] text-on-surface-variant leading-relaxed m-0">
                    Nilai yang disimpan akan langsung masuk ke sistem akademik dan dapat dilihat oleh mahasiswa terkait di portal mereka.
                </p>
            </div>
        </div>
    </aside>
</div>

<script>
    function openGradingPanel(rowElement, pengumpulanId, nama, nim, waktu, filePath, nilai, feedback) {
        // Hilangkan style aktif dari row lain
        const rows = document.querySelectorAll('.row-student');
        rows.forEach(r => r.classList.remove('active-row', 'border-l-4', 'border-primary'));
        
        // Tambahkan style aktif ke row yg diklik
        if(rowElement) {
            rowElement.classList.add('active-row', 'border-l-4', 'border-primary');
        }
        
        // Tampilkan panel
        const panel = document.getElementById('gradingPanelContainer');
        panel.style.display = 'block';
        
        // Animasi pop in ringan
        panel.animate([
            { opacity: 0, transform: 'translateY(10px)' },
            { opacity: 1, transform: 'translateY(0)' }
        ], { duration: 300, easing: 'ease-out' });
        
        // Set data ke dalam form
        document.getElementById('panel_peng_id').value = pengumpulanId;
        document.getElementById('panel_mhs_nama').innerText = nama + ' (' + nim + ')';
        document.getElementById('panel_waktu').innerText = 'Dikumpulkan: ' + waktu;
        
        const fileLink = document.getElementById('panel_file_link');
        fileLink.href = filePath;
        
        document.getElementById('panel_nilai').value = nilai || '';
        document.getElementById('panel_feedback').value = feedback || '';
        
        // Fokuskan input nilai
        setTimeout(() => document.getElementById('panel_nilai').focus(), 100);
    }
    
    function closeGradingPanel() {
        document.getElementById('gradingPanelContainer').style.display = 'none';
        const rows = document.querySelectorAll('.row-student');
        rows.forEach(r => r.classList.remove('active-row', 'border-l-4', 'border-primary'));
    }
</script>

<?php include 'components/footer.php'; ?>
