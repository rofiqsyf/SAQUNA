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

$transkrip = $repo->getTranskrip((int)$mhs['id']);
$dataKrs = $transkrip['data'];

$title = "Transkrip Nilai Sementara";
$current_page = "mahasiswa_transkrip.php";
include 'components/header.php';

$selected_semester = $_GET['semester'] ?? 'all';
$jenis_laporan = "Transkrip Akademik";
$jenis_ip = "Indeks Prestasi Kumulatif (IPK)";

if ($selected_semester !== 'all') {
    $jenis_laporan = "Kartu Hasil Studi (KHS) - Semester " . $selected_semester;
    $jenis_ip = "Indeks Prestasi Semester (IPS)";
    
    $filteredKrs = [];
    $totalSks = 0;
    $totalBobot = 0;
    $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
    
    foreach ($dataKrs as $row) {
        if ($row['semester_aktif'] === $selected_semester) {
            $filteredKrs[] = $row;
            $sks = (int)$row['sks'];
            $nilai = $row['nilai_huruf'];
            $totalSks += $sks;
            if (isset($bobotNilai[$nilai])) {
                $totalBobot += ($sks * $bobotNilai[$nilai]);
            }
        }
    }
    $dataKrs = $filteredKrs;
    $transkrip['ipk'] = $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0.0;
    $transkrip['total_sks'] = $totalSks;
}
?>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter no-print">
    <section class="lg:col-span-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-2">
        <div>
            <h1 class="font-display-lg text-display-lg text-primary font-black"><?= htmlspecialchars($jenis_laporan) ?></h1>
            <p class="text-on-surface-variant opacity-80 font-body-md mt-1">Rekam jejak akademik Anda.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" class="flex items-center m-0">
                <div class="bg-surface border border-outline-variant/30 rounded-xl overflow-hidden flex items-center pr-3 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
                    <span class="material-symbols-outlined text-on-surface-variant pl-3">filter_list</span>
                    <select name="semester" onchange="this.form.submit()" class="bg-transparent border-none text-on-surface font-body-md px-3 py-2.5 focus:ring-0 outline-none cursor-pointer">
                        <option value="all" <?= ($selected_semester === 'all') ? 'selected' : '' ?>>Seluruh Semester (Transkrip)</option>
                        <option value="Ganjil" <?= ($selected_semester === 'Ganjil') ? 'selected' : '' ?>>Semester Ganjil (KHS)</option>
                        <option value="Genap" <?= ($selected_semester === 'Genap') ? 'selected' : '' ?>>Semester Genap (KHS)</option>
                        <option value="Pendek" <?= ($selected_semester === 'Pendek') ? 'selected' : '' ?>>Semester Pendek (KHS)</option>
                    </select>
                </div>
            </form>
            <button onclick="window.print()" class="btn-primary flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px]">print</span> Cetak / PDF
            </button>
        </div>
    </section>
</div>

<div id="print-area">
    <!-- KOP SURAT (Only visible on print) -->
    <div class="print-only mb-8 border-b-4 border-primary pb-4 flex items-center gap-6">
        <div class="w-24 h-24 bg-primary text-white rounded-full flex items-center justify-center font-bold text-3xl">SQ</div>
        <div>
            <h1 class="text-2xl font-bold text-on-surface uppercase m-0">Kementerian Pendidikan, Kebudayaan, Riset, dan Teknologi</h1>
            <h2 class="text-3xl font-black text-primary uppercase m-0">Universitas Saquna</h2>
            <p class="text-on-surface-variant text-sm m-0">Jl. Inovasi Teknologi No. 99, Kota Masa Depan | Telp: (021) 555-1234 | Web: saquna.ac.id</p>
        </div>
    </div>
    
    <div class="print-only text-center mb-6">
        <h3 class="text-xl font-bold uppercase underline mb-1"><?= htmlspecialchars($jenis_laporan) ?></h3>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter mt-4">
        <!-- Info Panel -->
        <section class="lg:col-span-12">
            <div class="glass-panel rounded-3xl p-8 shadow-sm border border-white/40 flex flex-wrap gap-8 justify-between items-center bg-gradient-to-br from-surface to-surface-container-low relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-64 h-64 bg-primary/10 rounded-full -mr-32 -mt-32 blur-3xl group-hover:bg-primary/20 transition-colors"></div>
                
                <div class="relative z-10">
                    <h3 class="text-primary font-bold text-lg mb-4 flex items-center gap-2"><span class="material-symbols-outlined">person</span> Data Mahasiswa</h3>
                    <div class="space-y-2">
                        <p class="text-base font-medium text-on-surface-variant"><span class="w-32 inline-block opacity-70">Nama Lengkap</span> <strong class="text-on-surface"><?= htmlspecialchars($mhs['nama']) ?></strong></p>
                        <p class="text-base font-medium text-on-surface-variant"><span class="w-32 inline-block opacity-70">NIM</span> <strong class="text-on-surface"><?= htmlspecialchars($mhs['nim']) ?></strong></p>
                        <p class="text-base font-medium text-on-surface-variant"><span class="w-32 inline-block opacity-70">Program Studi</span> <strong class="text-on-surface"><?= htmlspecialchars($mhs['program_studi']) ?></strong></p>
                    </div>
                </div>
                <div class="text-right min-w-[200px] relative z-10">
                    <p class="text-base text-on-surface-variant font-bold mb-2"><?= htmlspecialchars($jenis_ip) ?></p>
                    <h1 class="text-7xl font-black text-primary leading-none tracking-tighter mb-2"><?= number_format($transkrip['ipk'], 2) ?></h1>
                    <p class="text-base text-on-surface-variant font-medium">Total SKS Lulus: <span class="bg-primary/10 text-primary px-3 py-1 rounded-full font-bold ml-1"><?= $transkrip['total_sks'] ?> SKS</span></p>
                </div>
            </div>
        </section>

        <!-- Tabel Nilai -->
        <section class="lg:col-span-12">
            <div class="glass-panel rounded-3xl shadow-sm border border-white/40 overflow-hidden mb-stack-md">
                <div class="p-6 border-b border-white/20 bg-surface/30 flex justify-between items-center">
                    <div>
                        <h3 class="font-title-lg text-lg font-bold text-primary flex items-center gap-2">
                            <span class="material-symbols-outlined">checklist</span> Daftar Mata Kuliah Lulus
                        </h3>
                        <p class="text-sm text-on-surface-variant mt-1">Nilai E tidak ditampilkan dan tidak diakumulasi.</p>
                    </div>
                </div>
                
                <?php if (empty($dataKrs)): ?>
                    <div class="p-12 text-center flex flex-col items-center justify-center">
                        <div class="w-20 h-20 bg-tertiary-container text-on-tertiary-container rounded-full flex items-center justify-center mb-4">
                            <span class="material-symbols-outlined text-4xl">folder_off</span>
                        </div>
                        <p class="font-body-lg font-bold text-on-surface-variant">Belum ada data nilai / transkrip yang tersedia.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive-wrapper custom-scrollbar">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="bg-surface/50 text-on-surface font-label-md text-sm">
                                    <th class="px-6 py-4 font-bold border-b border-white/20 w-16 text-center">No</th>
                                    <th class="px-6 py-4 font-bold border-b border-white/20">Kode MK</th>
                                    <th class="px-6 py-4 font-bold border-b border-white/20">Mata Kuliah</th>
                                    <th class="px-6 py-4 font-bold border-b border-white/20 text-center">SKS</th>
                                    <th class="px-6 py-4 font-bold border-b border-white/20 text-center">Nilai Mutu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/10">
                                <?php $no = 1; foreach ($dataKrs as $row): ?>
                                <tr class="hover:bg-white/20 transition-colors">
                                    <td class="px-6 py-4 font-bold text-on-surface-variant text-sm text-center"><?= $no++ ?></td>
                                    <td class="px-6 py-4 font-bold text-on-surface-variant text-sm"><?= htmlspecialchars($row['kode']) ?></td>
                                    <td class="px-6 py-4 font-bold text-on-surface text-base"><?= htmlspecialchars($row['mk_nama']) ?></td>
                                    <td class="px-6 py-4 font-black text-center text-primary"><?= htmlspecialchars((string)$row['sks']) ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="bg-success-container text-on-success-container px-3 py-1 rounded-xl text-sm font-black shadow-sm inline-flex items-center justify-center min-w-[40px]">
                                            <?= htmlspecialchars($row['nilai_huruf']) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- TANDA TANGAN (Only visible on print) -->
    <div class="print-only flex justify-end mt-12 pt-8">
        <div class="text-center w-64">
            <p class="mb-1">Kota Masa Depan, <?= date('d F Y') ?></p>
            <p class="mb-20 font-bold">Wakil Rektor Bidang Akademik</p>
            <p class="font-bold underline mb-0">Prof. Dr. Inovasi Digital, S.Kom., M.T.</p>
            <p class="text-sm">NIP. 19800101 200501 1 001</p>
        </div>
    </div>
</div>

<style>
    .print-only { display: none; }
    
    @media print {
        @page { margin: 1.5cm; size: A4; }
        
        body { 
            background: white !important; 
            color: black !important;
            font-size: 12pt;
        }
        
        /* Hide UI elements */
        #sidebar, #main-header, .no-print, button, form { 
            display: none !important; 
        }
        
        /* Reset Main Content Margin */
        #main-content {
            margin-left: 0 !important;
            padding-top: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Show Print Elements */
        .print-only { display: flex !important; }
        .print-only.text-center { display: block !important; }
        
        /* Remove Panel Styles */
        .glass-panel {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-bottom: 2rem !important;
        }
        
        /* Ensure Table Prints well */
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000 !important; padding: 8px; color: black !important; }
        th { background-color: #f3f4f6 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<?php include 'components/footer.php'; ?>
