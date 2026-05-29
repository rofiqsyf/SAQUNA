<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();

// Ambil parameter filter dari GET
$filters = [
    'search' => $_GET['search'] ?? '',
    'fakultas' => $_GET['fakultas'] ?? '',
    'program_studi' => $_GET['program_studi'] ?? '',
    'semester' => $_GET['semester'] ?? ''
];

$mahasiswa = $repo->getAllMahasiswa($filters);

// Set header agar browser mendownload file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=data_mahasiswa_' . date('Ymd_His') . '.csv');

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis BOM agar excel membaca UTF-8 dengan benar
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Header Kolom CSV
fputcsv($output, ['NIM', 'Nama Lengkap', 'Fakultas', 'Program Studi', 'Semester', 'Dosen Wali']);

// Isi baris data
foreach ($mahasiswa as $m) {
    fputcsv($output, [
        $m['nim'],
        $m['nama'],
        $m['fakultas'] ?? '-',
        $m['program_studi'],
        $m['semester'] ?? '-',
        $m['dosen_wali_nama'] ?? 'Belum ada'
    ]);
}

fclose($output);
exit();
