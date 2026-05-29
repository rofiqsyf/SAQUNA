<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Memulai Data Seeder...\n\n";

    // 1. Update mahasiswa lama menjadi semester 2
    $stmtUpdate = $pdo->query("UPDATE mahasiswa SET semester = 2 WHERE semester != 2 OR semester IS NULL");
    $affected = $stmtUpdate->rowCount();
    echo "1. Memperbarui $affected mahasiswa lama menjadi semester 2.\n";

    // 2. Buat Dosen Baru (15 Dosen)
    echo "2. Membuat 15 dosen baru...\n";
    $fakultas_list = ['Fakultas Teknik', 'Fakultas Ilmu Komputer', 'Fakultas Ekonomi', 'Fakultas Bahasa'];
    $prodi_list = ['Teknik Informatika', 'Sistem Informasi', 'Ilmu Komunikasi', 'Manajemen Bisnis', 'Sastra Inggris'];
    $keahlian_list = ['Pemrograman Web', 'Kecerdasan Buatan', 'Jaringan Komputer', 'Manajemen Proyek', 'Bahasa Asing'];

    $dosenIds = [];
    for ($i = 1; $i <= 15; $i++) {
        $nip = '1980' . rand(10000000, 99999999) . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
        $nama = "Dr. Dosen Dummy Ke-" . $i . ", M.Kom.";
        
        // Cek jika NIP sudah ada
        $stmtCek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCek->execute([$nip]);
        if ($stmtCek->fetch()) continue;

        // Insert User Dosen
        $passwordHash = password_hash($nip, PASSWORD_DEFAULT);
        $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'dosen')");
        $stmtUser->execute([$nip, $passwordHash]);
        $userId = $pdo->lastInsertId();

        // Insert Dosen
        $fakultas = $fakultas_list[array_rand($fakultas_list)];
        $prodi = $prodi_list[array_rand($prodi_list)];
        $keahlian = $keahlian_list[array_rand($keahlian_list)];
        $email = 'dosen' . $nip . '@saquna.ac.id';
        
        try {
            $stmtDosen = $pdo->prepare("INSERT INTO dosen (user_id, nidn, nama, fakultas, program_studi, email) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtDosen->execute([$userId, $nip, $nama, $fakultas, $prodi, $email]);
            $dosenIds[] = $pdo->lastInsertId();
        } catch (Exception $e) {
            // Abaikan error duplikat dan lanjutkan
        }
    }
    echo "   Berhasil menambahkan " . count($dosenIds) . " dosen baru.\n";

    // Ambil semua Dosen ID yang tersedia untuk Wali
    $semuaDosen = $pdo->query("SELECT id FROM dosen")->fetchAll(PDO::FETCH_COLUMN);

    // 3. Buat Mata Kuliah Baru (15 Mata Kuliah untuk semester genap: 2, 4, 6, 8)
    echo "3. Membuat 15 mata kuliah baru...\n";
    $mk_names = [
        'Pemrograman Web Lanjut', 'Basis Data Terdistribusi', 'Kecerdasan Buatan', 
        'Sistem Operasi Lanjut', 'Manajemen Jaringan', 'Cloud Computing',
        'Analisis Algoritma', 'Metodologi Penelitian', 'Manajemen Proyek TI',
        'Keamanan Jaringan', 'Data Mining', 'Internet of Things',
        'E-Commerce', 'Sistem Pakar', 'Rekayasa Perangkat Lunak'
    ];
    $semesters = [2, 4, 6, 8];
    $mkIds = [];
    foreach ($mk_names as $index => $mk_nama) {
        $kode = 'MKG' . str_pad((string)($index + 101), 3, '0', STR_PAD_LEFT);
        $sks = rand(2, 4);
        $semester_min = $semesters[array_rand($semesters)];
        $prodi = $prodi_list[array_rand($prodi_list)];

        // Cek kode
        $stmtCek = $pdo->prepare("SELECT id FROM mata_kuliah WHERE kode = ?");
        $stmtCek->execute([$kode]);
        if ($stmtCek->fetch()) continue;

        $stmtMK = $pdo->prepare("INSERT INTO mata_kuliah (kode, nama, sks, prodi, semester_mk) VALUES (?, ?, ?, ?, ?)");
        $stmtMK->execute([$kode, $mk_nama, $sks, $prodi, $semester_min]);
        $mkIds[] = $pdo->lastInsertId();
    }
    echo "   Berhasil menambahkan " . count($mkIds) . " mata kuliah baru.\n";

    // 4. Buat Mahasiswa Baru (Semester 4, 6, 8) masing-masing 100
    echo "4. Membuat 300 mahasiswa baru (100 per semester genap: 4, 6, 8)...\n";
    $target_semesters = [4, 6, 8];
    $totalMahasiswaBaru = 0;

    foreach ($target_semesters as $sem) {
        $sukses_sem = 0;
        // Gunakan tahun angkatan berdasarkan semester untuk NIM (Semester 4 = angkatan - 2 tahun, dst)
        // Misal sekarang 2026.
        // Sem 4 -> masuk 2024
        // Sem 6 -> masuk 2023
        // Sem 8 -> masuk 2022
        $tahun_angkatan = 2026 - ceil($sem / 2);
        
        for ($i = 1; $i <= 100; $i++) {
            $nim = $tahun_angkatan . '10' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT) . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
            $nama = "Mahasiswa Sem $sem Ke-" . $i;
            
            // Cek jika NIM sudah ada
            $stmtCek = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmtCek->execute([$nim]);
            if ($stmtCek->fetch()) continue;

            // Insert User Mahasiswa
            $passwordHash = password_hash($nim, PASSWORD_DEFAULT);
            $stmtUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'mahasiswa')");
            $stmtUser->execute([$nim, $passwordHash]);
            $userId = $pdo->lastInsertId();

            // Insert Mahasiswa
            $fakultas = $fakultas_list[array_rand($fakultas_list)];
            $prodi = $prodi_list[array_rand($prodi_list)];
            $dosenWaliId = !empty($semuaDosen) ? $semuaDosen[array_rand($semuaDosen)] : null;
            
            try {
                $stmtMhs = $pdo->prepare("INSERT INTO mahasiswa (user_id, nim, nama, fakultas, program_studi, semester, dosen_wali_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtMhs->execute([$userId, $nim, $nama, $fakultas, $prodi, $sem, $dosenWaliId]);
                
                $sukses_sem++;
                $totalMahasiswaBaru++;
            } catch (Exception $e) {
                // Abaikan
            }
        }
        echo "   Berhasil menambahkan $sukses_sem mahasiswa untuk Semester $sem.\n";
    }

    echo "\nSelesai! Total Mahasiswa Baru: $totalMahasiswaBaru. Mahasiswa Lama Sem 2: $affected.\n";

} catch (PDOException $e) {
    echo "Terjadi kesalahan database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage() . "\n";
}
