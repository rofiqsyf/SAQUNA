<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireDosen();

$repo = new DosenRepository();
$dosen = $repo->getDosenByUserId($_SESSION['user_id']);

if (!$dosen) {
    die("Data dosen tidak ditemukan.");
}

$dosenId = (int)$dosen['id'];
$matakuliahId = isset($_GET['matakuliah_id']) ? (int)$_GET['matakuliah_id'] : 0;
$stmtSmt = \Config\Database::getConnection()->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
$semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
$semester = $_GET['semester'] ?? $semesterAktif;

if ($matakuliahId === 0) {
    die("Mata kuliah tidak valid.");
}

// Get the actual class info to name the file properly
$semuaMk = $repo->getAllMataKuliah();
$mkInfo = array_filter($semuaMk, fn($m) => (int)$m['id'] === $matakuliahId);
if (empty($mkInfo)) {
    die("Data mata kuliah tidak ditemukan.");
}
$mkInfo = current($mkInfo);

$mahasiswaKelas = $repo->getDaftarMahasiswaKelas($dosenId, $matakuliahId, $semester);
$rekapSemuaPresensi = $repo->getRekapPresensiKelas($dosenId, $matakuliahId, $semester);

// Organize attendance into a lookup table: $attendanceData[nim][pertemuan] = status
$attendanceData = [];
foreach ($rekapSemuaPresensi as $p) {
    $attendanceData[$p['nim']][$p['pertemuan_ke']] = $p['status'];
}

$filename = "Rekap_Presensi_" . str_replace(' ', '_', $mkInfo['kode']) . "_" . $semester . "_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Write Header
$header = ['No', 'NIM', 'Nama Mahasiswa'];
for ($i = 1; $i <= 16; $i++) {
    $header[] = 'P' . $i;
}
$header[] = 'Total Hadir';
$header[] = 'Total Alpha/Kosong';
fputcsv($output, $header);

// Write Data
$no = 1;
foreach ($mahasiswaKelas as $mhs) {
    $nim = $mhs['nim'];
    $row = [$no++, $nim, $mhs['mahasiswa_nama']];
    $totalHadir = 0;
    $totalAlpha = 0;

    for ($i = 1; $i <= 16; $i++) {
        $status = $attendanceData[$nim][$i] ?? '-';
        if ($status === 'Hadir') {
            $totalHadir++;
            $row[] = 'H';
        } elseif ($status === 'Alpha') {
            $totalAlpha++;
            $row[] = 'A';
        } else {
            $totalAlpha++;
            $row[] = '-';
        }
    }
    
    $row[] = $totalHadir;
    $row[] = $totalAlpha;
    fputcsv($output, $row);
}

fclose($output);
exit();
