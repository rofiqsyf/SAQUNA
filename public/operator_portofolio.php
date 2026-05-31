<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();

// Ambil semua data tridharma
$penelitian = $repo->getAllPenelitian();
$pengabdian = $repo->getAllPengabdian();

$title = "Rekap Portofolio Tridharma";
$current_page = "operator_portofolio.php";
include 'components/header.php';
?>

<div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
        <h1>Rekap Portofolio Tridharma</h1>
        <p class="text-on-surface-variant font-body-lg">Pantau seluruh data Penelitian dan Pengabdian Masyarakat Dosen.</p>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
    <!-- Kolom Penelitian -->
    <div class="card p-0 overflow-hidden flex flex-col h-full border border-primary/20 shadow-md">
        <div class="bg-primary text-on-primary p-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-3xl">science</span>
                <h2 class="text-xl font-bold m-0">Rekap Penelitian Dosen</h2>
            </div>
            <span class="bg-white/20 px-3 py-1 rounded-full text-sm font-bold"><?= count($penelitian) ?> Data</span>
        </div>
        
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 max-h-[600px] bg-surface-container-lowest">
            <?php if (empty($penelitian)): ?>
                <div class="text-center py-10 text-on-surface-variant">
                    <span class="material-symbols-outlined text-5xl mb-2 opacity-50">search_off</span>
                    <p class="italic">Belum ada data penelitian dosen di sistem.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($penelitian as $p): ?>
                        <div class="bg-surface border border-outline-variant/30 p-4 rounded-xl shadow-sm hover:border-primary/50 transition-colors">
                            <div class="flex justify-between items-start mb-2 gap-2">
                                <h3 class="font-bold text-lg leading-tight flex-1"><?= htmlspecialchars($p['judul']) ?></h3>
                                <span class="bg-primary-container text-on-primary-container text-xs font-bold px-2 py-1 rounded-md whitespace-nowrap">
                                    <?= htmlspecialchars($p['jenis']) ?>
                                </span>
                            </div>
                            <p class="text-sm text-primary font-bold mb-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">person</span>
                                <?= htmlspecialchars($p['dosen_nama']) ?> (NIDN: <?= htmlspecialchars($p['nidn'] ?: '-') ?>)
                            </p>
                            <div class="flex items-center gap-4 text-sm text-on-surface-variant">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">calendar_today</span> <?= $p['tahun'] ?></span>
                                <?php if ($p['link_publikasi']): ?>
                                    <a href="<?= htmlspecialchars($p['link_publikasi']) ?>" target="_blank" class="flex items-center gap-1 text-primary hover:underline">
                                        <span class="material-symbols-outlined text-[16px]">link</span> Buka Tautan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Kolom Pengabdian -->
    <div class="card p-0 overflow-hidden flex flex-col h-full border border-secondary/20 shadow-md">
        <div class="bg-secondary text-on-secondary p-4 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-3xl">volunteer_activism</span>
                <h2 class="text-xl font-bold m-0">Rekap Pengabdian Dosen</h2>
            </div>
            <span class="bg-white/20 px-3 py-1 rounded-full text-sm font-bold"><?= count($pengabdian) ?> Data</span>
        </div>
        
        <div class="p-6 overflow-y-auto custom-scrollbar flex-1 max-h-[600px] bg-surface-container-lowest">
            <?php if (empty($pengabdian)): ?>
                <div class="text-center py-10 text-on-surface-variant">
                    <span class="material-symbols-outlined text-5xl mb-2 opacity-50">search_off</span>
                    <p class="italic">Belum ada data pengabdian dosen di sistem.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($pengabdian as $p): ?>
                        <div class="bg-surface border border-outline-variant/30 p-4 rounded-xl shadow-sm hover:border-secondary/50 transition-colors">
                            <h3 class="font-bold text-lg leading-tight mb-2"><?= htmlspecialchars($p['judul']) ?></h3>
                            <p class="text-sm text-secondary font-bold mb-2 flex items-center gap-1">
                                <span class="material-symbols-outlined text-[16px]">person</span>
                                <?= htmlspecialchars($p['dosen_nama']) ?> (NIDN: <?= htmlspecialchars($p['nidn'] ?: '-') ?>)
                            </p>
                            <div class="flex flex-col gap-1 text-sm text-on-surface-variant">
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">calendar_today</span> Tahun: <?= $p['tahun'] ?></span>
                                <?php if ($p['lokasi']): ?>
                                    <span class="flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span> <?= htmlspecialchars($p['lokasi']) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($p['deskripsi']): ?>
                                <p class="text-sm mt-3 bg-surface-variant/30 p-3 rounded-lg border border-outline-variant/20">
                                    <?= nl2br(htmlspecialchars($p['deskripsi'])) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
