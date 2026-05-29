<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    echo "Mulai migrasi Phase 3 & 4 (Layanan, Yudisium, Komponen Nilai)...\n";
    
    // 1. Tabel layanan_surat
    $pdo->exec("CREATE TABLE IF NOT EXISTS layanan_surat (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mahasiswa_id INT UNSIGNED NOT NULL,
        jenis_surat VARCHAR(50) NOT NULL,
        keperluan TEXT NOT NULL,
        status ENUM('Pending', 'Diproses', 'Selesai', 'Ditolak') DEFAULT 'Pending',
        file_surat VARCHAR(255) NULL,
        catatan_operator TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
    )");
    echo "- Tabel layanan_surat berhasil dibuat.\n";

    // 2. Tabel yudisium
    $pdo->exec("CREATE TABLE IF NOT EXISTS yudisium (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mahasiswa_id INT UNSIGNED NOT NULL UNIQUE,
        status_pengajuan ENUM('Draft', 'Diajukan', 'Disetujui', 'Ditolak') DEFAULT 'Draft',
        tanggal_lulus DATE NULL,
        no_sk VARCHAR(100) NULL,
        catatan TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
    )");
    echo "- Tabel yudisium berhasil dibuat.\n";

    // 3. Tabel komponen_nilai
    $pdo->exec("CREATE TABLE IF NOT EXISTS komponen_nilai (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        krs_id INT UNSIGNED NOT NULL UNIQUE,
        nilai_tugas DECIMAL(5,2) DEFAULT 0,
        nilai_uts DECIMAL(5,2) DEFAULT 0,
        nilai_uas DECIMAL(5,2) DEFAULT 0,
        nilai_praktikum DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (krs_id) REFERENCES krs(id) ON DELETE CASCADE
    )");
    echo "- Tabel komponen_nilai berhasil dibuat.\n";

    echo "\nSemua migrasi Phase 3 & 4 selesai! 🎉\n";

} catch (\PDOException $e) {
    die("Kesalahan Database: " . $e->getMessage() . "\n");
}
