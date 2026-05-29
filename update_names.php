<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Memulai proses pembaruan nama...\n\n";

    // 1. Load CSV data
    $csvFile = 'd:/xampp/htdocs/uts-pemweb/indonesian-names.csv';
    if (!file_exists($csvFile)) {
        die("File CSV tidak ditemukan di $csvFile\n");
    }

    $names = [];
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        $header = fgetcsv($handle, 1000, ",");
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (count($data) >= 2) {
                // Capitalize each word for names
                $name = ucwords(strtolower(trim($data[0])));
                $gender = trim(strtolower($data[1]));
                $jk = ($gender === 'm') ? 'Laki-laki' : 'Perempuan';
                $names[] = ['nama' => $name, 'jenis_kelamin' => $jk];
            }
        }
        fclose($handle);
    }
    
    if (empty($names)) {
        die("Gagal membaca nama dari CSV atau file kosong.\n");
    }
    
    echo "Berhasil memuat " . count($names) . " nama dari CSV.\n\n";

    $pdo->beginTransaction();

    // 2. Update Mahasiswa
    $stmtMhs = $pdo->query("SELECT id FROM mahasiswa");
    $mahasiswaList = $stmtMhs->fetchAll(PDO::FETCH_COLUMN);
    $mhsCount = 0;
    
    $updateMhsStmt = $pdo->prepare("UPDATE mahasiswa SET nama = ?, jenis_kelamin = ? WHERE id = ?");
    
    foreach ($mahasiswaList as $mhsId) {
        $randomNameData = $names[array_rand($names)];
        $updateMhsStmt->execute([$randomNameData['nama'], $randomNameData['jenis_kelamin'], $mhsId]);
        $mhsCount++;
    }
    
    echo "Berhasil memperbarui nama untuk $mhsCount mahasiswa.\n";

    // 3. Update Dosen
    $stmtDosen = $pdo->query("SELECT id FROM dosen");
    $dosenList = $stmtDosen->fetchAll(PDO::FETCH_COLUMN);
    $dosenCount = 0;
    
    $updateDosenStmt = $pdo->prepare("UPDATE dosen SET nama = ?, jenis_kelamin = ? WHERE id = ?");
    
    foreach ($dosenList as $dosenId) {
        $randomNameData = $names[array_rand($names)];
        
        // Tambahkan gelar untuk Dosen agar terlihat realistis
        $gelarDepan = (rand(0, 1) == 1) ? 'Dr. ' : '';
        $gelarBelakang = ['S.Kom., M.Kom.', 'S.T., M.T.', 'S.E., M.Si.', 'S.Pd., M.Pd.'];
        $gelar = $gelarBelakang[array_rand($gelarBelakang)];
        
        $namaDosen = $gelarDepan . $randomNameData['nama'] . ', ' . $gelar;
        
        $updateDosenStmt->execute([$namaDosen, $randomNameData['jenis_kelamin'], $dosenId]);
        $dosenCount++;
    }
    
    echo "Berhasil memperbarui nama untuk $dosenCount dosen.\n";
    
    $pdo->commit();
    echo "\nSelesai!\n";

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
