<?php
require_once __DIR__ . '/autoload.php';
$db = Config\Database::getConnection();

try {
    echo "Memulai migrasi database...\n";
    
    // 1. Tambah kolom jadwal ke dosen_matakuliah
    $db->exec("ALTER TABLE dosen_matakuliah ADD COLUMN hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NULL DEFAULT 'Senin'");
    $db->exec("ALTER TABLE dosen_matakuliah ADD COLUMN jam_mulai TIME NULL DEFAULT '08:00:00'");
    $db->exec("ALTER TABLE dosen_matakuliah ADD COLUMN jam_selesai TIME NULL DEFAULT '10:00:00'");
    $db->exec("ALTER TABLE dosen_matakuliah ADD COLUMN ruangan VARCHAR(50) NULL DEFAULT 'Ruang Utama'");
    echo "✅ Kolom jadwal berhasil ditambahkan ke tabel dosen_matakuliah.\n";

    // 2. Tambah kolom kategori ke pengumuman
    $db->exec("ALTER TABLE pengumuman ADD COLUMN kategori ENUM('Umum', 'Event', 'Beasiswa') NOT NULL DEFAULT 'Umum'");
    echo "✅ Kolom kategori berhasil ditambahkan ke tabel pengumuman.\n";

    // 3. Tambah kolom foto ke mahasiswa
    $db->exec("ALTER TABLE mahasiswa ADD COLUMN foto VARCHAR(255) NULL DEFAULT 'assets/default_mhs.png'");
    echo "✅ Kolom foto berhasil ditambahkan ke tabel mahasiswa.\n";
    
    // 4. Seeding Data Jadwal (Dummy)
    // Update semua kelas yang ada agar punya jadwal bervariasi
    $hariList = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
    $jamList = [
        ['08:00:00', '10:30:00'],
        ['10:30:00', '13:00:00'],
        ['13:00:00', '15:30:00']
    ];
    
    $stmt = $db->query("SELECT dosen_id, matakuliah_id FROM dosen_matakuliah");
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($classes as $idx => $c) {
        $hari = $hariList[$idx % count($hariList)];
        $jam = $jamList[$idx % count($jamList)];
        $ruang = "Gedung A Ruang " . (100 + $idx);
        
        $up = $db->prepare("UPDATE dosen_matakuliah SET hari = ?, jam_mulai = ?, jam_selesai = ?, ruangan = ? WHERE dosen_id = ? AND matakuliah_id = ?");
        $up->execute([$hari, $jam[0], $jam[1], $ruang, $c['dosen_id'], $c['matakuliah_id']]);
    }
    echo "✅ Data jadwal dummy berhasil di-seed.\n";
    
    // 5. Seeding Data Pengumuman Kemahasiswaan & Beasiswa
    $db->exec("INSERT INTO pengumuman (judul, isi, target_role, kategori) VALUES 
        ('Beasiswa Unggulan Kemendikbud 2026', 'Pendaftaran Beasiswa Unggulan telah dibuka. Persyaratan: IPK min 3.25, tidak sedang menerima beasiswa lain. Batas akhir: 30 Juni 2026.', 'mahasiswa', 'Beasiswa'),
        ('Pemilihan Raya Presiden BEM', 'Jangan golput! Gunakan hak pilihmu dalam Pemira BEM Universitas tanggal 5 Juli 2026 di Aula Utama.', 'mahasiswa', 'Event'),
        ('Seminar Nasional AI & Web Dev', 'Ikuti seminar tech terbesar tahun ini bersama Google Developer Group. Gratis untuk mahasiswa SAQUNA.', 'mahasiswa', 'Event')
    ");
    echo "✅ Data dummy pengumuman Event dan Beasiswa berhasil dimasukkan.\n";

} catch (PDOException $e) {
    // Kalau kolom sudah ada, abaikan saja errornya.
    echo "Info: " . $e->getMessage() . "\n";
}
