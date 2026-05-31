<?php
declare(strict_types=1);

namespace Src;

use Config\Database;
use PDO;
use Exception;

class MahasiswaRepository {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function getMahasiswaByUserId(int $userId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM mahasiswa WHERE user_id = ?");
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function getPenawaranMK(int $mahasiswaId, string $semesterAktif): array {
        // Ambil data mahasiswa
        $stmtMhs = $this->pdo->prepare("SELECT program_studi, semester FROM mahasiswa WHERE id = ?");
        $stmtMhs->execute([$mahasiswaId]);
        $mhs = $stmtMhs->fetch(\PDO::FETCH_ASSOC);
        
        if (!$mhs) return [];
        $prodiMhs = $mhs['program_studi'];
        $semesterMhs = (int)$mhs['semester'];

        // Ambil histori nilai untuk cek retake (nilai C, D, E)
        // Ambil nilai terbaik yang didapat jika pernah mengambil berkali-kali
        $stmtNilai = $this->pdo->prepare("
            SELECT matakuliah_id, MIN(FIELD(nilai_huruf, 'A', 'B', 'C', 'D', 'E')) as best_score_idx 
            FROM krs 
            WHERE mahasiswa_id = ? AND nilai_huruf IS NOT NULL AND status = 'Disetujui'
            GROUP BY matakuliah_id
        ");
        $stmtNilai->execute([$mahasiswaId]);
        
        $retakeEligibleMkIds = [];
        // FIELD returns index 1 for A, 2 for B, 3 for C, 4 for D, 5 for E.
        // Jika nilai terbaik adalah C (3), D (4), atau E (5), mahasiswa berhak mengulang
        while ($row = $stmtNilai->fetch(\PDO::FETCH_ASSOC)) {
            if ((int)$row['best_score_idx'] >= 3) {
                $retakeEligibleMkIds[] = (int)$row['matakuliah_id'];
            }
        }

        // Mengambil daftar mata kuliah yang ditawarkan di semester aktif ini beserta dosennya
        $sql = "SELECT dm.dosen_id, dm.matakuliah_id, dm.semester as periode, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama,
                       COALESCE(mk.prodi, '') as prodi, COALESCE(mk.semester_mk, 0) as semester_mk
                FROM dosen_matakuliah dm
                JOIN mata_kuliah mk ON dm.matakuliah_id = mk.id
                JOIN dosen d ON dm.dosen_id = d.id
                WHERE d.status = 'aktif' AND d.deleted_at IS NULL AND dm.semester = ?
                ORDER BY mk.nama ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$semesterAktif]);
        $allOfferings = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $isPendek = (stripos($semesterAktif, 'Pendek') !== false);

        $filteredOfferings = [];
        foreach ($allOfferings as $offering) {
            // Filter 1: Harus sesuai dengan Program Studi mahasiswa
            if ($offering['prodi'] !== $prodiMhs) {
                continue;
            }

            $mkSemester = (int)$offering['semester_mk'];
            
            // Filter 2: Aturan semester
            $isCurrentSemester = ($mkSemester === $semesterMhs);
            
            // Logika mengulang (nilai <= C)
            $isRetakeEligible = ($mkSemester < $semesterMhs) && in_array((int)$offering['matakuliah_id'], $retakeEligibleMkIds);
            
            // Validasi Genap/Ganjil jika BUKAN semester pendek
            if (!$isPendek) {
                // Di semester reguler, mahasiswa hanya bisa ambil MK semester reguler (yang parity-nya sesuai)
                // Parity (ganjil/genap) harus sama: ($mkSemester % 2) == ($semesterMhs % 2)
                $parityMatch = (($mkSemester % 2) === ($semesterMhs % 2));
                
                if ($isCurrentSemester || ($isRetakeEligible && $parityMatch)) {
                    $filteredOfferings[] = $offering;
                }
            } else {
                // Semester Pendek: Mahasiswa bisa ambil MK baru (akselerasi) atau mengulang (retake), tanpa batas paritas
                // Biasanya dalam SP, semua penawaran yang dibuat untuk SP bisa diambil
                if ($isCurrentSemester || $isRetakeEligible || $mkSemester > $semesterMhs) {
                    $filteredOfferings[] = $offering;
                }
            }
        }

        return $filteredOfferings;
    }

    public function simpanKRS(int $mahasiswaId, array $pilihanKrs, string $semesterAktif): array {
        // Return array [status => bool, error_msg => string]
        try {
            $this->pdo->beginTransaction();

            // Ambil semua prasyarat
            $stmtPrasyarat = $this->pdo->query("SELECT * FROM mk_prasyarat");
            $prasyaratList = $stmtPrasyarat->fetchAll(\PDO::FETCH_ASSOC);
            
            // Ambil histori nilai mahasiswa
            $stmtNilai = $this->pdo->prepare("SELECT matakuliah_id, nilai_huruf FROM krs WHERE mahasiswa_id = ? AND status = 'Disetujui' AND nilai_huruf IS NOT NULL");
            $stmtNilai->execute([$mahasiswaId]);
            $historiNilai = [];
            while ($row = $stmtNilai->fetch(\PDO::FETCH_ASSOC)) {
                $historiNilai[$row['matakuliah_id']] = $row['nilai_huruf'];
            }
            
            // Mapping bobot nilai
            $bobot = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];

            // Hapus yang statusnya belum disetujui (Menunggu/Ditolak) untuk direset
            $stmt = $this->pdo->prepare("DELETE FROM krs WHERE mahasiswa_id = ? AND semester_aktif = ? AND status != 'Disetujui'");
            $stmt->execute([$mahasiswaId, $semesterAktif]);

            $sqlInsert = "INSERT INTO krs (mahasiswa_id, dosen_id, matakuliah_id, semester_aktif, status) VALUES (?, ?, ?, ?, 'Menunggu')";
            $stmtInsert = $this->pdo->prepare($sqlInsert);

            foreach ($pilihanKrs as $pilihan) {
                $parts = explode('|', $pilihan);
                if (count($parts) !== 2) {
                    $this->pdo->rollBack();
                    return ['status' => false, 'error_msg' => "Format pilihan KRS tidak valid."];
                }
                list($dosenId, $matakuliahId) = $parts;
                
            // --- Validasi Prasyarat ---
                foreach ($prasyaratList as $p) {
                    if ($p['matakuliah_id'] == $matakuliahId) {
                        $prasyaratMkId = $p['prasyarat_mk_id'];
                        $nilaiMinimal = $p['nilai_minimal'];
                        
                        if (!isset($historiNilai[$prasyaratMkId])) {
                            $this->pdo->rollBack();
                            return ['status' => false, 'error_msg' => "Anda belum mengambil mata kuliah prasyarat untuk MK ID $matakuliahId."];
                        }
                        
                        $nilaiMhs = $historiNilai[$prasyaratMkId];
                        // Guard: pastikan kedua nilai ada di tabel bobot sebelum dibandingkan
                        if (isset($bobot[$nilaiMhs]) && isset($bobot[$nilaiMinimal])) {
                            if ($bobot[$nilaiMhs] < $bobot[$nilaiMinimal]) {
                                $this->pdo->rollBack();
                                return ['status' => false, 'error_msg' => "Nilai prasyarat tidak memenuhi. Minimal $nilaiMinimal, nilai Anda $nilaiMhs."];
                            }
                        }
                    }
                }
                // --- End Validasi ---

                // Cek Kuota dan Kunci Baris (Pessimistic Locking) & Validasi IDOR Semester
                $stmtQuota = $this->pdo->prepare("SELECT kuota, (SELECT COUNT(id) FROM krs WHERE matakuliah_id = ? AND dosen_id = ? AND semester_aktif = ? AND status != 'Ditolak') as terisi FROM jadwal_kelas WHERE matakuliah_id = ? AND dosen_id = ? AND semester = ? FOR UPDATE");
                $stmtQuota->execute([(int)$matakuliahId, (int)$dosenId, $semesterAktif, (int)$matakuliahId, (int)$dosenId, $semesterAktif]);
                $quotaData = $stmtQuota->fetch();
                
                if (!$quotaData) {
                    $this->pdo->rollBack();
                    return ['status' => false, 'error_msg' => "Mata Kuliah (ID $matakuliahId) kelas Dosen ID $dosenId tidak ditawarkan pada semester ini."];
                }

                if ($quotaData['terisi'] >= $quotaData['kuota']) {
                    $this->pdo->rollBack();
                    return ['status' => false, 'error_msg' => "Mata Kuliah (ID $matakuliahId) kelas Dosen ID $dosenId sudah penuh."];
                }

                // Cek apakah sudah ada (mencegah duplikat)
                $cek = $this->pdo->prepare("SELECT id FROM krs WHERE mahasiswa_id = ? AND dosen_id = ? AND matakuliah_id = ? AND semester_aktif = ?");
                $cek->execute([$mahasiswaId, (int)$dosenId, (int)$matakuliahId, $semesterAktif]);
                if (!$cek->fetch()) {
                    $stmtInsert->execute([$mahasiswaId, (int)$dosenId, (int)$matakuliahId, $semesterAktif]);
                }
            }

            Auth::logActivity($_SESSION['user_id'] ?? null, 'create', 'krs', $mahasiswaId, "Mengisi KRS Mahasiswa semester $semesterAktif", $this->pdo);

            $this->pdo->commit();
            return ['status' => true, 'error_msg' => ''];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return ['status' => false, 'error_msg' => 'Terjadi kesalahan internal server.'];
        }
    }

    public function getKrsMahasiswa(int $mahasiswaId, string $semester): array {
        $sql = "SELECT k.*, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama,
                       COALESCE(jk.hari, '') as hari,
                       COALESCE(jk.jam_mulai, '') as jam_mulai,
                       COALESCE(jk.jam_selesai, '') as jam_selesai,
                       COALESCE(CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, kp.nama_kampus), 'TBD') as ruangan
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                JOIN dosen d ON k.dosen_id = d.id
                LEFT JOIN jadwal_kelas jk ON k.dosen_id = jk.dosen_id 
                       AND k.matakuliah_id = jk.matakuliah_id 
                       AND k.semester_aktif = jk.semester
                LEFT JOIN ruangan r ON jk.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus kp ON g.kampus_id = kp.id
                WHERE k.mahasiswa_id = ? AND k.semester_aktif = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester]);
        return $stmt->fetchAll();
    }

    public function getJadwalKuliah(int $mahasiswaId, string $semester): array {
        // Hanya ambil KRS yang sudah disetujui untuk jadwal resmi
        $sql = "SELECT k.*, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama,
                       COALESCE(jk.hari, '') as hari,
                       COALESCE(jk.jam_mulai, '00:00:00') as jam_mulai,
                       COALESCE(jk.jam_selesai, '00:00:00') as jam_selesai,
                       COALESCE(CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, kp.nama_kampus), 'TBD') as ruangan,
                       jk.id as jadwal_kelas_id
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                JOIN dosen d ON k.dosen_id = d.id
                LEFT JOIN jadwal_kelas jk ON k.dosen_id = jk.dosen_id 
                       AND k.matakuliah_id = jk.matakuliah_id 
                       AND k.semester_aktif = jk.semester
                LEFT JOIN ruangan r ON jk.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus kp ON g.kampus_id = kp.id
                WHERE k.mahasiswa_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'
                ORDER BY FIELD(jk.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), jk.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester]);
        return $stmt->fetchAll();
    }

    // --- LOGIKA EDOM ---
    public function cekEdomLengkap(int $mahasiswaId, string $semester): bool {
        $krs = $this->getKrsMahasiswa($mahasiswaId, $semester);
        if (empty($krs)) return true; // Jika tidak ada KRS, anggap lengkap

        $krsIds = array_column($krs, 'id');
        $placeholders = implode(',', array_fill(0, count($krsIds), '?'));

        $sql = "SELECT COUNT(*) FROM edom WHERE krs_id IN ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($krsIds);
        $countEdom = (int)$stmt->fetchColumn();

        return $countEdom === count($krsIds);
    }

    public function getKrsBelumEdom(int $mahasiswaId, string $semester): array {
        $sql = "SELECT k.*, mk.nama as mk_nama, d.nama as dosen_nama 
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                JOIN dosen d ON k.dosen_id = d.id
                LEFT JOIN edom e ON k.id = e.krs_id
                WHERE k.mahasiswa_id = ? AND k.semester_aktif = ? AND e.id IS NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester]);
        return $stmt->fetchAll();
    }

    public function simpanEdom(array $dataEdom): bool {
        try {
            $this->pdo->beginTransaction();

            $sql = "INSERT INTO edom (krs_id, skala_nilai, komentar_saran) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);

            foreach ($dataEdom as $krsId => $edom) {
                $stmt->execute([(int)$krsId, (int)$edom['skala'], $edom['komentar']]);
            }
            Auth::logActivity($_SESSION['user_id'] ?? null, 'create', 'edom', null, "Mengisi EDOM", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    // --- TUGAS AKHIR (MAHASISWA) ---
    public function getTugasAkhir(int $mahasiswaId): ?array {
        $stmt = $this->pdo->prepare("SELECT ta.*, d.nama as dosen_nama FROM tugas_akhir ta JOIN dosen d ON ta.dosen_id = d.id WHERE ta.mahasiswa_id = ? ORDER BY ta.created_at DESC LIMIT 1");
        $stmt->execute([$mahasiswaId]);
        $ta = $stmt->fetch();
        return $ta ?: null;
    }

    public function submitTugasAkhir(int $mahasiswaId, int $dosenId, string $judul, string $deskripsi): bool {
        // Cek jika sudah ada TA yang belum ditolak
        $stmt = $this->pdo->prepare("SELECT id FROM tugas_akhir WHERE mahasiswa_id = ? AND status != 'Ditolak'");
        $stmt->execute([$mahasiswaId]);
        if ($stmt->fetch()) return false;

        $stmt = $this->pdo->prepare("INSERT INTO tugas_akhir (mahasiswa_id, dosen_id, judul, deskripsi) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$mahasiswaId, $dosenId, $judul, $deskripsi]);
    }

    public function getLogbookTA(int $taId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM logbook_ta WHERE tugas_akhir_id = ? ORDER BY tanggal DESC");
        $stmt->execute([$taId]);
        return $stmt->fetchAll();
    }

    public function addLogbookTA(int $taId, int $mahasiswaId, string $tanggal, string $kegiatan): bool {
        // Verify ownership
        $stmt = $this->pdo->prepare("SELECT id FROM tugas_akhir WHERE id = ? AND mahasiswa_id = ?");
        $stmt->execute([$taId, $mahasiswaId]);
        if (!$stmt->fetch()) return false;

        $stmt = $this->pdo->prepare("INSERT INTO logbook_ta (tugas_akhir_id, tanggal, kegiatan) VALUES (?, ?, ?)");
        return $stmt->execute([$taId, $tanggal, $kegiatan]);
    }

    // --- LOGIKA PRESENSI ---
    public function simpanPresensi(int $krsId, int $pertemuanKe, string $tokenQr = ''): array {
        try {
            $this->pdo->beginTransaction();

            // Ambil data KRS untuk mencari sesi
            $stmtKrs = $this->pdo->prepare("SELECT dosen_id, matakuliah_id, semester_aktif FROM krs WHERE id = ?");
            $stmtKrs->execute([$krsId]);
            $krs = $stmtKrs->fetch(\PDO::FETCH_ASSOC);

            if (!$krs) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Data KRS tidak valid.'];
            }

            // Cek status sesi presensi dan Token QR
            $stmtSesi = $this->pdo->prepare("SELECT id, status, token_qr, token_expired_at FROM sesi_presensi WHERE dosen_id = ? AND matakuliah_id = ? AND semester_aktif = ? AND pertemuan_ke = ?");
            $stmtSesi->execute([$krs['dosen_id'], $krs['matakuliah_id'], $krs['semester_aktif'], $pertemuanKe]);
            $sesi = $stmtSesi->fetch(\PDO::FETCH_ASSOC);

            if (!$sesi || $sesi['status'] !== 'Buka') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Presensi untuk pertemuan ini belum dibuka atau sudah ditutup oleh Dosen.'];
            }

            if (!empty($sesi['token_qr'])) {
                if (empty($tokenQr) || $sesi['token_qr'] !== $tokenQr || strtotime($sesi['token_expired_at']) < time()) {
                    $this->pdo->rollBack();
                    return ['success' => false, 'message' => 'Token QR tidak valid, wajib diisi, atau sudah kedaluwarsa. Silakan scan ulang!'];
                }
            }

            // Cek apakah sudah presensi
            $stmtCek = $this->pdo->prepare("SELECT id FROM presensi WHERE krs_id = ? AND pertemuan_ke = ?");
            $stmtCek->execute([$krsId, $pertemuanKe]);
            if ($stmtCek->fetch()) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Anda sudah mengisi presensi untuk pertemuan ini.'];
            }

            $sql = "INSERT INTO presensi (krs_id, pertemuan_ke, status) VALUES (?, ?, 'Hadir')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$krsId, $pertemuanKe]);

            Auth::logActivity($_SESSION['user_id'] ?? null, 'create', 'presensi', $krsId, "Mengisi presensi mandiri pertemuan $pertemuanKe", $this->pdo);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Presensi berhasil dicatat.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Terjadi kesalahan sistem.'];
        }
    }

    // --- DASHBOARD STATS ---
    public function getDashboardStats(int $mahasiswaId): array {
        $stats = [];
        
        // SKS Lulus (Nilai tidak NULL, bukan E)
        $sqlSks = "SELECT SUM(mk.sks) 
                   FROM krs k
                   JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                   WHERE k.mahasiswa_id = ? AND k.nilai_huruf IS NOT NULL AND k.nilai_huruf != 'E'";
        $stmtSks = $this->pdo->prepare($sqlSks);
        $stmtSks->execute([$mahasiswaId]);
        $stats['total_sks_lulus'] = (int)$stmtSks->fetchColumn();

        return $stats;
    }
    // --- PROFIL MAHASISWA
    public function getSemesterAktif(): string {
        $stmt = $this->pdo->query("SELECT semester FROM periode_krs WHERE status = 'Buka' AND NOW() BETWEEN tanggal_buka AND tanggal_tutup LIMIT 1");
        $res = $stmt->fetch();
        if ($res) {
            return $res['semester'];
        }
        $stmt2 = $this->pdo->query("SELECT semester FROM periode_krs ORDER BY id DESC LIMIT 1");
        $res2 = $stmt2->fetch();
        return $res2 ? $res2['semester'] : 'Ganjil';
    }

    public function getMahasiswaProfile(int $userId): ?array {
        $sql = "SELECT m.*, d.nama as dosen_wali_nama 
                FROM mahasiswa m 
                LEFT JOIN dosen d ON m.dosen_wali_id = d.id 
                WHERE m.user_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public function updateBiodata(int $mahasiswaId, array $data): bool {
        $sql = "UPDATE mahasiswa 
                SET nama = ?, tempat_tanggal_lahir = ?, alamat_asal = ?, domisili = ?, email = ?, no_hp = ?, jenis_kelamin = ? 
                WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['nama'],
            $data['tempat_tanggal_lahir'],
            $data['alamat_asal'],
            $data['domisili'],
            $data['email'],
            $data['no_hp'],
            $data['jenis_kelamin'],
            $mahasiswaId
        ]);
    }

    public function updateFoto(int $mahasiswaId, string $fotoPath): bool {
        $sql = "UPDATE mahasiswa SET foto = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$fotoPath, $mahasiswaId]);
    }

    // --- TRANSKRIP & IPK ---
    public function getTranskrip(int $mahasiswaId): array {
        $sql = "SELECT k.*, mk.kode, mk.nama as mk_nama, mk.sks 
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE k.mahasiswa_id = ? AND k.nilai_huruf IS NOT NULL";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId]);
        $krs = $stmt->fetchAll();

        $totalSks = 0;
        $totalBobot = 0;
        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];

        foreach ($krs as $row) {
            $sks = (int)$row['sks'];
            $nilai = $row['nilai_huruf'];
            $totalSks += $sks;
            if (isset($bobotNilai[$nilai])) {
                $totalBobot += ($sks * $bobotNilai[$nilai]);
            }
        }

        $ipk = $totalSks > 0 ? round($totalBobot / $totalSks, 2) : 0.0;

        return [
            'data' => $krs,
            'total_sks' => $totalSks,
            'ipk' => $ipk
        ];
    }

    public function getStatistikIPK(int $mahasiswaId): array {
        $sql = "SELECT k.semester_aktif, SUM(mk.sks) as total_sks, k.nilai_huruf, mk.sks 
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                WHERE k.mahasiswa_id = ? AND k.nilai_huruf IS NOT NULL
                GROUP BY k.semester_aktif, k.nilai_huruf, mk.sks
                ORDER BY k.semester_aktif ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId]);
        $rows = $stmt->fetchAll();

        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
        $stats = [];
        $kumulatifSks = 0;
        $kumulatifBobot = 0;

        // Group by semester
        $semesters = [];
        foreach ($rows as $r) {
            $sem = $r['semester_aktif'];
            if (!isset($semesters[$sem])) {
                $semesters[$sem] = ['sks' => 0, 'bobot' => 0];
            }
            $sks = (int)$r['sks'];
            $nilai = $r['nilai_huruf'];
            $semesters[$sem]['sks'] += $sks;
            if (isset($bobotNilai[$nilai])) {
                $semesters[$sem]['bobot'] += ($sks * $bobotNilai[$nilai]);
            }
        }

        foreach ($semesters as $sem => $data) {
            $ips = $data['sks'] > 0 ? round($data['bobot'] / $data['sks'], 2) : 0;
            $kumulatifSks += $data['sks'];
            $kumulatifBobot += $data['bobot'];
            $ipk = $kumulatifSks > 0 ? round($kumulatifBobot / $kumulatifSks, 2) : 0;
            
            $stats[] = [
                'semester' => $sem,
                'ips' => $ips,
                'ipk' => $ipk,
                'sks_semester' => $data['sks'],
                'sks_total' => $kumulatifSks
            ];
        }

        return $stats;
    }

    public function getRiwayatBeasiswa(int $mahasiswaId): array {
        $sql = "SELECT * FROM beasiswa_penerima WHERE mahasiswa_id = ? ORDER BY tahun DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId]);
        return $stmt->fetchAll();
    }

    // --- TAGIHAN / KEUANGAN ---
    public function getTagihan(int $mahasiswaId): array {
        $sql = "SELECT * FROM tagihan_pembayaran WHERE mahasiswa_id = ? ORDER BY id DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId]);
        return $stmt->fetchAll();
    }

    // --- TUGAS KULIAH & PENGUMPULAN ---
    public function getTugasKuliah(int $mahasiswaId, string $semester): array {
        // Ambil mata kuliah yang diambil mahasiswa (dari KRS) lalu gabung ke tugas_kuliah
        $sql = "SELECT t.*, mk.nama as mk_nama, d.nama as dosen_nama, p.file_path, p.keterangan, p.tautan, p.waktu_kumpul, p.nilai, p.feedback_dosen
                FROM tugas_kuliah t
                JOIN krs k ON t.matakuliah_id = k.matakuliah_id AND t.dosen_id = k.dosen_id AND t.semester = k.semester_aktif
                JOIN mata_kuliah mk ON t.matakuliah_id = mk.id
                JOIN dosen d ON t.dosen_id = d.id
                LEFT JOIN pengumpulan_tugas p ON t.id = p.tugas_id AND p.mahasiswa_id = ?
                WHERE k.mahasiswa_id = ? AND t.semester = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $mahasiswaId, $semester]);
        return $stmt->fetchAll();
    }

    public function uploadTugas(int $tugasId, int $mahasiswaId, string $filePath, string $keterangan, string $tautan): bool {
        // Keamanan IDOR: Pastikan Mahasiswa benar-benar terdaftar di kelas (KRS Disetujui) yang memiliki Tugas ini
        $stmtCheck = $this->pdo->prepare("SELECT k.id FROM krs k JOIN tugas_kuliah tk ON k.matakuliah_id = tk.matakuliah_id AND k.semester_aktif = tk.semester WHERE tk.id = ? AND k.mahasiswa_id = ? AND k.status = 'Disetujui'");
        $stmtCheck->execute([$tugasId, $mahasiswaId]);
        if (!$stmtCheck->fetch()) return false;

        // Cek jika sudah pernah upload
        $cek = $this->pdo->prepare("SELECT id FROM pengumpulan_tugas WHERE tugas_id = ? AND mahasiswa_id = ?");
        $cek->execute([$tugasId, $mahasiswaId]);
        
        if ($cek->fetch()) {
            $sql = "UPDATE pengumpulan_tugas SET file_path = ?, keterangan = ?, tautan = ?, waktu_kumpul = CURRENT_TIMESTAMP WHERE tugas_id = ? AND mahasiswa_id = ?";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$filePath, $keterangan, $tautan, $tugasId, $mahasiswaId]);
        } else {
            $sql = "INSERT INTO pengumpulan_tugas (tugas_id, mahasiswa_id, file_path, keterangan, tautan) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$tugasId, $mahasiswaId, $filePath, $keterangan, $tautan]);
        }
    }

    // --- TANYA JAWAB / PESAN ---
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
        $sql = "SELECT id, username, role FROM users WHERE role IN ('operator', 'dosen') ORDER BY role, username";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }

    // --- PENGUMUMAN KAMPUS ---
    public function getPengumumanByRole(string $kategori = null): array {
        if ($kategori) {
            $stmt = $this->pdo->prepare("SELECT * FROM pengumuman WHERE target_role IN ('semua', 'mahasiswa') AND kategori = ? ORDER BY created_at DESC");
            $stmt->execute([$kategori]);
        } else {
            $stmt = $this->pdo->query("SELECT * FROM pengumuman WHERE target_role IN ('semua', 'mahasiswa') ORDER BY created_at DESC");
        }
        return $stmt->fetchAll();
    }

    public function getJadwalUjianMahasiswa(int $mahasiswaId, string $semester): array {
        $sql = "SELECT ju.*, mk.kode, mk.nama as mk_nama, CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, k.nama_kampus) as nama_ruangan, r.kode_ruangan, jk.jam_mulai as jam_kuliah
                FROM jadwal_ujian ju
                JOIN jadwal_kelas jk ON ju.kelas_id = jk.id
                JOIN krs kr ON kr.matakuliah_id = jk.matakuliah_id AND kr.dosen_id = jk.dosen_id
                JOIN mata_kuliah mk ON jk.matakuliah_id = mk.id
                JOIN ruangan r ON ju.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus k ON g.kampus_id = k.id
                WHERE kr.mahasiswa_id = ? AND kr.semester_aktif = ? AND kr.status = 'Disetujui'
                ORDER BY ju.tanggal ASC, ju.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getProgressStudi(int $mahasiswaId): array {
        // Ambil semester aktif secara dinamis
        $semesterAktif = $this->getSemesterAktif();

        $sqlSks = "SELECT SUM(mk.sks) as total_sks, COUNT(mk.id) as total_mk 
                   FROM krs k
                   JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                   WHERE k.mahasiswa_id = ? AND k.nilai_huruf IS NOT NULL AND k.nilai_huruf != 'E'";
        $stmt = $this->pdo->prepare($sqlSks);
        $stmt->execute([$mahasiswaId]);
        $res = $stmt->fetch(\PDO::FETCH_ASSOC);

        $sqlSmt = "SELECT SUM(mk.sks) as sks_semester, COUNT(mk.id) as mk_semester 
                   FROM krs k
                   JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                   WHERE k.mahasiswa_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'";
        $stmtSmt = $this->pdo->prepare($sqlSmt);
        $stmtSmt->execute([$mahasiswaId, $semesterAktif]);
        $resSmt = $stmtSmt->fetch(\PDO::FETCH_ASSOC);

        $sksLulus = (int)($res['total_sks'] ?? 0);
        $estimasiSmt = min(8, ceil($sksLulus / 18) + 1);

        return [
            'sks_lulus'        => $sksLulus,
            'mk_lulus'         => (int)($res['total_mk'] ?? 0),
            'sks_semester'     => (int)($resSmt['sks_semester'] ?? 0),
            'mk_semester'      => (int)($resSmt['mk_semester'] ?? 0),
            'estimasi_semester'=> $estimasiSmt
        ];
    }

    public function getRekapKehadiran(int $mahasiswaId, string $semester): array {
        $sqlKrs = "SELECT k.id as krs_id, mk.nama as mk_nama, mk.sks 
                   FROM krs k
                   JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                   WHERE k.mahasiswa_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui'";
        $stmtKrs = $this->pdo->prepare($sqlKrs);
        $stmtKrs->execute([$mahasiswaId, $semester]);
        $krsList = $stmtKrs->fetchAll(\PDO::FETCH_ASSOC);

        $rekap = [];
        foreach ($krsList as $k) {
            $krsId = $k['krs_id'];
            $stmtSesi = $this->pdo->prepare("
                SELECT COUNT(*) FROM sesi_presensi sp 
                JOIN krs kr ON kr.dosen_id = sp.dosen_id AND kr.matakuliah_id = sp.matakuliah_id 
                WHERE kr.id = ? AND sp.status != 'Jadwal'");
            $stmtSesi->execute([$krsId]);
            $totalPertemuan = (int)$stmtSesi->fetchColumn();
            
            $targetPertemuan = max($totalPertemuan, 14);

            $stmtHadir = $this->pdo->prepare("SELECT COUNT(*) FROM presensi WHERE krs_id = ? AND status = 'Hadir'");
            $stmtHadir->execute([$krsId]);
            $hadir = (int)$stmtHadir->fetchColumn();

            $persentase = $targetPertemuan > 0 ? round(($hadir / $targetPertemuan) * 100) : 0;
            
            $rekap[] = [
                'krs_id' => $krsId,
                'mk_nama' => $k['mk_nama'],
                'hadir' => $hadir,
                'total' => $targetPertemuan,
                'persentase' => $persentase
            ];
        }

        return $rekap;
    }

    public function getJadwalHariIni(int $mahasiswaId, string $semester): array {
        $mapHari = [1=>'Senin', 2=>'Selasa', 3=>'Rabu', 4=>'Kamis', 5=>'Jumat', 6=>'Sabtu', 7=>'Minggu'];
        $hariIni = $mapHari[date('N')] ?? 'Senin';

        $sql = "SELECT k.*, mk.kode, mk.nama as mk_nama, mk.sks, d.nama as dosen_nama, 
                       jk.hari, jk.jam_mulai, jk.jam_selesai, CONCAT_WS(' - ', r.nama_ruangan, g.nama_gedung, kp.nama_kampus) as ruangan
                FROM krs k
                JOIN mata_kuliah mk ON k.matakuliah_id = mk.id
                JOIN dosen d ON k.dosen_id = d.id
                JOIN jadwal_kelas jk ON k.dosen_id = jk.dosen_id AND k.matakuliah_id = jk.matakuliah_id AND k.semester_aktif = jk.semester
                JOIN ruangan r ON jk.ruangan_id = r.id
                LEFT JOIN master_gedung g ON r.gedung_id = g.id
                LEFT JOIN master_kampus kp ON g.kampus_id = kp.id
                WHERE k.mahasiswa_id = ? AND k.semester_aktif = ? AND k.status = 'Disetujui' AND jk.hari = ?
                ORDER BY jk.jam_mulai ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester, $hariIni]);
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

    public function getStatusKRS(int $mahasiswaId, string $semester): string {
        // Ambil semua status KRS untuk semester ini, prioritaskan 'Menunggu' > 'Ditolak' > 'Disetujui'
        $sql = "SELECT status FROM krs WHERE mahasiswa_id = ? AND semester_aktif = ?
                ORDER BY FIELD(status, 'Menunggu', 'Ditolak', 'Disetujui') ASC
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$mahasiswaId, $semester]);
        $status = $stmt->fetchColumn();
        return $status ?: 'Belum Diisi';
    }

    // --- PERWALIAN ---
    public function getCatatanPerwalian(int $mahasiswaId, string $semester): ?string {
        $stmt = $this->pdo->prepare("SELECT catatan FROM catatan_perwalian WHERE mahasiswa_id = ? AND semester = ? ORDER BY waktu_bimbingan DESC LIMIT 1");
        $stmt->execute([$mahasiswaId, $semester]);
        $res = $stmt->fetchColumn();
        return $res ?: null;
    }
}
