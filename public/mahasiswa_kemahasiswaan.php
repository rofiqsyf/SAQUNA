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

$pengumumanEvent = $repo->getPengumumanByRole('Event');
$pengumumanAkademik = $repo->getPengumumanByRole('Umum');

$title = "Kemahasiswaan - SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md flex justify-between items-end">
    <div>
        <h2 class="font-display-lg text-display-lg text-primary">Informasi Kemahasiswaan</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Update terkini mengenai event kampus, lomba, PKM, dan kegiatan mahasiswa lainnya.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    <!-- Kolom Utama (Span 8) -->
    <section class="lg:col-span-8 space-y-stack-md">
        
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <h3 class="font-headline-md text-headline-md text-primary mb-stack-md flex items-center gap-2">
                <span class="material-symbols-outlined">campaign</span> Papan Event Kampus
            </h3>
            
            <?php if (empty($pengumumanEvent)): ?>
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-on-surface-variant opacity-40 mb-2">event_busy</span>
                    <p class="text-on-surface-variant opacity-60">Belum ada informasi event kampus terbaru.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pengumumanEvent as $event): ?>
                    <div class="bg-surface-container-lowest p-stack-md rounded-2xl border border-outline-variant/30 hover:shadow-md transition-shadow relative overflow-hidden group">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-secondary"></div>
                        <div class="ml-2">
                            <div class="flex justify-between items-start mb-2">
                                <h4 class="font-title-lg text-primary group-hover:text-secondary transition-colors"><?= htmlspecialchars($event['judul']) ?></h4>
                                <span class="bg-secondary-container text-on-secondary-container px-3 py-1 rounded-full text-xs font-label-md">Event</span>
                            </div>
                            <p class="font-body-md text-on-surface-variant mb-4 whitespace-pre-wrap"><?= htmlspecialchars($event['isi']) ?></p>
                            <div class="flex items-center gap-2 text-on-surface-variant opacity-60 text-xs font-label-md">
                                <span class="material-symbols-outlined text-[16px]">schedule</span> Dipublikasikan: <?= date('d M Y, H:i', strtotime($event['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Kolom Samping (Span 4) -->
    <section class="lg:col-span-4 space-y-stack-md">
        
        <!-- Call to action / Pintasan -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 bg-gradient-to-br from-secondary-container to-white relative overflow-hidden">
            <span class="material-symbols-outlined absolute -bottom-5 -right-5 text-8xl text-secondary opacity-10">rocket_launch</span>
            <h3 class="font-headline-sm text-secondary font-bold mb-2 relative z-10">Program Kreativitas Mahasiswa (PKM)</h3>
            <p class="font-body-sm text-on-surface-variant mb-4 relative z-10">Ayo tuangkan ide kreatif dan inovatifmu. Pendaftaran PKM dan P2MW telah dibuka di portal Kemahasiswaan Universitas.</p>
            <button class="w-full bg-secondary hover:bg-on-secondary-fixed-variant text-on-secondary py-3 rounded-xl font-label-md shadow-md transition-all relative z-10">
                Panduan Pendaftaran
            </button>
        </div>

        <!-- Info Akademik Ringkas -->
        <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40">
            <h3 class="font-headline-sm text-primary mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined">info</span> Info Akademik
            </h3>
            <div class="space-y-3">
                <?php if (empty($pengumumanAkademik)): ?>
                    <p class="text-on-surface-variant opacity-60 text-sm">Tidak ada info.</p>
                <?php else: foreach (array_slice($pengumumanAkademik, 0, 3) as $akd): ?>
                    <div class="pb-3 border-b border-outline-variant/20 last:border-0 last:pb-0">
                        <h4 class="font-label-md font-bold text-on-surface line-clamp-1"><?= htmlspecialchars($akd['judul']) ?></h4>
                        <small class="text-on-surface-variant opacity-70"><?= date('d M Y', strtotime($akd['created_at'])) ?></small>
                    </div>
                <?php endforeach; endif; ?>
            </div>
            <a href="mahasiswa_dashboard.php" class="inline-block mt-4 text-primary font-label-md hover:underline">Ke Dashboard &rarr;</a>
        </div>
        
    </section>
</div>

<?php include 'components/footer.php'; ?>
