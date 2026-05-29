<?php
declare(strict_types=1);

namespace Src;

use Config\Database;
use PDO;
use Exception;

class DosenRepository {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function getAllMataKuliah(): array {
        $stmt = $this->pdo->query("SELECT id, kode, nama, sks, prodi FROM mata_kuliah ORDER BY nama ASC");
        return $stmt->fetchAll();
    }

    public function getDosenMataKuliahIds(int $dosenId): array {
        $stmt = $this->pdo->prepare("SELECT matakuliah_id FROM dosen_matakuliah WHERE dosen_id = ?");
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getJadwalMengajar(int $dosenId): array {
        $sql = "SELECT jk.id, jk.hari, jk.jam_mulai, jk.jam_selesai, CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, k.nama_kampus) as ruangan, 
                       mk.kode, mk.nama as mk_nama, mk.sks 
                FROM jadwal_kelas jk
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                JOIN ruangan r ON jk.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus k ON g.kampus_id = k.id
                WHERE jk.dosen_id = ?
                ORDER BY FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jk.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll();
    }

    public function paginate(int $page = 1, int $perPage = 5, string $search = '', string $filterProdi = '', string $filterStatus = '', string $sort = 'id', string $dir = 'DESC'): array {
        $offset = ($page - 1) * $perPage;
        
        $where = ["d.deleted_at IS NULL"];
        $params = [];

        if ($search !== '') {
            $where[] = "(d.nama LIKE ? OR d.nidn LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($filterProdi !== '') {
            $where[] = "d.program_studi = ?";
            $params[] = $filterProdi;
        }
        if ($filterStatus !== '') {
            $where[] = "d.status = ?";
            $params[] = $filterStatus;
        }

        $whereSql = implode(" AND ", $where);
        
        // Allowed sort columns for safety
        $allowedSort = ['id', 'nidn', 'nama', 'program_studi', 'status'];
        $sort = in_array($sort, $allowedSort, true) ? $sort : 'id';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        // Get total records
        $stmtTotal = $this->pdo->prepare("SELECT COUNT(*) FROM dosen d WHERE $whereSql");
        $stmtTotal->execute($params);
        $total = (int)$stmtTotal->fetchColumn();

        // Get records with JOIN for counting matakuliah
        $sql = "SELECT d.*, COUNT(dm.matakuliah_id) as jumlah_mk 
                FROM dosen d 
                LEFT JOIN dosen_matakuliah dm ON d.id = dm.dosen_id 
                WHERE $whereSql 
                GROUP BY d.id 
                ORDER BY d.$sort $dir 
                LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ];
    }

    public function find(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dosen WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data, array $matakuliahIds, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();

            // Insert Dosen
            $sql = "INSERT INTO dosen (nidn, nama, email, fakultas, program_studi, foto, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nidn'], 
                $data['nama'], 
                $data['email'], 
                $data['fakultas'] ?? null,
                $data['program_studi'], 
                $data['foto'], 
                $data['status']
            ]);
            $dosenId = (int)$this->pdo->lastInsertId();

            // Insert Relasi Mata Kuliah
            if (!empty($matakuliahIds)) {
                $this->insertRelasiMk($dosenId, $matakuliahIds);
            }

            // Audit Log
            Auth::logActivity($userId, 'create', 'dosen', $dosenId, "Menambahkan dosen: {$data['nama']}", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function update(int $id, array $data, array $matakuliahIds, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();

            $setClause = "nidn = ?, nama = ?, email = ?, fakultas = ?, program_studi = ?, status = ?";
            $params = [
                $data['nidn'], 
                $data['nama'], 
                $data['email'], 
                $data['fakultas'] ?? null,
                $data['program_studi'], 
                $data['status']
            ];

            if ($data['foto'] !== null) {
                $setClause .= ", foto = ?";
                $params[] = $data['foto'];
            }
            
            $params[] = $id;

            $sql = "UPDATE dosen SET $setClause WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            // Sync Relasi Mata Kuliah (Hapus yang lama, insert yang baru)
            $this->pdo->prepare("DELETE FROM dosen_matakuliah WHERE dosen_id = ?")->execute([$id]);
            if (!empty($matakuliahIds)) {
                $this->insertRelasiMk($id, $matakuliahIds);
            }

            // Audit Log
            Auth::logActivity($userId, 'update', 'dosen', $id, "Mengubah data dosen ID: $id", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    private function insertRelasiMk(int $dosenId, array $mkIds): void {
        $stmtSmt = $this->pdo->query("SELECT semester FROM periode_krs WHERE status = 'Aktif' LIMIT 1");
        $semesterAktif = $stmtSmt->fetchColumn() ?: 'Ganjil';
        
        $sql = "INSERT INTO dosen_matakuliah (dosen_id, matakuliah_id, semester) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        foreach ($mkIds as $mkId) {
            $stmt->execute([$dosenId, (int)$mkId, $semesterAktif]);
        }
    }

    public function softDelete(int $id, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE dosen SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$id]);

            Auth::logActivity($userId, 'delete', 'dosen', $id, "Soft delete dosen ID: $id", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function getTrashed(): array {
        $stmt = $this->pdo->query("SELECT * FROM dosen WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
        return $stmt->fetchAll();
    }

    public function restore(int $id, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE dosen SET deleted_at = NULL WHERE id = ?");
            $stmt->execute([$id]);

            Auth::logActivity($userId, 'restore', 'dosen', $id, "Memulihkan dosen ID: $id", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    // Level 3 Dashboard Stats
    public function getDashboardStats(): array {
        $stats = [];
        
        $stats['total_dosen_aktif'] = $this->pdo->query("SELECT COUNT(*) FROM dosen WHERE status = 'aktif' AND deleted_at IS NULL")->fetchColumn();
        $stats['total_dosen_nonaktif'] = $this->pdo->query("SELECT COUNT(*) FROM dosen WHERE status = 'nonaktif' AND deleted_at IS NULL")->fetchColumn();
        
        $stats['prodi_stats'] = $this->pdo->query("SELECT program_studi, COUNT(*) as jumlah FROM dosen WHERE deleted_at IS NULL GROUP BY program_studi")->fetchAll();
        
        // Total SKS diampu (hanya yang aktif)
        $stats['total_sks'] = $this->pdo->query("
            SELECT SUM(mk.sks) 
            FROM dosen_matakuliah dm
            JOIN dosen d ON dm.dosen_id = d.id
            JOIN mata_kuliah mk ON dm.matakuliah_id = mk.id
            WHERE d.deleted_at IS NULL AND d.status = 'aktif'
        ")->fetchColumn();

        return $stats;
    }

    // --- MANAJEMEN PROFIL DOSEN ---
    public function getDosenProfile(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dosen WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function updateBiodata(int $dosenId, array $data): bool {
        $sql = "UPDATE dosen SET nama = ?, tempat_tanggal_lahir = ?, jenis_kelamin = ?, no_hp = ?, email = ?, alamat_asal = ?, domisili = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nama'],
            $data['tempat_tanggal_lahir'],
            $data['jenis_kelamin'],
            $data['no_hp'],
            $data['email'],
            $data['alamat_asal'],
            $data['domisili'],
            $dosenId
        ]);
    }

    public function updateFoto(int $dosenId, string $path): bool {
        $stmt = $this->pdo->prepare("UPDATE dosen SET foto = ? WHERE id = ?");
        return $stmt->execute([$path, $dosenId]);
    }



    public function getDaftarMahasiswaKelas(int $dosenId, int $matakuliahId, string $semester): array {
        $sql = "SELECT k.id as krs_id, k.nilai_huruf, m.nama as mahasiswa_nama, m.nim, m.program_studi,
                       (SELECT COUNT(*) FROM krs k2 WHERE k2.mahasiswa_id = m.id AND k2.matakuliah_id = k.matakuliah_id AND k2.id != k.id AND k2.status = 'Disetujui') as is_mengulang 
                FROM krs k
                JOIN mahasiswa m ON k.mahasiswa_id = m.id
                WHERE k.dosen_id = ? AND k.matakuliah_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'
                ORDER BY m.nim ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $matakuliahId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function inputNilaiAkhir(int $dosenId, int $krsId, string $nilaiHuruf): bool {
        $stmt = $this->pdo->prepare("UPDATE krs SET nilai_huruf = ? WHERE id = ? AND dosen_id = ?");
        return $stmt->execute([$nilaiHuruf, $krsId, $dosenId]);
    }

    public function saveNilaiKomprehensif(int $dosenId, int $krsId, float $nTugas, float $nUts, float $nUas, float $nPraktikum, string $nilaiHuruf): bool {
        try {
            $this->pdo->beginTransaction();

            // Verifikasi kepemilikan
            $stmtCheck = $this->pdo->prepare("SELECT id FROM krs WHERE id = ? AND dosen_id = ?");
            $stmtCheck->execute([$krsId, $dosenId]);
            if (!$stmtCheck->fetch()) {
                $this->pdo->rollBack();
                return false;
            }

            // Upsert komponen_nilai
            $stmtKn = $this->pdo->prepare("INSERT INTO komponen_nilai (krs_id, nilai_tugas, nilai_uts, nilai_uas, nilai_praktikum) 
                                           VALUES (?, ?, ?, ?, ?) 
                                           ON DUPLICATE KEY UPDATE nilai_tugas = VALUES(nilai_tugas), nilai_uts = VALUES(nilai_uts), nilai_uas = VALUES(nilai_uas), nilai_praktikum = VALUES(nilai_praktikum)");
            $stmtKn->execute([$krsId, $nTugas, $nUts, $nUas, $nPraktikum]);

            // Update krs
            $this->inputNilaiAkhir($dosenId, $krsId, $nilaiHuruf);

            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function getPresensiKelas(int $dosenId, int $matakuliahId, string $semester, int $pertemuanKe): array {
        $sql = "SELECT p.*, m.nama as mahasiswa_nama, m.nim
                FROM presensi p
                JOIN krs k ON p.krs_id = k.id
                JOIN mahasiswa m ON k.mahasiswa_id = m.id
                WHERE k.dosen_id = ? AND k.matakuliah_id = ? AND k.semester_aktif = ? AND p.pertemuan_ke = ?
                ORDER BY p.waktu_presensi DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $matakuliahId, $semester, $pertemuanKe]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getRekapPresensiKelas(int $dosenId, int $matakuliahId, string $semester): array {
        $sql = "SELECT p.pertemuan_ke, p.status, m.nim
                FROM presensi p
                JOIN krs k ON p.krs_id = k.id
                JOIN mahasiswa m ON k.mahasiswa_id = m.id
                WHERE k.dosen_id = ? AND k.matakuliah_id = ? AND k.semester_aktif = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $matakuliahId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getSesiPresensi(int $dosenId, int $matakuliahId, string $semester, int $pertemuanKe): ?array {
        $sql = "SELECT * FROM sesi_presensi WHERE dosen_id = ? AND matakuliah_id = ? AND semester_aktif = ? AND pertemuan_ke = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $matakuliahId, $semester, $pertemuanKe]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function toggleSesiPresensi(int $dosenId, int $matakuliahId, string $semester, int $pertemuanKe, string $status): bool {
        $sesi = $this->getSesiPresensi($dosenId, $matakuliahId, $semester, $pertemuanKe);
        if ($sesi) {
            $sql = "UPDATE sesi_presensi SET status = ?, " . ($status === 'Buka' ? 'waktu_buka = CURRENT_TIMESTAMP' : 'waktu_tutup = CURRENT_TIMESTAMP') . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$status, $sesi['id']]);
        } else {
            $sql = "INSERT INTO sesi_presensi (dosen_id, matakuliah_id, semester_aktif, pertemuan_ke, status, waktu_buka) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$dosenId, $matakuliahId, $semester, $pertemuanKe, $status]);
        }
    }

    public function getStatistikMahasiswaPerKelas(int $dosenId, string $semester): array {
        $sql = "SELECT mk.nama as mata_kuliah, COUNT(k.id) as jumlah_mahasiswa 
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE k.dosen_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'
                GROUP BY mk.id, mk.nama";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function presensiManual(int $dosenId, int $krsId, int $pertemuanKe, string $status): bool {
        // Cek Keamanan: Pastikan KRS ini untuk kelas yang diajar oleh Dosen ini
        $stmtCheck = $this->pdo->prepare("SELECT id FROM krs WHERE id = ? AND dosen_id = ?");
        $stmtCheck->execute([$krsId, $dosenId]);
        if (!$stmtCheck->fetch()) return false;

        $stmtCek = $this->pdo->prepare("SELECT id FROM presensi WHERE krs_id = ? AND pertemuan_ke = ?");
        $stmtCek->execute([$krsId, $pertemuanKe]);
        if ($stmtCek->fetch()) {
            $stmt = $this->pdo->prepare("UPDATE presensi SET status = ? WHERE krs_id = ? AND pertemuan_ke = ?");
            return $stmt->execute([$status, $krsId, $pertemuanKe]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO presensi (krs_id, pertemuan_ke, status) VALUES (?, ?, ?)");
            return $stmt->execute([$krsId, $pertemuanKe, $status]);
        }
    }

    // --- MANAJEMEN TUGAS KULIAH ---
    public function getValidasiSKS(int $mahasiswaId): array {
        $stmt = $this->pdo->prepare("SELECT SUM(mk.sks) as total_sks 
            FROM krs k 
            JOIN mata_kuliah mk ON k.matakuliah_id = mk.id 
            WHERE k.mahasiswa_id = ? AND k.status = 'Disetujui'");
        $stmt->execute([$mahasiswaId]);
        $res = $stmt->fetch();
        return ['total_sks' => $res['total_sks'] ?? 0];
    }

    public function getJadwalPengawasUjian(int $dosenId): array {
        $sql = "SELECT ju.*, mk.kode, mk.nama as mk_nama, CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, k.nama_kampus) as nama_ruangan, r.kode_ruangan, d.nama as dosen_pengampu
                FROM jadwal_ujian ju
                JOIN jadwal_kelas jk ON ju.kelas_id = jk.id
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                JOIN dosen d ON jk.dosen_id = d.id
                JOIN ruangan r ON ju.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus k ON g.kampus_id = k.id
                WHERE ju.pengawas_id = ?
                ORDER BY ju.tanggal ASC, ju.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDaftarTugas(int $dosenId): array {
        $sql = "SELECT t.*, mk.nama as mk_nama, mk.kode 
                FROM tugas_kuliah t
                JOIN mata_kuliah mk ON t.matakuliah_id = mk.id
                WHERE t.dosen_id = ? ORDER BY t.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll();
    }

    public function buatTugas(int $dosenId, array $data): bool {
        $sql = "INSERT INTO tugas_kuliah (dosen_id, matakuliah_id, semester, judul_tugas, deskripsi, bobot_nilai, due_date, toleransi_keterlambatan_menit) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $dosenId, $data['matakuliah_id'], $data['semester'], $data['judul_tugas'], 
            $data['deskripsi'], $data['bobot_nilai'], $data['due_date'], $data['toleransi_keterlambatan_menit']
        ]);
    }

    public function getPengumpulanTugas(int $tugasId): array {
        $sql = "SELECT p.*, m.nama as mhs_nama, m.nim 
                FROM pengumpulan_tugas p
                JOIN mahasiswa m ON p.mahasiswa_id = m.id
                WHERE p.tugas_id = ? ORDER BY p.waktu_kumpul ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tugasId]);
        return $stmt->fetchAll();
    }

    public function nilaiTugas(int $dosenId, int $pengumpulanId, int $nilai, string $feedback): bool {
        // Cek Keamanan (IDOR): Pastikan pengumpulan ini untuk tugas yang dibuat oleh Dosen tersebut
        $stmtCheck = $this->pdo->prepare("SELECT pt.id FROM pengumpulan_tugas pt JOIN tugas_kuliah tk ON pt.tugas_kuliah_id = tk.id WHERE pt.id = ? AND tk.dosen_id = ?");
        $stmtCheck->execute([$pengumpulanId, $dosenId]);
        if (!$stmtCheck->fetch()) return false;

        $sql = "UPDATE pengumpulan_tugas SET nilai = ?, feedback_dosen = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$nilai, $feedback, $pengumpulanId]);
    }

    // --- BIMBINGAN TUGAS AKHIR ---
    public function getTugasAkhirBimbingan(int $dosenId): array {
        $sql = "SELECT ta.*, m.nama as mhs_nama, m.nim 
                FROM tugas_akhir ta
                JOIN mahasiswa m ON ta.mahasiswa_id = m.id
                WHERE ta.dosen_id = ? ORDER BY ta.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll();
    }

    public function updateStatusTA(int $dosenId, int $taId, string $status): bool {
        $stmtCheck = $this->pdo->prepare("SELECT id FROM tugas_akhir WHERE id = ? AND dosen_id = ?");
        $stmtCheck->execute([$taId, $dosenId]);
        if (!$stmtCheck->fetch()) return false;

        $sql = "UPDATE tugas_akhir SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $taId]);
    }

    public function getLogbookTA(int $taId): array {
        $sql = "SELECT * FROM logbook_ta WHERE tugas_akhir_id = ? ORDER BY tanggal DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$taId]);
        return $stmt->fetchAll();
    }

    public function updateStatusLogbook(int $dosenId, int $logId, string $status, string $catatan): bool {
        $stmtCheck = $this->pdo->prepare("SELECT l.id FROM logbook_ta l JOIN tugas_akhir ta ON l.tugas_akhir_id = ta.id WHERE l.id = ? AND ta.dosen_id = ?");
        $stmtCheck->execute([$logId, $dosenId]);
        if (!$stmtCheck->fetch()) return false;

        $sql = "UPDATE logbook_ta SET status = ?, catatan_dosen = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $catatan, $logId]);
    }

    // --- PERWALIAN & KRS ---
    public function getKrsMenunggu(int $dosenId): array {
        $sql = "SELECT k.*, m.nama as mhs_nama, m.nim, mk.nama as mk_nama, mk.sks 
                FROM krs k
                JOIN mahasiswa m ON k.mahasiswa_id = m.id
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE m.dosen_wali_id = ? ORDER BY k.mahasiswa_id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        return $stmt->fetchAll();
    }

    public function updateStatusKrs(int $dosenId, int $krsId, string $status): bool {
        // IDOR Check: Pastikan mahasiswa pemilik KRS ini adalah mahasiswa perwalian dari Dosen ini
        $stmtCheck = $this->pdo->prepare("SELECT k.id FROM krs k JOIN mahasiswa m ON k.mahasiswa_id = m.id WHERE k.id = ? AND m.dosen_wali_id = ?");
        $stmtCheck->execute([$krsId, $dosenId]);
        if (!$stmtCheck->fetch()) return false;

        $sql = "UPDATE krs SET status = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $krsId]);
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

    public function getKontakPenerima(): array {
        // Dosen bisa mengirim pesan ke Operator atau Mahasiswa
        $sql = "SELECT id, username, role FROM users WHERE role IN ('operator', 'mahasiswa') ORDER BY role, username";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    // Helper untuk mendapatkan Dosen by User ID
    public function getDosenByUserId(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM dosen WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getPengumumanByRole(): array {
        $stmt = $this->pdo->query("SELECT * FROM pengumuman WHERE target_role IN ('semua', 'dosen') ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getAll(): array {
        $stmt = $this->pdo->query("SELECT * FROM dosen ORDER BY nama ASC");
        return $stmt->fetchAll();
    }

    // --- PRESENSI QR DENGAN TOKEN DINAMIS ---
    public function generateTokenQr(int $sesiId, int $expireSeconds = 15): string {
        $token = bin2hex(random_bytes(8)); // 16 char
        $expiredAt = date('Y-m-d H:i:s', time() + $expireSeconds);
        $stmt = $this->pdo->prepare("UPDATE sesi_presensi SET token_qr = ?, token_expired_at = ? WHERE id = ?");
        $stmt->execute([$token, $expiredAt, $sesiId]);
        return $token;
    }

    // --- NEW DASHBOARD WIDGETS ---
    public function getJadwalMengajarHariIni(int $dosenId, string $semester): array {
        $mapHari = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu'];
        $hariIni = $mapHari[date('N')] ?? 'Senin';

        $sql = "SELECT jk.hari, jk.jam_mulai, jk.jam_selesai, CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, k.nama_kampus) as ruangan, 
                       mk.kode, mk.nama as mk_nama, mk.sks, jk.matakuliah_id
                FROM jadwal_kelas jk
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                JOIN ruangan r ON jk.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus k ON g.kampus_id = k.id
                WHERE jk.dosen_id = ? AND jk.semester = ? AND jk.hari = ?
                ORDER BY jk.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $semester, $hariIni]);
        $jadwal = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $jamSekarang = date('H:i:s');
        foreach ($jadwal as &$j) {
            if ($jamSekarang >= $j['jam_mulai'] && $jamSekarang <= $j['jam_selesai']) {
                $j['status_kelas'] = 'BERLANGSUNG';
            } elseif ($jamSekarang < $j['jam_mulai']) {
                $j['status_kelas'] = 'AKAN DATANG';
            } else {
                $j['status_kelas'] = 'SELESAI';
            }
        }
        return $jadwal;
    }

    public function getRingkasanBebanMengajar(int $dosenId, string $semester): array {
        $sql = "SELECT COUNT(DISTINCT jk.matakuliah_id) as jumlah_mk, 
                       COALESCE(SUM(mk.sks), 0) as total_sks 
                FROM jadwal_kelas jk
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                WHERE jk.dosen_id = ? AND jk.semester = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $semester]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Hitung pertemuan yang sudah dilakukan (sesi_presensi dengan status Tutup atau Buka)
        $sqlSesi = "SELECT COUNT(DISTINCT pertemuan_ke) as pertemuan_selesai 
                    FROM sesi_presensi 
                    WHERE dosen_id = ? AND semester_aktif = ? AND status = 'Tutup'";
        $stmtSesi = $this->pdo->prepare($sqlSesi);
        $stmtSesi->execute([$dosenId, $semester]);
        $resSesi = $stmtSesi->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'jumlah_mk'         => (int)($res['jumlah_mk'] ?? 0),
            'total_sks'         => (int)($res['total_sks'] ?? 0),
            'pertemuan_selesai' => (int)($resSesi['pertemuan_selesai'] ?? 0),
            'total_pertemuan'   => 16   // Standar 16 pertemuan per semester
        ];
    }

    public function getStatusInputNilai(int $dosenId, string $semester): array {
        $sql = "SELECT mk.nama as mk_nama, COUNT(k.id) as total_mhs, 
                       SUM(CASE WHEN k.nilai_huruf IS NOT NULL THEN 1 ELSE 0 END) as sudah_dinilai
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE k.dosen_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'
                GROUP BY mk.id, mk.nama";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getMahasiswaWaliOverview(int $dosenId): array {
        $sqlKrs = "SELECT COUNT(DISTINCT k.mahasiswa_id) FROM krs k 
                   JOIN mahasiswa m ON k.mahasiswa_id = m.id
                   WHERE m.dosen_wali_id = ? AND k.status = 'Menunggu'";
        $stmtKrs = $this->pdo->prepare($sqlKrs);
        $stmtKrs->execute([$dosenId]);
        $krsMenunggu = (int)$stmtKrs->fetchColumn();

        $sqlTotal = "SELECT COUNT(id) FROM mahasiswa WHERE dosen_wali_id = ?";
        $stmtTotal = $this->pdo->prepare($sqlTotal);
        $stmtTotal->execute([$dosenId]);
        $totalMahasiswa = (int)$stmtTotal->fetchColumn();

        return [
            'krs_menunggu' => $krsMenunggu,
            'total_mahasiswa' => $totalMahasiswa
        ];
    }

    public function getRekapPresensiDosen(int $dosenId, string $semester): array {
        $sql = "SELECT mk.nama as mk_nama, COUNT(sp.id) as sesi_dibuka 
                FROM dosen_matakuliah dm
                JOIN mata_kuliah mk ON dm.matakuliah_id = mk.id
                LEFT JOIN sesi_presensi sp ON dm.dosen_id = sp.dosen_id AND dm.matakuliah_id = sp.matakuliah_id AND sp.semester_aktif = ?
                WHERE dm.dosen_id = ? AND dm.semester = ?
                GROUP BY mk.id, mk.nama";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$semester, $dosenId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // --- MOCK DATA UNTUK KELENGKAPAN DASHBOARD WIDGET ---

    // --- STATISTIK NYATA DASHBOARD DOSEN ---

    public function getStatistikDistribusiNilai(int $dosenId, string $semester): array {
        // Query distribusi nilai nyata dari database
        $sql = "SELECT k.nilai_huruf, COUNT(*) as jumlah
                FROM krs k
                WHERE k.dosen_id = ? AND k.semester_aktif = ? 
                      AND k.status = 'Disetujui' AND k.nilai_huruf IS NOT NULL
                GROUP BY k.nilai_huruf
                ORDER BY k.nilai_huruf ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId, $semester]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $distribusi = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0];
        foreach ($rows as $r) {
            $distribusi[$r['nilai_huruf']] = (int)$r['jumlah'];
        }
        return $distribusi;
    }

    public function getMahasiswaWaliPerhatianKhusus(int $dosenId): array {
        // Ambil mahasiswa wali yang IPK < 2.5 atau kehadiran ada yang < 75%
        $bobotNilai = "CASE k.nilai_huruf WHEN 'A' THEN 4 WHEN 'B' THEN 3 WHEN 'C' THEN 2 WHEN 'D' THEN 1 ELSE 0 END";
        
        $sql = "SELECT m.nim, m.nama, m.program_studi,
                       ROUND(SUM(mk.sks * ($bobotNilai)) / NULLIF(SUM(CASE WHEN k.nilai_huruf IS NOT NULL THEN mk.sks ELSE 0 END), 0), 2) as ipk
                FROM mahasiswa m
                LEFT JOIN krs k ON k.mahasiswa_id = m.id AND k.nilai_huruf IS NOT NULL
                LEFT JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE m.dosen_wali_id = ?
                GROUP BY m.id, m.nim, m.nama, m.program_studi
                HAVING ipk < 2.5 OR ipk IS NULL
                ORDER BY ipk ASC
                LIMIT 10";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $ipk = $r['ipk'] ?? 0;
            $result[] = [
                'nim'     => $r['nim'],
                'nama'    => $r['nama'],
                'masalah' => $ipk > 0 ? 'IPK ' . number_format((float)$ipk, 2) . ' (di bawah 2.5)' : 'Belum ada nilai',
                'tipe'    => $ipk < 2.0 ? 'akademik' : 'perhatian',
            ];
        }
        return $result;
    }

    public function getRingkasanEdom(int $dosenId): array {
        // Query rata-rata skor EDOM nyata dari database
        $sql = "SELECT 
                    ROUND(AVG(e.skala_nilai), 2) as skor_rata_rata,
                    COUNT(e.id) as total_responden,
                    5.0 as skor_maksimal
                FROM edom e
                JOIN krs k ON e.krs_id = k.id
                WHERE k.dosen_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Ambil 2 komentar terbaru
        $sqlKomen = "SELECT e.komentar_saran FROM edom e
                     JOIN krs k ON e.krs_id = k.id
                     WHERE k.dosen_id = ? AND e.komentar_saran IS NOT NULL AND e.komentar_saran != ''
                     ORDER BY e.created_at DESC LIMIT 2";
        $stmtKomen = $this->pdo->prepare($sqlKomen);
        $stmtKomen->execute([$dosenId]);
        $komentar = $stmtKomen->fetchAll(\PDO::FETCH_COLUMN);

        $skor = (float)($res['skor_rata_rata'] ?? 0);
        $kategori = 'Belum Ada Data';
        if ($skor > 0) {
            if ($skor >= 4.5) $kategori = 'Sangat Baik';
            elseif ($skor >= 3.5) $kategori = 'Baik';
            elseif ($skor >= 2.5) $kategori = 'Cukup';
            else $kategori = 'Perlu Peningkatan';
        }

        return [
            'skor_rata_rata'   => $skor,
            'total_responden'  => (int)($res['total_responden'] ?? 0),
            'skor_maksimal'    => 5.0,
            'kategori'         => $kategori,
            'komentar_terbaru' => !empty($komentar) ? $komentar : ['Belum ada komentar dari mahasiswa.']
        ];
    }

    public function getRingkasanBimbinganTA(int $dosenId): array {
        // Query data bimbingan TA nyata dari database
        $sql = "SELECT ta.id, ta.judul, ta.status, ta.created_at,
                       m.nama, m.nim,
                       (SELECT lb.kegiatan FROM logbook_ta lb WHERE lb.tugas_akhir_id = ta.id ORDER BY lb.tanggal DESC LIMIT 1) as progress_terakhir,
                       (SELECT COUNT(*) FROM logbook_ta lb WHERE lb.tugas_akhir_id = ta.id) as jumlah_logbook
                FROM tugas_akhir ta
                JOIN mahasiswa m ON ta.mahasiswa_id = m.id
                WHERE ta.dosen_id = ?
                ORDER BY ta.created_at DESC
                LIMIT 10";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$dosenId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            // Estimasi persentase berdasarkan status
            $persentase = match($r['status']) {
                'Diajukan' => 10,
                'Diterima' => 30,
                'Revisi'   => 50,
                'Lulus'    => 100,
                'Ditolak'  => 0,
                default    => 20
            };
            // Sesuaikan berdasarkan jumlah logbook
            if ($r['status'] !== 'Lulus' && $r['jumlah_logbook'] > 0) {
                $persentase = min(95, $persentase + ((int)$r['jumlah_logbook'] * 5));
            }
            $result[] = [
                'nama'       => $r['nama'],
                'nim'        => $r['nim'],
                'progress'   => $r['progress_terakhir'] ?? 'Baru Diajukan',
                'status'     => $r['status'],
                'persentase' => $persentase,
            ];
        }
        return $result;
    }
}
