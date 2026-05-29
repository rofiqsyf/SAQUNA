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

$pengumumanBeasiswa = $repo->getPengumumanByRole('Beasiswa');
$riwayatBeasiswa = $repo->getRiwayatBeasiswa($mhs['id']);

$title = "Beasiswa - SAQUNA";
include 'components/header.php';
?>

<div class="mb-stack-md flex justify-between items-end">
    <div>
        <h2 class="font-display-lg text-display-lg text-primary">Informasi Beasiswa</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">Temukan peluang pendanaan pendidikan dan kelola riwayat beasiswa Anda.</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-gutter">
    
    <!-- Status Beasiswa (Span 12) -->
    <section class="lg:col-span-12 mb-stack-md">
        <h3 class="font-headline-md text-headline-md text-primary mb-stack-sm flex items-center gap-2">
            <span class="material-symbols-outlined">verified</span> Status Penerimaan Beasiswa Anda
        </h3>
        
        <?php if (empty($riwayatBeasiswa)): ?>
            <div class="glass-panel rounded-3xl p-stack-md shadow-sm border border-white/40 flex items-center justify-center bg-tertiary-container/10">
                <p class="text-on-surface-variant opacity-70">Anda belum terdaftar sebagai penerima beasiswa manapun saat ini.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-stack-md">
                <?php foreach ($riwayatBeasiswa as $b): 
                    $bgClass = $b['status'] === 'Aktif' ? 'from-tertiary to-primary bg-gradient-to-br text-white' : 'bg-surface-container-highest text-on-surface';
                    $icon = $b['status'] === 'Aktif' ? 'workspace_premium' : 'history';
                ?>
                <div class="rounded-3xl p-stack-md shadow-lg border border-white/20 relative overflow-hidden <?= $bgClass ?>">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-white/20 rounded-full blur-xl"></div>
                    <div class="flex justify-between items-start relative z-10 mb-4">
                        <span class="material-symbols-outlined text-4xl opacity-80"><?= $icon ?></span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold <?= $b['status'] === 'Aktif' ? 'bg-white/20 text-white' : 'bg-outline-variant/30 text-on-surface-variant' ?>">
                            <?= htmlspecialchars($b['status']) ?>
                        </span>
                    </div>
                    <div class="relative z-10">
                        <h4 class="font-title-lg font-bold mb-1 line-clamp-2"><?= htmlspecialchars($b['nama_beasiswa']) ?></h4>
                        <p class="font-body-sm opacity-80">T.A. <?= htmlspecialchars($b['tahun']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Informasi Beasiswa Terbaru (Span 12) -->
    <section class="lg:col-span-12">
        <div class="glass-panel rounded-3xl p-stack-lg shadow-sm border border-white/40">
            <h3 class="font-headline-md text-headline-md text-tertiary mb-stack-md flex items-center gap-2">
                <span class="material-symbols-outlined">campaign</span> Penawaran Beasiswa Terbuka
            </h3>
            
            <?php if (empty($pengumumanBeasiswa)): ?>
                <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 text-center">
                    <p class="text-on-surface-variant opacity-60">Belum ada penawaran beasiswa terbaru dari kampus.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-stack-md">
                    <?php foreach ($pengumumanBeasiswa as $info): ?>
                    <div class="bg-surface-container-lowest p-stack-md rounded-2xl border border-outline-variant/30 hover:shadow-md transition-shadow flex flex-col h-full">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-title-lg text-tertiary font-bold"><?= htmlspecialchars($info['judul']) ?></h4>
                        </div>
                        <p class="font-body-md text-on-surface-variant mb-4 whitespace-pre-wrap flex-1"><?= htmlspecialchars($info['isi']) ?></p>
                        <div class="flex justify-between items-center mt-auto pt-4 border-t border-outline-variant/20">
                            <div class="flex items-center gap-2 text-on-surface-variant opacity-60 text-xs font-label-md">
                                <span class="material-symbols-outlined text-[16px]">schedule</span> <?= date('d M Y', strtotime($info['created_at'])) ?>
                            </div>
                            <button class="text-tertiary font-label-md hover:underline flex items-center gap-1">
                                Pelajari <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include 'components/footer.php'; ?>
