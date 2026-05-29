<?php
/**
 * Migration: Tambah Fitur Baru SAQUNA
 * - Tabel periode_krs
 */
require_once __DIR__ . '/autoload.php';

$pdo = \Config\Database::getConnection();

$migrations = [];

// 1. Tabel periode_krs
$migrations[] = [
    'name' => 'Create periode_krs table',
    'sql' => "CREATE TABLE IF NOT EXISTS `periode_krs` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nama_periode` VARCHAR(100) NOT NULL,
        `semester` VARCHAR(20) NOT NULL,
        `tahun_ajaran` VARCHAR(20) NOT NULL,
        `tanggal_buka` DATETIME NOT NULL,
        `tanggal_tutup` DATETIME NOT NULL,
        `status` ENUM('Buka','Tutup') DEFAULT 'Tutup',
        `catatan` TEXT NULL,
        `created_by` INT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// 2. Seed: Insert periode KRS default jika tabel kosong
$migrations[] = [
    'name' => 'Seed default periode KRS',
    'sql' => "INSERT IGNORE INTO `periode_krs` (`nama_periode`, `semester`, `tahun_ajaran`, `tanggal_buka`, `tanggal_tutup`, `status`, `catatan`)
              VALUES 
              ('KRS Ganjil 2025/2026', 'Ganjil', '2025/2026', '2025-08-01 00:00:00', '2025-08-15 23:59:59', 'Tutup', 'Periode KRS Semester Ganjil'),
              ('KRS Genap 2025/2026', 'Genap', '2025/2026', '2026-01-15 00:00:00', '2026-01-30 23:59:59', 'Tutup', 'Periode KRS Semester Genap')"
];

// Jalankan migrations
$errors = [];
$success = [];

foreach ($migrations as $migration) {
    try {
        $pdo->exec($migration['sql']);
        $success[] = "✅ " . $migration['name'];
    } catch (\PDOException $e) {
        $errors[] = "❌ " . $migration['name'] . ": " . $e->getMessage();
    }
}

echo "<h2>Migration Result</h2>";
echo "<h3>✅ Success (" . count($success) . ")</h3><ul>";
foreach ($success as $s) echo "<li>$s</li>";
echo "</ul>";

if (!empty($errors)) {
    echo "<h3>❌ Errors (" . count($errors) . ")</h3><ul>";
    foreach ($errors as $e) echo "<li style='color:red'>$e</li>";
    echo "</ul>";
}

echo "<p><strong>Done.</strong></p>";
