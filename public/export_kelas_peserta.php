<?php
require_once __DIR__ . '/../autoload.php';
use Src\Auth;
use Config\Database;

Auth::startSession();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['dosen', 'operator'])) {
    die("Akses ditolak. Hanya Dosen dan Operator yang dapat mengekspor data.");
}

if (!isset($_GET['id'])) {
    die("ID jadwal tidak ditemukan.");
}

$jadwalId = (int)$_GET['id'];
$pdo = Database::getConnection();

// 1. Ambil Data Jadwal & Mata Kuliah
$sqlJadwal = "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.semester, 
                     m.kode as mk_kode, m.nama as mk_nama, m.sks, 
                     d.nama as dosen_nama, j.dosen_id, j.matakuliah_id
              FROM jadwal_kelas j
              JOIN mata_kuliah m ON j.matakuliah_id = m.id
              JOIN dosen d ON j.dosen_id = d.id
              WHERE j.id = ?";
$stmtJ = $pdo->prepare($sqlJadwal);
$stmtJ->execute([$jadwalId]);
$jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

if (!$jadwal) {
    die("Jadwal tidak ditemukan.");
}

// Validasi Keamanan (IDOR Check)
if ($_SESSION['role'] === 'dosen') {
    $stmtCekDosen = $pdo->prepare("SELECT id FROM dosen WHERE user_id = ?");
    $stmtCekDosen->execute([$_SESSION['user_id']]);
    $dosenLog = $stmtCekDosen->fetch(PDO::FETCH_ASSOC);
    
    if (!$dosenLog || $dosenLog['id'] != $jadwal['dosen_id']) {
        die("403 Forbidden: Akses ditolak. Anda tidak berhak mengekspor data peserta dari kelas dosen lain.");
    }
}

// 2. Ambil Daftar Mahasiswa
$sqlMhs = "SELECT mhs.nim, mhs.nama, mhs.program_studi 
           FROM krs k 
           JOIN mahasiswa mhs ON k.mahasiswa_id = mhs.id 
           WHERE k.dosen_id = ? AND k.matakuliah_id = ? 
             AND k.semester_aktif = ? AND k.status = 'Disetujui'
           ORDER BY mhs.nim ASC";
$stmtM = $pdo->prepare($sqlMhs);
$stmtM->execute([$jadwal['dosen_id'], $jadwal['matakuliah_id'], $jadwal['semester']]);
$mahasiswa = $stmtM->fetchAll(PDO::FETCH_ASSOC);

// Bersihkan output buffer sebelum output CSV
if (ob_get_length()) ob_clean();

$filename = "Peserta_Kelas_" . preg_replace('/[^A-Za-z0-9\-]/', '_', $jadwal['mk_nama']) . "_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Header Kelas
fputcsv($output, ["Mata Kuliah", $jadwal['mk_nama'] . ' (' . $jadwal['mk_kode'] . ')']);
fputcsv($output, ["Dosen Pengampu", $jadwal['dosen_nama']]);
fputcsv($output, ["Jadwal", $jadwal['hari'] . ', ' . substr($jadwal['jam_mulai'], 0, 5) . ' - ' . substr($jadwal['jam_selesai'], 0, 5)]);
fputcsv($output, ["Total Peserta", count($mahasiswa) . ' Mahasiswa']);
fputcsv($output, []); // Baris kosong

// Header Tabel Peserta
fputcsv($output, ['No', 'NIM', 'Nama Mahasiswa', 'Program Studi']);

// Data Peserta
$no = 1;
foreach ($mahasiswa as $mhs) {
    fputcsv($output, [
        $no++,
        $mhs['nim'],
        $mhs['nama'],
        $mhs['program_studi']
    ]);
}

fclose($output);
exit;
