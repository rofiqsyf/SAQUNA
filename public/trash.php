<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireLogin();
Auth::requireOperator();

$repo = new DosenRepository();

// Handle restore logic
if (isset($_GET['restore_id'])) {
    $id = (int)$_GET['restore_id'];
    if ($repo->restore($id, $_SESSION['user_id'])) {
        header("Location: trash.php?msg=restored");
    } else {
        header("Location: trash.php?msg=restore_failed");
    }
    exit;
}

$dosens = $repo->getTrashed();
$role = Auth::getRole();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sampah Dosen - SAQUNA</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'components/header.php'; ?>

<div class="container animate-fade-in">
    <div class="d-flex justify-between align-center mb-4">
        <div>
            <h1>Tempat Sampah</h1>
            <p class="text-on-surface-variant opacity-80">Data dosen yang dihapus (soft delete).</p>
        </div>
        <a href="index.php" class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center">Kembali ke Daftar Dosen</a>
    </div>

    <div class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">NIDN</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Nama Dosen</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Program Studi</th>
                    <th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">Dihapus Pada</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dosens)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Tidak ada data di tempat sampah.</td></tr>
                <?php else: ?>
                    <?php foreach ($dosens as $d): ?>
                    <tr>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><strong><?= htmlspecialchars($d['nidn']) ?></strong></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($d['nama']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($d['program_studi']) ?></td>
                        <td class="px-4 py-3 border-b border-outline-variant/20 font-body-md"><?= htmlspecialchars($d['deleted_at']) ?></td>
                        <td class="text-right">
                            <a href="trash.php?restore_id=<?= $d['id'] ?>" class="btn btn-sm btn-success" onclick="return confirm('Yakin ingin memulihkan data ini?');" style="background: var(--secondary); color: white;">Pulihkan</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<footer class="footer">
    <p>&copy; 2026 SAQUNA UNSIQ - Ujian Tengah Semester</p>
</footer>

