<?php
declare(strict_types=1);

// Mencegah timeout saat melakukan hashing ratusan password
set_time_limit(0);

// Menjalankan setup dari command line atau browser
$isCli = (php_sapi_name() === 'cli');

function out(string $msg) {
    global $isCli;
    echo $msg . ($isCli ? "\n" : "<br>\n");
}

try {
    $host = 'localhost';
    $user = 'root'; // Sesuaikan username Anda
    $pass = '';     // Sesuaikan password Anda
    $dbname = 'saquna';

    // 1. Koneksi ke MySQL server
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    out("Koneksi MySQL berhasil.");

    // 2. Buat database jika belum ada
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("Database `$dbname` siap.");

    // 3. Gunakan database
    $pdo->exec("USE `$dbname`");

    // 4. Import seed.sql
    $sqlFile = __DIR__ . '/seed.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("File seed.sql tidak ditemukan.");
    }
    
    $sqlContent = file_get_contents($sqlFile);
    $pdo->exec($sqlContent);
    out("Tabel berhasil diimpor dari seed.sql.");

    // 5. Seed Users (Admin & Operator)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $userCount = (int)$stmt->fetchColumn();

    if ($userCount === 0) {
        $insertUser = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");

        $opPass = password_hash('operator123', PASSWORD_BCRYPT);
        $insertUser->execute(['operator', $opPass, 'operator']);
        out("User 'operator' berhasil dibuat (password: operator123).");

        // Seed 100 Mahasiswa dengan nama Indonesia yang realistis
        $insertMahasiswa = $pdo->prepare("INSERT INTO mahasiswa (user_id, nim, nama, program_studi, email, semester) VALUES (?, ?, ?, ?, ?, ?)");
        $prodiArr = ['Teknik Informatika', 'Sistem Informasi', 'Teknik Elektro'];

        $namaDepan = [
            'Ahmad','Budi','Cahyo','Dian','Eko','Fajar','Galih','Hana','Irfan','Joko',
            'Kiki','Lina','Maulana','Novi','Omar','Puti','Rizky','Siti','Taufik','Ulfa',
            'Wahyu','Yusuf','Zahra','Agus','Bella','Citra','Dimas','Elsa','Fauzi','Gina',
            'Heri','Indah','Jaya','Kartika','Laila','Miko','Nadia','Putri','Rani','Surya',
            'Tina','Umar','Vera','Wendi','Yanti','Zaki','Aditya','Bagas','Cindy','Dani',
            'Enny','Fira','Gilang','Hesti','Ivan','Julia','Kevin','Luna','Mira','Nando',
            'Ola','Pram','Reza','Sari','Tommy','Utari','Vito','Wina','Yogi','Alfi',
            'Bintang','Clara','Erwin','Fitri','Ganda','Helmi','Imron','Johan','Kurnia','Luthfi',
            'Maya','Naufal','Okta','Pandu','Renata','Sigit','Tiara','Ulfan','Valeria','Wisnu',
            'Yoga','Zelda','Arif','Dwi','Ika','Firman','Hilda','Rino','Sinta','Dani'
        ];
        $namaBelakang = [
            'Pratama','Santoso','Wijaya','Kusuma','Setiawan','Hakim','Putra','Saputra',
            'Nugroho','Ardiansyah','Rahmawati','Fitriani','Anggraini','Lestari','Dewi',
            'Purnama','Cahyono','Hidayat','Fauzi','Maulana','Yusuf','Halim','Basuki',
            'Wahyudi','Suharto','Wibowo','Suryadi','Perdana','Gunawan','Hartono'
        ];

        for ($i = 1; $i <= 100; $i++) {
            $numStr = str_pad((string)$i, 3, '0', STR_PAD_LEFT);
            $nim = '2025' . $numStr;
            $username = $nim; // NIM sebagai username
            $passwordRaw = 'saquna' . $numStr; // password lebih mudah diingat
            $passHash = password_hash($passwordRaw, PASSWORD_BCRYPT);

            $insertUser->execute([$username, $passHash, 'mahasiswa']);
            $mhsUserId = $pdo->lastInsertId();

            $namaL = $namaDepan[($i - 1) % count($namaDepan)];
            $namaB = $namaBelakang[(($i - 1) * 3) % count($namaBelakang)];
            $namaLengkap = "$namaL $namaB";
            // Pilih prodi berdasarkan range NIM
            $prodi = $prodiArr[($i - 1) % 3];
            $email = strtolower($namaL . '.' . $namaB . $i) . '@mahasiswa.unsiq.ac.id';
            $semester = (($i - 1) % 8) + 1; // Semester 1-8 bergantian

            $insertMahasiswa->execute([$mhsUserId, $nim, $namaLengkap, $prodi, $email, $semester]);
            $mhsId = $pdo->lastInsertId();

            // Insert Tagihan Pembayaran (tahun 2025/2026)
            $statusTagihan = ($i % 3 === 0) ? 'Lunas' : 'Belum Lunas';
            $waktuBayar = ($statusTagihan === 'Lunas') ? date('Y-m-d H:i:s') : null;
            $nominal = 2500000 + (($i % 4) * 500000); // Nominal bervariasi 2.5-4jt
            $pdo->prepare("INSERT INTO tagihan_pembayaran (mahasiswa_id, semester, tahun_ajaran, nominal, status, waktu_bayar) VALUES (?, 'Ganjil', '2025/2026', ?, ?, ?)")->execute([$mhsId, $nominal, $statusTagihan, $waktuBayar]);
        }
        out("100 Mahasiswa (NIM 2025001 - 2025100) berhasil dibuat.");
        out("Password format: saquna001 s/d saquna100");

        // Seed 5 Dosen dengan nama dan gelar yang realistis
        $insertDosen = $pdo->prepare("INSERT INTO dosen (user_id, nidn, nama, email, program_studi, status) VALUES (?, ?, ?, ?, ?, 'aktif')");
        $dosenPass = password_hash('dosen123', PASSWORD_BCRYPT);

        $dataDosen = [
            ['dsn001', '0001017001', 'Dr. Ahmad Fauzi, M.Kom', 'ahmad.fauzi@unsiq.ac.id', 'Teknik Informatika'],
            ['dsn002', '0002027002', 'Prof. Budi Santoso, Ph.D', 'budi.santoso@unsiq.ac.id', 'Sistem Informasi'],
            ['dsn003', '0003037003', 'Dr. Cahyono Wibowo, M.T', 'cahyono.wibowo@unsiq.ac.id', 'Teknik Elektro'],
            ['dsn004', '0004047004', 'Dra. Siti Fatimah, M.Pd', 'siti.fatimah@unsiq.ac.id', 'Teknik Informatika'],
            ['dsn005', '0005057005', 'Dr. Ir. Hendra Kusuma, M.T', 'hendra.kusuma@unsiq.ac.id', 'Teknik Elektro'],
        ];

        foreach ($dataDosen as $d) {
            $insertUser->execute([$d[0], $dosenPass, 'dosen']);
            $dsnUserId = $pdo->lastInsertId();
            $insertDosen->execute([$dsnUserId, $d[1], $d[2], $d[3], $d[4]]);
        }
        out("5 Dosen (dsn001 - dsn005) berhasil dibuat (password: dosen123).");

    } else {
        out("Tabel users sudah berisi data. Seed user dilewati.");
    }

    out("<strong>SETUP SELESAI! SAQUNA siap digunakan.</strong>");

} catch (Exception $e) {
    out("Terjadi kesalahan: " . $e->getMessage());
}
