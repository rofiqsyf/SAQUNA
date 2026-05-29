<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

Auth::requireDosen();

header('Content-Type: application/json');

$sesiId = (int)($_GET['sesi_id'] ?? 0);
if ($sesiId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid Sesi ID']);
    exit;
}

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId($_SESSION['user_id']);
if (!$dosen) {
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

// Validasi kepemilikan: sesiId harus milik dosen yang sedang login
$pdo = Database::getConnection();
$stmtCek = $pdo->prepare("SELECT id FROM sesi_presensi WHERE id = ? AND dosen_id = ? AND status = 'Buka'");
$stmtCek->execute([$sesiId, (int)$dosen['id']]);
if (!$stmtCek->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan, tidak milik Anda, atau sudah ditutup.']);
    exit;
}

$token = $repo->generateTokenQr($sesiId, 15); // Valid 15 detik
echo json_encode(['success' => true, 'token' => $token]);
