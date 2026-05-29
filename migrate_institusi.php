<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();

    // 1. Tabel pengaturan_institusi
    $sql1 = "CREATE TABLE IF NOT EXISTS pengaturan_institusi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kunci VARCHAR(100) NOT NULL UNIQUE,
        nilai TEXT NOT NULL
    )";
    $pdo->exec($sql1);
    echo "Tabel pengaturan_institusi berhasil dibuat/sudah ada.\n";

    // Insert default Nama Univ if not exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM pengaturan_institusi WHERE kunci = 'nama_universitas'");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO pengaturan_institusi (kunci, nilai) VALUES ('nama_universitas', 'Universitas Teknologi SAQUNA')");
        echo "Default nama universitas ditambahkan.\n";
    }

    // 2. Tabel master_fakultas
    $sql2 = "CREATE TABLE IF NOT EXISTS master_fakultas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_fakultas VARCHAR(150) NOT NULL,
        singkatan VARCHAR(50) DEFAULT NULL
    )";
    $pdo->exec($sql2);
    echo "Tabel master_fakultas berhasil dibuat/sudah ada.\n";

    // 3. Tabel master_prodi
    $sql3 = "CREATE TABLE IF NOT EXISTS master_prodi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fakultas_id INT NOT NULL,
        nama_prodi VARCHAR(150) NOT NULL,
        jenjang VARCHAR(50) DEFAULT 'S1',
        FOREIGN KEY (fakultas_id) REFERENCES master_fakultas(id) ON DELETE CASCADE
    )";
    $pdo->exec($sql3);
    echo "Tabel master_prodi berhasil dibuat/sudah ada.\n";

    echo "\nMigrasi Institusi selesai!\n";

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
