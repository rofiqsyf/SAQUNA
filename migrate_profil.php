<?php
require_once __DIR__ . '/autoload.php';
$db = Config\Database::getConnection();

try {
    echo "Memulai migrasi profil mahasiswa MySQL...\n";
    
    $columns = [
        "tempat_tanggal_lahir" => "VARCHAR(255)",
        "alamat_asal" => "TEXT",
        "domisili" => "TEXT",
        "email" => "VARCHAR(100)",
        "no_hp" => "VARCHAR(20)",
        "jenis_kelamin" => "VARCHAR(20)",
        "dosen_wali_id" => "INTEGER",
        "semester" => "INTEGER",
        "fakultas" => "VARCHAR(100)"
    ];

    $stmt = $db->prepare("SHOW COLUMNS FROM mahasiswa");
    $stmt->execute();
    $existingCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColNames = array_map(function($c) { return $c['Field']; }, $existingCols);

    foreach ($columns as $column => $type) {
        if (!in_array($column, $existingColNames)) {
            $db->exec("ALTER TABLE mahasiswa ADD COLUMN {$column} {$type}");
            echo "Kolom {$column} berhasil ditambahkan.\n";
        } else {
            echo "Kolom {$column} sudah ada.\n";
        }
    }
    
    echo "Migrasi selesai.\n";

} catch (Exception $e) {
    die("Terjadi kesalahan migrasi: " . $e->getMessage() . "\n");
}
