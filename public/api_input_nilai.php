<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;
use Config\Database;

header('Content-Type: application/json');

Auth::startSession();
if (!Auth::isDosen()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!Auth::validateCsrf($input['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit;
}

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId((int)($_SESSION['user_id'] ?? 0));
if (!$dosen) {
    echo json_encode(['success' => false, 'message' => 'Data dosen tidak ditemukan.']);
    exit;
}
$dosenId = (int)$dosen['id'];

$krsId = (int)($input['krs_id'] ?? 0);
$nTugas = (float)($input['tugas'] ?? 0);
$nUts = (float)($input['uts'] ?? 0);
$nUas = (float)($input['uas'] ?? 0);
$nPraktikum = (float)($input['praktikum'] ?? 0);

if ($krsId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid KRS ID.']);
    exit;
}

// Hitung nilai akhir (Contoh bobot: Tugas 20%, UTS 30%, UAS 40%, Praktikum 10%)
$nilaiAkhir = ($nTugas * 0.20) + ($nUts * 0.30) + ($nUas * 0.40) + ($nPraktikum * 0.10);

// Konversi ke Huruf
if ($nilaiAkhir >= 85) $huruf = 'A';
elseif ($nilaiAkhir >= 70) $huruf = 'B';
elseif ($nilaiAkhir >= 55) $huruf = 'C';
elseif ($nilaiAkhir >= 40) $huruf = 'D';
else $huruf = 'E';

if ($repo->saveNilaiKomprehensif($dosenId, $krsId, $nTugas, $nUts, $nUas, $nPraktikum, $huruf)) {
    // Audit log per baris mungkin terlalu banyak (opsional, kita matikan atau log dengan detail minim)
    // Auth::logActivity($_SESSION['user_id'], 'update', 'krs', $krsId, "Auto-save nilai KRS $krsId", Database::getConnection());
    echo json_encode([
        'success' => true, 
        'huruf' => $huruf, 
        'nilai_akhir' => round($nilaiAkhir, 2)
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan atau Anda tidak berhak mengubah nilai ini.']);
}
