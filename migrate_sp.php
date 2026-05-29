<?php
require_once __DIR__ . '/autoload.php';
$pdo = \Config\Database::getConnection();

$tables = [
    ['table' => 'dosen_matakuliah', 'column' => 'semester'],
    ['table' => 'krs', 'column' => 'semester_aktif'],
    ['table' => 'tagihan_pembayaran', 'column' => 'semester'],
    ['table' => 'tugas_kuliah', 'column' => 'semester'],
    ['table' => 'kalender_akademik', 'column' => 'semester'],
    ['table' => 'jadwal_kelas', 'column' => 'semester'],
    ['table' => 'sesi_presensi', 'column' => 'semester_aktif']
];

foreach ($tables as $t) {
    try {
        $pdo->exec("ALTER TABLE `{$t['table']}` MODIFY COLUMN `{$t['column']}` ENUM('Ganjil','Genap','Pendek') NOT NULL");
        echo "Success: {$t['table']}.{$t['column']}\n";
    } catch (Exception $e) {
        echo "Error on {$t['table']}: " . $e->getMessage() . "\n";
    }
}
echo "Migration finished.\n";
