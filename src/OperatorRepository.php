<?php
declare(strict_types=1);

namespace Src;

use Config\Database;
use PDO;
use PDOException;

class OperatorRepository {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    // --- MASTER DATA MAHASISWA ---
    public function getAllMahasiswa(array $filters = []): array {
        $sql = "SELECT m.*, u.username, d.nama as dosen_wali_nama 
                FROM mahasiswa m 
                JOIN users u ON m.user_id = u.id 
                LEFT JOIN dosen d ON m.dosen_wali_id = d.id 
                WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($filters['fakultas'])) {
            $sql .= " AND m.fakultas = ?";
            $params[] = $filters['fakultas'];
        }
        if (!empty($filters['program_studi'])) {
            $sql .= " AND m.program_studi = ?";
            $params[] = $filters['program_studi'];
        }
        if (!empty($filters['semester'])) {
            $sql .= " AND m.semester = ?";
            $params[] = $filters['semester'];
        }

        $sql .= " ORDER BY m.nim ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createMahasiswa(array $data): bool {
        try {
            $this->pdo->beginTransaction();

            // 1. Create User — gunakan password_hash (bukan password)
            $passwordHash = password_hash($data['nim'], PASSWORD_DEFAULT);
            $stmtUser = $this->pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'mahasiswa')");
            $stmtUser->execute([$data['nim'], $passwordHash]);
            
            $userId = $this->pdo->lastInsertId();

            // 2. Create Mahasiswa
            $stmtMhs = $this->pdo->prepare("INSERT INTO mahasiswa (user_id, nim, nama, fakultas, program_studi, semester) VALUES (?, ?, ?, ?, ?, ?)");
            $stmtMhs->execute([
                $userId,
                $data['nim'],
                $data['nama'],
                $data['fakultas'] ?? null,
                $data['program_studi'] ?? null,
                $data['semester'] ?? 1
            ]);

            Auth::logActivity(null, 'create', 'mahasiswa', (int)$userId, "Membuat mahasiswa baru NIM: {$data['nim']}", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (\PDOException $e) {
            $this->pdo->rollBack();
            error_log('createMahasiswa error: ' . $e->getMessage());
            return false;
        }
    }

    public function importMahasiswaFromCSV(string $filePath): array {
        $result = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $result['errors'][] = "File tidak dapat dibaca.";
            return $result;
        }

        $header = null;
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                    continue;
                }
                
                // Expecting CSV format: nim, nama, fakultas, program_studi, semester
                if (count($row) >= 5) {
                    $data = [
                        'nim' => trim($row[0]),
                        'nama' => trim($row[1]),
                        'fakultas' => trim($row[2]),
                        'program_studi' => trim($row[3]),
                        'semester' => (int)trim($row[4])
                    ];
                    
                    if (empty($data['nim']) || empty($data['nama'])) {
                        $result['failed']++;
                        $result['errors'][] = "NIM atau Nama kosong pada baris.";
                        continue;
                    }

                    if ($this->createMahasiswa($data)) {
                        $result['success']++;
                    } else {
                        $result['failed']++;
                        $result['errors'][] = "Gagal menyimpan data NIM: " . $data['nim'];
                    }
                } else {
                    $result['failed']++;
                    $result['errors'][] = "Format baris tidak valid.";
                }
            }
            fclose($handle);
        }
        
        return $result;
    }

    public function updateDataAkademikMahasiswa(int $mahasiswaId, array $data): bool {
        try {
            $this->pdo->beginTransaction();
            
            // Build dynamic query to update both academic and basic data if provided
            $fields = [
                'program_studi' => $data['program_studi'],
                'fakultas' => $data['fakultas'],
                'semester' => $data['semester'],
                'dosen_wali_id' => $data['dosen_wali_id'] ?: null
            ];
            
            if (isset($data['nama'])) $fields['nama'] = $data['nama'];
            if (isset($data['nim'])) $fields['nim'] = $data['nim'];
            if (isset($data['foto'])) $fields['foto'] = $data['foto'];

            $setClause = [];
            $values = [];
            foreach ($fields as $key => $val) {
                $setClause[] = "$key = ?";
                $values[] = $val;
            }
            $values[] = $mahasiswaId;

            $sql = "UPDATE mahasiswa SET " . implode(", ", $setClause) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            // If NIM is updated, update the user's username
            if (isset($data['nim'])) {
                $stmtUser = $this->pdo->prepare("UPDATE users SET username = ? WHERE id = (SELECT user_id FROM mahasiswa WHERE id = ?)");
                $stmtUser->execute([$data['nim'], $mahasiswaId]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Gagal update mahasiswa: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteMahasiswa(int $mahasiswaId): bool {
        try {
            $this->pdo->beginTransaction();
            
            // Ambil user_id mahasiswa ini
            $stmt = $this->pdo->prepare("SELECT user_id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$mahasiswaId]);
            $user_id = $stmt->fetchColumn();
            
            // Hapus di tabel mahasiswa
            $stmtDel = $this->pdo->prepare("DELETE FROM mahasiswa WHERE id = ?");
            $stmtDel->execute([$mahasiswaId]);
            
            // Hapus di tabel users jika ada
            if ($user_id) {
                $stmtUsr = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmtUsr->execute([$user_id]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Gagal hapus mahasiswa: " . $e->getMessage());
            return false;
        }
    }

    public function getAllDosen(): array {
        $stmt = $this->pdo->query("SELECT * FROM dosen WHERE status = 'aktif' AND deleted_at IS NULL ORDER BY nama ASC");
        return $stmt->fetchAll();
    }

    // --- MASTER INSTITUSI ---
    public function getPengaturan(string $kunci): ?string {
        $stmt = $this->pdo->prepare("SELECT nilai FROM pengaturan_institusi WHERE kunci = ?");
        $stmt->execute([$kunci]);
        $res = $stmt->fetch();
        return $res ? $res['nilai'] : null;
    }

    public function setPengaturan(string $kunci, string $nilai): bool {
        $stmt = $this->pdo->prepare("INSERT INTO pengaturan_institusi (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = ?");
        return $stmt->execute([$kunci, $nilai, $nilai]);
    }

    // --- MASTER KAMPUS ---
    public function getAllKampus(): array {
        $stmt = $this->pdo->query("SELECT * FROM master_kampus ORDER BY nama_kampus ASC");
        return $stmt->fetchAll();
    }

    public function addKampus(string $nama, ?string $alamat = null): bool {
        $stmt = $this->pdo->prepare("INSERT INTO master_kampus (nama_kampus, alamat) VALUES (?, ?)");
        return $stmt->execute([$nama, $alamat]);
    }

    public function updateKampus(int $id, string $nama, ?string $alamat = null): bool {
        $stmt = $this->pdo->prepare("UPDATE master_kampus SET nama_kampus = ?, alamat = ? WHERE id = ?");
        return $stmt->execute([$nama, $alamat, $id]);
    }

    public function deleteKampus(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM master_kampus WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- MASTER GEDUNG ---
    public function getAllGedung(): array {
        $stmt = $this->pdo->query("SELECT g.*, k.nama_kampus FROM master_gedung g JOIN master_kampus k ON g.kampus_id = k.id ORDER BY k.nama_kampus ASC, g.nama_gedung ASC");
        return $stmt->fetchAll();
    }

    public function addGedung(int $kampusId, string $nama): bool {
        $stmt = $this->pdo->prepare("INSERT INTO master_gedung (kampus_id, nama_gedung) VALUES (?, ?)");
        return $stmt->execute([$kampusId, $nama]);
    }

    public function updateGedung(int $id, int $kampusId, string $nama): bool {
        $stmt = $this->pdo->prepare("UPDATE master_gedung SET kampus_id = ?, nama_gedung = ? WHERE id = ?");
        return $stmt->execute([$kampusId, $nama, $id]);
    }

    public function deleteGedung(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM master_gedung WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAllRuangan(): array {
        $stmt = $this->pdo->query("
            SELECT r.*, g.nama_gedung, k.nama_kampus 
            FROM ruangan r 
            LEFT JOIN master_gedung g ON r.gedung_id = g.id 
            LEFT JOIN master_kampus k ON g.kampus_id = k.id 
            ORDER BY k.nama_kampus ASC, g.nama_gedung ASC, r.kode_ruangan ASC
        ");
        return $stmt->fetchAll();
    }

    public function addRuangan(string $kode, string $nama, int $gedungId, int $kapasitas, string $jenis): bool {
        $stmt = $this->pdo->prepare("INSERT INTO ruangan (kode_ruangan, nama_ruangan, gedung_id, kapasitas, jenis) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$kode, $nama, $gedungId ?: null, $kapasitas, $jenis]);
    }

    public function updateRuangan(int $id, string $kode, string $nama, int $gedungId, int $kapasitas, string $jenis): bool {
        $stmt = $this->pdo->prepare("UPDATE ruangan SET kode_ruangan = ?, nama_ruangan = ?, gedung_id = ?, kapasitas = ?, jenis = ? WHERE id = ?");
        return $stmt->execute([$kode, $nama, $gedungId ?: null, $kapasitas, $jenis, $id]);
    }

    public function deleteRuangan(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM ruangan WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAllFakultas(): array {
        $stmt = $this->pdo->query("SELECT * FROM master_fakultas ORDER BY nama_fakultas ASC");
        return $stmt->fetchAll();
    }

    public function addFakultas(string $nama, string $singkatan): bool {
        $stmt = $this->pdo->prepare("INSERT INTO master_fakultas (nama_fakultas, singkatan) VALUES (?, ?)");
        return $stmt->execute([$nama, $singkatan]);
    }

    public function updateFakultas(int $id, string $nama, string $singkatan): bool {
        $stmt = $this->pdo->prepare("UPDATE master_fakultas SET nama_fakultas = ?, singkatan = ? WHERE id = ?");
        return $stmt->execute([$nama, $singkatan, $id]);
    }

    public function deleteFakultas(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM master_fakultas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAllProdi(): array {
        $stmt = $this->pdo->query("SELECT p.*, f.nama_fakultas FROM master_prodi p JOIN master_fakultas f ON p.fakultas_id = f.id ORDER BY p.nama_prodi ASC");
        return $stmt->fetchAll();
    }

    public function addProdi(int $fakultasId, string $nama, string $jenjang): bool {
        $stmt = $this->pdo->prepare("INSERT INTO master_prodi (fakultas_id, nama_prodi, jenjang) VALUES (?, ?, ?)");
        return $stmt->execute([$fakultasId, $nama, $jenjang]);
    }

    public function updateProdi(int $id, int $fakultasId, string $nama, string $jenjang): bool {
        $stmt = $this->pdo->prepare("UPDATE master_prodi SET fakultas_id = ?, nama_prodi = ?, jenjang = ? WHERE id = ?");
        return $stmt->execute([$fakultasId, $nama, $jenjang, $id]);
    }

    public function deleteProdi(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM master_prodi WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- MASTER DATA MATA KULIAH ---
    public function getAllMataKuliah(): array {
        // Ambil semua kolom — kolom opsional (prodi, semester_mk, kelas) bisa NULL jika belum di-migrate
        $stmt = $this->pdo->query("
            SELECT id, kode, nama, sks,
                   COALESCE(prodi, '') as prodi,
                   COALESCE(semester_mk, 0) as semester,
                   COALESCE(kelas, '') as kelas
            FROM mata_kuliah 
            ORDER BY prodi ASC, semester_mk ASC, kode ASC
        ");
        return $stmt->fetchAll();
    }

    public function getAllMataKuliahGrouped(): array {
        $stmt = $this->pdo->query("
            SELECT mk.id, mk.kode, mk.nama, mk.sks,
                   COALESCE(mk.prodi, 'Tidak Terdefinisi') as prodi,
                   COALESCE(mk.semester_mk, 0) as semester,
                   COALESCE(mk.kelas, 'Tanpa Kelas') as kelas,
                   d.nama as dosen_nama
            FROM mata_kuliah mk
            LEFT JOIN dosen_matakuliah dm ON mk.id = dm.matakuliah_id
            LEFT JOIN dosen d ON dm.dosen_id = d.id AND d.deleted_at IS NULL
            ORDER BY prodi ASC, semester ASC, mk.kode ASC
        ");
        $results = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($results as $row) {
            $prodi = !empty($row['prodi']) ? $row['prodi'] : 'Tidak Terdefinisi';
            $smt = (int)($row['semester'] ?? 0);
            $kelas = !empty($row['kelas']) ? $row['kelas'] : 'Tanpa Kelas';
            $mkId = $row['id'];
            
            if (!isset($grouped[$prodi])) $grouped[$prodi] = [];
            if (!isset($grouped[$prodi][$smt])) $grouped[$prodi][$smt] = [];
            if (!isset($grouped[$prodi][$smt][$kelas])) $grouped[$prodi][$smt][$kelas] = [];
            
            if (!isset($grouped[$prodi][$smt][$kelas][$mkId])) {
                $grouped[$prodi][$smt][$kelas][$mkId] = [
                    'id'       => $row['id'],
                    'kode'     => $row['kode'],
                    'nama'     => $row['nama'],
                    'sks'      => $row['sks'],
                    'prodi'    => $row['prodi'],
                    'semester' => $row['semester'],
                    'kelas'    => $row['kelas'],
                    'dosen'    => []
                ];
            }
            if (!empty($row['dosen_nama'])) {
                $grouped[$prodi][$smt][$kelas][$mkId]['dosen'][] = $row['dosen_nama'];
            }
        }
        return $grouped;
    }

    public function createMataKuliah(string $kode, string $nama, int $sks, ?string $prodi = null, ?int $semester = null, ?string $kelas = null): bool {
        // Gunakan kolom opsional yang tersedia setelah migrate_fix_all.php
        $stmt = $this->pdo->prepare("INSERT INTO mata_kuliah (kode, nama, sks, prodi, semester_mk, kelas) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$kode, $nama, $sks, $prodi, $semester, $kelas]);
    }

    public function updateMataKuliah(int $id, string $kode, string $nama, int $sks, ?string $prodi = null, ?int $semester = null, ?string $kelas = null): bool {
        $stmt = $this->pdo->prepare("UPDATE mata_kuliah SET kode = ?, nama = ?, sks = ?, prodi = ?, semester_mk = ?, kelas = ? WHERE id = ?");
        return $stmt->execute([$kode, $nama, $sks, $prodi, $semester, $kelas, $id]);
    }

    public function deleteMataKuliah(int $id): bool {
        // Hapus relasi dosen_matakuliah dulu jika diperlukan (cascade mungkin sudah diset di DB, tapi untuk aman)
        $this->pdo->prepare("DELETE FROM dosen_matakuliah WHERE matakuliah_id = ?")->execute([$id]);
        $stmt = $this->pdo->prepare("DELETE FROM mata_kuliah WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- MANAJEMEN KEUANGAN (TAGIHAN) ---
    public function getAllTagihan(array $filters = []): array {
        $sql = "SELECT t.*, m.nama as mhs_nama, m.nim 
                FROM tagihan_pembayaran t
                JOIN mahasiswa m ON t.mahasiswa_id = m.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['semester'])) {
            $sql .= " AND t.semester = ?";
            $params[] = $filters['semester'];
        }
        if (!empty($filters['tahun_ajaran'])) {
            $sql .= " AND t.tahun_ajaran LIKE ?";
            $params[] = '%' . $filters['tahun_ajaran'] . '%';
        }

        $sql .= " ORDER BY t.status DESC, t.waktu_bayar DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createTagihan(int $mahasiswaId, string $semester, string $tahunAjaran, float $nominal, string $status): bool {
        $waktuBayar = ($status === 'Lunas') ? date('Y-m-d H:i:s') : null;
        $sql = "INSERT INTO tagihan_pembayaran (mahasiswa_id, semester, tahun_ajaran, nominal, status, waktu_bayar) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$mahasiswaId, $semester, $tahunAjaran, $nominal, $status, $waktuBayar]);
    }

    public function updateTagihan(int $id, string $semester, string $tahunAjaran, float $nominal, string $status): bool {
        // Ambil status lama untuk menentukan apakah perlu update waktu_bayar
        $stmtOld = $this->pdo->prepare("SELECT status FROM tagihan_pembayaran WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldStatus = $stmtOld->fetchColumn();
        
        // Update waktu_bayar: set ke CURRENT_TIMESTAMP jika baru berubah ke Lunas, set NULL jika baru berubah ke Belum Lunas
        if ($status === 'Lunas' && $oldStatus !== 'Lunas') {
            $sql = "UPDATE tagihan_pembayaran SET semester = ?, tahun_ajaran = ?, nominal = ?, status = ?, waktu_bayar = CURRENT_TIMESTAMP WHERE id = ?";
        } elseif ($status !== 'Lunas') {
            $sql = "UPDATE tagihan_pembayaran SET semester = ?, tahun_ajaran = ?, nominal = ?, status = ?, waktu_bayar = NULL WHERE id = ?";
        } else {
            // Status Lunas dan sebelumnya juga Lunas: pertahankan waktu_bayar yang ada
            $sql = "UPDATE tagihan_pembayaran SET semester = ?, tahun_ajaran = ?, nominal = ?, status = ? WHERE id = ?";
        }
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$semester, $tahunAjaran, $nominal, $status, $id]);
    }

    public function deleteTagihan(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM tagihan_pembayaran WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function validasiPembayaran(int $tagihanId): bool {
        $sql = "UPDATE tagihan_pembayaran SET status = 'Lunas', waktu_bayar = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$tagihanId]);
    }

    // --- MANAJEMEN PENGUMUMAN ---
    public function getSemuaPengumuman(array $filters = []): array {
        $sql = "SELECT * FROM pengumuman WHERE 1=1";
        $params = [];

        if (!empty($filters['search'])) {
            $sql .= " AND (judul LIKE ? OR isi LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($filters['target_role'])) {
            $sql .= " AND target_role = ?";
            $params[] = $filters['target_role'];
        }
        if (!empty($filters['kategori'])) {
            $sql .= " AND kategori = ?";
            $params[] = $filters['kategori'];
        }

        $sql .= " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function buatPengumuman(string $judul, string $isi, string $targetRole, string $kategori = 'Umum'): bool {
        $sql = "INSERT INTO pengumuman (judul, isi, target_role, kategori) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$judul, $isi, $targetRole, $kategori]);
    }

    public function updatePengumuman(int $id, string $judul, string $isi, string $targetRole, string $kategori): bool {
        $sql = "UPDATE pengumuman SET judul = ?, isi = ?, target_role = ?, kategori = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$judul, $isi, $targetRole, $kategori, $id]);
    }

    public function hapusPengumuman(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM pengumuman WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // --- MANAJEMEN BEASISWA ---
    public function getAllBeasiswaPenerima(array $filters = []): array {
        $sql = "SELECT b.*, m.nama as mhs_nama, m.nim, m.program_studi 
                FROM beasiswa_penerima b
                JOIN mahasiswa m ON b.mahasiswa_id = m.id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['search'])) {
            $sql .= " AND (m.nim LIKE ? OR m.nama LIKE ? OR b.nama_beasiswa LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        if (!empty($filters['status'])) {
            $sql .= " AND b.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['program_studi'])) {
            $sql .= " AND m.program_studi = ?";
            $params[] = $filters['program_studi'];
        }
        if (!empty($filters['tahun'])) {
            $sql .= " AND b.tahun LIKE ?";
            $params[] = '%' . $filters['tahun'] . '%';
        }

        $sql .= " ORDER BY b.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function tambahBeasiswaPenerima(int $mahasiswaId, string $namaBeasiswa, string $tahun, string $status): bool {
        $sql = "INSERT INTO beasiswa_penerima (mahasiswa_id, nama_beasiswa, tahun, status) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$mahasiswaId, $namaBeasiswa, $tahun, $status]);
    }

    public function updateBeasiswaPenerima(int $id, string $namaBeasiswa, string $tahun, string $status): bool {
        $sql = "UPDATE beasiswa_penerima SET nama_beasiswa = ?, tahun = ?, status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$namaBeasiswa, $tahun, $status, $id]);
    }

    public function hapusBeasiswaPenerima(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM beasiswa_penerima WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // --- LAYANAN TANYA JAWAB (CHAT) ---
    public function getPesanMasuk(int $userId): array {
        $sql = "SELECT p.*, u.username as pengirim_nama, u.role as pengirim_role 
                FROM pesan_tanya_jawab p
                JOIN users u ON p.pengirim_user_id = u.id
                WHERE p.penerima_user_id = ? ORDER BY p.waktu_kirim DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function kirimPesan(int $pengirimId, int $penerimaId, string $subjek, string $pesan): bool {
        $sql = "INSERT INTO pesan_tanya_jawab (pengirim_user_id, penerima_user_id, subjek, pesan) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$pengirimId, $penerimaId, $subjek, $pesan]);
    }

    public function getSemuaPengguna(): array {
        $stmt = $this->pdo->query("SELECT id, username, role FROM users WHERE role != 'operator' ORDER BY role, username");
        return $stmt->fetchAll();
    }

    // --- Activity Log ---
    public function getActivityLogs(int $limit = 100): array {
        $sql = "SELECT a.*, u.username, u.role 
                FROM activity_log a
                LEFT JOIN users u ON a.user_id = u.id
                ORDER BY a.created_at DESC LIMIT " . $limit;
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    // --- Manajemen Prasyarat Mata Kuliah ---
    public function getPrasyaratMk(int $matakuliahId): array {
        $sql = "SELECT p.*, mk.kode, mk.nama as prasyarat_nama, mk.sks 
                FROM mk_prasyarat p
                JOIN mata_kuliah mk ON p.prasyarat_mk_id = mk.id
                WHERE p.matakuliah_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$matakuliahId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addPrasyaratMk(int $matakuliahId, int $prasyaratMkId, string $nilaiMinimal): bool {
        // Cek duplikat
        $stmtCek = $this->pdo->prepare("SELECT id FROM mk_prasyarat WHERE matakuliah_id = ? AND prasyarat_mk_id = ?");
        $stmtCek->execute([$matakuliahId, $prasyaratMkId]);
        if ($stmtCek->fetch()) return false;

        $stmt = $this->pdo->prepare("INSERT INTO mk_prasyarat (matakuliah_id, prasyarat_mk_id, nilai_minimal) VALUES (?, ?, ?)");
        return $stmt->execute([$matakuliahId, $prasyaratMkId, $nilaiMinimal]);
    }

    public function deletePrasyaratMk(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM mk_prasyarat WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- Manajemen Jadwal Ujian ---
    public function getJadwalUjian(): array {
        $sql = "SELECT ju.*, mk.kode, mk.nama as mk_nama, d_pengampu.nama as dosen_pengampu, 
                       CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, k.nama_kampus) as nama_ruangan, r.kode_ruangan, d_pengawas.nama as dosen_pengawas
                FROM jadwal_ujian ju
                JOIN jadwal_kelas jk ON ju.kelas_id = jk.id
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                JOIN dosen d_pengampu ON jk.dosen_id = d_pengampu.id
                JOIN ruangan r ON ju.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus k ON g.kampus_id = k.id
                JOIN dosen d_pengawas ON ju.pengawas_id = d_pengawas.id
                ORDER BY ju.tanggal ASC, ju.jam_mulai ASC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function createJadwalUjian(int $kelasId, string $jenis, string $tanggal, string $jamMulai, string $jamSelesai, int $ruanganId, int $pengawasId): bool {
        // Cek bentrok ruangan (Overlap: existing.start < new.end AND existing.end > new.start)
        $stmt = $this->pdo->prepare("SELECT id FROM jadwal_ujian WHERE ruangan_id = ? AND tanggal = ? AND jam_mulai < ? AND jam_selesai > ?");
        $stmt->execute([$ruanganId, $tanggal, $jamSelesai, $jamMulai]);
        if ($stmt->fetch()) return false;

        // Cek bentrok pengawas
        $stmt = $this->pdo->prepare("SELECT id FROM jadwal_ujian WHERE pengawas_id = ? AND tanggal = ? AND jam_mulai < ? AND jam_selesai > ?");
        $stmt->execute([$pengawasId, $tanggal, $jamSelesai, $jamMulai]);
        if ($stmt->fetch()) return false;

        $sql = "INSERT INTO jadwal_ujian (kelas_id, jenis_ujian, tanggal, jam_mulai, jam_selesai, ruangan_id, pengawas_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$kelasId, $jenis, $tanggal, $jamMulai, $jamSelesai, $ruanganId, $pengawasId]);
    }

    public function deleteJadwalUjian(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM jadwal_ujian WHERE id = ?");
        return $stmt->execute([$id]);
    }

    // --- MANAJEMEN OPERATOR ---
    public function getAllOperators(): array {
        $stmt = $this->pdo->query("SELECT id, username, created_at FROM users WHERE role = 'operator' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function createOperator(string $username, string $password): bool {
        // Cek username duplikat
        $stmtCheck = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->fetch()) return false;
        
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'operator')");
        return $stmt->execute([$username, $hash]);
    }
    
    public function updateOperator(int $id, string $username, ?string $password = null): bool {
        // Cek username duplikat selain id ini
        $stmtCheck = $this->pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmtCheck->execute([$username, $id]);
        if ($stmtCheck->fetch()) return false;
        
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = ? AND role = 'operator'");
            return $stmt->execute([$username, $hash, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET username = ? WHERE id = ? AND role = 'operator'");
            return $stmt->execute([$username, $id]);
        }
    }
    
    public function deleteOperator(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'operator'");
        return $stmt->execute([$id]);
    }
}
