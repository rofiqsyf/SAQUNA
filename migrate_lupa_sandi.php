<?php
require_once __DIR__ . '/autoload.php';

try {
    $db = \Config\Database::getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS lupa_sandi_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nomor_induk VARCHAR(50) NOT NULL,
        role VARCHAR(20) NOT NULL,
        catatan TEXT,
        status ENUM('pending', 'proses', 'selesai') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $db->exec($sql);
    echo "Tabel lupa_sandi_requests berhasil dibuat atau sudah ada.\n";
} catch (PDOException $e) {
    echo "Gagal membuat tabel: " . $e->getMessage() . "\n";
}
