<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    
    // 1. Prasyarat Mata Kuliah
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS mk_prasyarat (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        matakuliah_id INT UNSIGNED NOT NULL,
        prasyarat_mk_id INT UNSIGNED NOT NULL,
        nilai_minimal ENUM('A','B','C','D') NOT NULL DEFAULT 'C',
        FOREIGN KEY (matakuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
        FOREIGN KEY (prasyarat_mk_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE,
        UNIQUE KEY unique_prasyarat (matakuliah_id, prasyarat_mk_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel mk_prasyarat berhasil dibuat.\n";

    // 2. Modifikasi Tabel Role Enum
    $pdo->exec("
    ALTER TABLE users MODIFY COLUMN role ENUM('operator','mahasiswa','dosen','orang_tua') NOT NULL DEFAULT 'operator';
    ");
    echo "Role 'orang_tua' ditambahkan ke tabel users.\n";

    // 3. Portal Orang Tua
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS orang_tua (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        mahasiswa_id INT UNSIGNED NOT NULL,
        nama_ayah VARCHAR(100) NULL,
        nama_ibu VARCHAR(100) NULL,
        no_telp VARCHAR(20) NULL,
        email VARCHAR(120) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel orang_tua berhasil dibuat.\n";

    // 4. Jadwal Ujian
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS jadwal_ujian (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kelas_id INT UNSIGNED NOT NULL, -- Refers to jadwal_kelas
        jenis_ujian ENUM('UTS', 'UAS') NOT NULL,
        tanggal DATE NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        ruangan_id INT UNSIGNED NOT NULL,
        pengawas_id INT UNSIGNED NOT NULL,
        FOREIGN KEY (kelas_id) REFERENCES jadwal_kelas(id) ON DELETE CASCADE,
        FOREIGN KEY (ruangan_id) REFERENCES ruangan(id) ON DELETE CASCADE,
        FOREIGN KEY (pengawas_id) REFERENCES dosen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel jadwal_ujian berhasil dibuat.\n";

    // 5. Upgrade Presensi (Token QR)
    // First check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM sesi_presensi LIKE 'token_qr'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
        ALTER TABLE sesi_presensi 
        ADD COLUMN token_qr VARCHAR(100) NULL,
        ADD COLUMN token_expired_at TIMESTAMP NULL;
        ");
        echo "Kolom token_qr ditambahkan ke tabel sesi_presensi.\n";
    }

    echo "\nMigrasi Phase 2 Selesai!\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
