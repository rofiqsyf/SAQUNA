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

$tagihan = $repo->getTagihan((int)$mhs['id']);

$title = "Tagihan & Pembayaran";
$current_page = "mahasiswa_tagihan.php";
include 'components/header.php';
?>

<div class="mb-4">
    <h1>Informasi Keuangan</h1>
    <p class="text-on-surface-variant opacity-80">Riwayat pembayaran SPP/UKT Akademik Anda.</p>
</div>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <?php if (empty($tagihan)): ?>
            <div class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2">Belum ada tagihan pembayaran untuk Anda.</div>
        <?php else: ?>
            <div class="table-responsive-wrapper rounded-xl border border-outline-variant/30">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Tahun Ajaran</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Semester</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nominal Tagihan</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Status Pembayaran</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Waktu Transaksi</th>
                            <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tagihan as $t): ?>
                        <tr>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($t['tahun_ajaran']) ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($t['semester']) ?></td>
                            <td style="font-family: monospace; font-size: 1.1rem;">Rp <?= number_format($t['nominal'], 0, ',', '.') ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                <?php if ($t['status'] === 'Lunas'): ?>
                                    <span class="bg-secondary-fixed text-on-secondary-fixed px-3 py-1 rounded-full font-label-md text-xs">Lunas</span>
                                <?php else: ?>
                                    <span class="bg-error-container text-on-error-container px-3 py-1 rounded-full font-label-md text-xs">Belum Lunas</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= $t['waktu_bayar'] ? date('d M Y, H:i', strtotime($t['waktu_bayar'])) : '-' ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                <?php if ($t['status'] === 'Belum Lunas'): ?>
                                    <div class="flex flex-col gap-2 w-max">
                                        <button class="bg-primary hover:bg-primary/90 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1 shadow-sm" onclick="alert('Fitur integrasi Payment Gateway saat ini sedang dalam pengembangan.')">
                                            <span class="material-symbols-outlined text-[16px]">payments</span> Bayar
                                        </button>
                                        <button class="bg-error-container hover:bg-error text-on-error-container hover:text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-1 shadow-sm" onclick="alert('Pengajuan penundaan pembayaran sedang diteruskan ke biro keuangan fakultas.')">
                                            <span class="material-symbols-outlined text-[16px]">more_time</span> Ajukan Penundaan
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <button class="bg-surface-variant text-on-surface-variant px-4 py-2 rounded-lg text-sm font-medium opacity-60 cursor-not-allowed flex items-center justify-center gap-1" disabled>
                                        <span class="material-symbols-outlined text-[16px]">receipt_long</span> Cetak Kwitansi
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'components/footer.php'; ?>
