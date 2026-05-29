<?php
require 'autoload.php';
try {
    $db = \Config\Database::getConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS sesi_presensi (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dosen_id INT UNSIGNED NOT NULL,
            matakuliah_id INT UNSIGNED NOT NULL,
            semester_aktif ENUM('Ganjil','Genap') NOT NULL,
            pertemuan_ke TINYINT UNSIGNED NOT NULL,
            status ENUM('Buka','Tutup') NOT NULL DEFAULT 'Tutup',
            waktu_buka TIMESTAMP NULL,
            waktu_tutup TIMESTAMP NULL,
            FOREIGN KEY (dosen_id, matakuliah_id, semester_aktif) REFERENCES dosen_matakuliah(dosen_id, matakuliah_id, semester) ON DELETE CASCADE,
            UNIQUE KEY (dosen_id, matakuliah_id, semester_aktif, pertemuan_ke)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
