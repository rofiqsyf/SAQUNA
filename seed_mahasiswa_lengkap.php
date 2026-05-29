<?php
/**
 * seed_mahasiswa_lengkap.php
 * Seed data lengkap 100 mahasiswa:
 * - Assign fakultas & prodi dari master_fakultas + master_prodi
 * - Update semester (1-8)
 * - Seed mata kuliah per prodi (jika belum ada)
 * - Assign KRS mahasiswa sesuai semester & prodi
 * - Seed jadwal kelas untuk setiap dosen-matakuliah
 */

declare(strict_types=1);
require_once __DIR__ . '/autoload.php';

set_time_limit(300);
$isCli = (php_sapi_name() === 'cli');

$log = [];
function out(string $msg, bool $ok = true): void {
    global $log, $isCli;
    $icon = $ok ? '✅' : '❌';
    $log[] = ['ok' => $ok, 'msg' => $msg];
    if ($isCli) echo "$icon $msg\n";
}

try {
    $pdo = \Config\Database::getConnection();
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // =====================================================
    // STEP 1: Ambil semua data master dari DB
    // =====================================================
    $fakProdi = $pdo->query("
        SELECT p.id as prodi_id, p.nama_prodi, p.jenjang, 
               f.id as fak_id, f.nama_fakultas, f.singkatan
        FROM master_prodi p
        JOIN master_fakultas f ON p.fakultas_id = f.id
        ORDER BY p.id
    ")->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($fakProdi)) {
        die("ERROR: Tidak ada data master prodi. Tambahkan dahulu melalui operator panel.\n");
    }
    out("Ditemukan " . count($fakProdi) . " program studi dari master data");

    // =====================================================
    // STEP 2: Seed Mata Kuliah per Prodi (yang belum punya)
    // =====================================================
    // Template MK generic per prodi (5-6 MK per prodi, smt 1-6)
    $mkTemplates = [
        // Format: [kode_prefix, nama, sks, semester]
        'default' => [
            ['101', 'Pendidikan Agama Islam', 2, 1],
            ['102', 'Bahasa Indonesia', 2, 1],
            ['103', 'Bahasa Inggris', 2, 1],
            ['104', 'Pancasila dan Kewarganegaraan', 2, 1],
            ['201', 'Pengantar Ilmu Komputer', 3, 2],
            ['202', 'Matematika Dasar', 3, 2],
            ['301', 'Metodologi Penelitian', 3, 3],
            ['401', 'Kerja Praktek', 3, 7],
            ['501', 'Skripsi / Tugas Akhir', 6, 8],
        ],
        'Teknik Informatika' => [
            ['TI-101', 'Algoritma dan Pemrograman', 3, 1],
            ['TI-102', 'Matematika Diskrit', 3, 1],
            ['TI-103', 'Pengantar Teknologi Informasi', 2, 1],
            ['TI-201', 'Struktur Data', 3, 2],
            ['TI-202', 'Pemrograman Web', 3, 2],
            ['TI-203', 'Sistem Operasi', 2, 2],
            ['TI-301', 'Basis Data', 3, 3],
            ['TI-302', 'Jaringan Komputer', 3, 3],
            ['TI-401', 'Kecerdasan Buatan', 3, 4],
            ['TI-402', 'Pemrograman Mobile', 3, 4],
            ['TI-501', 'Keamanan Sistem Informasi', 3, 5],
            ['TI-601', 'Proyek Perangkat Lunak', 3, 6],
        ],
        'Manajemen Informatika' => [
            ['MI-101', 'Pengantar Sistem Informasi', 2, 1],
            ['MI-102', 'Algoritma Pemrograman Dasar', 3, 1],
            ['MI-201', 'Analisis dan Desain Sistem', 3, 2],
            ['MI-202', 'Pemrograman Web Dasar', 3, 2],
            ['MI-301', 'Manajemen Basis Data', 3, 3],
            ['MI-302', 'E-Commerce', 2, 3],
            ['MI-401', 'Sistem Informasi Manajemen', 3, 4],
            ['MI-402', 'Akuntansi Manajemen', 2, 4],
        ],
        'Teknik Sipil' => [
            ['TS-101', 'Mekanika Teknik I', 3, 1],
            ['TS-102', 'Matematika Rekayasa', 3, 1],
            ['TS-201', 'Mekanika Tanah', 3, 2],
            ['TS-202', 'Hidrolika', 3, 2],
            ['TS-301', 'Struktur Beton', 3, 3],
            ['TS-302', 'Manajemen Konstruksi', 2, 3],
            ['TS-401', 'Teknik Pondasi', 3, 4],
        ],
        'Teknik Mesin' => [
            ['TM-101', 'Mekanika Teknik', 3, 1],
            ['TM-102', 'Material Teknik', 3, 1],
            ['TM-201', 'Termodinamika', 3, 2],
            ['TM-202', 'Mekanika Fluida', 3, 2],
            ['TM-301', 'Proses Manufaktur', 3, 3],
            ['TM-401', 'Teknik Pengelasan', 2, 4],
        ],
        'Arsitektur' => [
            ['AR-101', 'Pengantar Desain Arsitektur', 3, 1],
            ['AR-102', 'Gambar Teknik', 2, 1],
            ['AR-201', 'Desain Arsitektur I', 4, 2],
            ['AR-301', 'Desain Arsitektur II', 4, 3],
            ['AR-401', 'Teknologi Bangunan', 3, 4],
        ],
        'Pendidikan Agama Islam' => [
            ['PAI-101', 'Ulum Al-Quran', 2, 1],
            ['PAI-102', 'Ulum Hadist', 2, 1],
            ['PAI-103', 'Fiqih Ibadah', 2, 1],
            ['PAI-201', 'Tafsir Al-Quran', 3, 2],
            ['PAI-202', 'Metode Pembelajaran PAI', 3, 2],
            ['PAI-301', 'Psikologi Pendidikan', 2, 3],
            ['PAI-401', 'Micro Teaching', 2, 4],
        ],
        'Pendidikan Fisika' => [
            ['PF-101', 'Fisika Dasar I', 3, 1],
            ['PF-102', 'Kalkulus I', 3, 1],
            ['PF-201', 'Fisika Dasar II', 3, 2],
            ['PF-202', 'Listrik Magnet', 3, 2],
            ['PF-301', 'Fisika Modern', 3, 3],
            ['PF-401', 'Didaktik Metodik Fisika', 2, 4],
        ],
        'Pendidikan Bahasa Arab' => [
            ['PBA-101', 'Nahwu I', 2, 1],
            ['PBA-102', 'Sharaf I', 2, 1],
            ['PBA-103', 'Maharah Kalam', 2, 1],
            ['PBA-201', 'Nahwu II', 2, 2],
            ['PBA-202', 'Balaghah', 2, 2],
            ['PBA-301', 'Muhadatsah', 2, 3],
            ['PBA-401', 'Metode Pengajaran Bahasa Arab', 3, 4],
        ],
        'Pendidikan Islam Anak Usia Dini' => [
            ['PIAUD-101', 'Konsep Dasar PAUD', 2, 1],
            ['PIAUD-102', 'Psikologi Anak', 2, 1],
            ['PIAUD-201', 'Kurikulum PAUD', 3, 2],
            ['PIAUD-202', 'Bermain dan Permainan', 2, 2],
            ['PIAUD-301', 'Kreativitas Anak', 2, 3],
        ],
        'Pendidikan Guru Madrasah Ibtidaiyah' => [
            ['PGMI-101', 'Konsep Dasar MI', 2, 1],
            ['PGMI-102', 'Ilmu Pendidikan Islam', 2, 1],
            ['PGMI-201', 'Pembelajaran Tematik', 3, 2],
            ['PGMI-202', 'Evaluasi Pembelajaran', 2, 2],
            ['PGMI-301', 'Manajemen Kelas', 2, 3],
        ],
        'Komunikasi Penyiaran Islam' => [
            ['KPI-101', 'Dasar Komunikasi Islam', 2, 1],
            ['KPI-102', 'Jurnalistik Dasar', 2, 1],
            ['KPI-201', 'Penyiaran Radio dan TV', 3, 2],
            ['KPI-202', 'Fotografi Jurnalistik', 2, 2],
            ['KPI-301', 'Manajemen Media', 2, 3],
        ],
        'Ilmu Politik' => [
            ['IP-101', 'Pengantar Ilmu Politik', 2, 1],
            ['IP-102', 'Sistem Politik Indonesia', 2, 1],
            ['IP-201', 'Perbandingan Politik', 3, 2],
            ['IP-202', 'Partai Politik', 2, 2],
            ['IP-301', 'Teori Politik', 3, 3],
        ],
        'Hukum Keluarga Islam' => [
            ['HKI-101', 'Pengantar Hukum Islam', 2, 1],
            ['HKI-102', 'Fiqih Munakahat', 2, 1],
            ['HKI-201', 'Hukum Perkawinan Islam', 3, 2],
            ['HKI-202', 'Hukum Waris Islam', 3, 2],
            ['HKI-301', 'Peradilan Agama', 3, 3],
        ],
        'Hukum Ekonomi Syariah' => [
            ['HES-101', 'Fiqih Muamalah I', 2, 1],
            ['HES-102', 'Ekonomi Mikro Islam', 2, 1],
            ['HES-201', 'Fiqih Muamalah II', 2, 2],
            ['HES-202', 'Perbankan Syariah', 3, 2],
            ['HES-301', 'Hukum Bisnis Syariah', 3, 3],
        ],
        'Ilmu Al-Quran dan Tafsir' => [
            ['IAT-101', 'Ulum Al-Quran I', 2, 1],
            ['IAT-102', 'Bahasa Arab I', 2, 1],
            ['IAT-201', 'Tafsir Al-Quran I', 3, 2],
            ['IAT-202', 'Ulumul Hadist', 2, 2],
            ['IAT-301', 'Tafsir Tematik', 3, 3],
        ],
        'Ilmu Hukum' => [
            ['IH-101', 'Pengantar Ilmu Hukum', 2, 1],
            ['IH-102', 'Pengantar Hukum Indonesia', 2, 1],
            ['IH-201', 'Hukum Perdata', 3, 2],
            ['IH-202', 'Hukum Pidana', 3, 2],
            ['IH-301', 'Hukum Tata Negara', 3, 3],
            ['IH-302', 'Hukum Administrasi Negara', 2, 3],
        ],
    ];

    // Mata kuliah umum (wajib semua prodi, smt 1)
    $mkUmum = [
        ['UNI-101', 'Pendidikan Agama Islam', 2, 1],
        ['UNI-102', 'Pancasila dan Kewarganegaraan', 2, 1],
        ['UNI-103', 'Bahasa Indonesia', 2, 1],
        ['UNI-104', 'Bahasa Inggris', 2, 1],
    ];

    // Seed MK umum dulu (jika belum ada)
    $insertMk = $pdo->prepare("INSERT IGNORE INTO mata_kuliah (kode, nama, sks, prodi, semester_mk, kelas) VALUES (?, ?, ?, 'Umum', ?, 'A')");
    foreach ($mkUmum as $mk) {
        try {
            $insertMk->execute([$mk[0], $mk[1], $mk[2], $mk[3]]);
        } catch (\Exception $e) { /* duplikat, skip */ }
    }
    out("MK umum (Universal) di-seed");

    // Seed MK per prodi
    $insertMkProdi = $pdo->prepare("INSERT IGNORE INTO mata_kuliah (kode, nama, sks, prodi, semester_mk, kelas) VALUES (?, ?, ?, ?, ?, 'A')");
    $totalMkSeeded = 0;
    foreach ($fakProdi as $fp) {
        $prodiNama = $fp['nama_prodi'];
        // Cari template yang sesuai nama prodi
        $template = $mkTemplates[$prodiNama] ?? null;
        if (!$template) {
            // Gunakan template default jika tidak ada spesifik
            // Buat prefix dari singkatan prodi
            $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $prodiNama), 0, 3));
            $template = [
                ["$prefix-101", "Pengantar " . $prodiNama, 2, 1],
                ["$prefix-102", "Dasar-Dasar " . $prodiNama, 2, 1],
                ["$prefix-201", "Teori " . $prodiNama . " I", 3, 2],
                ["$prefix-301", "Praktikum " . $prodiNama, 2, 3],
                ["$prefix-401", "Metodologi Riset " . $prodiNama, 3, 4],
            ];
        }
        foreach ($template as $mk) {
            try {
                $res = $insertMkProdi->execute([$mk[0], $mk[1], $mk[2], $prodiNama, $mk[3]]);
                if ($insertMkProdi->rowCount() > 0) $totalMkSeeded++;
            } catch (\Exception $e) { /* duplikat, skip */ }
        }
    }
    out("$totalMkSeeded mata kuliah baru berhasil di-seed untuk semua prodi");

    // =====================================================
    // STEP 3: Assign Dosen ke Mata Kuliah yang belum punya
    // =====================================================
    $dosenList = $pdo->query("SELECT id, program_studi FROM dosen WHERE deleted_at IS NULL ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
    $mkTanpaDosen = $pdo->query("
        SELECT mk.id, mk.prodi FROM mata_kuliah mk 
        WHERE mk.id NOT IN (SELECT DISTINCT matakuliah_id FROM dosen_matakuliah)
    ")->fetchAll(\PDO::FETCH_ASSOC);

    $insertDm = $pdo->prepare("INSERT IGNORE INTO dosen_matakuliah (dosen_id, matakuliah_id, semester) VALUES (?, ?, ?)");
    $dmSeeded = 0;
    $semesterCycle = ['Ganjil', 'Genap'];
    foreach ($mkTanpaDosen as $idx => $mk) {
        // Assign dosen yang prodi-nya cocok dulu, kalau tidak ada pakai round-robin
        $dosenCocok = null;
        foreach ($dosenList as $d) {
            if ($d['program_studi'] === $mk['prodi']) {
                $dosenCocok = $d;
                break;
            }
        }
        $dosenPilih = $dosenCocok ?? $dosenList[$idx % count($dosenList)];
        $semester = $semesterCycle[$idx % 2];
        try {
            $insertDm->execute([$dosenPilih['id'], $mk['id'], $semester]);
            if ($insertDm->rowCount() > 0) $dmSeeded++;
        } catch (\Exception $e) { /* skip duplikat */ }
    }
    out("$dmSeeded relasi dosen-matakuliah baru ditambahkan");

    // =====================================================
    // STEP 4: Seed Jadwal Kelas (dari dosen_matakuliah yang belum ada jadwalnya)
    // =====================================================
    $ruanganIds = $pdo->query("SELECT id FROM ruangan ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
    if (empty($ruanganIds)) {
        // Seed ruangan minimal jika tidak ada
        $pdo->exec("INSERT IGNORE INTO ruangan (nama_ruangan, kode_ruangan, kapasitas) VALUES
            ('Ruang A101', 'A101', 40), ('Ruang A102', 'A102', 40),
            ('Ruang B201', 'B201', 35), ('Ruang B202', 'B202', 35),
            ('Lab Komputer I', 'LAB1', 30), ('Lab Komputer II', 'LAB2', 30),
            ('Aula Utama', 'AULA', 200), ('Ruang Seminar', 'SEM', 60)");
        $ruanganIds = $pdo->query("SELECT id FROM ruangan ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        out("8 ruangan baru di-seed");
    }

    $hariList  = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    $jamList   = [
        ['07:30:00', '09:10:00'], ['09:10:00', '10:50:00'],
        ['10:50:00', '12:30:00'], ['13:30:00', '15:10:00'],
        ['15:10:00', '16:50:00'],
    ];

    // Ambil semua dosen_matakuliah yang belum punya jadwal
    $dmTanpaJadwal = $pdo->query("
        SELECT dm.dosen_id, dm.matakuliah_id, dm.semester
        FROM dosen_matakuliah dm
        WHERE NOT EXISTS (
            SELECT 1 FROM jadwal_kelas jk 
            WHERE jk.dosen_id = dm.dosen_id AND jk.matakuliah_id = dm.matakuliah_id AND jk.semester = dm.semester
        )
    ")->fetchAll(\PDO::FETCH_ASSOC);

    $insertJk = $pdo->prepare("INSERT IGNORE INTO jadwal_kelas (dosen_id, matakuliah_id, semester, ruangan_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $jkSeeded = 0;
    // Track slot waktu per dosen & ruangan biar tidak bentrok
    $slotDosen   = []; // dosen_id => [hari_jam]
    $slotRuangan = []; // ruangan_id => [hari_jam]

    foreach ($dmTanpaJadwal as $idx => $dm) {
        // Cari slot yang tidak bentrok
        $assigned = false;
        foreach ($hariList as $hi => $hari) {
            foreach ($jamList as $ji => $jam) {
                $slotKey = "$hari-{$jam[0]}";
                $rid = $ruanganIds[($idx + $hi + $ji) % count($ruanganIds)];
                $dosenSlot = $slotDosen[$dm['dosen_id']] ?? [];
                $ruanganSlot = $slotRuangan[$rid] ?? [];
                if (!in_array($slotKey, $dosenSlot) && !in_array($slotKey, $ruanganSlot)) {
                    $insertJk->execute([$dm['dosen_id'], $dm['matakuliah_id'], $dm['semester'], $rid, $hari, $jam[0], $jam[1]]);
                    if ($insertJk->rowCount() > 0) {
                        $jkSeeded++;
                        $slotDosen[$dm['dosen_id']][] = $slotKey;
                        $slotRuangan[$rid][] = $slotKey;
                    }
                    $assigned = true;
                    break 2;
                }
            }
        }
        if (!$assigned) {
            // Fallback: assign tanpa cek bentrok
            $hari = $hariList[$idx % 5];
            $jam  = $jamList[$idx % 5];
            $rid  = $ruanganIds[$idx % count($ruanganIds)];
            try {
                $insertJk->execute([$dm['dosen_id'], $dm['matakuliah_id'], $dm['semester'], $rid, $hari, $jam[0], $jam[1]]);
                if ($insertJk->rowCount() > 0) $jkSeeded++;
            } catch (\Exception $e) {}
        }
    }
    out("$jkSeeded jadwal kelas baru berhasil di-seed");

    // =====================================================
    // STEP 5: Update 100 Mahasiswa dengan Fakultas, Prodi, Semester
    // =====================================================
    $allMahasiswa = $pdo->query("SELECT id, program_studi FROM mahasiswa ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
    $totalMhs = count($allMahasiswa);

    $updateMhs = $pdo->prepare("UPDATE mahasiswa SET fakultas = ?, program_studi = ?, semester = ? WHERE id = ?");
    $updatedMhs = 0;

    // Distribusikan mahasiswa ke prodi secara merata
    // Setiap prodi dapat beberapa mahasiswa
    $mhsPerProdi = ceil($totalMhs / count($fakProdi));

    foreach ($allMahasiswa as $idx => $mhs) {
        $prodiIdx   = (int)floor($idx / $mhsPerProdi) % count($fakProdi);
        $fp         = $fakProdi[$prodiIdx];
        $semester   = (($idx % 8) + 1); // Semester 1-8 merata

        $updateMhs->execute([$fp['nama_fakultas'], $fp['nama_prodi'], $semester, $mhs['id']]);
        $updatedMhs++;
    }
    out("$updatedMhs mahasiswa diupdate: fakultas, prodi, dan semester");

    // =====================================================
    // STEP 6: Seed KRS untuk setiap Mahasiswa
    // =====================================================
    // Ambil semua MK berdasarkan prodi
    $mkByProdi = [];
    $allMk = $pdo->query("SELECT id, kode, nama, sks, prodi, COALESCE(semester_mk, 1) as semester_mk FROM mata_kuliah ORDER BY prodi, semester_mk")->fetchAll(\PDO::FETCH_ASSOC);
    // Tambah MK umum
    $mkUmumDb = $pdo->query("SELECT id, kode, nama, sks, prodi, 1 as semester_mk FROM mata_kuliah WHERE prodi = 'Umum'")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($allMk as $mk) {
        $mkByProdi[$mk['prodi']][] = $mk;
    }

    // Ambil dosen per MK
    $dosenPerMk = [];
    $dmAll = $pdo->query("SELECT matakuliah_id, dosen_id FROM dosen_matakuliah")->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($dmAll as $dm) {
        $dosenPerMk[$dm['matakuliah_id']] = $dm['dosen_id'];
    }

    // Ambil semester aktif dari periode_krs
    $semesterAktif = $pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!$semesterAktif) $semesterAktif = 'Ganjil';

    // Pastikan kolom tahun_ajaran ada di tabel krs
    $krsKolom = $pdo->query("SHOW COLUMNS FROM krs LIKE 'tahun_ajaran'")->rowCount();
    if ($krsKolom === 0) {
        $pdo->exec("ALTER TABLE krs ADD COLUMN tahun_ajaran VARCHAR(9) NULL DEFAULT '2025/2026' AFTER semester_aktif");
        out("Kolom tahun_ajaran ditambahkan ke tabel krs");
    }

    $allMhsWithProdi = $pdo->query("SELECT id, program_studi, semester FROM mahasiswa ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
    
    $insertKrs = $pdo->prepare("
        INSERT IGNORE INTO krs (mahasiswa_id, matakuliah_id, dosen_id, semester_aktif, tahun_ajaran, status)
        VALUES (?, ?, ?, ?, '2025/2026', ?)
    ");
    
    $totalKrsSeeded = 0;
    $krsStatus = ['Disetujui', 'Disetujui', 'Disetujui', 'Menunggu']; // 75% disetujui

    foreach ($allMhsWithProdi as $mhsIdx => $mhs) {
        $prodiNama = $mhs['program_studi'];
        $mhsSemester = (int)$mhs['semester'];

        // Kumpulkan MK yang bisa diambil: MK prodi sesuai semester + MK umum
        $mkTersedia = array_merge(
            $mkUmumDb,
            isset($mkByProdi[$prodiNama]) ? array_filter($mkByProdi[$prodiNama], fn($m) => (int)$m['semester_mk'] <= $mhsSemester) : []
        );

        if (empty($mkTersedia)) {
            // Fallback: ambil MK apapun
            $mkTersedia = $allMk;
        }

        // Ambil 4-6 MK (simulasi beban normal 18-21 SKS)
        $mkDiambil = [];
        $totalSks = 0;
        $mkTersedia = array_values(array_unique($mkTersedia, SORT_REGULAR));
        
        // Shuffle deterministik (pakai seed berdasarkan mhs ID)
        $shuffled = $mkTersedia;
        // Lakukan seleksi berdasarkan indeks
        $offset = $mhsIdx * 3; 
        $shuffled = array_merge(
            array_slice($mkTersedia, $offset % max(1, count($mkTersedia))),
            array_slice($mkTersedia, 0, $offset % max(1, count($mkTersedia)))
        );

        foreach ($shuffled as $mk) {
            if ($totalSks >= 18) break;
            if (count($mkDiambil) >= 6) break;
            $mkDiambil[] = $mk;
            $totalSks += (int)$mk['sks'];
        }

        // Insert KRS
        $status = $krsStatus[$mhsIdx % count($krsStatus)];
        foreach ($mkDiambil as $mk) {
            $dosenId = $dosenPerMk[$mk['id']] ?? $dosenList[0]['id'];
            try {
                $insertKrs->execute([$mhs['id'], $mk['id'], $dosenId, $semesterAktif, $status]);
                if ($insertKrs->rowCount() > 0) $totalKrsSeeded++;
            } catch (\Exception $e) { /* skip duplikat */ }
        }
    }
    out("$totalKrsSeeded data KRS berhasil di-seed untuk " . count($allMhsWithProdi) . " mahasiswa");

    // =====================================================
    // STEP 7: Seed Data Presensi (kehadiran beberapa pertemuan)
    // =====================================================
    // Ambil semua KRS yang sudah Disetujui
    $krsDisetujui = $pdo->query("
        SELECT k.id as krs_id FROM krs k WHERE k.status = 'Disetujui' LIMIT 500
    ")->fetchAll(\PDO::FETCH_COLUMN);

    $insertPresensi = $pdo->prepare("INSERT IGNORE INTO presensi (krs_id, pertemuan_ke, status, waktu_presensi) VALUES (?, ?, ?, ?)");
    $presensiSeeded = 0;
    $statusPresensi = ['Hadir', 'Hadir', 'Hadir', 'Hadir', 'Izin', 'Hadir', 'Hadir', 'Alfa'];

    foreach ($krsDisetujui as $kidx => $krsId) {
        // Seed 8 pertemuan pertama (dari 16)
        $pertemuanSampai = 8;
        for ($p = 1; $p <= $pertemuanSampai; $p++) {
            $st = $statusPresensi[($kidx + $p) % count($statusPresensi)];
            $waktu = date('Y-m-d H:i:s', strtotime("2025-09-01") + (($p - 1) * 7 * 86400) + ($kidx * 60));
            try {
                $insertPresensi->execute([$krsId, $p, $st, $waktu]);
                if ($insertPresensi->rowCount() > 0) $presensiSeeded++;
            } catch (\Exception $e) {}
        }
    }
    out("$presensiSeeded data presensi di-seed (8 pertemuan x " . count($krsDisetujui) . " KRS)");

    // =====================================================
    // STEP 8: Tambah nilai huruf untuk semester lalu (simulasi transkrip)
    // =====================================================
    // Mahasiswa semester 3+ sudah punya nilai semester lalu
    $krsUntukNilai = $pdo->query("
        SELECT k.id FROM krs k 
        JOIN mahasiswa m ON k.mahasiswa_id = m.id 
        WHERE k.status = 'Disetujui' AND m.semester >= 3
        LIMIT 300
    ")->fetchAll(\PDO::FETCH_COLUMN);

    $nilaiList = ['A', 'A', 'B', 'B', 'B', 'C', 'C', 'A', 'B', 'A'];
    $updateNilai = $pdo->prepare("UPDATE krs SET nilai_huruf = ? WHERE id = ? AND nilai_huruf IS NULL");
    $nilaiSeeded = 0;
    foreach ($krsUntukNilai as $nidx => $krsId) {
        $nilai = $nilaiList[$nidx % count($nilaiList)];
        $updateNilai->execute([$nilai, $krsId]);
        if ($updateNilai->rowCount() > 0) $nilaiSeeded++;
    }
    out("$nilaiSeeded nilai huruf di-seed untuk mahasiswa semester 3+");

    // =====================================================
    // STEP 9: Update data profil mahasiswa (tempat lahir, no_hp, domisili)
    // =====================================================
    $kotaList = [
        'Wonosobo', 'Semarang', 'Yogyakarta', 'Solo', 'Purwokerto',
        'Magelang', 'Kebumen', 'Banjarnegara', 'Temanggung', 'Purworejo',
        'Jakarta', 'Bandung', 'Surabaya', 'Malang', 'Makassar',
        'Medan', 'Palembang', 'Pekanbaru', 'Banjarmasin', 'Balikpapan'
    ];
    $domisiliList = [
        'Jl. Raya Wonosobo No. ' , 'Jl. Diponegoro No. ', 'Jl. Ahmad Yani No. ',
        'Jl. Sudirman No. ', 'Jl. Gajah Mada No. '
    ];

    $allMhsForProfil = $pdo->query("SELECT id, nama FROM mahasiswa WHERE tempat_tanggal_lahir IS NULL OR tempat_tanggal_lahir = ''")->fetchAll(\PDO::FETCH_ASSOC);
    $updateProfil = $pdo->prepare("UPDATE mahasiswa SET tempat_tanggal_lahir = ?, no_hp = ?, domisili = ?, alamat_asal = ?, jenis_kelamin = ? WHERE id = ?");
    $profilSeeded = 0;
    foreach ($allMhsForProfil as $pidx => $mhs) {
        $kota = $kotaList[$pidx % count($kotaList)];
        $tahunLahir = 2000 + ($pidx % 6); // 2000-2005
        $bulan = str_pad((string)(($pidx % 12) + 1), 2, '0', STR_PAD_LEFT);
        $tgl = str_pad((string)(($pidx % 28) + 1), 2, '0', STR_PAD_LEFT);
        $ttl = "$kota, $tgl-$bulan-$tahunLahir";
        $noHp = '08' . (12 + ($pidx % 8)) . str_pad((string)(10000000 + $pidx * 13), 8, '0', STR_PAD_LEFT);
        $jk = ($pidx % 2 === 0) ? 'Laki-laki' : 'Perempuan';
        $domisili = $domisiliList[$pidx % count($domisiliList)] . (($pidx % 99) + 1) . ', ' . $kota;
        $alamatAsal = 'Ds. ' . $kotaList[($pidx + 5) % count($kotaList)] . ' RT.' . str_pad((string)(($pidx % 15) + 1), 2, '0', STR_PAD_LEFT) . ' RW.0' . (($pidx % 5) + 1);
        $updateProfil->execute([$ttl, $noHp, $domisili, $alamatAsal, $jk, $mhs['id']]);
        if ($updateProfil->rowCount() > 0) $profilSeeded++;
    }
    out("$profilSeeded profil mahasiswa dilengkapi (TTL, no HP, domisili, alamat, jenis kelamin)");

    // =====================================================
    // STEP 10: Update dosen_wali_id (assign wali dosen ke mahasiswa)
    // =====================================================
    $dosenIds = $pdo->query("SELECT id FROM dosen WHERE deleted_at IS NULL ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
    if (!empty($dosenIds)) {
        $allMhsIds = $pdo->query("SELECT id FROM mahasiswa WHERE dosen_wali_id IS NULL ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
        $updateWali = $pdo->prepare("UPDATE mahasiswa SET dosen_wali_id = ? WHERE id = ?");
        $waliSeeded = 0;
        foreach ($allMhsIds as $widx => $mhsId) {
            $dosenWali = $dosenIds[$widx % count($dosenIds)];
            $updateWali->execute([$dosenWali, $mhsId]);
            $waliSeeded++;
        }
        out("$waliSeeded mahasiswa diassign dosen wali");
    }

    // =====================================================
    // RINGKASAN AKHIR
    // =====================================================
    echo "\n";
    $totalMhsFinal  = $pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
    $totalMkFinal   = $pdo->query("SELECT COUNT(*) FROM mata_kuliah")->fetchColumn();
    $totalKrsFinal  = $pdo->query("SELECT COUNT(*) FROM krs")->fetchColumn();
    $totalJkFinal   = $pdo->query("SELECT COUNT(*) FROM jadwal_kelas")->fetchColumn();
    $totalPresFinal = $pdo->query("SELECT COUNT(*) FROM presensi")->fetchColumn();
    out("=== RINGKASAN DATABASE SAQUNA ===");
    out("Mahasiswa    : $totalMhsFinal");
    out("Mata Kuliah  : $totalMkFinal");
    out("KRS entries  : $totalKrsFinal");
    out("Jadwal Kelas : $totalJkFinal");
    out("Presensi     : $totalPresFinal");

} catch (\PDOException $e) {
    $log[] = ['ok' => false, 'msg' => 'FATAL DB ERROR: ' . $e->getMessage()];
    if ($isCli) echo "❌ FATAL: " . $e->getMessage() . "\n";
} catch (\Exception $e) {
    $log[] = ['ok' => false, 'msg' => 'ERROR: ' . $e->getMessage()];
    if ($isCli) echo "❌ ERROR: " . $e->getMessage() . "\n";
}

// ============================================================
// Tampilkan output HTML jika dijalankan via browser
// ============================================================
if (!$isCli):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seed Mahasiswa Lengkap – SAQUNA</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #0d1f17; color: #d4ede2; padding: 2rem; min-height: 100vh; }
        h1 { color: #4ade80; font-size: 1.75rem; margin-bottom: 0.25rem; }
        .subtitle { color: #7bb89a; font-size: 0.9rem; margin-bottom: 2rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat { background: #1a2e22; border: 1px solid #2d5040; border-radius: 10px; padding: 1rem; text-align: center; }
        .stat-num { font-size: 2rem; font-weight: 900; color: #4ade80; }
        .stat-label { font-size: 0.8rem; color: #7bb89a; margin-top: 0.25rem; }
        .log-card { background: #1a2e22; border: 1px solid #2d5040; border-radius: 12px; padding: 1.5rem; }
        h2 { font-size: 1rem; color: #4ade80; margin-bottom: 1rem; }
        ul { list-style: none; max-height: 500px; overflow-y: auto; }
        li { padding: 0.3rem 0; font-size: 0.85rem; border-bottom: 1px solid #1e3828; }
        li:last-child { border-bottom: none; }
        .ok { color: #4ade80; }
        .err { color: #f87171; }
        .btn { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #196b50; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; transition: background 0.2s; }
        .btn:hover { background: #1a7a5c; }
    </style>
</head>
<body>
    <h1>🎓 Seed Data Mahasiswa Lengkap</h1>
    <p class="subtitle">Dijalankan: <?= date('d M Y H:i:s') ?></p>

    <?php
    $sukses = array_filter($log, fn($l) => $l['ok']);
    $gagal  = array_filter($log, fn($l) => !$l['ok']);
    ?>
    <div class="stats-grid">
        <div class="stat"><div class="stat-num"><?= count($sukses) ?></div><div class="stat-label">Langkah Sukses</div></div>
        <div class="stat"><div class="stat-num" style="color:<?= count($gagal) > 0 ? '#f87171' : '#4ade80' ?>"><?= count($gagal) ?></div><div class="stat-label">Error</div></div>
    </div>

    <div class="log-card">
        <h2>📋 Log Proses</h2>
        <ul>
            <?php foreach ($log as $l): ?>
            <li class="<?= $l['ok'] ? 'ok' : 'err' ?>">
                <?= $l['ok'] ? '✅' : '❌' ?> <?= htmlspecialchars($l['msg']) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <a href="public/login.php" class="btn">← Kembali ke Login SAQUNA</a>
</body>
</html>
<?php endif;
