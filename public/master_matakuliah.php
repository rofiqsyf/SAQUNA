<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireLogin();
Auth::requireOperator();

$repo = new OperatorRepository();

$action = $_POST['action'] ?? '';
$alertMessage = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add_mk') {
        $kode = $_POST['kode'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $sks = (int)($_POST['sks'] ?? 0);
        $prodi = $_POST['prodi'] ?? null;
        if (empty($prodi)) $prodi = null;
        $semester = !empty($_POST['semester']) ? (int)$_POST['semester'] : null;
        $kelas = $_POST['kelas'] ?? null;
        if (empty($kelas)) $kelas = null;
        $hari = $_POST['hari'] ?? null;
        if (empty($hari)) $hari = null;
        $jam = $_POST['jam'] ?? null;
        if (empty($jam)) $jam = null;

        if ($repo->createMataKuliah($kode, $nama, $sks, $prodi, $semester, $kelas, $hari, $jam)) {
            $alertMessage = "Mata kuliah berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambahkan mata kuliah (Kode mungkin sudah terdaftar).";
            $alertType = "error";
        }
    } elseif ($action === 'edit_mk') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = $_POST['kode'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $sks = (int)($_POST['sks'] ?? 0);
        $prodi = $_POST['prodi'] ?? null;
        if (empty($prodi)) $prodi = null;
        $semester = !empty($_POST['semester']) ? (int)$_POST['semester'] : null;
        $kelas = $_POST['kelas'] ?? null;
        if (empty($kelas)) $kelas = null;
        $hari = $_POST['hari'] ?? null;
        if (empty($hari)) $hari = null;
        $jam = $_POST['jam'] ?? null;
        if (empty($jam)) $jam = null;

        if ($repo->updateMataKuliah($id, $kode, $nama, $sks, $prodi, $semester, $kelas, $hari, $jam)) {
            $alertMessage = "Mata kuliah berhasil diperbarui.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal memperbarui mata kuliah.";
            $alertType = "error";
        }
    } elseif ($action === 'delete_mk') {
        $id = (int)($_POST['id'] ?? 0);
        if ($repo->deleteMataKuliah($id)) {
            $alertMessage = "Mata kuliah berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus mata kuliah.";
            $alertType = "error";
        }
    } elseif ($action === 'add_prasyarat') {
        $matakuliahId = (int)($_POST['mk_id'] ?? 0);
        $prasyaratId = (int)($_POST['prasyarat_id'] ?? 0);
        $nilai = $_POST['nilai_minimal'] ?? 'C';
        
        if ($repo->addPrasyaratMk($matakuliahId, $prasyaratId, $nilai)) {
            $alertMessage = "Prasyarat berhasil ditambahkan.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menambah prasyarat (mungkin sudah ada).";
            $alertType = "error";
        }
    } elseif ($action === 'delete_prasyarat') {
        $id = (int)($_POST['prasyarat_id_delete'] ?? 0);
        if ($repo->deletePrasyaratMk($id)) {
            $alertMessage = "Prasyarat berhasil dihapus.";
            $alertType = "success";
        } else {
            $alertMessage = "Gagal menghapus prasyarat.";
            $alertType = "error";
        }
    }
}

$groupedMk = $repo->getAllMataKuliahGrouped();
$prodiList = $repo->getAllProdi();
$allMkList = $repo->getAllMataKuliah();

$current_page = 'master_matakuliah.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Mata Kuliah - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <style>
        .accordion-header {
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        .accordion-header:hover {
            background-color: rgba(var(--primary-rgb, 79, 70, 229), 0.04);
        }
        .accordion-open {
            background: linear-gradient(90deg, rgba(var(--primary-rgb, 79, 70, 229), 0.08) 0%, transparent 100%);
            border-left: 4px solid var(--primary);
        }
        .accordion-open h3 {
            color: var(--primary) !important;
        }
        .accordion-icon {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .accordion-open .accordion-icon {
            transform: rotate(180deg);
            color: var(--primary);
        }
    </style>
</head>
<body class="bg-surface">

<?php include 'components/header.php'; ?>

<div class="container animate-fade-in py-8">
    <div class="d-flex justify-between align-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-on-surface mb-2">Manajemen Data Mata Kuliah</h1>
            <p class="text-on-surface-variant">Kelola daftar mata kuliah, SKS, Semester, dan lihat Dosen pengampu</p>
        </div>
        <button onclick="toggleAddForm()" class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 flex items-center gap-2">
            <span class="material-symbols-outlined">add</span> Tambah Mata Kuliah
        </button>
    </div>

    <?php if ($alertMessage): ?>
        <div class="p-4 mb-6 rounded-xl font-medium flex items-center gap-2 <?= $alertType === 'success' ? 'bg-secondary-container text-on-secondary-container' : 'bg-error-container text-on-error-container' ?> animate-fade-in">
            <span class="material-symbols-outlined"><?= $alertType === 'success' ? 'check_circle' : 'error' ?></span>
            <?= htmlspecialchars($alertMessage) ?>
        </div>
    <?php endif; ?>

    <!-- Form Tambah Mata Kuliah (Hidden by default) -->
    <div id="addFormContainer" class="card mb-8 hidden">
        <h2 class="text-xl font-bold mb-4">Tambah Mata Kuliah Baru</h2>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add_mk">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">Kode MK</label>
                    <input type="text" name="kode" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required placeholder="Contoh: TIF101">
                </div>
                <div class="form-group mb-0 md:col-span-2">
                    <label class="form-label text-sm text-on-surface">Nama Mata Kuliah</label>
                    <input type="text" name="nama" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required placeholder="Contoh: Algoritma">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">Kelas</label>
                    <input type="text" name="kelas" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" placeholder="A/B/C">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">SKS</label>
                    <input type="number" name="sks" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required min="1" max="6" value="3">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">Semester</label>
                    <input type="number" name="semester" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required min="1" max="14" placeholder="1-8">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">Hari</label>
                    <select name="hari" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">-- Pilih Hari --</option>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface">Jam (Misal: 08:00 - 10:30)</label>
                    <input type="text" name="jam" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" placeholder="08:00 - 10:30">
                </div>
            </div>
            <div class="form-group mt-4">
                <label class="form-label text-sm text-on-surface">Program Studi</label>
                <select name="prodi" class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                    <option value="">-- Pilih Program Studi --</option>
                    <?php foreach($prodiList as $p): ?>
                        <option value="<?= htmlspecialchars($p['nama_prodi']) ?>"><?= htmlspecialchars($p['jenjang'] . ' ' . $p['nama_prodi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mt-4 flex gap-2">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-hover transition-colors">Simpan Mata Kuliah</button>
                <button type="button" onclick="toggleAddForm()" class="bg-surface-variant text-on-surface px-4 py-2 rounded-lg font-medium hover:bg-outline-variant transition-colors">Batal</button>
            </div>
        </form>
    </div>

    <!-- Header Aksi & Filter Futuristik -->
    <div class="glass-panel rounded-3xl p-6 mb-8 border border-white/20 shadow-lg flex flex-col lg:flex-row justify-between items-center gap-6 relative overflow-hidden group">
        <div class="absolute inset-0 bg-gradient-to-r from-primary/10 to-transparent opacity-50 z-0"></div>
        <div class="relative z-10 w-full lg:w-2/3 flex flex-col md:flex-row gap-4">
            <!-- Search Bar -->
            <div class="relative flex-1">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant opacity-60">search</span>
                <input type="text" id="searchInput" placeholder="Cari nama atau kode MK..." class="w-full bg-surface/50 backdrop-blur-md border border-outline-variant/30 rounded-2xl pl-12 pr-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:bg-surface transition-all shadow-sm">
            </div>
            <!-- Filter Prodi -->
            <div class="relative w-full md:w-48">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant opacity-60">category</span>
                <select id="filterProdi" style="background-image: none;" class="w-full bg-surface/50 backdrop-blur-md border border-outline-variant/30 rounded-2xl pl-12 pr-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:bg-surface appearance-none cursor-pointer transition-all shadow-sm">
                    <option value="">Semua Prodi</option>
                    <?php foreach($prodiList as $p): ?>
                        <option value="<?= htmlspecialchars($p['nama_prodi']) ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none opacity-60 text-sm">expand_more</span>
            </div>
            <!-- Filter Semester -->
            <div class="relative w-full md:w-36">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant opacity-60">format_list_numbered</span>
                <select id="filterSemester" style="background-image: none;" class="w-full bg-surface/50 backdrop-blur-md border border-outline-variant/30 rounded-2xl pl-12 pr-4 py-3 text-on-surface focus:outline-none focus:ring-2 focus:ring-primary focus:bg-surface appearance-none cursor-pointer transition-all shadow-sm">
                    <option value="">Smt (Semua)</option>
                    <?php for($i=1; $i<=8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
                <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none opacity-60 text-sm">expand_more</span>
            </div>
        </div>
        <div class="relative z-10 w-full lg:w-auto text-right">
             <div class="inline-flex items-center gap-2 bg-surface-container-low px-4 py-2 rounded-xl border border-outline-variant/20 shadow-sm">
                <span class="text-sm font-bold text-on-surface-variant">Total Data:</span>
                <span id="totalDataIndicator" class="text-primary font-black text-lg">0</span>
             </div>
        </div>
    </div>

    <!-- Data Grid (Cards) -->
    <div id="courseGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php 
        // Mem-flatten data untuk grid layout
        $flatMks = [];
        if (!empty($groupedMk)) {
            foreach ($groupedMk as $prodi => $semesters) {
                foreach ($semesters as $smt => $kelasArr) {
                    foreach ($kelasArr as $kelas => $mks) {
                        foreach ($mks as $mkId => $mk) {
                            $flatMks[] = $mk;
                        }
                    }
                }
            }
        }
        ?>

        <?php if (empty($flatMks)): ?>
            <div class="col-span-full text-center py-12">
                <span class="material-symbols-outlined text-6xl text-outline mb-4">folder_off</span>
                <p class="text-on-surface-variant font-bold text-xl">Belum ada data Mata Kuliah.</p>
            </div>
        <?php else: ?>
            <?php foreach ($flatMks as $mk): ?>
                <div class="course-card relative group glass-panel rounded-2xl p-5 border border-white/20 shadow-sm hover:shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:border-primary/40 transition-all duration-300 hover:-translate-y-1 bg-surface/40 backdrop-blur-sm overflow-hidden flex flex-col justify-between"
                     data-kode="<?= strtolower(htmlspecialchars($mk['kode'])) ?>" 
                     data-nama="<?= strtolower(htmlspecialchars($mk['nama'])) ?>"
                     data-prodi="<?= htmlspecialchars($mk['prodi'] ?? '') ?>"
                     data-semester="<?= $mk['semester'] ?? '' ?>">
                    
                    <!-- Dekorasi Glow (Merespons hover) -->
                    <div class="absolute -top-10 -right-10 w-32 h-32 bg-primary/10 rounded-full blur-2xl group-hover:bg-primary/20 group-hover:scale-150 transition-all duration-500 z-0"></div>

                    <div class="relative z-10">
                        <div class="flex justify-between items-start mb-3 gap-2">
                            <span class="inline-block bg-primary/10 text-primary font-bold px-3 py-1 rounded-full text-xs uppercase tracking-wide border border-primary/20">
                                <?= htmlspecialchars($mk['kode']) ?>
                            </span>
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-secondary-container text-on-secondary-container text-sm font-black shadow-sm" title="SKS">
                                <?= $mk['sks'] ?>
                            </span>
                        </div>
                        
                        <h3 class="font-bold text-lg text-on-surface leading-tight mb-2 group-hover:text-primary transition-colors line-clamp-2" title="<?= htmlspecialchars($mk['nama']) ?>">
                            <?= htmlspecialchars($mk['nama']) ?>
                        </h3>

                        <div class="flex flex-wrap gap-2 mb-4">
                            <span class="inline-flex items-center gap-1 bg-surface text-on-surface-variant px-2 py-1 rounded border border-outline-variant/30 text-[11px] font-medium">
                                <span class="material-symbols-outlined text-[14px]">school</span> <?= htmlspecialchars($mk['prodi'] === 'Tidak Terdefinisi' ? 'Umum' : $mk['prodi']) ?>
                            </span>
                            <span class="inline-flex items-center gap-1 bg-surface text-on-surface-variant px-2 py-1 rounded border border-outline-variant/30 text-[11px] font-medium">
                                <span class="material-symbols-outlined text-[14px]">format_list_numbered</span> Smt <?= $mk['semester'] ?>
                            </span>
                            <span class="inline-flex items-center gap-1 bg-surface text-on-surface-variant px-2 py-1 rounded border border-outline-variant/30 text-[11px] font-medium">
                                <span class="material-symbols-outlined text-[14px]">meeting_room</span> Kls <?= htmlspecialchars($mk['kelas'] === 'Tanpa Kelas' ? '-' : $mk['kelas']) ?>
                            </span>
                        </div>

                        <div class="text-xs">
                            <span class="text-on-surface-variant font-medium block mb-1">Dosen Pengampu:</span>
                            <?php if (empty($mk['dosen'])): ?>
                                <span class="text-error/80 italic">Belum ada dosen</span>
                            <?php else: ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    // Tampilkan max 2 dosen
                                    $dosenLimit = array_slice($mk['dosen'], 0, 2);
                                    foreach ($dosenLimit as $dName): ?>
                                        <span class="inline-flex items-center gap-1 bg-surface text-primary px-2 py-1 rounded border border-primary/20 text-[10px] font-bold">
                                            <?= htmlspecialchars($dName) ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if(count($mk['dosen']) > 2): ?>
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-surface text-on-surface-variant border border-outline-variant/30 text-[10px] font-bold">
                                            +<?= count($mk['dosen']) - 2 ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tombol Aksi Melayang pada Hover -->
                    <div class="absolute bottom-4 right-4 translate-y-12 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300 z-20 flex gap-2">
                        <button onclick="openPrasyaratModal(<?= $mk['id'] ?>, '<?= htmlspecialchars($mk['kode'], ENT_QUOTES) ?> - <?= htmlspecialchars($mk['nama'], ENT_QUOTES) ?>')" class="w-10 h-10 rounded-full bg-secondary text-on-secondary shadow-lg flex items-center justify-center hover:scale-110 active:scale-95 transition-all" title="Prasyarat">
                            <span class="material-symbols-outlined text-[18px]">account_tree</span>
                        </button>
                        <button onclick="openEditMkModal(<?= $mk['id'] ?>, '<?= htmlspecialchars($mk['kode'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mk['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($mk['kelas'] ?? '', ENT_QUOTES) ?>', '', '', <?= $mk['sks'] ?>, <?= $mk['semester'] ?>, '<?= htmlspecialchars($mk['prodi'] ?? '', ENT_QUOTES) ?>')" class="w-10 h-10 rounded-full bg-primary text-on-primary shadow-lg flex items-center justify-center hover:scale-110 active:scale-95 transition-all" title="Edit">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                        </button>
                        <form method="POST" class="inline m-0" onsubmit="return confirm('Hapus MK ini secara permanen?');">
                            <input type="hidden" name="action" value="delete_mk">
                            <input type="hidden" name="id" value="<?= $mk['id'] ?>">
                            <button type="submit" class="w-10 h-10 rounded-full bg-error text-on-error shadow-lg flex items-center justify-center hover:scale-110 active:scale-95 transition-all" title="Hapus">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
// Logika Pencarian & Filter Instan
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('searchInput');
    const filterProdi = document.getElementById('filterProdi');
    const filterSemester = document.getElementById('filterSemester');
    const cards = document.querySelectorAll('.course-card');
    const totalIndicator = document.getElementById('totalDataIndicator');

    function filterCards() {
        const query = searchInput.value.toLowerCase().trim();
        const prodi = filterProdi.value;
        const semester = filterSemester.value;
        let visibleCount = 0;

        cards.forEach(card => {
            const cardNama = card.getAttribute('data-nama');
            const cardKode = card.getAttribute('data-kode');
            const cardProdi = card.getAttribute('data-prodi');
            const cardSemester = card.getAttribute('data-semester');

            const matchQuery = !query || cardNama.includes(query) || cardKode.includes(query);
            const matchProdi = !prodi || cardProdi === prodi;
            const matchSemester = !semester || cardSemester === semester;

            if (matchQuery && matchProdi && matchSemester) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (totalIndicator) totalIndicator.textContent = visibleCount;
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
        filterProdi.addEventListener('change', filterCards);
        filterSemester.addEventListener('change', filterCards);
        
        // Inisialisasi awal
        filterCards();
    }
});
function toggleAddForm() {
    const form = document.getElementById('addFormContainer');
    form.classList.toggle('hidden');
}

function openEditMkModal(id, kode, nama, kelas, hari, jam, sks, semester, prodi) {
    document.getElementById('edit_mk_id').value = id;
    document.getElementById('edit_mk_kode').value = kode;
    document.getElementById('edit_mk_nama').value = nama;
    document.getElementById('edit_mk_kelas').value = kelas;
    document.getElementById('edit_mk_hari').value = hari;
    document.getElementById('edit_mk_jam').value = jam;
    document.getElementById('edit_mk_sks').value = sks;
    document.getElementById('edit_mk_semester').value = semester;
    
    const prodiSelect = document.getElementById('edit_mk_prodi');
    for (let i = 0; i < prodiSelect.options.length; i++) {
        if (prodiSelect.options[i].value === prodi) {
            prodiSelect.selectedIndex = i;
            break;
        }
    }
    
    document.getElementById('modalEditMk').classList.remove('hidden');
}

function openPrasyaratModal(mkId, mkName) {
    document.getElementById('prasyarat_mk_id').value = mkId;
    document.getElementById('prasyarat_mk_title').innerText = "Prasyarat: " + mkName;
    
    // Fetch current prasyarat via AJAX
    fetch('api_get_prasyarat.php?mk_id=' + mkId)
        .then(response => response.json())
        .then(data => {
            const list = document.getElementById('prasyarat_list');
            list.innerHTML = '';
            if (data.length === 0) {
                list.innerHTML = '<p class="text-on-surface-variant text-sm italic py-2 text-center">Tidak ada prasyarat.</p>';
            } else {
                data.forEach(item => {
                    list.innerHTML += `
                        <div class="flex justify-between items-center bg-surface-container-lowest border border-outline-variant/30 p-3 rounded-lg mb-2">
                            <div>
                                <span class="font-bold text-sm text-primary">${item.kode} - ${item.nama}</span>
                                <div class="text-xs text-on-surface-variant">Nilai Minimal: <span class="font-bold">${item.nilai_minimal}</span></div>
                            </div>
                            <form method="POST" action="" class="inline" onsubmit="return confirm('Hapus prasyarat ini?');">
                                <input type="hidden" name="action" value="delete_prasyarat">
                                <input type="hidden" name="prasyarat_id_delete" value="${item.id}">
                                <button type="submit" class="text-error hover:text-error-container p-1"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                            </form>
                        </div>
                    `;
                });
            }
            document.getElementById('modalPrasyarat').classList.remove('hidden');
        });
}

// Pindahkan modal ke document.body agar tidak terpengaruh stacking context dari <main>
document.addEventListener('DOMContentLoaded', () => {
    const modalEdit = document.getElementById('modalEditMk');
    const modalPrasyarat = document.getElementById('modalPrasyarat');
    if (modalEdit) document.body.appendChild(modalEdit);
    if (modalPrasyarat) document.body.appendChild(modalPrasyarat);
});
</script>

<!-- Modal Edit Mata Kuliah -->
<div id="modalEditMk" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface">Edit Mata Kuliah</h3>
            <button onclick="document.getElementById('modalEditMk').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form method="POST" action="" class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <input type="hidden" name="action" value="edit_mk">
            <input type="hidden" name="id" id="edit_mk_id">
            <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Kode</label>
                    <input type="text" name="kode" id="edit_mk_kode" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                </div>
                <div class="form-group mb-0 md:col-span-2">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Nama Mata Kuliah</label>
                    <input type="text" name="nama" id="edit_mk_nama" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Kelas</label>
                    <input type="text" name="kelas" id="edit_mk_kelas" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" placeholder="A/B/C">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">SKS</label>
                    <input type="number" name="sks" id="edit_mk_sks" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required min="1" max="6">
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Semester</label>
                    <input type="number" name="semester" id="edit_mk_semester" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required min="1" max="14">
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-3">
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Hari</label>
                    <select name="hari" id="edit_mk_hari" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                        <option value="">-- Pilih Hari --</option>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Jam (Misal: 08:00 - 10:30)</label>
                    <input type="text" name="jam" id="edit_mk_jam" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" placeholder="08:00 - 10:30">
                </div>
            </div>
            <div class="form-group mt-4">
                <label class="form-label text-sm text-on-surface-variant font-semibold">Program Studi</label>
                <select name="prodi" id="edit_mk_prodi" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                    <option value="">-- Pilih Program Studi --</option>
                    <?php foreach($prodiList as $p): ?>
                        <option value="<?= htmlspecialchars($p['nama_prodi']) ?>"><?= htmlspecialchars($p['jenjang'] . ' ' . $p['nama_prodi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modalEditMk').classList.add('hidden')" class="px-5 py-2 rounded-xl text-on-surface-variant bg-surface-variant hover:bg-outline-variant transition-colors font-medium">Batal</button>
                <button type="submit" class="px-5 py-2 rounded-xl bg-primary text-on-primary hover:bg-primary-container hover:text-on-primary-container transition-colors font-medium flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Prasyarat -->
<div id="modalPrasyarat" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-lg mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface" id="prasyarat_mk_title">Atur Prasyarat</h3>
            <button onclick="document.getElementById('modalPrasyarat').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
            <div id="prasyarat_list" class="mb-6 max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                <!-- Loaded via AJAX -->
            </div>
            
            <hr class="border-outline-variant/30 mb-4">
            <h4 class="font-bold text-sm text-primary mb-3">Tambah Prasyarat Baru</h4>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_prasyarat">
                <input type="hidden" name="mk_id" id="prasyarat_mk_id">
                
                <div class="mb-3">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Mata Kuliah Prasyarat</label>
                    <select name="prasyarat_id" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                        <option value="">-- Pilih MK --</option>
                        <?php foreach($allMkList as $mk): ?>
                            <option value="<?= $mk['id'] ?>"><?= htmlspecialchars($mk['kode'] . ' - ' . $mk['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-sm text-on-surface-variant font-semibold">Nilai Kelulusan Minimal</label>
                    <select name="nilai_minimal" class="w-full bg-surface-container-lowest border border-outline-variant/50 rounded-xl px-4 py-2 text-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C" selected>C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-5 py-2 rounded-xl bg-secondary text-on-secondary hover:bg-secondary-container hover:text-on-secondary-container transition-colors font-medium flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px]">add</span> Tambah Prasyarat
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
</body>
</html>
