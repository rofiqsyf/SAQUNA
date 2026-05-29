# Project Context: SAQUNA (Sistem Akademik Universitas Sains Al Qur'an)

## 1. Project Overview
**Name:** SAQUNA (Sistem Akademik Universitas Sains Al Qur'an)
**Type:** Web-based Academic Information System (SIAKAD)
**Objective:** Mampu menangani seluruh alur administrasi akademik secara terpadu (*end-to-end*) yang melibatkan tiga aktor utama: Mahasiswa, Dosen, dan Operator. Fokus utama saat ini adalah untuk perancangan ulang antarmuka (UI/UX) agar tampil modern, responsif, elegan, dan *user-friendly* sekelas aplikasi kampus premium.

## 2. Tech Stack Saat Ini
- **Backend:** PHP 8+ (Vanilla/Native Object Oriented PHP)
- **Database:** MySQL (menggunakan ekstensi PDO)
- **Frontend / UI:** Vanilla HTML, CSS, JavaScript (Tanpa framework CSS eksternal seperti Bootstrap/Tailwind secara *default*, mengandalkan `index.css` atau *custom styling*)
- **Arsitektur:** Repository Pattern sederhana (`Auth`, `DosenRepository`, `MahasiswaRepository`, `OperatorRepository`)

## 3. User Roles & Capabilities

Sistem ini memiliki 3 hak akses dengan dasbor dan *tools* yang berbeda:

### A. Mahasiswa (End-User)
- **Dashboard:** Melihat ringkasan data diri (NIM, Prodi) dan Papan Pengumuman Kampus.
- **KRS (Kartu Rencana Studi):** Memilih dan mengajukan mata kuliah di semester aktif.
- **Tugas Kuliah:** Melihat daftar tugas dari dosen, mengunggah *file* jawaban, dan melihat nilai/feedback.
- **KHS / Transkrip:** Melihat rekap nilai akhir.
- **Tugas Akhir / Skripsi:** Mengajukan judul skripsi, memilih dosen pembimbing, dan mengisi laporan *logbook* bimbingan mingguan.
- **UKT / Pembayaran:** Melihat tagihan administrasi/semester.
- **Chat:** Mengirim keluhan/pesan ke dosen atau operator.

### B. Dosen (Tenaga Pengajar & Wali)
- **Dashboard:** Melihat profil akademik dan Papan Pengumuman.
- **Persetujuan KRS:** Menerima (ACC) atau menolak mata kuliah yang diajukan mahasiswa perwaliannya.
- **Penilaian Tugas:** Membuat tugas baru (tenggat waktu, bobot, toleransi keterlambatan) dan memberikan nilai (0-100) serta *feedback* tertulis pada *file* jawaban mahasiswa.
- **Bimbingan TA:** Memverifikasi, meng-ACC, atau menolak judul dan laporan *logbook* mahasiswa bimbingannya.
- **Chat (Inbox):** Membaca pesan masuk dari mahasiswa dan membalasnya.

### C. Operator (Staf Administrasi & Keuangan)
- **Dashboard:** Manajemen terpusat (*Super Admin*).
- **Master Data:** Melihat dan mengelola data induk Mahasiswa, Dosen, dan Mata Kuliah.
- **Validasi Keuangan:** Melakukan verifikasi dan mengubah status tagihan mahasiswa menjadi "Lunas".
- **Pengumuman:** Membuat *broadcast* pengumuman yang bisa ditargetkan khusus ke Dasbor Dosen, Mahasiswa, atau keduanya.
- **Helpdesk (Chat):** Menjawab tiket keluhan/bantuan administratif.
- **Log Sistem (Audit Trail):** Memantau rekam jejak aksi pengguna (Login, Tambah Data, Ubah Nilai, dll) di seluruh sistem demi keamanan.

## 4. UI/UX Design Requirements (Guidelines)
Ketika membuat UI/UX untuk aplikasi ini (khususnya jika menggunakan Google Stitch / UI Builder), perhatikan hal-hal berikut:

1. **Estetika Premium & Modern:** Hindari desain sistem kampus yang kaku dan membosankan (era 2010-an). Gunakan *glassmorphism*, bayangan (*soft shadows*) yang elegan, serta palet warna yang dinamis dan vibran.
2. **Typography:** Gunakan *font* Google Sans, Inter, Roboto, atau Outfit.
3. **Card-Based Interface:** Sebagian besar data (seperti daftar tugas, pengumuman, dan bimbingan) lebih baik disajikan dalam bentuk *Cards* (kartu) daripada sekadar tabel kaku.
4. **Responsive Layout:** Dasbor harus fleksibel (memiliki *sidebar* navigasi di layar besar, dan *hamburger menu* atau *bottom navigation* di perangkat genggam).
5. **Micro-interactions:** Sediakan transisi halus (*fade-in* / *slide-up*) ketika berpindah tab atau saat tombol (*hover state*) disentuh. Notifikasi (sukses/gagal) sebaiknya muncul sebagai *Toast / Snackbar*.

## 5. Konvensi Database (Skema Inti)
Sistem ini menggunakan relasi database (MySQL) yang terikat pada tabel `users`.
- `users`: id, username, password, role ('mahasiswa','dosen','operator')
- `mahasiswa`: id, user_id, nim, nama, program_studi
- `dosen`: id, user_id, nidn, nama, program_studi
- `mata_kuliah`: id, kode, nama, sks, semester
- `krs`: id, mahasiswa_id, mata_kuliah_id, semester_aktif, status ('Menunggu','Disetujui','Ditolak')
- `tugas_kuliah`: id, dosen_id, matakuliah_id, judul_tugas, bobot_nilai, due_date
- `pengumpulan_tugas`: id, tugas_id, mahasiswa_id, file_path, nilai, feedback_dosen
- `tugas_akhir`: id, mahasiswa_id, dosen_id, judul, status ('Diajukan','Diterima','Revisi','Ditolak','Lulus')
- `tagihan_pembayaran`: id, mahasiswa_id, semester, nominal, status ('Lunas','Belum Lunas')
- `pengumuman`: id, judul, isi, target_role
- `pesan_tanya_jawab`: id, pengirim_user_id, penerima_user_id, subjek, pesan
- `activity_log`: id, user_id, aksi, entitas, keterangan

---
*Gunakan dokumen ini sebagai konteks sistem utama (System Context) ketika memberikan prompt pada AI Design Builder (seperti Google Project IDX / Stitch / V0 / Figma AI) untuk merancang antarmuka frontend SAQUNA.*
