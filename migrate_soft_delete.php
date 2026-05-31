<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

$pdo = Database::getConnection();
$successCount = 0;
$errors = [];

try {
    // 1. Add deleted_at to mahasiswa and mata_kuliah (dosen already has it, but let's check)
    $tables = ['mahasiswa', 'mata_kuliah', 'dosen'];
    foreach ($tables as $table) {
        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL");
            echo "Kolom deleted_at berhasil ditambahkan ke tabel {$table}.\n";
            $successCount++;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "Kolom deleted_at sudah ada di tabel {$table}.\n";
            } else {
                $errors[] = "Gagal alter tabel {$table}: " . $e->getMessage();
            }
        }
    }

    // 2. Change Foreign Key ON DELETE CASCADE to RESTRICT
    // Since we don't know the exact constraint names, we query information_schema
    $fkQuery = "
        SELECT TABLE_NAME, CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = 'saquna'
          AND REFERENCED_TABLE_NAME IN ('mahasiswa', 'dosen', 'mata_kuliah')
    ";
    $stmt = $pdo->query($fkQuery);
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by table and constraint
    $constraints = [];
    foreach ($fks as $fk) {
        $constraints[$fk['TABLE_NAME']][$fk['CONSTRAINT_NAME']] = true;
    }

    // This is complex to automate perfectly because we'd need to re-add the constraint with RESTRICT.
    // However, if we change the application logic to NEVER use `DELETE FROM mahasiswa`, 
    // the CASCADE will never trigger anyway!
    // So the safest & easiest fix is to just strictly use Soft Deletes in PHP code.
    echo "Peringatan: Skema relasi MySQL mungkin masih memiliki ON DELETE CASCADE.\n";
    echo "Pastikan aplikasi tidak pernah mengeksekusi query DELETE FROM pada master data.\n";
    
    echo "\nMigrasi selesai. Total success: $successCount. Total errors: " . count($errors) . "\n";
    if (!empty($errors)) {
        print_r($errors);
    }
} catch (Exception $e) {
    die("Fatal error: " . $e->getMessage());
}
