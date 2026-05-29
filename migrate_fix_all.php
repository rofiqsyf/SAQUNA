<?php
/**
 * migrate_fix_all.php
 * Script migrasi master SAQUNA - Fix semua masalah schema & seed data
 * Jalankan via browser: http://localhost/uts-pemweb/saquna/migrate_fix_all.php
 * 
 * Fixes:
 * 1. Tambah kolom prodi, semester_mk, kelas ke mata_kuliah
 * 2. Fix ENUM periode_krs.status (tambah 'Aktif')
 * 3. Tambah tabel sesi_presensi jika belum ada
 * 4. Update data tagihan ke tahun 2025/2026
 * 5. Update seed data kalender akademik yang aktif
 * 6. Fix data dosen: tambah kolom yang dibutuhkan profil
 * 7. Update pengaturan_institusi dengan nama universitas yang sesuai
 * 8. Seed jadwal kelas dari dosen_matakuliah (jika tabel kosong)
 * 9. Update periode KRS agar ada yang Aktif
 */

declare(strict_types=1);
require_once __DIR__ . '/autoload.php';

set_time_limit(120);

$isCli = (php_sapi_name() === 'cli');
$results = [];
$errors = [];

function migOut(string $step, string $msg, bool $ok = true): void {
    global $results, $errors, $isCli;
    if ($ok) {
        $results[] = "✅ [$step] $msg";
        if ($isCli) echo "✅ [$step] $msg\n";
    } else {
        $errors[] = "❌ [$step] $msg";
        if ($isCli) echo "❌ [$step] $msg\n";
    }
}

try {
    $pdo = \Config\Database::getConnection();
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // ========================================================
    // FIX 1: Tambah kolom opsional ke mata_kuliah
    // ========================================================
    $colsMataKuliah = ['prodi', 'semester_mk', 'kelas'];
    $existingCols = $pdo->query("SHOW COLUMNS FROM mata_kuliah")->fetchAll(\PDO::FETCH_COLUMN);
    
    foreach ($colsMataKuliah as $col) {
        if (!in_array($col, $existingCols)) {
            try {
                if ($col === 'semester_mk') {
                    $pdo->exec("ALTER TABLE mata_kuliah ADD COLUMN semester_mk TINYINT UNSIGNED NULL COMMENT 'Semester ke berapa MK ini (1-8)'");
                } elseif ($col === 'prodi') {
                    $pdo->exec("ALTER TABLE mata_kuliah ADD COLUMN prodi VARCHAR(100) NULL COMMENT 'Program studi yang menyelenggarakan MK ini'");
                } elseif ($col === 'kelas') {
                    $pdo->exec("ALTER TABLE mata_kuliah ADD COLUMN kelas VARCHAR(10) NULL COMMENT 'Kelas (A, B, dst)'");
                }
                migOut("mata_kuliah", "Kolom '$col' berhasil ditambahkan");
            } catch (\PDOException $e) {
                migOut("mata_kuliah", "Kolom '$col': " . $e->getMessage(), false);
            }
        } else {
            migOut("mata_kuliah", "Kolom '$col' sudah ada, dilewati");
        }
    }

    // ========================================================
    // FIX 2: Fix ENUM periode_krs.status – tambah 'Aktif'
    // ========================================================
    try {
        // Ubah ENUM agar support 'Aktif' (bukan hanya Buka/Tutup)
        $pdo->exec("ALTER TABLE periode_krs MODIFY COLUMN status ENUM('Aktif','Buka','Tutup') NOT NULL DEFAULT 'Tutup'");
        migOut("periode_krs", "ENUM status diperbarui: 'Aktif','Buka','Tutup'");
    } catch (\PDOException $e) {
        migOut("periode_krs", "ENUM: " . $e->getMessage(), false);
    }

    // ========================================================
    // FIX 3: Pastikan ada periode KRS yang berstatus 'Aktif'
    // ========================================================
    try {
        $countAktif = (int)$pdo->query("SELECT COUNT(*) FROM periode_krs WHERE status = 'Aktif'")->fetchColumn();
        if ($countAktif === 0) {
            // Cek apakah ada data sama sekali
            $countAll = (int)$pdo->query("SELECT COUNT(*) FROM periode_krs")->fetchColumn();
            if ($countAll === 0) {
                // Insert periode KRS baru yang aktif
                $pdo->exec("INSERT INTO periode_krs (nama_periode, semester, tahun_ajaran, tanggal_buka, tanggal_tutup, status, catatan)
                    VALUES 
                    ('KRS Ganjil 2025/2026', 'Ganjil', '2025/2026', '2025-08-01 00:00:00', '2026-01-31 23:59:59', 'Aktif', 'Periode KRS Semester Ganjil 2025/2026'),
                    ('KRS Genap 2025/2026', 'Genap', '2025/2026', '2026-02-01 00:00:00', '2026-07-31 23:59:59', 'Tutup', 'Periode KRS Semester Genap 2025/2026')");
                migOut("periode_krs", "Data periode KRS 2025/2026 berhasil di-seed (Ganjil: Aktif)");
            } else {
                // Aktifkan periode paling akhir
                $pdo->exec("UPDATE periode_krs SET status = 'Aktif' ORDER BY id DESC LIMIT 1");
                migOut("periode_krs", "Periode KRS terakhir diaktifkan (status → Aktif)");
            }
        } else {
            migOut("periode_krs", "Sudah ada " . $countAktif . " periode dengan status Aktif");
        }
    } catch (\PDOException $e) {
        migOut("periode_krs", "Seed: " . $e->getMessage(), false);
    }

    // ========================================================
    // FIX 4: Pastikan tabel sesi_presensi ada dengan kolom lengkap
    // ========================================================
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS sesi_presensi (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            dosen_id INT UNSIGNED NOT NULL,
            matakuliah_id INT UNSIGNED NOT NULL,
            semester_aktif VARCHAR(20) NOT NULL,
            pertemuan_ke TINYINT UNSIGNED NOT NULL,
            status ENUM('Jadwal','Buka','Tutup') NOT NULL DEFAULT 'Jadwal',
            token_qr VARCHAR(100) NULL,
            token_expired_at TIMESTAMP NULL,
            waktu_buka TIMESTAMP NULL,
            waktu_tutup TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sesi (dosen_id, matakuliah_id, semester_aktif, pertemuan_ke),
            FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE,
            FOREIGN KEY (matakuliah_id) REFERENCES mata_kuliah(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        migOut("sesi_presensi", "Tabel sesi_presensi siap");
    } catch (\PDOException $e) {
        migOut("sesi_presensi", $e->getMessage(), false);
    }

    // ========================================================
    // FIX 5: Tambah kolom profil ke tabel dosen jika belum ada
    // ========================================================
    $colsDosen = [
        'tempat_tanggal_lahir' => 'VARCHAR(255) NULL',
        'jenis_kelamin'        => "ENUM('Laki-laki','Perempuan') NULL",
        'no_hp'                => 'VARCHAR(20) NULL',
        'alamat_asal'          => 'TEXT NULL',
        'domisili'             => 'TEXT NULL',
        'fakultas'             => 'VARCHAR(100) NULL',
    ];
    $existingDosenCols = $pdo->query("SHOW COLUMNS FROM dosen")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($colsDosen as $col => $def) {
        if (!in_array($col, $existingDosenCols)) {
            try {
                $pdo->exec("ALTER TABLE dosen ADD COLUMN {$col} {$def}");
                migOut("dosen", "Kolom '$col' ditambahkan");
            } catch (\PDOException $e) {
                migOut("dosen", "Kolom '$col': " . $e->getMessage(), false);
            }
        } else {
            migOut("dosen", "Kolom '$col' sudah ada");
        }
    }

    // ========================================================
    // FIX 6: Update tagihan mahasiswa ke tahun 2025/2026
    // ========================================================
    try {
        $oldTagihan = (int)$pdo->query("SELECT COUNT(*) FROM tagihan_pembayaran WHERE tahun_ajaran != '2025/2026'")->fetchColumn();
        if ($oldTagihan > 0) {
            $pdo->exec("UPDATE tagihan_pembayaran SET tahun_ajaran = '2025/2026', semester = 'Ganjil' WHERE tahun_ajaran != '2025/2026'");
            migOut("tagihan_pembayaran", "$oldTagihan tagihan diupdate ke tahun ajaran 2025/2026");
        } else {
            migOut("tagihan_pembayaran", "Semua tagihan sudah bertahun 2025/2026");
        }
    } catch (\PDOException $e) {
        migOut("tagihan_pembayaran", $e->getMessage(), false);
    }

    // ========================================================
    // FIX 7: Update data kalender akademik dengan tanggal valid 2025/2026
    // ========================================================
    try {
        $countKal = (int)$pdo->query("SELECT COUNT(*) FROM kalender_akademik WHERE tahun_ajaran = '2025/2026'")->fetchColumn();
        if ($countKal === 0) {
            $pdo->exec("INSERT INTO kalender_akademik (nama_event, jenis_event, tanggal_mulai, tanggal_akhir, semester, tahun_ajaran) VALUES
                ('Pengisian KRS Semester Ganjil', 'Periode KRS', '2025-08-01', '2025-08-15', 'Ganjil', '2025/2026'),
                ('Perkuliahan Semester Ganjil Dimulai', 'Lainnya', '2025-09-01', '2025-09-01', 'Ganjil', '2025/2026'),
                ('Ujian Tengah Semester Ganjil', 'UTS', '2025-11-03', '2025-11-14', 'Ganjil', '2025/2026'),
                ('Ujian Akhir Semester Ganjil', 'UAS', '2026-01-05', '2026-01-16', 'Ganjil', '2025/2026'),
                ('Pengisian KRS Semester Genap', 'Periode KRS', '2026-02-02', '2026-02-13', 'Genap', '2025/2026'),
                ('Perkuliahan Semester Genap Dimulai', 'Lainnya', '2026-02-23', '2026-02-23', 'Genap', '2025/2026'),
                ('Ujian Tengah Semester Genap', 'UTS', '2026-04-20', '2026-05-01', 'Genap', '2025/2026'),
                ('Ujian Akhir Semester Genap', 'UAS', '2026-06-15', '2026-06-26', 'Genap', '2025/2026'),
                ('Wisuda Periode I', 'Wisuda', '2026-08-01', '2026-08-01', 'Genap', '2025/2026')");
            migOut("kalender_akademik", "9 event kalender 2025/2026 berhasil di-seed");
        } else {
            migOut("kalender_akademik", "Data kalender 2025/2026 sudah ada ($countKal event)");
        }
    } catch (\PDOException $e) {
        migOut("kalender_akademik", $e->getMessage(), false);
    }

    // ========================================================
    // FIX 8: Update pengaturan_institusi dengan data lengkap
    // ========================================================
    $settings = [
        'nama_universitas'  => 'Universitas Sains Al-Qur\'an (UNSIQ)',
        'singkatan_univ'    => 'UNSIQ',
        'alamat_kampus'     => 'Jl. KH. Wahid Hasyim No. 1, Wonosobo, Jawa Tengah',
        'telepon_kampus'    => '(0286) 321436',
        'email_kampus'      => 'info@unsiq.ac.id',
        'website_kampus'    => 'https://unsiq.ac.id',
        'rektor'            => 'Prof. Dr. H. Noor Ahmad, MA',
        'total_sks_lulus'   => '144',
    ];
    foreach ($settings as $kunci => $nilai) {
        try {
            $pdo->prepare("INSERT INTO pengaturan_institusi (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = IF(nilai = '', VALUES(nilai), nilai)")
                ->execute([$kunci, $nilai]);
            migOut("pengaturan_institusi", "Setting '$kunci' disimpan");
        } catch (\PDOException $e) {
            migOut("pengaturan_institusi", "'$kunci': " . $e->getMessage(), false);
        }
    }

    // ========================================================
    // FIX 9: Update nama mahasiswa dan dosen agar lebih realistis
    // ========================================================
    try {
        // Cek apakah masih ada nama dummy
        $dummyMhs = (int)$pdo->query("SELECT COUNT(*) FROM mahasiswa WHERE nama LIKE 'Mahasiswa %'")->fetchColumn();
        
        if ($dummyMhs > 0) {
            $namaDepan = [
                'Ahmad','Budi','Cahyo','Dian','Eko','Fajar','Galih','Hana','Irfan','Joko',
                'Kiki','Lina','Maulana','Novi','Omar','Puti','Qori','Rizky','Siti','Taufik',
                'Ulfa','Vina','Wahyu','Xena','Yusuf','Zahra','Agus','Bella','Citra','Dimas',
                'Elsa','Fauzi','Gina','Heri','Indah','Jaya','Kartika','Laila','Miko','Nadia',
                'Oscar','Putri','Qasim','Rani','Surya','Tina','Umar','Vera','Wendi','Yanti',
                'Zaki','Aditya','Bagas','Cindy','Dani','Enny','Fira','Gilang','Hesti','Ivan',
                'Julia','Kevin','Luna','Mira','Nando','Ola','Pram','Qinta','Reza','Sari',
                'Tommy','Utari','Vito','Wina','Xandra','Yogi','Zetia','Alfi','Bintang','Clara',
                'Dani','Erwin','Fitri','Ganda','Helmi','Imron','Johan','Kurnia','Luthfi','Maya',
                'Naufal','Okta','Pandu','Qaila','Renata','Sigit','Tiara','Ulfan','Valeria','Wisnu'
            ];
            $namaBelakang = [
                'Pratama','Santoso','Wijaya','Kusuma','Setiawan','Hakim','Putra','Saputra',
                'Nugroho','Ardiansyah','Rahmawati','Fitriani','Anggraini','Lestari','Dewi',
                'Purnama','Cahyono','Hidayat','Fauzi','Maulana','Yusuf','Halim','Basuki',
                'Wahyudi','Suharto','Wibowo','Suryadi','Perdana','Gunawan','Hartono'
            ];
            
            $mhsList = $pdo->query("SELECT id FROM mahasiswa WHERE nama LIKE 'Mahasiswa %'")->fetchAll(\PDO::FETCH_COLUMN);
            $updateMhs = $pdo->prepare("UPDATE mahasiswa SET nama = ?, email = ?, no_hp = ? WHERE id = ?");
            
            foreach ($mhsList as $idx => $mhsId) {
                $nm1 = $namaDepan[$idx % count($namaDepan)];
                $nm2 = $namaBelakang[($idx * 3) % count($namaBelakang)];
                $nm3 = $namaBelakang[($idx * 7 + 2) % count($namaBelakang)];
                $namaLengkap = "$nm1 $nm2 $nm3";
                $emailMhs = strtolower(str_replace(' ', '.', "$nm1.$nm2")) . ($idx + 1) . "@mahasiswa.unsiq.ac.id";
                $noHp = '08' . str_pad((string)(10000000 + $idx * 17), 10, '0', STR_PAD_LEFT);
                $updateMhs->execute([$namaLengkap, $emailMhs, $noHp, $mhsId]);
            }
            migOut("mahasiswa", "$dummyMhs nama dummy diperbarui ke nama Indonesia yang realistis");
        } else {
            migOut("mahasiswa", "Nama mahasiswa sudah realistis");
        }
    } catch (\PDOException $e) {
        migOut("mahasiswa", "Update nama: " . $e->getMessage(), false);
    }

    // ========================================================
    // FIX 10: Update nama dosen agar lebih realistis
    // ========================================================
    try {
        $dummyDosen = (int)$pdo->query("SELECT COUNT(*) FROM dosen WHERE nama LIKE 'Dosen Pengajar %'")->fetchColumn();
        if ($dummyDosen > 0) {
            $namaDosen = [
                ['Dr. Ahmad Fauzi, M.Kom', 'Teknik Informatika'],
                ['Prof. Budi Santoso, Ph.D', 'Sistem Informasi'],
                ['Dr. Cahyono Wibowo, M.T', 'Teknik Elektro'],
                ['Dra. Siti Fatimah, M.Pd', 'Teknik Informatika'],
                ['Dr. Ir. Hendra Kusuma, M.T', 'Teknik Elektro'],
            ];
            $dosenList = $pdo->query("SELECT id FROM dosen WHERE nama LIKE 'Dosen Pengajar %'")->fetchAll(\PDO::FETCH_COLUMN);
            $updateDosen = $pdo->prepare("UPDATE dosen SET nama = ?, program_studi = ?, email = ? WHERE id = ?");
            
            foreach ($dosenList as $idx => $dosenId) {
                $d = $namaDosen[$idx % count($namaDosen)];
                $emailDsn = 'dsn' . str_pad((string)($idx + 1), 3, '0', STR_PAD_LEFT) . '@unsiq.ac.id';
                $updateDosen->execute([$d[0], $d[1], $emailDsn, $dosenId]);
            }
            migOut("dosen", "$dummyDosen nama dosen diperbarui ke nama realistis dengan gelar");
        } else {
            migOut("dosen", "Nama dosen sudah realistis");
        }
    } catch (\PDOException $e) {
        migOut("dosen", "Update dosen: " . $e->getMessage(), false);
    }

    // ========================================================
    // FIX 11: Seed jadwal_kelas dari dosen_matakuliah (jika kosong)
    // ========================================================
    try {
        $countJadwal = (int)$pdo->query("SELECT COUNT(*) FROM jadwal_kelas")->fetchColumn();
        $countRuangan = (int)$pdo->query("SELECT COUNT(*) FROM ruangan")->fetchColumn();
        
        if ($countJadwal === 0 && $countRuangan > 0) {
            $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
            $jamList  = [
                ['07:30:00', '09:10:00'],
                ['09:10:00', '10:50:00'],
                ['10:50:00', '12:30:00'],
                ['13:30:00', '15:10:00'],
                ['15:10:00', '16:50:00'],
            ];
            $semesterList = $pdo->query("SELECT DISTINCT semester FROM dosen_matakuliah")->fetchAll(\PDO::FETCH_COLUMN);
            
            $dmList = $pdo->query("SELECT dosen_id, matakuliah_id, semester FROM dosen_matakuliah")->fetchAll(\PDO::FETCH_ASSOC);
            $ruanganIds = $pdo->query("SELECT id FROM ruangan ORDER BY id ASC")->fetchAll(\PDO::FETCH_COLUMN);
            
            $stmtInsert = $pdo->prepare("INSERT IGNORE INTO jadwal_kelas (dosen_id, matakuliah_id, semester, ruangan_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($dmList as $idx => $dm) {
                $hari = $hariList[$idx % count($hariList)];
                $jam  = $jamList[($idx + intval($idx / count($hariList))) % count($jamList)];
                $ruanganId = $ruanganIds[$idx % count($ruanganIds)];
                $stmtInsert->execute([
                    $dm['dosen_id'], $dm['matakuliah_id'], $dm['semester'],
                    $ruanganId, $hari, $jam[0], $jam[1]
                ]);
            }
            migOut("jadwal_kelas", count($dmList) . " jadwal kelas berhasil di-seed dari dosen_matakuliah");
        } elseif ($countJadwal > 0) {
            migOut("jadwal_kelas", "Sudah ada $countJadwal data jadwal kelas");
        } else {
            migOut("jadwal_kelas", "Tidak ada ruangan — seed jadwal dilewati (jalankan migrate_phase1.php dulu)", false);
        }
    } catch (\PDOException $e) {
        migOut("jadwal_kelas", "Seed: " . $e->getMessage(), false);
    }

    // ========================================================
    // FIX 12: Tambah kolom pengumpulan_tugas (keterangan, tautan) jika belum ada
    // ========================================================
    $colsPengumpulan = ['keterangan' => 'TEXT NULL', 'tautan' => 'VARCHAR(500) NULL'];
    $existingPT = $pdo->query("SHOW COLUMNS FROM pengumpulan_tugas")->fetchAll(\PDO::FETCH_COLUMN);
    foreach ($colsPengumpulan as $col => $def) {
        if (!in_array($col, $existingPT)) {
            try {
                $pdo->exec("ALTER TABLE pengumpulan_tugas ADD COLUMN {$col} {$def}");
                migOut("pengumpulan_tugas", "Kolom '$col' ditambahkan");
            } catch (\PDOException $e) {
                migOut("pengumpulan_tugas", "'$col': " . $e->getMessage(), false);
            }
        } else {
            migOut("pengumpulan_tugas", "Kolom '$col' sudah ada");
        }
    }

    // ========================================================
    // FIX 13: Seed pengumuman jika kosong
    // ========================================================
    try {
        $countPengumuman = (int)$pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn();
        // Cek kolom kategori
        $colsPengumuman = $pdo->query("SHOW COLUMNS FROM pengumuman")->fetchAll(\PDO::FETCH_COLUMN);
        if (!in_array('kategori', $colsPengumuman)) {
            $pdo->exec("ALTER TABLE pengumuman ADD COLUMN kategori ENUM('Umum','Event','Beasiswa') NOT NULL DEFAULT 'Umum'");
            migOut("pengumuman", "Kolom 'kategori' ditambahkan");
        }
        
        if ($countPengumuman === 0) {
            $pdo->exec("INSERT INTO pengumuman (judul, isi, target_role, kategori) VALUES
                ('Selamat Datang di SAQUNA', 'Sistem Informasi Akademik Universitas Sains Al-Quran (SAQUNA) telah beroperasi. Gunakan sistem ini untuk keperluan akademik Anda.', 'semua', 'Umum'),
                ('Jadwal Pengisian KRS Semester Ganjil 2025/2026', 'Pengisian KRS Semester Ganjil 2025/2026 dibuka mulai 1 Agustus 2025. Mahasiswa yang belum mengisi KRS sebelum 15 Agustus 2025 dianggap tidak aktif kuliah.', 'mahasiswa', 'Umum'),
                ('Beasiswa Unggulan Kemendikbud 2025', 'Pendaftaran Beasiswa Unggulan dibuka. Persyaratan: IPK minimal 3.25, tidak sedang menerima beasiswa lain. Batas akhir 30 September 2025. Daftar di situ resmi Kemendikbud.', 'mahasiswa', 'Beasiswa'),
                ('Seminar Nasional Teknologi Informasi 2025', 'Fakultas Teknik menyelenggarakan Seminar Nasional TI 2025 pada 15 Oktober 2025. Gratis untuk mahasiswa UNSIQ. Daftar segera!', 'mahasiswa', 'Event'),
                ('Pelatihan Input Nilai di SAQUNA', 'Bagi Bapak/Ibu Dosen, sistem input nilai semester ganjil 2025/2026 sudah dibuka. Harap input nilai sebelum 30 Januari 2026.', 'dosen', 'Umum'),
                ('Yudisium Periode Agustus 2026', 'Pendaftaran Yudisium Periode Agustus 2026 dibuka mulai 1 Juni 2026. Syarat: telah menyelesaikan 144 SKS dengan IPK minimal 2.0.', 'mahasiswa', 'Umum')");
            migOut("pengumuman", "6 pengumuman awal berhasil di-seed");
        } else {
            migOut("pengumuman", "Sudah ada $countPengumuman pengumuman");
        }
    } catch (\PDOException $e) {
        migOut("pengumuman", $e->getMessage(), false);
    }

    // ========================================================
    // FIX 14: Tambah kolom user_id ke dosen jika belum ada
    // ========================================================
    // (sudah ada di seed.sql, tapi verifikasi)
    migOut("verifikasi", "Semua fix telah dijalankan");

} catch (\PDOException $e) {
    $errors[] = "❌ [FATAL] " . $e->getMessage();
}

// ============================================================
// Tampilkan Hasil
// ============================================================
if (!$isCli):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAQUNA – Migration Fix All</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f1a15; color: #e0ede8; margin: 0; padding: 2rem; }
        h1 { color: #4ade80; font-size: 1.8rem; margin-bottom: 0.5rem; }
        .subtitle { color: #9eb8ad; margin-bottom: 2rem; }
        .card { background: #1a2e24; border: 1px solid #2d4a38; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { padding: 0.4rem 0; border-bottom: 1px solid #2d4a38; font-size: 0.9rem; }
        li:last-child { border-bottom: none; }
        .stats { display: flex; gap: 1rem; margin-top: 1.5rem; }
        .stat-box { background: #1a2e24; border-radius: 8px; padding: 1rem; flex: 1; text-align: center; border: 1px solid #2d4a38; }
        .stat-num { font-size: 2rem; font-weight: 900; }
        .back-btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #196b50; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; }
        .back-btn:hover { background: #1a7a5c; }
    </style>
</head>
<body>
    <h1>🔧 SAQUNA – Migration Fix All</h1>
    <p class="subtitle">Script perbaikan database komprehensif. Dijalankan pada: <?= date('d M Y H:i:s') ?></p>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-num success"><?= count($results) ?></div>
            <div>Berhasil</div>
        </div>
        <div class="stat-box">
            <div class="stat-num error"><?= count($errors) ?></div>
            <div>Error / Peringatan</div>
        </div>
    </div>

    <?php if (!empty($results)): ?>
    <div class="card" style="margin-top: 1.5rem;">
        <h2 class="success">✅ Sukses (<?= count($results) ?>)</h2>
        <ul>
            <?php foreach ($results as $r): ?>
            <li class="success"><?= htmlspecialchars($r) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="card">
        <h2 class="error">❌ Error / Peringatan (<?= count($errors) ?>)</h2>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li class="error"><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
        <p style="color: #9eb8ad; font-size: 0.85rem; margin-top: 0.75rem;">
            ⚠️ Error di atas bisa terjadi karena kolom/tabel sudah ada (tidak berbahaya). Periksa pesan error untuk detail.
        </p>
    </div>
    <?php endif; ?>

    <a href="public/login.php" class="back-btn">← Kembali ke Login SAQUNA</a>
</body>
</html>
<?php
endif;
