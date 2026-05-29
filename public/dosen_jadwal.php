<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$stmtSmt = \Config\Database::getConnection()->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';

// Ambil jadwal mengajar lengkap, diurutkan per hari
$jadwalSemua = $repo->getJadwalMengajar($dosenId);

// Kelompokkan per hari
$urutanHari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$jadwalPerHari = array_fill_keys($urutanHari, []);

foreach ($jadwalSemua as $j) {
    $hari = $j['hari'];
    if (isset($jadwalPerHari[$hari])) {
        $jadwalPerHari[$hari][] = $j;
    }
}

// Hitung statistik
$totalMK = count(array_unique(array_column($jadwalSemua, 'mk_nama')));
$totalSKS = array_sum(array_unique(array_column($jadwalSemua, 'sks')));
$totalKelas = count($jadwalSemua);
$hariMengajar = count(array_filter($jadwalPerHari, fn($h) => count($h) > 0));

// Hari mengajar hari ini
$mapHari = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu'];
$hariIni = $mapHari[date('N')] ?? 'Senin';
$jadwalHariIni = $jadwalPerHari[$hariIni] ?? [];
$jamSekarang = date('H:i:s');

$title = "Jadwal Mengajar — SAQUNA";
$current_page = "dosen_jadwal.php";
include 'components/header.php';
?>

<div class="mb-stack-md flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h2 class="font-display-sm text-display-sm text-primary">Jadwal Mengajar</h2>
        <p class="font-body-lg text-body-lg text-on-surface-variant mt-1">
            Semester <?= htmlspecialchars($semesterAktif) ?> — jadwal mengajar Anda secara keseluruhan.
        </p>
    </div>
    <div class="flex gap-3">
        <button onclick="window.print()" 
                class="px-5 py-2.5 rounded-xl border border-outline-variant/30 text-on-surface-variant hover:bg-surface-variant/20 transition-all font-label-md flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">print</span> Cetak
        </button>
    </div>
</div>

<!-- Statistik Ringkasan -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-gutter mb-stack-lg">
    <div class="glass-panel rounded-3xl p-4 shadow-sm border border-white/40 text-center">
        <span class="material-symbols-outlined text-3xl text-primary mb-2">library_books</span>
        <p class="font-headline-md text-primary font-black text-3xl"><?= $totalMK ?></p>
        <p class="font-body-sm text-on-surface-variant text-xs">Matakuliah</p>
    </div>
    <div class="glass-panel rounded-3xl p-4 shadow-sm border border-white/40 text-center">
        <span class="material-symbols-outlined text-3xl text-secondary mb-2">class</span>
        <p class="font-headline-md text-secondary font-black text-3xl"><?= $totalKelas ?></p>
        <p class="font-body-sm text-on-surface-variant text-xs">Kelas Aktif</p>
    </div>
    <div class="glass-panel rounded-3xl p-4 shadow-sm border border-white/40 text-center">
        <span class="material-symbols-outlined text-3xl text-tertiary mb-2">grade</span>
        <p class="font-headline-md text-tertiary font-black text-3xl"><?= $totalSKS ?></p>
        <p class="font-body-sm text-on-surface-variant text-xs">Total SKS</p>
    </div>
    <div class="glass-panel rounded-3xl p-4 shadow-sm border border-white/40 text-center">
        <span class="material-symbols-outlined text-3xl text-success mb-2">calendar_today</span>
        <p class="font-headline-md text-success font-black text-3xl"><?= $hariMengajar ?></p>
        <p class="font-body-sm text-on-surface-variant text-xs">Hari Mengajar</p>
    </div>
</div>

<!-- Jadwal Per Hari -->
<?php if (empty($jadwalSemua)): ?>
<div class="glass-panel rounded-3xl p-stack-xl shadow-sm border border-outline-variant/30 text-center">
    <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-30 mb-4">calendar_view_week</span>
    <h3 class="font-headline-sm text-on-surface-variant mb-2">Belum Ada Jadwal</h3>
    <p class="text-on-surface-variant max-w-md mx-auto">
        Jadwal mengajar belum diatur oleh operator. Hubungi BAK untuk informasi lebih lanjut.
    </p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-gutter">
    <?php foreach ($jadwalPerHari as $hari => $jadwals): 
        $isHariIni = ($hari === $hariIni);
    ?>
    <div class="glass-panel rounded-3xl p-stack-md shadow-sm 
                <?= $isHariIni ? 'border-2 border-primary/40' : 'border border-white/40' ?> 
                flex flex-col relative overflow-hidden">
        <?php if ($isHariIni): ?>
        <div class="absolute top-3 right-3">
            <span class="bg-primary text-on-primary px-2 py-0.5 rounded-full text-xs font-bold">Hari Ini</span>
        </div>
        <?php endif; ?>
        
        <div class="flex justify-between items-center mb-stack-sm pb-3 border-b border-outline-variant/30">
            <h3 class="font-headline-sm <?= $isHariIni ? 'text-primary' : 'text-on-surface' ?> font-bold flex items-center gap-2">
                <span class="material-symbols-outlined <?= $isHariIni ? 'text-primary' : 'text-on-surface-variant' ?>">event</span>
                <?= $hari ?>
            </h3>
            <span class="<?= count($jadwals) > 0 ? 'bg-primary/10 text-primary' : 'bg-surface-variant/30 text-on-surface-variant' ?> text-xs font-bold px-2 py-1 rounded-full">
                <?= count($jadwals) ?> Kelas
            </span>
        </div>
        
        <div class="flex-1 space-y-3">
            <?php if (empty($jadwals)): ?>
            <div class="flex flex-col items-center justify-center h-28 text-on-surface-variant opacity-40">
                <span class="material-symbols-outlined text-3xl mb-1">hotel</span>
                <p class="text-xs">Tidak Ada Jadwal</p>
            </div>
            <?php else: ?>
                <?php foreach ($jadwals as $j): 
                    $status = '';
                    if ($isHariIni) {
                        if ($jamSekarang >= $j['jam_mulai'] && $jamSekarang <= $j['jam_selesai']) {
                            $status = 'BERLANGSUNG';
                        } elseif ($jamSekarang < $j['jam_mulai']) {
                            $status = 'AKAN DATANG';
                        } else {
                            $status = 'SELESAI';
                        }
                    }
                    $statusColor = $status === 'BERLANGSUNG' ? 'bg-success' : ($status === 'AKAN DATANG' ? 'bg-primary' : 'bg-on-surface-variant/40');
                ?>
                <div onclick="showKelasDetail(<?= $j['id'] ?>)" class="bg-surface-container-lowest p-4 rounded-2xl border border-outline-variant/30 hover:border-primary/50 hover:bg-surface-variant/10 transition-all relative overflow-hidden cursor-pointer group">
                    <div class="absolute left-0 top-0 bottom-0 w-1.5 <?= $status === 'BERLANGSUNG' ? 'bg-success' : 'bg-primary/60 group-hover:bg-primary' ?> rounded-l-2xl transition-colors"></div>
                    <div class="ml-2">
                        <!-- Waktu -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="bg-primary/10 text-primary font-label-md text-xs font-bold px-2 py-1 rounded-lg">
                                <?= substr($j['jam_mulai'], 0, 5) ?> – <?= substr($j['jam_selesai'], 0, 5) ?>
                            </span>
                            <?php if ($status !== ''): ?>
                            <span class="<?= $statusColor ?> text-white text-xs font-bold px-2 py-0.5 rounded-full animate-pulse-soft">
                                <?= $status ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Nama MK -->
                        <h4 class="font-bold text-on-surface line-clamp-2 text-sm mt-1"><?= htmlspecialchars($j['mk_nama']) ?></h4>
                        
                        <div class="flex flex-col gap-1 mt-2">
                            <!-- Kode MK -->
                            <span class="text-xs text-on-surface-variant font-bold"><?= htmlspecialchars($j['kode']) ?> · <?= htmlspecialchars($j['sks']) ?> SKS</span>
                            <!-- Ruangan -->
                            <p class="text-xs text-on-surface-variant flex items-center gap-1">
                                <span class="material-symbols-outlined text-xs text-secondary">meeting_room</span>
                                <?= htmlspecialchars($j['ruangan'] ?? 'Ruangan TBD') ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
@media print {
    nav, header, footer { display: none !important; }
    .glass-panel { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<!-- Modal Pop-up Kelas Detail -->
<div id="modalKelasDetail" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-outline-variant/30 flex justify-between items-center bg-surface-container-low flex-shrink-0">
            <h3 class="text-xl font-bold text-on-surface flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">info</span>
                Informasi Kelas
            </h3>
            <button onclick="document.getElementById('modalKelasDetail').classList.add('hidden')" class="text-on-surface-variant hover:text-error transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div id="modalLoading" class="p-10 flex flex-col items-center justify-center text-on-surface-variant">
            <span class="material-symbols-outlined animate-spin text-4xl text-primary mb-2">progress_activity</span>
            <p>Memuat informasi kelas...</p>
        </div>

        <div id="modalContent" class="hidden flex-col h-full max-h-[70vh]">
            <!-- Info Header -->
            <div class="p-6 bg-gradient-to-r from-primary-container/30 to-transparent border-b border-outline-variant/20">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 id="det_mk_nama" class="text-2xl font-bold text-primary mb-1">Nama Mata Kuliah</h4>
                        <div class="flex flex-wrap gap-2 text-sm text-on-surface-variant font-medium">
                            <span id="det_mk_kode" class="bg-surface-variant/50 px-2 py-0.5 rounded border border-outline-variant/30">Kode</span>
                            <span id="det_sks" class="bg-surface-variant/50 px-2 py-0.5 rounded border border-outline-variant/30">SKS</span>
                            <span id="det_dosen" class="bg-surface-variant/50 px-2 py-0.5 rounded border border-outline-variant/30 text-secondary">Dosen</span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="bg-surface px-3 py-2 rounded-xl shadow-sm border border-outline-variant/20">
                            <div class="text-sm font-bold text-primary flex items-center gap-1 justify-end">
                                <span class="material-symbols-outlined text-[16px]">schedule</span> 
                                <span id="det_waktu">00:00 - 00:00</span>
                            </div>
                            <div class="text-xs text-on-surface-variant mt-1 flex items-center gap-1 justify-end">
                                <span class="material-symbols-outlined text-[14px]">meeting_room</span> 
                                <span id="det_ruang">Ruang</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Student List -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                <div class="flex justify-between items-center mb-3">
                    <h5 class="font-bold text-on-surface text-lg">Daftar Mahasiswa Terdaftar</h5>
                    <div class="flex items-center gap-2">
                        <span id="det_jml_mhs" class="text-xs bg-primary text-on-primary px-2 py-1 rounded-lg font-bold">0 Mahasiswa</span>
                        <a href="#" id="det_export_btn" class="text-xs bg-tertiary hover:bg-tertiary-fixed-dim text-on-tertiary px-3 py-1.5 rounded-lg font-bold transition-colors flex items-center gap-1 shadow-sm">
                            <span class="material-symbols-outlined text-[14px]">download</span> Export
                        </a>
                    </div>
                </div>
                <div class="border border-outline-variant/30 rounded-xl overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-surface-container-low text-on-surface-variant text-sm font-bold border-b border-outline-variant/30">
                            <tr>
                                <th class="py-2 px-4 w-12 text-center">No</th>
                                <th class="py-2 px-4">NIM</th>
                                <th class="py-2 px-4">Nama Mahasiswa</th>
                                <th class="py-2 px-4">Program Studi</th>
                            </tr>
                        </thead>
                        <tbody id="det_mhs_body" class="text-sm divide-y divide-outline-variant/10">
                            <!-- Diisi oleh JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="p-4 border-t border-outline-variant/30 bg-surface-container-lowest text-right rounded-b-3xl">
                <button onclick="document.getElementById('modalKelasDetail').classList.add('hidden')" class="btn-primary btn-sm px-6">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showKelasDetail(id) {
    const modal = document.getElementById('modalKelasDetail');
    const loading = document.getElementById('modalLoading');
    const content = document.getElementById('modalContent');
    const tbody = document.getElementById('det_mhs_body');
    
    modal.classList.remove('hidden');
    loading.classList.remove('hidden');
    content.classList.add('hidden');
    content.style.display = 'none';
    loading.style.display = 'flex';
    
    fetch('ajax_get_kelas_info.php?id=' + id)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const k = res.data.kelas;
                const m = res.data.mahasiswa;
                
                document.getElementById('det_mk_nama').textContent = k.mk_nama;
                document.getElementById('det_mk_kode').textContent = k.mk_kode;
                document.getElementById('det_sks').textContent = k.sks + ' SKS';
                document.getElementById('det_dosen').textContent = k.dosen_nama;
                document.getElementById('det_waktu').textContent = k.hari + ', ' + k.jam_mulai.substring(0,5) + ' - ' + k.jam_selesai.substring(0,5);
                document.getElementById('det_ruang').textContent = k.nama_ruangan || 'TBD';
                document.getElementById('det_jml_mhs').textContent = m.length + ' Mahasiswa';
                document.getElementById('det_export_btn').href = 'export_kelas_peserta.php?id=' + id;
                
                tbody.innerHTML = '';
                if (m.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="py-6 px-4 text-center text-on-surface-variant italic">Belum ada mahasiswa yang terdaftar (KRS Disetujui) di kelas ini.</td></tr>';
                } else {
                    m.forEach((mhs, idx) => {
                        const tr = document.createElement('tr');
                        tr.className = 'hover:bg-surface-variant/10 transition-colors';
                        tr.innerHTML = `
                            <td class="py-2 px-4 text-center text-on-surface-variant">${idx + 1}</td>
                            <td class="py-2 px-4 font-bold text-secondary">${mhs.nim}</td>
                            <td class="py-2 px-4 font-medium text-on-surface">${mhs.nama}</td>
                            <td class="py-2 px-4 text-xs text-on-surface-variant">${mhs.program_studi}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
                
                loading.style.display = 'none';
                loading.classList.add('hidden');
                content.classList.remove('hidden');
                content.style.display = 'flex';
            } else {
                alert(res.error || 'Terjadi kesalahan saat memuat data kelas.');
                modal.classList.add('hidden');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Kesalahan jaringan.');
            modal.classList.add('hidden');
        });
}
document.addEventListener('DOMContentLoaded', () => {
    const modalKelasDetail = document.getElementById('modalKelasDetail');
    if (modalKelasDetail) document.body.appendChild(modalKelasDetail);
});
</script>

<?php include 'components/footer.php'; ?>
