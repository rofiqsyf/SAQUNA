<?php
require_once __DIR__ . '/../autoload.php';
use Src\Auth;
use Config\Database;

// Hanya Dosen, Mahasiswa, atau Operator yang boleh mengakses
Auth::startSession();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

$jadwalId = (int)$_GET['id'];
$pdo = Database::getConnection();

try {
    // 1. Ambil Data Jadwal & Mata Kuliah
    $sqlJadwal = "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.semester, 
                         m.kode as mk_kode, m.nama as mk_nama, m.sks, 
                         d.nama as dosen_nama, r.nama_ruangan, j.dosen_id, j.matakuliah_id
                  FROM jadwal_kelas j
                  JOIN mata_kuliah m ON j.matakuliah_id = m.id
                  JOIN dosen d ON j.dosen_id = d.id
                  LEFT JOIN ruangan r ON j.ruangan_id = r.id
                  WHERE j.id = ?";
    $stmtJ = $pdo->prepare($sqlJadwal);
    $stmtJ->execute([$jadwalId]);
    $jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

    if (!$jadwal) {
        http_response_code(404);
        echo json_encode(['error' => 'Jadwal tidak ditemukan']);
        exit;
    }

    // 2. Ambil Daftar Mahasiswa
    // Filter KRS berdasarkan dosen_id, matakuliah_id, dan semester yang sama dengan jadwal
    $sqlMhs = "SELECT mhs.nim, mhs.nama, mhs.program_studi 
               FROM krs k 
               JOIN mahasiswa mhs ON k.mahasiswa_id = mhs.id 
               WHERE k.dosen_id = ? AND k.matakuliah_id = ? 
                 AND k.semester_aktif = ? AND k.status = 'Disetujui'
               ORDER BY mhs.nim ASC";
    $stmtM = $pdo->prepare($sqlMhs);
    // Kita asumsikan semester_aktif di KRS menggunakan format 'Ganjil'/'Genap' 
    // Sama dengan field 'semester' di jadwal_kelas.
    $stmtM->execute([$jadwal['dosen_id'], $jadwal['matakuliah_id'], $jadwal['semester']]);
    $mahasiswa = $stmtM->fetchAll(PDO::FETCH_ASSOC);

    // Kirim Respon
    echo json_encode([
        'success' => true,
        'data' => [
            'kelas' => $jadwal,
            'mahasiswa' => $mahasiswa
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
