<?php
require_once __DIR__ . '/autoload.php';
$db = Config\Database::getConnection();

try {
    echo "Memulai migrasi database beasiswa...\n";
    
    // Buat tabel beasiswa_penerima
    $db->exec("CREATE TABLE IF NOT EXISTS beasiswa_penerima (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        mahasiswa_id INT UNSIGNED NOT NULL,
        nama_beasiswa VARCHAR(255) NOT NULL,
        tahun VARCHAR(9) NOT NULL,
        status ENUM('Aktif', 'Selesai', 'Dibatalkan') DEFAULT 'Aktif',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
    )");
    
    echo "✅ Tabel beasiswa_penerima berhasil dibuat.\n";
    
    // Seed dummy data
    $stmt = $db->query("SELECT id FROM mahasiswa LIMIT 3");
    $mhsIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($mhsIds)) {
        // Hapus data lama biar bersih
        $db->exec("TRUNCATE TABLE beasiswa_penerima");
        
        $sql = "INSERT INTO beasiswa_penerima (mahasiswa_id, nama_beasiswa, tahun, status) VALUES (?, ?, ?, ?)";
        $ins = $db->prepare($sql);
        
        $ins->execute([$mhsIds[0], "Beasiswa Djarum Plus", "2025/2026", "Aktif"]);
        $ins->execute([$mhsIds[0], "PPA Kemenristekdikti", "2024/2025", "Selesai"]);
        
        if (isset($mhsIds[1])) {
            $ins->execute([$mhsIds[1], "Beasiswa Unggulan Kemendikbud", "2025/2026", "Aktif"]);
        }
    }
    
    echo "✅ Data dummy beasiswa berhasil dimasukkan.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
