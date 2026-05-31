<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

$pdo = Database::getConnection();
$successCount = 0;
$errors = [];

$queries = [
    "CREATE TABLE IF NOT EXISTS dosen_penelitian (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dosen_id INT UNSIGNED NOT NULL,
        judul VARCHAR(255) NOT NULL,
        tahun INT(4) NOT NULL,
        link_publikasi VARCHAR(255) NULL,
        jenis ENUM('Nasional', 'Internasional') NOT NULL DEFAULT 'Nasional',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS dosen_pengabdian (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dosen_id INT UNSIGNED NOT NULL,
        judul VARCHAR(255) NOT NULL,
        tahun INT(4) NOT NULL,
        lokasi VARCHAR(255) NULL,
        deskripsi TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "CREATE TABLE IF NOT EXISTS catatan_perwalian (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dosen_wali_id INT UNSIGNED NOT NULL,
        mahasiswa_id INT UNSIGNED NOT NULL,
        semester VARCHAR(10) NOT NULL,
        catatan TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (dosen_wali_id) REFERENCES dosen(id) ON DELETE CASCADE,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

foreach ($queries as $index => $sql) {
    try {
        $pdo->exec($sql);
        echo "Tabel " . ($index + 1) . " berhasil dibuat/dicek.\n";
        $successCount++;
    } catch (\PDOException $e) {
        $errors[] = "Gagal query ke-" . ($index + 1) . ": " . $e->getMessage();
    }
}

echo "\nMigrasi Tridharma selesai. Total success: $successCount. Total errors: " . count($errors) . "\n";
if (!empty($errors)) {
    print_r($errors);
}
