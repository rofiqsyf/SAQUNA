<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

Auth::requireLogin();
$repo = new DosenRepository();

// Ambil parameter filter dari query string
$search = $_GET['search'] ?? '';
$prodi = $_GET['prodi'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'id';
$dir = $_GET['dir'] ?? 'DESC';

// Ambil semua data (perPage di-set ke jumlah besar untuk export seluruh hasil filter)
$result = $repo->paginate(1, 10000, $search, $prodi, $status, $sort, $dir);
$dosens = $result['data'];

// Set header CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="data_dosen_' . date('Ymd_His') . '.csv"');

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis BOM untuk mendukung UTF-8 di Excel
fputs($output, "\xEF\xBB\xBF");

// Tulis header kolom
fputcsv($output, ['ID', 'NIDN', 'Nama Lengkap', 'Email', 'Program Studi', 'Status', 'Jumlah MK Diampu', 'Ditambahkan Pada']);

// Tulis baris data
foreach ($dosens as $d) {
    fputcsv($output, [
        $d['id'],
        $d['nidn'],
        $d['nama'],
        $d['email'],
        $d['program_studi'],
        $d['status'],
        $d['jumlah_mk'],
        $d['created_at']
    ]);
}

fclose($output);
exit;
