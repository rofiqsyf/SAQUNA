-- Skema Database SAQUNA

-- Pengguna sistem (untuk autentikasi & otorisasi)
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('operator','mahasiswa','dosen') NOT NULL DEFAULT 'operator',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data dosen (dengan soft delete)
CREATE TABLE IF NOT EXISTS dosen (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED NULL,
    nidn           CHAR(10)     NOT NULL UNIQUE,
    nama           VARCHAR(100) NOT NULL,
    email          VARCHAR(120) NOT NULL UNIQUE,
    program_studi  ENUM('Teknik Informatika','Sistem Informasi','Teknik Elektro') NOT NULL,
    foto           VARCHAR(255) NULL,
    status         ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
    created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     TIMESTAMP    NULL,
    INDEX idx_status (status),
    INDEX idx_deleted (deleted_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mata kuliah
CREATE TABLE IF NOT EXISTS mata_kuliah (
    id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    kode  VARCHAR(12)  NOT NULL UNIQUE,
    nama  VARCHAR(100) NOT NULL,
    sks   TINYINT UNSIGNED NOT NULL CHECK (sks BETWEEN 1 AND 6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot many-to-many: 1 dosen mengampu banyak MK, 1 MK bisa diampu banyak dosen
CREATE TABLE IF NOT EXISTS dosen_matakuliah (
    dosen_id       INT UNSIGNED NOT NULL,
    matakuliah_id  INT UNSIGNED NOT NULL,
    semester       ENUM('Ganjil','Genap') NOT NULL,
    PRIMARY KEY (dosen_id, matakuliah_id, semester),
    FOREIGN KEY (dosen_id)      REFERENCES dosen(id)        ON DELETE CASCADE,
    FOREIGN KEY (matakuliah_id) REFERENCES mata_kuliah(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log (Level 3) — siapa melakukan apa & kapan
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    aksi        VARCHAR(20)  NOT NULL,   -- 'create' | 'update' | 'delete' | 'restore' | 'login'
    entitas     VARCHAR(50)  NOT NULL,   -- 'dosen' | 'mata_kuliah' | ...
    entitas_id  INT UNSIGNED NULL,
    keterangan  VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert Mata Kuliah dummy jika tabel kosong
INSERT IGNORE INTO mata_kuliah (id, kode, nama, sks) VALUES
(1, 'TI-101', 'Algoritma & Pemrograman', 3),
(2, 'TI-102', 'Struktur Data', 3),
(3, 'TI-201', 'Pemrograman Web', 3),
(4, 'TI-202', 'Basis Data', 3),
(5, 'TI-301', 'Kecerdasan Buatan', 3),
(6, 'SI-101', 'Pengantar Sistem Informasi', 2),
(7, 'SI-201', 'Analisis & Desain Sistem', 3),
(8, 'TE-101', 'Rangkaian Listrik', 3);

-- Data Mahasiswa
CREATE TABLE IF NOT EXISTS mahasiswa (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    nim           CHAR(10)     NOT NULL UNIQUE,
    nama          VARCHAR(100) NOT NULL,
    program_studi ENUM('Teknik Informatika','Sistem Informasi','Teknik Elektro') NOT NULL,
    alamat        TEXT NULL,
    no_telp       VARCHAR(20) NULL,
    domisili      VARCHAR(100) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- KRS Mahasiswa
CREATE TABLE IF NOT EXISTS krs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id        INT UNSIGNED NOT NULL,
    dosen_id            INT UNSIGNED NOT NULL,
    matakuliah_id       INT UNSIGNED NOT NULL,
    semester_aktif      ENUM('Ganjil','Genap') NOT NULL,
    status              ENUM('Menunggu','Disetujui','Ditolak') NOT NULL DEFAULT 'Menunggu',
    nilai_huruf         ENUM('A','B','C','D','E') NULL,
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id, matakuliah_id, semester_aktif) REFERENCES dosen_matakuliah(dosen_id, matakuliah_id, semester) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Presensi Mahasiswa
CREATE TABLE IF NOT EXISTS presensi (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    krs_id           INT UNSIGNED NOT NULL,
    pertemuan_ke     TINYINT UNSIGNED NOT NULL,
    token_validasi   VARCHAR(10) NULL,
    status           ENUM('Hadir','Alpha') NOT NULL DEFAULT 'Alpha',
    waktu_presensi   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (krs_id) REFERENCES krs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evaluasi Dosen oleh Mahasiswa (EDOM)
CREATE TABLE IF NOT EXISTS edom (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    krs_id           INT UNSIGNED NOT NULL UNIQUE,
    skala_nilai      TINYINT UNSIGNED NOT NULL CHECK (skala_nilai BETWEEN 1 AND 5),
    komentar_saran   TEXT NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (krs_id) REFERENCES krs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tagihan Pembayaran (UKT/SPP)
CREATE TABLE IF NOT EXISTS tagihan_pembayaran (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id     INT UNSIGNED NOT NULL,
    semester         ENUM('Ganjil','Genap') NOT NULL,
    tahun_ajaran     VARCHAR(9) NOT NULL,
    nominal          DECIMAL(10,2) NOT NULL,
    status           ENUM('Lunas','Belum Lunas') NOT NULL DEFAULT 'Belum Lunas',
    waktu_bayar      TIMESTAMP NULL,
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tugas Kuliah
CREATE TABLE IF NOT EXISTS tugas_kuliah (
    id                            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dosen_id                      INT UNSIGNED NOT NULL,
    matakuliah_id                 INT UNSIGNED NOT NULL,
    semester                      ENUM('Ganjil','Genap') NOT NULL,
    judul_tugas                   VARCHAR(255) NOT NULL,
    deskripsi                     TEXT NOT NULL,
    bobot_nilai                   TINYINT UNSIGNED NOT NULL CHECK (bobot_nilai <= 100),
    due_date                      DATETIME NOT NULL,
    toleransi_keterlambatan_menit INT UNSIGNED DEFAULT 0,
    created_at                    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dosen_id, matakuliah_id, semester) REFERENCES dosen_matakuliah(dosen_id, matakuliah_id, semester) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pengumpulan Tugas
CREATE TABLE IF NOT EXISTS pengumpulan_tugas (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tugas_id         INT UNSIGNED NOT NULL,
    mahasiswa_id     INT UNSIGNED NOT NULL,
    file_path        VARCHAR(255) NOT NULL,
    waktu_kumpul     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    nilai            TINYINT UNSIGNED NULL CHECK (nilai <= 100),
    feedback_dosen   TEXT NULL,
    FOREIGN KEY (tugas_id) REFERENCES tugas_kuliah(id) ON DELETE CASCADE,
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tugas Akhir / Skripsi
CREATE TABLE IF NOT EXISTS tugas_akhir (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mahasiswa_id     INT UNSIGNED NOT NULL,
    dosen_id         INT UNSIGNED NOT NULL,
    judul            VARCHAR(255) NOT NULL,
    deskripsi        TEXT NOT NULL,
    status           ENUM('Diajukan','Diterima','Revisi','Ditolak','Lulus') NOT NULL DEFAULT 'Diajukan',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa(id) ON DELETE CASCADE,
    FOREIGN KEY (dosen_id) REFERENCES dosen(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Logbook Bimbingan TA
CREATE TABLE IF NOT EXISTS logbook_ta (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tugas_akhir_id   INT UNSIGNED NOT NULL,
    tanggal          DATE NOT NULL,
    kegiatan         TEXT NOT NULL,
    catatan_dosen    TEXT NULL,
    status           ENUM('Menunggu','Disetujui','Revisi') NOT NULL DEFAULT 'Menunggu',
    FOREIGN KEY (tugas_akhir_id) REFERENCES tugas_akhir(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tanya Jawab / Pesan
CREATE TABLE IF NOT EXISTS pesan_tanya_jawab (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pengirim_user_id INT UNSIGNED NOT NULL,
    penerima_user_id INT UNSIGNED NOT NULL,
    subjek           VARCHAR(150) NOT NULL,
    pesan            TEXT NOT NULL,
    waktu_kirim      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read          BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (pengirim_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (penerima_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pengumuman Sistem
CREATE TABLE IF NOT EXISTS pengumuman (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    judul            VARCHAR(255) NOT NULL,
    isi              TEXT NOT NULL,
    target_role      ENUM('semua','dosen','mahasiswa') NOT NULL DEFAULT 'semua',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
