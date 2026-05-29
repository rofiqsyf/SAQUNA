<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Memulai Re-indexing NIM dan NIDN...\n\n";

    $pdo->beginTransaction();

    // 1. Update Mahasiswa
    echo "1. Memperbarui NIM Mahasiswa...\n";
    $stmtMhs = $pdo->query("SELECT id, user_id FROM mahasiswa ORDER BY id ASC");
    $mahasiswaList = $stmtMhs->fetchAll(PDO::FETCH_ASSOC);
    
    $updateMhs = $pdo->prepare("UPDATE mahasiswa SET nim = ? WHERE id = ?");
    $updateUser = $pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ?");
    
    $mhsCounter = 1;
    foreach ($mahasiswaList as $mhs) {
        $newNim = sprintf("AA%03d", $mhsCounter);
        $newPass = password_hash($newNim, PASSWORD_DEFAULT);
        
        // Update tabel mahasiswa
        $updateMhs->execute([$newNim, $mhs['id']]);
        
        // Update tabel users
        if ($mhs['user_id']) {
            $updateUser->execute([$newNim, $newPass, $mhs['user_id']]);
        }
        
        $mhsCounter++;
    }
    echo "   Berhasil memperbarui NIM untuk " . ($mhsCounter - 1) . " mahasiswa.\n\n";


    // 2. Update Dosen
    echo "2. Memperbarui NIDN Dosen...\n";
    $stmtDosen = $pdo->query("SELECT id, user_id FROM dosen ORDER BY id ASC");
    $dosenList = $stmtDosen->fetchAll(PDO::FETCH_ASSOC);
    
    $updateDosen = $pdo->prepare("UPDATE dosen SET nidn = ? WHERE id = ?");
    
    $dosenCounter = 1;
    foreach ($dosenList as $dosen) {
        $newNidn = sprintf("20262112%03d", $dosenCounter);
        $newPass = password_hash($newNidn, PASSWORD_DEFAULT);
        
        // Update tabel dosen
        $updateDosen->execute([$newNidn, $dosen['id']]);
        
        // Update tabel users
        if ($dosen['user_id']) {
            $updateUser->execute([$newNidn, $newPass, $dosen['user_id']]);
        }
        
        $dosenCounter++;
    }
    echo "   Berhasil memperbarui NIDN untuk " . ($dosenCounter - 1) . " dosen.\n\n";

    $pdo->commit();
    echo "Selesai!\n";

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Terjadi kesalahan database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Terjadi kesalahan: " . $e->getMessage() . "\n";
}
