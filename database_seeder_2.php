<?php
require_once __DIR__ . '/autoload.php';

use Config\Database;

try {
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Memulai Data Seeder Tahap 2...\n\n";

    // 1. Ambil semua program studi dari Dosen
    $stmtProdi = $pdo->query("SELECT DISTINCT program_studi FROM dosen WHERE program_studi IS NOT NULL AND program_studi != ''");
    $prodiList = $stmtProdi->fetchAll(PDO::FETCH_COLUMN);

    if (empty($prodiList)) {
        die("Tidak ada program studi yang ditemukan di tabel dosen.\n");
    }

    echo "1. Membuat tambahan Mata Kuliah...\n";
    // Mata kuliah generik per prodi
    $mk_names = [
        'Pengantar', 'Lanjut', 'Terapan', 'Analisis', 'Desain', 'Manajemen', 
        'Sistem', 'Teori', 'Praktik', 'Metodologi', 'Rekayasa', 'Kajian'
    ];
    $mk_topics = [
        'Data', 'Algoritma', 'Bisnis', 'Komunikasi', 'Jaringan', 'Aplikasi',
        'Industri', 'Sosial', 'Multimedia', 'Keamanan', 'Strategi', 'Dasar'
    ];
    
    $mkIds = [];
    foreach ($prodiList as $prodi) {
        // Buat 5-8 mata kuliah untuk setiap prodi
        $jumlahMk = rand(5, 8);
        for ($i = 0; $i < $jumlahMk; $i++) {
            $namaMk = $mk_names[array_rand($mk_names)] . ' ' . $mk_topics[array_rand($mk_topics)] . ' ' . rand(1, 3);
            $kode = strtoupper(substr($prodi, 0, 3)) . rand(100, 999);
            $sks = rand(2, 4);
            $semester_min = rand(1, 8); // Sebar rata
            
            try {
                $stmtMK = $pdo->prepare("INSERT INTO mata_kuliah (kode, nama, sks, prodi, semester_mk) VALUES (?, ?, ?, ?, ?)");
                $stmtMK->execute([$kode, $namaMk, $sks, $prodi, $semester_min]);
                $mkIds[] = $pdo->lastInsertId();
            } catch (Exception $e) {
                // Abaikan duplikat kode
            }
        }
    }
    echo "   Berhasil menambahkan " . count($mkIds) . " mata kuliah baru.\n\n";

    // 2. Ambil seluruh Ruangan
    $stmtRuangan = $pdo->query("SELECT id FROM ruangan");
    $ruanganList = $stmtRuangan->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($ruanganList)) {
        die("Tabel ruangan kosong. Tidak bisa membuat jadwal.\n");
    }

    echo "2. Menjadwalkan Dosen untuk mengajar...\n";
    
    // Ambil semua Dosen
    $stmtDosen = $pdo->query("SELECT id, program_studi FROM dosen WHERE status = 'aktif'");
    $dosenList = $stmtDosen->fetchAll(PDO::FETCH_ASSOC);

    $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $jamList = [
        ['08:00:00', '10:00:00'],
        ['10:15:00', '12:15:00'],
        ['13:00:00', '15:00:00'],
        ['15:15:00', '17:15:00']
    ];

    $jadwalCount = 0;
    
    foreach ($dosenList as $dosen) {
        $dosenId = $dosen['id'];
        $prodi = $dosen['program_studi'];
        
        // Cari mata kuliah yang relevan (sesuai prodi)
        $stmtMKRelevan = $pdo->prepare("SELECT id FROM mata_kuliah WHERE prodi = ?");
        $stmtMKRelevan->execute([$prodi]);
        $mkRelevan = $stmtMKRelevan->fetchAll(PDO::FETCH_COLUMN);
        
        // Jika tidak ada prodi yg persis cocok, ambil MK apa saja yang ada di prodiList 
        // (ini untuk fallback jika ada mismatch string)
        if (empty($mkRelevan)) {
            $stmtMKAll = $pdo->query("SELECT id FROM mata_kuliah ORDER BY RAND() LIMIT 5");
            $mkRelevan = $stmtMKAll->fetchAll(PDO::FETCH_COLUMN);
        }

        // Setiap dosen akan mengajar 1 hingga 3 kelas
        $jumlahMengajar = rand(1, 3);
        
        for ($i = 0; $i < $jumlahMengajar; $i++) {
            $mkId = $mkRelevan[array_rand($mkRelevan)];
            $ruanganId = $ruanganList[array_rand($ruanganList)];
            $hari = $hariList[array_rand($hariList)];
            $jam = $jamList[array_rand($jamList)];
            $jamMulai = $jam[0];
            $jamSelesai = $jam[1];
            $semester = (date('n') > 6) ? 'Ganjil' : 'Genap'; // Misal genap jika bulan awal tahun

            try {
                // 1. Insert into dosen_matakuliah (Parent table)
                $stmtDM = $pdo->prepare("INSERT IGNORE INTO dosen_matakuliah (dosen_id, matakuliah_id, semester) VALUES (?, ?, ?)");
                $stmtDM->execute([$dosenId, $mkId, $semester]);
                
                // 2. Insert into jadwal_kelas (Child table)
                $stmtJadwal = $pdo->prepare("INSERT INTO jadwal_kelas (dosen_id, matakuliah_id, semester, ruangan_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtJadwal->execute([$dosenId, $mkId, $semester, $ruanganId, $hari, $jamMulai, $jamSelesai]);
                $jadwalCount++;
            } catch (Exception $e) {
                // Abaikan constraint violation
            }
        }
    }

    echo "   Berhasil menjadwalkan $jadwalCount kelas baru!\n\n";
    echo "Selesai!\n";

} catch (PDOException $e) {
    echo "Terjadi kesalahan database: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Terjadi kesalahan: " . $e->getMessage() . "\n";
}
