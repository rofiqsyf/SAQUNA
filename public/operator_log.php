<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();
$logs = $repo->getActivityLogs(200); // Ambil 200 aktivitas terakhir

$title = "Sistem Audit & Log";
$current_page = "operator_log.php";
include 'components/header.php';
?>

<div class="mb-4 flex flex-col md:flex-row justify-between items-start md:items-end gap-3">
    <div>
        <h1>Log Aktivitas Sistem (Audit Trail)</h1>
        <p class="text-on-surface-variant opacity-80">Pantau seluruh rekam jejak aksi pengguna demi keamanan dan akuntabilitas sistem.</p>
    </div>
    <a href="operator_export_log.php" class="btn-success">
        <span class="material-symbols-outlined text-[18px]">download</span> Export CSV
    </a>
</div>

<div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <div class="overflow-x-auto rounded-xl border border-outline-variant/30">
            <table class="w-full text-left border-collapse" style="font-size: 0.9rem;">
                <thead>
                    <tr>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Waktu (Timestamp)</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Aksi</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Entitas / Tabel</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">User Pelaku</th>
                        <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Keterangan Lengkap</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="5" class="text-center">Belum ada riwayat aktivitas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="white-space: nowrap;"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                <?php 
                                $aksiColor = [
                                    'login' => 'primary',
                                    'logout' => 'secondary',
                                    'create' => 'success',
                                    'update' => 'warning',
                                    'delete' => 'danger',
                                    'restore' => 'success'
                                ];
                                $color = $aksiColor[$log['aksi']] ?? 'secondary';
                                ?>
                                <span class="badge badge-<?= $color ?>"><?= strtoupper(htmlspecialchars($log['aksi'])) ?></span>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><code><?= htmlspecialchars($log['entitas']) ?></code> <small class="text-on-surface-variant opacity-80">ID: <?= htmlspecialchars((string)$log['entitas_id']) ?></small></td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">
                                <strong><?= htmlspecialchars($log['username'] ?? 'System') ?></strong><br>
                                <small class="text-on-surface-variant opacity-80">[<?= strtoupper(htmlspecialchars($log['role'] ?? 'SYSTEM')) ?>]</small>
                            </td>
                            <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($log['keterangan'] ?? '-') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>
