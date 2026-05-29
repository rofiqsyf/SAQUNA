<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\MahasiswaRepository;

Auth::requireMahasiswa();
$repo = new MahasiswaRepository();

// Get Mahasiswa Data
$mhs = $repo->getMahasiswaProfile($_SESSION['user_id']);
if (!$mhs) {
    die("Data mahasiswa tidak ditemukan.");
}

// Fetch Full Schedule
$jadwalKuliah = $repo->getJadwalKuliah($mhs['id'], $repo->getSemesterAktif()); // Simulasi Semester Ganjil

// Kelompokkan jadwal berdasarkan hari
$jadwalPerHari = [
    'Senin' => [],
    'Selasa' => [],
    'Rabu' => [],
    'Kamis' => [],
    'Jumat' => [],
    'Sabtu' => []
];

foreach ($jadwalKuliah as $j) {
    if (isset($jadwalPerHari[$j['hari']])) {
        $jadwalPerHari[$j['hari']][] = $j;
    }
}

$title = "Jadwal Kuliah - SAQUNA";
include 'components/header.php';
?>

<style>
/* Scoped Jadwal Kuliah Styles - No Tailwind Dependency */
.jadwal-wrapper {
    font-family: 'Inter', sans-serif;
    color: #1f2937;
    margin-top: 1rem;
}
.jadwal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}
.jadwal-title h2 {
    font-family: 'Outfit', sans-serif;
    font-size: 2.5rem;
    color: #111827;
    margin-bottom: 0.25rem;
}
.jadwal-title p {
    color: #6b7280;
    font-size: 1.125rem;
}
.jadwal-layout {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 2rem;
    align-items: start;
}

/* Glass Card */
.jadwal-glass-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

/* Day Selector */
.day-btn {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-radius: 16px;
    border: 1px solid rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: all 0.3s ease;
    margin-bottom: 0.75rem;
    font-family: 'Inter', sans-serif;
}
.day-btn:last-child {
    margin-bottom: 0;
}
.day-btn:hover {
    background: rgba(255,255,255,0.8);
}
.day-btn.active {
    background: #4caf50; 
    border-color: #4caf50;
    color: white;
    box-shadow: 0 10px 15px -3px rgba(76, 175, 80, 0.3);
}
.day-btn .day-name {
    font-size: 1rem;
    font-weight: 500;
    color: #4b5563;
}
.day-btn.active .day-name {
    color: white;
    font-weight: 600;
}
.day-btn .day-date {
    font-family: 'Outfit', sans-serif;
    font-size: 1.25rem;
    font-weight: 700;
}

/* Timeline Cards */
.schedule-day {
    display: none;
}
.schedule-day.active {
    display: block;
}
.timeline-card {
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 32px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1.5rem;
    transition: all 0.3s ease;
}
.timeline-card:hover {
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05);
}
.timeline-card.ongoing {
    box-shadow: 0 20px 40px -15px rgba(76, 175, 80, 0.3);
    position: relative;
    overflow: hidden;
}
.timeline-card.ongoing::before {
    content: '';
    position: absolute;
    left: -4px;
    top: 1rem;
    bottom: 1rem;
    width: 6px;
    border-radius: 4px;
    background: #4caf50;
    animation: pulse 2s infinite;
}
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.timeline-info {
    flex: 1;
}
.timeline-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.status-badge {
    padding: 0.375rem 1rem;
    border-radius: 999px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}
.status-badge.ongoing {
    background: rgba(76, 175, 80, 0.1);
    color: #4caf50;
}
.status-badge.ongoing .dot {
    width: 10px;
    height: 10px;
    background: #4caf50;
    border-radius: 50%;
    animation: pulse 2s infinite;
}
.status-badge.upcoming {
    background: #f3f4f6;
    color: #4b5563;
}
.status-badge.later {
    background: #f3f4f6;
    color: #4b5563;
}
.sks-badge {
    padding: 0.375rem 0.75rem;
    background: rgba(76, 175, 80, 0.1);
    color: #4caf50;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 700;
}
.mk-title {
    font-family: 'Outfit', sans-serif;
    font-size: 1.75rem;
    color: #111827;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}
.details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
.detail-item {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.detail-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #f3f4f6;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #4b5563;
}
.detail-text label {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 0.25rem;
}
.detail-text p {
    font-weight: 600;
    color: #1f2937;
    margin: 0;
}

/* Dosen Box */
.dosen-box {
    width: 250px;
    background: rgba(255,255,255,0.5);
    border: 1px solid rgba(255, 255, 255, 0.4);
    border-radius: 24px;
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
}
.dosen-box label {
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6b7280;
    margin-bottom: 1rem;
    letter-spacing: 0.05em;
}
.dosen-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    margin-bottom: 1rem;
    border: 2px solid rgba(76, 175, 80, 0.2);
}
.dosen-name {
    font-weight: 700;
    color: #111827;
    margin-bottom: 1rem;
    line-height: 1.2;
}
.dosen-link {
    color: #4caf50;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}
.dosen-link:hover {
    text-decoration: underline;
}

/* Later Card (Simplified) */
.timeline-card.later {
    opacity: 0.6;
    padding: 1.5rem 2rem;
    align-items: center;
}
.timeline-card.later:hover {
    opacity: 1;
}
.later-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}
.later-left {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.later-right {
    display: flex;
    align-items: center;
    gap: 2rem;
}
.later-detail {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #4b5563;
    font-weight: 600;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #6b7280;
}
.empty-state .material-symbols-outlined {
    font-size: 3rem;
    opacity: 0.5;
    margin-bottom: 1rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .jadwal-layout {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 768px) {
    .jadwal-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .timeline-card {
        flex-direction: column;
    }
    .dosen-box {
        width: 100%;
    }
    .details-grid {
        grid-template-columns: 1fr;
    }
    .later-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    .later-right {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
}
</style>

<?php
// Calculate dates for current week
$dates = [];
$currentDayOfWeek = date('N'); // 1 (Mon) to 7 (Sun)
$mondayTimestamp = strtotime('-' . ($currentDayOfWeek - 1) . ' days');
$daysMap = ['Senin' => 0, 'Selasa' => 1, 'Rabu' => 2, 'Kamis' => 3, 'Jumat' => 4, 'Sabtu' => 5];
foreach ($daysMap as $h => $offset) {
    $dates[$h] = date('d', strtotime("+$offset days", $mondayTimestamp));
}
?>

<div class="jadwal-wrapper container">
    <!-- Header Section -->
    <header class="jadwal-header">
        <div class="jadwal-title">
            <h2>Jadwal Kuliah</h2>
            <p>Semester <?= htmlspecialchars($repo->getSemesterAktif() ?? 'Berjalan') ?></p>
        </div>
        <div>
            <button onclick="window.print()" class="btn btn-primary d-flex align-center gap-2">
                <span class="material-symbols-outlined">download</span> Unduh (PDF)
            </button>
        </div>
    </header>

    <!-- Main Layout Split -->
    <div class="jadwal-layout">
        <!-- Left Column: Day Selector -->
        <div>
            <div class="jadwal-glass-card">
                <h3 style="font-family: 'Outfit'; font-size: 1.25rem; margin-bottom: 1.5rem;">Pilih Hari</h3>
                <div id="day-selector">
                    <?php 
                    $first = true;
                    foreach ($jadwalPerHari as $hari => $jadwals): 
                        if (empty($jadwals) && $hari === 'Sabtu') continue; // Hide Saturday if empty
                        $tgl = $dates[$hari] ?? '';
                    ?>
                    <button data-hari="<?= htmlspecialchars($hari) ?>" class="day-btn <?= $first ? 'active' : '' ?>">
                        <span class="day-name"><?= htmlspecialchars($hari) ?></span>
                        <span class="day-date"><?= $tgl ?></span>
                    </button>
                    <?php 
                    $first = false;
                    endforeach; 
                    ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Schedule Timeline -->
        <div id="schedule-container">
            <?php 
            $firstDay = true;
            foreach ($jadwalPerHari as $hari => $jadwals): 
                if (empty($jadwals) && $hari === 'Sabtu') continue;
            ?>
            <div class="schedule-day <?= $firstDay ? 'active' : '' ?>" id="schedule-<?= htmlspecialchars($hari) ?>">
                <?php if (empty($jadwals)): ?>
                    <div class="jadwal-glass-card empty-state">
                        <span class="material-symbols-outlined">hotel</span>
                        <p>Tidak ada jadwal kuliah hari <?= htmlspecialchars($hari) ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($jadwals as $idx => $j): 
                        $cardStyle = $idx === 0 ? 'ongoing' : ($idx === 1 ? 'upcoming' : 'later');
                        $jamRaw = $j['jam'] ?? '00:00 - 00:00';
                        $jamParts = explode('-', $jamRaw);
                        $jamMulai = trim($jamParts[0] ?? '00:00');
                        $jamSelesai = trim($jamParts[1] ?? '00:00');
                        $dosenName = $j['dosen_nama'] ?? 'Dosen Belum Ditentukan';
                        $dosenImg = generateSvgAvatar($dosenName);
                    ?>
                        <?php if ($cardStyle === 'ongoing'): ?>
                            <!-- Ongoing Card -->
                            <div class="timeline-card ongoing" style="cursor: pointer;" onclick="showKelasDetail(<?= $j['jadwal_kelas_id'] ?? 0 ?>)">
                                <div class="timeline-info">
                                    <div class="timeline-header">
                                        <div class="status-badge ongoing">
                                            <span class="dot"></span>
                                            Sedang Berlangsung
                                        </div>
                                        <span class="sks-badge"><?= htmlspecialchars($j['sks'] ?? '2') ?> SKS</span>
                                    </div>
                                    <h3 class="mk-title"><?= htmlspecialchars($j['mk_nama']) ?></h3>
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <span class="material-symbols-outlined">schedule</span>
                                            </div>
                                            <div class="detail-text">
                                                <label>Waktu</label>
                                                <p><?= htmlspecialchars($jamMulai) ?> — <?= htmlspecialchars($jamSelesai) ?> WIB</p>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <span class="material-symbols-outlined">location_on</span>
                                            </div>
                                            <div class="detail-text">
                                                <label>Lokasi</label>
                                                <p><?= htmlspecialchars($j['ruangan'] ?? 'TBD') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dosen-box">
                                    <label>Dosen Pengampu</label>
                                    <img alt="<?= htmlspecialchars($dosenName) ?>" class="dosen-img" src="<?= $dosenImg ?>"/>
                                    <p class="dosen-name"><?= htmlspecialchars($dosenName) ?></p>
                                    <a href="#" class="dosen-link">
                                        Lihat Profil <span class="material-symbols-outlined" style="font-size: 1rem;">open_in_new</span>
                                    </a>
                                </div>
                            </div>
                        <?php elseif ($cardStyle === 'upcoming'): ?>
                            <!-- Upcoming Card -->
                            <div class="timeline-card" style="cursor: pointer;" onclick="showKelasDetail(<?= $j['jadwal_kelas_id'] ?? 0 ?>)">
                                <div class="timeline-info">
                                    <div class="timeline-header">
                                        <div class="status-badge upcoming">
                                            Akan Datang
                                        </div>
                                        <span class="sks-badge" style="background: rgba(0,0,0,0.05); color: #4b5563;"><?= htmlspecialchars($j['sks'] ?? '2') ?> SKS</span>
                                    </div>
                                    <h3 class="mk-title"><?= htmlspecialchars($j['mk_nama']) ?></h3>
                                    <div class="details-grid">
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <span class="material-symbols-outlined">schedule</span>
                                            </div>
                                            <div class="detail-text">
                                                <label>Waktu</label>
                                                <p><?= htmlspecialchars($jamMulai) ?> — <?= htmlspecialchars($jamSelesai) ?> WIB</p>
                                            </div>
                                        </div>
                                        <div class="detail-item">
                                            <div class="detail-icon">
                                                <span class="material-symbols-outlined">location_on</span>
                                            </div>
                                            <div class="detail-text">
                                                <label>Lokasi</label>
                                                <p><?= htmlspecialchars($j['ruangan'] ?? 'TBD') ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="dosen-box" style="opacity: 0.9; background: rgba(255,255,255,0.3);">
                                    <label>Dosen Pengampu</label>
                                    <img alt="<?= htmlspecialchars($dosenName) ?>" class="dosen-img" src="<?= $dosenImg ?>"/>
                                    <p class="dosen-name"><?= htmlspecialchars($dosenName) ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Later Card -->
                            <div class="timeline-card later" style="cursor: pointer;" onclick="showKelasDetail(<?= $j['jadwal_kelas_id'] ?? 0 ?>)">
                                <div class="later-info">
                                    <div class="later-left">
                                        <div class="status-badge later" style="width: max-content;">Berikutnya</div>
                                        <h3 style="font-family: 'Outfit'; font-size: 1.5rem; margin: 0;"><?= htmlspecialchars($j['mk_nama']) ?></h3>
                                    </div>
                                    <div class="later-right">
                                        <div class="later-detail">
                                            <span class="material-symbols-outlined">schedule</span>
                                            <span><?= htmlspecialchars($jamMulai) ?> — <?= htmlspecialchars($jamSelesai) ?> WIB</span>
                                        </div>
                                        <div class="later-detail">
                                            <span class="material-symbols-outlined">location_on</span>
                                            <span><?= htmlspecialchars($j['ruangan'] ?? 'TBD') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php 
            $firstDay = false;
            endforeach; 
            ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const dayButtons = document.querySelectorAll('.day-btn');
        const scheduleDays = document.querySelectorAll('.schedule-day');

        // Micro-interactions for desktop
        dayButtons.forEach(btn => {
            btn.addEventListener('mousedown', function() {
                this.style.transform = 'scale(0.98)';
            });
            btn.addEventListener('mouseup', function() {
                this.style.transform = 'scale(1)';
            });
            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

        dayButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                dayButtons.forEach(b => b.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');

                // Show target day
                const targetDay = btn.getAttribute('data-hari');
                scheduleDays.forEach(day => {
                    day.classList.remove('active');
                });
                const targetElem = document.getElementById(`schedule-${targetDay}`);
                if (targetElem) {
                    targetElem.classList.add('active');
                }
            });
        });
    });
</script>

<!-- Modal Pop-up Kelas Detail -->
<div id="modalKelasDetail" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-fade-in">
    <div class="bg-surface rounded-3xl shadow-xl w-full max-w-2xl mx-4 overflow-hidden border border-outline-variant/30 max-h-[90vh] flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center flex-shrink-0" style="background: #f9fafb;">
            <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2" style="margin: 0; font-family: 'Outfit', sans-serif;">
                <span class="material-symbols-outlined text-green-600">info</span>
                Informasi Kelas
            </h3>
            <button onclick="document.getElementById('modalKelasDetail').classList.add('hidden')" class="text-gray-400 hover:text-red-500 transition-colors" style="background: none; border: none; cursor: pointer;">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        
        <div id="modalLoading" class="p-10 flex flex-col items-center justify-center text-gray-500" style="flex: 1;">
            <span class="material-symbols-outlined animate-spin text-4xl text-green-600 mb-2">progress_activity</span>
            <p>Memuat informasi kelas...</p>
        </div>

        <div id="modalContent" class="hidden flex-col flex-1" style="overflow: hidden;">
            <!-- Info Header -->
            <div class="p-6 border-b border-gray-200" style="background: linear-gradient(to right, rgba(76, 175, 80, 0.1), transparent);">
                <div class="flex items-start justify-between gap-4" style="display: flex; justify-content: space-between;">
                    <div>
                        <h4 id="det_mk_nama" class="text-2xl font-bold text-green-700 mb-1" style="font-family: 'Outfit', sans-serif; margin-bottom: 0.5rem; margin-top: 0;">Nama Mata Kuliah</h4>
                        <div class="flex flex-wrap gap-2 text-sm text-gray-600 font-medium" style="display: flex; gap: 0.5rem;">
                            <span id="det_mk_kode" class="px-2 py-0.5 rounded border border-gray-300" style="background: rgba(255,255,255,0.7);">Kode</span>
                            <span id="det_sks" class="px-2 py-0.5 rounded border border-gray-300" style="background: rgba(255,255,255,0.7);">SKS</span>
                            <span id="det_dosen" class="px-2 py-0.5 rounded border border-gray-300 font-bold" style="background: rgba(255,255,255,0.7); color: #1f2937;">Dosen</span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="px-3 py-2 rounded-xl shadow-sm border border-gray-200 bg-white">
                            <div class="text-sm font-bold text-green-700 flex items-center gap-1 justify-end">
                                <span class="material-symbols-outlined text-[16px]">schedule</span> 
                                <span id="det_waktu">00:00 - 00:00</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1 flex items-center gap-1 justify-end">
                                <span class="material-symbols-outlined text-[14px]">meeting_room</span> 
                                <span id="det_ruang">Ruang</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Student List -->
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1" style="flex: 1;">
                <div class="flex justify-between items-center mb-3" style="display: flex; justify-content: space-between; margin-bottom: 1rem;">
                    <h5 class="font-bold text-gray-900 text-lg" style="margin: 0; font-family: 'Outfit', sans-serif;">Rekan Sekelas (Terdaftar)</h5>
                    <span id="det_jml_mhs" class="text-xs bg-green-600 text-white px-2 py-1 rounded-lg font-bold">0 Mahasiswa</span>
                </div>
                <div class="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                    <table class="w-full text-left border-collapse" style="width: 100%;">
                        <thead class="text-gray-500 text-sm font-bold border-b border-gray-200" style="background: #f9fafb;">
                            <tr>
                                <th class="py-2 px-4 w-12 text-center" style="padding: 0.5rem 1rem;">No</th>
                                <th class="py-2 px-4" style="padding: 0.5rem 1rem;">NIM</th>
                                <th class="py-2 px-4" style="padding: 0.5rem 1rem;">Nama Mahasiswa</th>
                                <th class="py-2 px-4" style="padding: 0.5rem 1rem;">Program Studi</th>
                            </tr>
                        </thead>
                        <tbody id="det_mhs_body" class="text-sm divide-y divide-gray-100" style="color: #4b5563;">
                            <!-- Diisi oleh JS -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="p-4 border-t border-gray-200 text-right rounded-b-3xl" style="background: #f9fafb;">
                <button onclick="document.getElementById('modalKelasDetail').classList.add('hidden')" class="btn btn-primary px-6" style="padding: 0.5rem 1.5rem; font-weight: 600;">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function showKelasDetail(id) {
    if (!id || id == 0) {
        alert("ID Jadwal tidak tersedia. Silakan hubungi admin.");
        return;
    }
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
                
                tbody.innerHTML = '';
                if (m.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="py-6 px-4 text-center text-gray-400 italic">Belum ada mahasiswa yang terdaftar di kelas ini.</td></tr>';
                } else {
                    const currentUsername = '<?= $_SESSION['username'] ?? '' ?>';
                    m.forEach((mhs, idx) => {
                        const tr = document.createElement('tr');
                        const isMe = mhs.nim === currentUsername;
                        if (isMe) {
                            tr.style.background = 'rgba(76, 175, 80, 0.05)';
                        }
                        tr.innerHTML = `
                            <td class="py-2 px-4 text-center border-b border-gray-100" style="padding: 0.5rem 1rem;">${idx + 1}</td>
                            <td class="py-2 px-4 font-bold border-b border-gray-100" style="padding: 0.5rem 1rem; color: ${isMe ? '#059669' : '#374151'};">${mhs.nim}</td>
                            <td class="py-2 px-4 font-medium border-b border-gray-100" style="padding: 0.5rem 1rem; color: #111827;">${mhs.nama} ${isMe ? '<span style="font-size: 10px; background: #059669; color: white; padding: 1px 4px; border-radius: 4px; margin-left: 4px;">ANDA</span>' : ''}</td>
                            <td class="py-2 px-4 text-xs border-b border-gray-100" style="padding: 0.5rem 1rem;">${mhs.program_studi}</td>
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
document.addEventListener('DOMContentLoaded', () => {
    const modalKelasDetail = document.getElementById('modalKelasDetail');
    if (modalKelasDetail) document.body.appendChild(modalKelasDetail);
});
</script>

<?php include 'components/footer.php'; ?>
