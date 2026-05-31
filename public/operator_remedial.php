<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::requireOperator();

$pdo = Database::getConnection();
$userId = $_SESSION['user_id'] ?? null;

// Ambil data statistik mata kuliah yang perlu diremedial
$sql = "SELECT
            sub.matakuliah_id,
            sub.kode,
            sub.nama,
            sub.prodi,
            sub.sks,
            COUNT(sub.mahasiswa_id) as jumlah_mengulang
        FROM (
            SELECT 
                k.mahasiswa_id, 
                k.matakuliah_id,
                mk.kode,
                mk.nama,
                mk.prodi,
                mk.sks,
                MIN(FIELD(k.nilai_huruf, 'A', 'B', 'C', 'D', 'E')) as best_score_idx
            FROM krs k
            JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
            WHERE k.nilai_huruf IS NOT NULL AND k.status = 'Disetujui'
            GROUP BY k.mahasiswa_id, k.matakuliah_id, mk.kode, mk.nama, mk.prodi, mk.sks
        ) sub
        WHERE sub.best_score_idx >= 3
        GROUP BY sub.matakuliah_id, sub.kode, sub.nama, sub.prodi, sub.sks
        ORDER BY jumlah_mengulang DESC";

$stmt = $pdo->query($sql);
$remedialStats = $stmt->fetchAll();

$current_page = 'operator_remedial.php';
$page_title = 'Manajemen Remedial & Semester Pendek';
require_once __DIR__ . '/components/header.php';
?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl md:text-3xl font-black text-primary">Rekapitulasi Remedial</h1>
        <p class="text-on-surface/70 mt-1">Daftar mata kuliah dengan jumlah mahasiswa yang belum lulus (Nilai C, D, E).</p>
    </div>
</div>

<div class="bg-primary-container text-on-primary-container p-4 rounded-2xl mb-6 flex gap-3 shadow-sm border border-primary/20">
    <span class="material-symbols-outlined text-2xl">info</span>
    <div>
        <p class="font-bold">Informasi Penjadwalan</p>
        <p class="text-sm">Gunakan data ini untuk mempertimbangkan pembukaan kelas pada <strong>Semester Pendek</strong> atau penambahan kapasitas pada <strong>Semester Reguler</strong> berikutnya.</p>
    </div>
</div>

<div class="card bg-surface rounded-2xl shadow-sm border border-outline-variant/30 overflow-hidden">
    <?php if (empty($remedialStats)): ?>
    <div class="p-10 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl opacity-30 mb-2">task_alt</span>
        <p class="font-bold">Semua mahasiswa lulus dengan baik!</p>
        <p class="text-sm">Tidak ada mahasiswa yang mendapatkan nilai C ke bawah.</p>
    </div>
    <?php else: ?>
    <div class="table-responsive-wrapper">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-surface-container-low">
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 w-12">No</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Kode MK</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Mata Kuliah</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">SKS</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30">Program Studi</th>
                    <th class="px-5 py-3 text-on-surface font-label-md text-sm border-b border-outline-variant/30 text-center">Jumlah Mahasiswa Mengulang</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/20">
                <?php $no = 1; foreach ($remedialStats as $r): ?>
                <tr class="hover:bg-surface-variant/10 transition-colors">
                    <td class="px-5 py-4 text-sm text-on-surface-variant text-center"><?= $no++ ?></td>
                    <td class="px-5 py-4 font-bold text-primary text-sm"><?= htmlspecialchars($r['kode']) ?></td>
                    <td class="px-5 py-4 font-bold text-on-surface"><?= htmlspecialchars($r['nama']) ?></td>
                    <td class="px-5 py-4 text-sm font-medium"><?= (int)$r['sks'] ?></td>
                    <td class="px-5 py-4 text-sm text-on-surface-variant"><?= htmlspecialchars($r['prodi']) ?></td>
                    <td class="px-5 py-4 text-center">
                        <span class="px-4 py-1.5 rounded-full text-sm font-black <?= $r['jumlah_mengulang'] > 10 ? 'bg-error text-white' : 'bg-secondary-container text-on-secondary-container' ?>">
                            <?= (int)$r['jumlah_mengulang'] ?> Mahasiswa
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/components/footer.php'; ?>
