<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    
    // 1. Kalender Akademik
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS kalender_akademik (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nama_event VARCHAR(255) NOT NULL,
        jenis_event ENUM('Periode KRS', 'Perubahan KRS', 'UTS', 'UAS', 'Libur', 'Wisuda', 'Lainnya') NOT NULL,
        tanggal_mulai DATE NOT NULL,
        tanggal_akhir DATE NOT NULL,
        semester ENUM('Ganjil','Genap') NOT NULL,
        tahun_ajaran VARCHAR(9) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel kalender_akademik berhasil dibuat.\n";

    // 2. Ruangan
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS ruangan (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        kode_ruangan VARCHAR(20) NOT NULL UNIQUE,
        nama_ruangan VARCHAR(100) NOT NULL,
        gedung VARCHAR(50) NULL,
        kapasitas INT UNSIGNED NOT NULL,
        jenis ENUM('Teori', 'Praktikum/Lab', 'Studio') NOT NULL DEFAULT 'Teori'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel ruangan berhasil dibuat.\n";

    // 3. Jadwal Kelas
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS jadwal_kelas (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dosen_id INT UNSIGNED NOT NULL,
        matakuliah_id INT UNSIGNED NOT NULL,
        semester ENUM('Ganjil','Genap') NOT NULL,
        ruangan_id INT UNSIGNED NOT NULL,
        hari ENUM('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
        jam_mulai TIME NOT NULL,
        jam_selesai TIME NOT NULL,
        FOREIGN KEY (dosen_id, matakuliah_id, semester) REFERENCES dosen_matakuliah(dosen_id, matakuliah_id, semester) ON DELETE CASCADE,
        FOREIGN KEY (ruangan_id) REFERENCES ruangan(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel jadwal_kelas berhasil dibuat.\n";

    // 4. Modifikasi Tabel Mahasiswa (Tambah Dosen Wali)
    $stmt = $pdo->query("SHOW COLUMNS FROM mahasiswa LIKE 'dosen_wali_id'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
        ALTER TABLE mahasiswa 
        ADD COLUMN dosen_wali_id INT UNSIGNED NULL,
        ADD FOREIGN KEY (dosen_wali_id) REFERENCES dosen(id) ON DELETE SET NULL;
        ");
        echo "Kolom dosen_wali_id berhasil ditambahkan ke tabel mahasiswa.\n";
    }

    // 5. Catatan Perwalian
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS catatan_perwalian (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mahasiswa_id INT UNSIGNED NOT NULL,
        dosen_wali_id INT UNSIGNED NOT NULL,
        semester ENUM('Ganjil','Genap') NOT NULL,
        tahun_ajaran VARCHAR(9) NOT NULL,
        catatan TEXT NOT NULL,
        waktu_bimbingan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE,
        FOREIGN KEY (dosen_wali_id) REFERENCES dosen(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Tabel catatan_perwalian berhasil dibuat.\n";
    
    // Seed some dummy rooms and calendar events if empty
    $resR = $pdo->query("SELECT COUNT(*) FROM ruangan")->fetchColumn();
    if ($resR == 0) {
        $pdo->exec("INSERT INTO ruangan (kode_ruangan, nama_ruangan, gedung, kapasitas, jenis) VALUES
            ('R101', 'Ruang Teori 101', 'Gedung A', 40, 'Teori'),
            ('R102', 'Ruang Teori 102', 'Gedung A', 40, 'Teori'),
            ('L201', 'Laboratorium Komputer', 'Gedung B', 30, 'Praktikum/Lab')
        ");
        echo "Data dummy ruangan di-seed.\n";
    }

    $resC = $pdo->query("SELECT COUNT(*) FROM kalender_akademik")->fetchColumn();
    if ($resC == 0) {
        $year = date('Y');
        $pdo->exec("INSERT INTO kalender_akademik (nama_event, jenis_event, tanggal_mulai, tanggal_akhir, semester, tahun_ajaran) VALUES
            ('Pengisian KRS Ganjil', 'Periode KRS', '{$year}-08-01', '{$year}-08-14', 'Ganjil', '{$year}/".($year+1)."'),
            ('UTS Ganjil', 'UTS', '{$year}-10-15', '{$year}-10-25', 'Ganjil', '{$year}/".($year+1)."'),
            ('UAS Ganjil', 'UAS', '{$year}-12-15', '{$year}-12-25', 'Ganjil', '{$year}/".($year+1)."')
        ");
        echo "Data dummy kalender_akademik di-seed.\n";
    }

    echo "\nMigrasi Phase 1 Selesai!\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
