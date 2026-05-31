# 🎓 Panduan Lengkap: Sistem Akademik User-Friendly & Responsif
> Ditulis oleh: Dewa Programmer × UI/UX Designer × QA Website  
> Target: Sistem Akademik Web-Based | Level: Production-Grade

---

## 📋 Daftar Isi

1. [Audit Awal — Checklist Sebelum Mulai](#1-audit-awal--checklist-sebelum-mulai)
2. [Struktur HTML Semantik](#2-struktur-html-semantik)
3. [CSS Responsif — Fondasi Wajib](#3-css-responsif--fondasi-wajib)
4. [Komponen UI Sistem Akademik](#4-komponen-ui-sistem-akademik)
5. [Navigasi & Sidebar Responsif](#5-navigasi--sidebar-responsif)
6. [Tabel Data Akademik Responsif](#6-tabel-data-akademik-responsif)
7. [Form Input (KRS, Biodata, dll)](#7-form-input-krs-biodata-dll)
8. [Dashboard & Kartu Statistik](#8-dashboard--kartu-statistik)
9. [Tipografi & Keterbacaan](#9-tipografi--keterbacaan)
10. [Aksesibilitas (WCAG 2.1)](#10-aksesibilitas-wcag-21)
11. [Performa & Optimasi](#11-performa--optimasi)
12. [Kompatibilitas Browser](#12-kompatibilitas-browser)
13. [Testing & QA Checklist](#13-testing--qa-checklist)
14. [Referensi Cepat Breakpoint](#14-referensi-cepat-breakpoint)

---

## 1. Audit Awal — Checklist Sebelum Mulai

Sebelum menyentuh kode, lakukan audit ini terlebih dahulu:

```
[ ] Buka DevTools → Tab "Responsive" → test di 320px, 768px, 1024px, 1440px
[ ] Jalankan Lighthouse (DevTools → Lighthouse) → target skor ≥ 80 semua kategori
[ ] Validasi HTML: https://validator.w3.org
[ ] Cek CSS: https://jigsaw.w3.org/css-validator
[ ] Test keyboard-only navigation (Tab, Enter, Esc)
[ ] Cek color contrast: https://webaim.org/resources/contrastchecker
[ ] Periksa apakah ada elemen yang overflow horizontal di mobile
```

---

## 2. Struktur HTML Semantik

### ✅ Template Dasar Halaman Sistem Akademik

```html
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <!-- WAJIB: Tanpa ini tampilan mobile hancur -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sistem Informasi Akademik Universitas">
  <title>SIAKAD — Nama Universitas</title>

  <!-- Preconnect font (jika pakai Google Fonts) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="styles/main.css">
</head>
<body>

  <!-- Skip navigation untuk aksesibilitas -->
  <a href="#main-content" class="skip-link">Langsung ke Konten</a>

  <!-- Header utama -->
  <header class="site-header" role="banner">
    <div class="header-inner container">
      <a href="/dashboard" class="logo" aria-label="Beranda SIAKAD">
        <img src="logo.png" alt="Logo Universitas" width="120" height="40">
      </a>

      <!-- Tombol hamburger untuk mobile -->
      <button class="nav-toggle" aria-expanded="false" aria-controls="main-nav"
              aria-label="Buka menu navigasi">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
      </button>

      <nav id="main-nav" class="main-nav" role="navigation" aria-label="Navigasi Utama">
        <ul class="nav-list">
          <li><a href="/dashboard" aria-current="page">Dashboard</a></li>
          <li><a href="/krs">KRS</a></li>
          <li><a href="/nilai">Nilai</a></li>
          <li><a href="/jadwal">Jadwal</a></li>
          <li><a href="/keuangan">Keuangan</a></li>
        </ul>
      </nav>

      <!-- Info user -->
      <div class="user-info" role="complementary">
        <span class="user-name">Budi Santoso</span>
        <button class="btn-logout" aria-label="Keluar dari akun">Logout</button>
      </div>
    </div>
  </header>

  <!-- Layout utama -->
  <div class="app-layout">

    <!-- Sidebar (untuk desktop) -->
    <aside class="sidebar" aria-label="Menu Samping">
      <!-- Isi sidebar -->
    </aside>

    <!-- Konten utama -->
    <main id="main-content" class="main-content" role="main">

      <!-- Breadcrumb -->
      <nav aria-label="Breadcrumb" class="breadcrumb">
        <ol>
          <li><a href="/dashboard">Dashboard</a></li>
          <li aria-current="page">Kartu Rencana Studi</li>
        </ol>
      </nav>

      <!-- Judul halaman -->
      <div class="page-header">
        <h1 class="page-title">Kartu Rencana Studi</h1>
        <p class="page-subtitle">Semester Genap 2024/2025</p>
      </div>

      <!-- Konten dinamis di sini -->
      <section aria-labelledby="section-title">
        <h2 id="section-title">Mata Kuliah Diambil</h2>
        <!-- ... -->
      </section>

    </main>
  </div>

  <!-- Footer -->
  <footer class="site-footer" role="contentinfo">
    <p>© 2025 Universitas. Versi 2.1.0</p>
  </footer>

  <script src="scripts/main.js" defer></script>
</body>
</html>
```

### ⚠️ Kesalahan Umum yang Harus Dihindari

```html
<!-- ❌ SALAH: div semua -->
<div class="header">
  <div class="nav">
    <div class="nav-item">Dashboard</div>
  </div>
</div>

<!-- ✅ BENAR: Semantik -->
<header>
  <nav aria-label="Navigasi Utama">
    <a href="/dashboard">Dashboard</a>
  </nav>
</header>

<!-- ❌ SALAH: Tabel untuk layout -->
<table>
  <tr><td>Sidebar</td><td>Konten</td></tr>
</table>

<!-- ✅ BENAR: CSS Grid/Flexbox -->
<div class="app-layout">
  <aside>Sidebar</aside>
  <main>Konten</main>
</div>
```

---

## 3. CSS Responsif — Fondasi Wajib

### Variabel CSS (Design Tokens)

```css
/* styles/tokens.css */
:root {
  /* === WARNA === */
  --color-primary:       #1a56db;   /* Biru utama */
  --color-primary-dark:  #1e429f;   /* Hover state */
  --color-primary-light: #e8f0fe;   /* Background ringan */
  --color-success:       #0e9f6e;
  --color-warning:       #e3a008;
  --color-danger:        #e02424;
  --color-neutral-50:    #f9fafb;
  --color-neutral-100:   #f3f4f6;
  --color-neutral-200:   #e5e7eb;
  --color-neutral-500:   #6b7280;
  --color-neutral-700:   #374151;
  --color-neutral-900:   #111827;

  /* === TIPOGRAFI === */
  --font-sans:    'Plus Jakarta Sans', system-ui, sans-serif;
  --font-mono:    'JetBrains Mono', monospace;
  --text-xs:      0.75rem;   /* 12px */
  --text-sm:      0.875rem;  /* 14px */
  --text-base:    1rem;      /* 16px */
  --text-lg:      1.125rem;  /* 18px */
  --text-xl:      1.25rem;   /* 20px */
  --text-2xl:     1.5rem;    /* 24px */
  --text-3xl:     1.875rem;  /* 30px */

  /* === SPASI === */
  --space-1:  0.25rem;   /* 4px  */
  --space-2:  0.5rem;    /* 8px  */
  --space-3:  0.75rem;   /* 12px */
  --space-4:  1rem;      /* 16px */
  --space-6:  1.5rem;    /* 24px */
  --space-8:  2rem;      /* 32px */
  --space-12: 3rem;      /* 48px */
  --space-16: 4rem;      /* 64px */

  /* === BORDER RADIUS === */
  --radius-sm: 0.25rem;
  --radius-md: 0.5rem;
  --radius-lg: 0.75rem;
  --radius-xl: 1rem;
  --radius-full: 9999px;

  /* === SHADOW === */
  --shadow-sm: 0 1px 2px rgba(0,0,0,.06);
  --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.04);
  --shadow-lg: 0 10px 15px rgba(0,0,0,.08), 0 4px 6px rgba(0,0,0,.04);

  /* === SIDEBAR & HEADER === */
  --sidebar-width:      260px;
  --header-height:      64px;
  --sidebar-bg:         #1e2a3b;
  --sidebar-text:       #cbd5e1;
  --sidebar-active-bg:  rgba(255,255,255,.1);
  --sidebar-active-text:#ffffff;

  /* === TRANSISI === */
  --transition-fast:   150ms ease;
  --transition-normal: 250ms ease;
  --transition-slow:   400ms ease;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
  :root {
    --color-neutral-50:  #1f2937;
    --color-neutral-100: #111827;
    --color-neutral-900: #f9fafb;
    /* ... sesuaikan semua warna */
  }
}
```

### Reset & Base CSS

```css
/* styles/base.css */
*, *::before, *::after {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html {
  font-size: 16px;           /* Base untuk rem */
  scroll-behavior: smooth;
  -webkit-text-size-adjust: 100%; /* Cegah zoom teks di iOS */
}

body {
  font-family: var(--font-sans);
  font-size: var(--text-base);
  line-height: 1.6;
  color: var(--color-neutral-900);
  background-color: var(--color-neutral-50);
  min-height: 100vh;
  /* Cegah konten meluber di mobile */
  overflow-x: hidden;
}

img, video, svg {
  max-width: 100%;  /* KRITIS: cegah gambar meluber */
  height: auto;
  display: block;
}

/* Fokus yang terlihat untuk aksesibilitas */
:focus-visible {
  outline: 3px solid var(--color-primary);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

/* Skip link aksesibilitas */
.skip-link {
  position: absolute;
  top: -100%;
  left: var(--space-4);
  background: var(--color-primary);
  color: #fff;
  padding: var(--space-2) var(--space-4);
  border-radius: var(--radius-md);
  z-index: 9999;
  text-decoration: none;
  font-weight: 600;
  transition: top var(--transition-fast);
}
.skip-link:focus {
  top: var(--space-4);
}
```

### Layout Grid Sistem

```css
/* styles/layout.css */

/* Container responsif */
.container {
  width: 100%;
  max-width: 1280px;
  margin-inline: auto;
  padding-inline: var(--space-4);
}

@media (min-width: 640px)  { .container { padding-inline: var(--space-6); } }
@media (min-width: 1024px) { .container { padding-inline: var(--space-8); } }

/* Layout aplikasi utama */
.app-layout {
  display: grid;
  grid-template-columns: 1fr;  /* Mobile: 1 kolom */
  min-height: calc(100vh - var(--header-height));
  margin-top: var(--header-height);
}

@media (min-width: 1024px) {
  .app-layout {
    grid-template-columns: var(--sidebar-width) 1fr;  /* Desktop: sidebar + konten */
  }
}

/* Konten utama */
.main-content {
  padding: var(--space-6) var(--space-4);
  max-width: 100%;
  overflow-x: hidden;
}

@media (min-width: 768px)  { .main-content { padding: var(--space-8) var(--space-6); } }
@media (min-width: 1280px) { .main-content { padding: var(--space-8); } }

/* Grid fleksibel untuk kartu/widget */
.grid-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: var(--space-6);
}

/* Grid 2 kolom */
.grid-2 {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-6);
}
@media (min-width: 768px) {
  .grid-2 { grid-template-columns: repeat(2, 1fr); }
}

/* Grid 3 kolom */
.grid-3 {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--space-6);
}
@media (min-width: 768px)  { .grid-3 { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .grid-3 { grid-template-columns: repeat(3, 1fr); } }
```

---

## 4. Komponen UI Sistem Akademik

### Kartu (Card)

```css
/* styles/components/card.css */
.card {
  background: #ffffff;
  border: 1px solid var(--color-neutral-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  overflow: hidden;
  transition: box-shadow var(--transition-normal),
              transform var(--transition-normal);
}

.card:hover {
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}

.card-header {
  padding: var(--space-4) var(--space-6);
  border-bottom: 1px solid var(--color-neutral-200);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

.card-title {
  font-size: var(--text-lg);
  font-weight: 600;
  color: var(--color-neutral-900);
}

.card-body {
  padding: var(--space-6);
}

.card-footer {
  padding: var(--space-4) var(--space-6);
  border-top: 1px solid var(--color-neutral-200);
  background: var(--color-neutral-50);
}
```

### Tombol (Button)

```css
/* styles/components/button.css */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-sm);
  font-weight: 500;
  font-family: inherit;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  cursor: pointer;
  text-decoration: none;
  transition: all var(--transition-fast);
  white-space: nowrap;
  /* Ukuran touch target minimal 44x44px */
  min-height: 44px;
  min-width: 44px;
  /* Cegah teks terpilih saat klik */
  user-select: none;
  /* Responsif */
  max-width: 100%;
}

/* Ukuran tombol */
.btn-sm { padding: var(--space-1) var(--space-3); min-height: 36px; font-size: var(--text-xs); }
.btn-lg { padding: var(--space-3) var(--space-6); min-height: 52px; font-size: var(--text-lg); }

/* Variasi warna */
.btn-primary {
  background: var(--color-primary);
  color: #ffffff;
  border-color: var(--color-primary);
}
.btn-primary:hover {
  background: var(--color-primary-dark);
  border-color: var(--color-primary-dark);
}

.btn-outline {
  background: transparent;
  color: var(--color-primary);
  border-color: var(--color-primary);
}
.btn-outline:hover {
  background: var(--color-primary-light);
}

.btn-danger {
  background: var(--color-danger);
  color: #ffffff;
}

.btn-ghost {
  background: transparent;
  color: var(--color-neutral-700);
  border-color: transparent;
}
.btn-ghost:hover {
  background: var(--color-neutral-100);
}

/* State disabled */
.btn:disabled,
.btn[aria-disabled="true"] {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

/* Loading state */
.btn.loading {
  pointer-events: none;
  opacity: 0.8;
}
.btn.loading::before {
  content: "";
  width: 1em;
  height: 1em;
  border: 2px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Full width di mobile */
@media (max-width: 480px) {
  .btn-block-mobile {
    width: 100%;
  }
}
```

### Badge / Status

```css
/* styles/components/badge.css */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px var(--space-2);
  font-size: var(--text-xs);
  font-weight: 600;
  border-radius: var(--radius-full);
  line-height: 1.5;
  white-space: nowrap;
}

.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef9c3; color: #854d0e; }
.badge-danger  { background: #fee2e2; color: #991b1b; }
.badge-info    { background: #dbeafe; color: #1e40af; }
.badge-neutral { background: var(--color-neutral-100); color: var(--color-neutral-700); }
```

---

## 5. Navigasi & Sidebar Responsif

### CSS Sidebar

```css
/* styles/components/sidebar.css */

/* === HEADER TETAP === */
.site-header {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  height: var(--header-height);
  background: #ffffff;
  border-bottom: 1px solid var(--color-neutral-200);
  z-index: 100;
  box-shadow: var(--shadow-sm);
}

.header-inner {
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

/* === TOMBOL HAMBURGER === */
.nav-toggle {
  display: flex;
  flex-direction: column;
  gap: 5px;
  background: none;
  border: none;
  cursor: pointer;
  padding: var(--space-2);
  border-radius: var(--radius-md);
  min-width: 44px;
  min-height: 44px;
  justify-content: center;
  align-items: center;
}

.hamburger-line {
  display: block;
  width: 22px;
  height: 2px;
  background: var(--color-neutral-700);
  border-radius: 2px;
  transition: all var(--transition-normal);
}

/* Animasi hamburger → ✕ */
.nav-toggle[aria-expanded="true"] .hamburger-line:nth-child(1) {
  transform: translateY(7px) rotate(45deg);
}
.nav-toggle[aria-expanded="true"] .hamburger-line:nth-child(2) {
  opacity: 0;
}
.nav-toggle[aria-expanded="true"] .hamburger-line:nth-child(3) {
  transform: translateY(-7px) rotate(-45deg);
}

/* Sembunyikan di desktop */
@media (min-width: 1024px) {
  .nav-toggle { display: none; }
}

/* === SIDEBAR === */
.sidebar {
  position: fixed;
  top: var(--header-height);
  left: 0;
  width: var(--sidebar-width);
  height: calc(100vh - var(--header-height));
  background: var(--sidebar-bg);
  overflow-y: auto;
  overflow-x: hidden;
  z-index: 90;
  padding: var(--space-4) 0;
  /* Scrollbar tipis */
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.2) transparent;

  /* Mobile: sembunyikan ke kiri */
  transform: translateX(-100%);
  transition: transform var(--transition-normal);
}

/* Saat sidebar terbuka */
.sidebar.is-open {
  transform: translateX(0);
}

/* Desktop: selalu tampil */
@media (min-width: 1024px) {
  .sidebar {
    transform: translateX(0);
    position: sticky;    /* Ikut scroll konten */
    top: var(--header-height);
    height: calc(100vh - var(--header-height));
    align-self: start;   /* Penting untuk sticky dalam grid */
  }
}

/* Overlay gelap saat sidebar mobile terbuka */
.sidebar-overlay {
  display: none;
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.5);
  z-index: 80;
  backdrop-filter: blur(2px);
}

.sidebar-overlay.is-active {
  display: block;
}

@media (min-width: 1024px) {
  .sidebar-overlay { display: none !important; }
}

/* === MENU ITEM SIDEBAR === */
.sidebar-menu {
  list-style: none;
  padding: 0 var(--space-3);
}

.sidebar-menu-item a {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  color: var(--sidebar-text);
  text-decoration: none;
  font-size: var(--text-sm);
  font-weight: 500;
  transition: all var(--transition-fast);
  min-height: 44px;  /* Touch target */
}

.sidebar-menu-item a:hover {
  background: var(--sidebar-active-bg);
  color: var(--sidebar-active-text);
}

.sidebar-menu-item a[aria-current="page"],
.sidebar-menu-item a.active {
  background: var(--color-primary);
  color: #ffffff;
}

.sidebar-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

/* Label grup menu */
.sidebar-group-label {
  font-size: var(--text-xs);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: rgba(255,255,255,.4);
  padding: var(--space-4) var(--space-4) var(--space-2);
  margin-top: var(--space-2);
}
```

### JavaScript Sidebar

```javascript
// scripts/sidebar.js

const sidebarToggle = document.querySelector('.nav-toggle');
const sidebar       = document.querySelector('.sidebar');
const overlay       = document.querySelector('.sidebar-overlay');

function openSidebar() {
  sidebar.classList.add('is-open');
  overlay.classList.add('is-active');
  sidebarToggle.setAttribute('aria-expanded', 'true');
  document.body.style.overflow = 'hidden'; // Cegah scroll di belakang overlay
}

function closeSidebar() {
  sidebar.classList.remove('is-open');
  overlay.classList.remove('is-active');
  sidebarToggle.setAttribute('aria-expanded', 'false');
  document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', () => {
  const isOpen = sidebarToggle.getAttribute('aria-expanded') === 'true';
  isOpen ? closeSidebar() : openSidebar();
});

// Tutup saat klik overlay
overlay?.addEventListener('click', closeSidebar);

// Tutup saat tekan Esc
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') closeSidebar();
});

// Tutup otomatis saat resize ke desktop
const mediaQuery = window.matchMedia('(min-width: 1024px)');
mediaQuery.addEventListener('change', (e) => {
  if (e.matches) closeSidebar();
});

// Tandai menu aktif berdasarkan URL
document.querySelectorAll('.sidebar-menu-item a').forEach(link => {
  if (link.href === window.location.href) {
    link.setAttribute('aria-current', 'page');
  }
});
```

---

## 6. Tabel Data Akademik Responsif

Tabel adalah komponen paling bermasalah di mobile. Gunakan salah satu strategi ini:

### Strategi A: Horizontal Scroll (untuk tabel data berat)

```html
<div class="table-wrapper" role="region" aria-label="Daftar Mata Kuliah" tabindex="0">
  <table class="data-table">
    <caption class="sr-only">Daftar mata kuliah semester ini</caption>
    <thead>
      <tr>
        <th scope="col">Kode MK</th>
        <th scope="col">Nama Mata Kuliah</th>
        <th scope="col">SKS</th>
        <th scope="col">Kelas</th>
        <th scope="col">Dosen</th>
        <th scope="col">Status</th>
        <th scope="col">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td data-label="Kode MK">CS2101</td>
        <td data-label="Nama Mata Kuliah">Algoritma & Pemrograman</td>
        <td data-label="SKS">3</td>
        <td data-label="Kelas">A</td>
        <td data-label="Dosen">Dr. Ahmad</td>
        <td data-label="Status"><span class="badge badge-success">Disetujui</span></td>
        <td data-label="Aksi">
          <button class="btn btn-sm btn-outline">Edit</button>
        </td>
      </tr>
    </tbody>
  </table>
</div>
```

```css
/* Tabel: CSS */
.table-wrapper {
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;   /* Smooth scroll di iOS */
  border: 1px solid var(--color-neutral-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  /* Indikator scroll — hint visual bahwa bisa di-scroll */
  background:
    linear-gradient(to right, white 30%, rgba(255,255,255,0)),
    linear-gradient(to left, white 30%, rgba(255,255,255,0)) 100% 0,
    radial-gradient(farthest-side at 0%, rgba(0,0,0,.15), rgba(0,0,0,0)),
    radial-gradient(farthest-side at 100%, rgba(0,0,0,.15), rgba(0,0,0,0)) 100% 0;
  background-repeat: no-repeat;
  background-size: 40px 100%, 40px 100%, 14px 100%, 14px 100%;
  background-attachment: local, local, scroll, scroll;
}

.data-table {
  width: 100%;
  min-width: 600px;          /* Paksa scroll kalau layar sempit */
  border-collapse: collapse;
  font-size: var(--text-sm);
}

.data-table thead {
  background: var(--color-neutral-100);
  border-bottom: 2px solid var(--color-neutral-200);
}

.data-table th {
  padding: var(--space-3) var(--space-4);
  text-align: left;
  font-weight: 600;
  font-size: var(--text-xs);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-neutral-500);
  white-space: nowrap;
}

.data-table td {
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--color-neutral-200);
  color: var(--color-neutral-700);
  vertical-align: middle;
}

.data-table tbody tr:hover {
  background: var(--color-neutral-50);
}

.data-table tbody tr:last-child td {
  border-bottom: none;
}
```

### Strategi B: Card Stack (untuk tabel sederhana di mobile)

```css
/* Di layar kecil, ubah tabel menjadi kartu vertikal */
@media (max-width: 639px) {
  .data-table--stacked,
  .data-table--stacked thead,
  .data-table--stacked tbody,
  .data-table--stacked th,
  .data-table--stacked td,
  .data-table--stacked tr {
    display: block;
  }

  /* Sembunyikan header tabel */
  .data-table--stacked thead tr {
    position: absolute;
    top: -9999px;
    left: -9999px;
  }

  /* Setiap baris jadi kartu */
  .data-table--stacked tbody tr {
    border: 1px solid var(--color-neutral-200);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-4);
    padding: var(--space-4);
    box-shadow: var(--shadow-sm);
    background: #ffffff;
  }

  /* Setiap sel tampil dengan label */
  .data-table--stacked td {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-2) 0;
    border: none;
    border-bottom: 1px solid var(--color-neutral-100);
    font-size: var(--text-sm);
    gap: var(--space-4);
  }

  .data-table--stacked td:last-child {
    border-bottom: none;
  }

  /* Label dari atribut data-label */
  .data-table--stacked td::before {
    content: attr(data-label);
    font-weight: 600;
    color: var(--color-neutral-500);
    font-size: var(--text-xs);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    flex-shrink: 0;
    min-width: 100px;
  }
}
```

---

## 7. Form Input (KRS, Biodata, dll)

### HTML Form Terbaik

```html
<form class="form" novalidate>
  <!-- Group field tunggal -->
  <div class="form-group">
    <label class="form-label" for="nim">
      NIM <span class="required" aria-hidden="true">*</span>
    </label>
    <input
      type="text"
      id="nim"
      name="nim"
      class="form-control"
      placeholder="Contoh: 2021001001"
      autocomplete="off"
      required
      aria-required="true"
      aria-describedby="nim-hint nim-error"
      pattern="[0-9]{10}"
    >
    <p id="nim-hint" class="form-hint">Masukkan 10 digit NIM Anda.</p>
    <p id="nim-error" class="form-error" role="alert" hidden>
      NIM harus terdiri dari 10 angka.
    </p>
  </div>

  <!-- Group field dua kolom -->
  <div class="form-row">
    <div class="form-group">
      <label class="form-label" for="nama-depan">Nama Depan</label>
      <input type="text" id="nama-depan" class="form-control" autocomplete="given-name">
    </div>
    <div class="form-group">
      <label class="form-label" for="nama-belakang">Nama Belakang</label>
      <input type="text" id="nama-belakang" class="form-control" autocomplete="family-name">
    </div>
  </div>

  <!-- Select -->
  <div class="form-group">
    <label class="form-label" for="prodi">Program Studi</label>
    <div class="select-wrapper">
      <select id="prodi" class="form-control form-select">
        <option value="">-- Pilih Program Studi --</option>
        <option value="ti">Teknik Informatika</option>
        <option value="si">Sistem Informasi</option>
      </select>
    </div>
  </div>

  <!-- Textarea -->
  <div class="form-group">
    <label class="form-label" for="alamat">Alamat</label>
    <textarea id="alamat" class="form-control" rows="4" maxlength="500"
              aria-describedby="alamat-count"></textarea>
    <p id="alamat-count" class="form-hint">
      <span id="char-count">0</span>/500 karakter
    </p>
  </div>

  <!-- Tombol aksi -->
  <div class="form-actions">
    <button type="button" class="btn btn-ghost">Batal</button>
    <button type="submit" class="btn btn-primary">
      <svg ...></svg> Simpan Data
    </button>
  </div>
</form>
```

### CSS Form

```css
/* styles/components/form.css */
.form-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  margin-bottom: var(--space-5);
}

.form-row {
  display: grid;
  grid-template-columns: 1fr;   /* Mobile: 1 kolom */
  gap: var(--space-4);
}
@media (min-width: 640px) {
  .form-row { grid-template-columns: repeat(2, 1fr); }
}

.form-label {
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-neutral-700);
}

.required { color: var(--color-danger); }

/* Input dasar */
.form-control {
  width: 100%;
  padding: var(--space-2) var(--space-3);
  font-size: var(--text-base);
  font-family: inherit;
  color: var(--color-neutral-900);
  background: #ffffff;
  border: 1.5px solid var(--color-neutral-200);
  border-radius: var(--radius-md);
  transition: border-color var(--transition-fast),
              box-shadow var(--transition-fast);
  /* KRITIS: Cegah zoom di iOS (font-size harus ≥ 16px) */
  font-size: 1rem;
  /* Tinggi touch-friendly */
  min-height: 44px;
  appearance: none;
  -webkit-appearance: none;
}

.form-control:focus {
  outline: none;
  border-color: var(--color-primary);
  box-shadow: 0 0 0 3px var(--color-primary-light);
}

.form-control::placeholder {
  color: var(--color-neutral-500);
}

/* State valid/error */
.form-control.is-valid {
  border-color: var(--color-success);
}
.form-control.is-invalid {
  border-color: var(--color-danger);
  box-shadow: 0 0 0 3px rgba(224,36,36,.1);
}

/* Select dengan arrow custom */
.select-wrapper {
  position: relative;
}
.select-wrapper::after {
  content: "▾";
  position: absolute;
  right: var(--space-3);
  top: 50%;
  transform: translateY(-50%);
  pointer-events: none;
  color: var(--color-neutral-500);
}
.form-select { padding-right: var(--space-8); }

/* Hint dan Error */
.form-hint  { font-size: var(--text-xs); color: var(--color-neutral-500); }
.form-error {
  font-size: var(--text-xs);
  color: var(--color-danger);
  display: flex;
  align-items: center;
  gap: var(--space-1);
}
.form-error::before { content: "⚠"; }

/* Tombol form */
.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  justify-content: flex-end;
  padding-top: var(--space-4);
  border-top: 1px solid var(--color-neutral-200);
  margin-top: var(--space-4);
}

@media (max-width: 480px) {
  .form-actions {
    flex-direction: column-reverse;
  }
  .form-actions .btn {
    width: 100%;
    justify-content: center;
  }
}
```

---

## 8. Dashboard & Kartu Statistik

### HTML Widget Statistik

```html
<!-- Dashboard stats -->
<section class="stats-grid" aria-label="Ringkasan Akademik">
  <article class="stat-card">
    <div class="stat-icon stat-icon--blue" aria-hidden="true">📚</div>
    <div class="stat-content">
      <p class="stat-label">Total SKS</p>
      <p class="stat-value" aria-label="Total SKS: 120">120</p>
      <p class="stat-change stat-change--up">+6 SKS semester ini</p>
    </div>
  </article>
  <!-- ... kartu lain -->
</section>
```

### CSS Stats

```css
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: var(--space-4);
  margin-bottom: var(--space-8);
}

.stat-card {
  background: #ffffff;
  border: 1px solid var(--color-neutral-200);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  display: flex;
  align-items: flex-start;
  gap: var(--space-4);
  box-shadow: var(--shadow-sm);
  transition: box-shadow var(--transition-normal);
}

.stat-card:hover { box-shadow: var(--shadow-md); }

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}

.stat-icon--blue   { background: #dbeafe; }
.stat-icon--green  { background: #dcfce7; }
.stat-icon--yellow { background: #fef9c3; }
.stat-icon--red    { background: #fee2e2; }

.stat-label {
  font-size: var(--text-sm);
  color: var(--color-neutral-500);
  margin-bottom: var(--space-1);
}

.stat-value {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--color-neutral-900);
  line-height: 1.2;
}

.stat-change {
  font-size: var(--text-xs);
  margin-top: var(--space-1);
}
.stat-change--up   { color: var(--color-success); }
.stat-change--down { color: var(--color-danger); }
```

---

## 9. Tipografi & Keterbacaan

```css
/* styles/typography.css */

/* Impor font (tambahkan di <head> HTML) */
/*
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
*/

/* Heading hierarchy */
h1, .h1 { font-size: clamp(1.5rem, 3vw + 1rem, 2.25rem); font-weight: 700; line-height: 1.2; }
h2, .h2 { font-size: clamp(1.25rem, 2vw + 0.75rem, 1.875rem); font-weight: 600; line-height: 1.3; }
h3, .h3 { font-size: clamp(1.1rem, 1.5vw + 0.5rem, 1.5rem); font-weight: 600; line-height: 1.4; }
h4, .h4 { font-size: var(--text-lg); font-weight: 600; line-height: 1.4; }

/* Ukuran teks responsif dengan clamp() */
/* clamp(min, preferred, max) */
.text-responsive {
  font-size: clamp(0.875rem, 1vw + 0.5rem, 1rem);
}

/* Konten panjang (artikel, pengumuman) */
.prose {
  max-width: 65ch;  /* Lebar ideal untuk baca */
  line-height: 1.7;
  color: var(--color-neutral-700);
}

.prose p     { margin-bottom: 1em; }
.prose ul,
.prose ol    { padding-left: var(--space-6); margin-bottom: 1em; }
.prose li    { margin-bottom: var(--space-1); }
.prose a     { color: var(--color-primary); text-decoration: underline; }
.prose a:hover { color: var(--color-primary-dark); }

/* Teks pembantu */
.text-muted    { color: var(--color-neutral-500); font-size: var(--text-sm); }
.text-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.text-wrap     { overflow-wrap: break-word; word-break: break-word; }

/* Kontrast minimal WCAG AA: 4.5:1 untuk teks normal, 3:1 untuk teks besar */
```

---

## 10. Aksesibilitas (WCAG 2.1)

```css
/* styles/accessibility.css */

/* Sembunyikan secara visual, tapi tetap dibaca screen reader */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Tampilkan saat difokus (untuk skip links) */
.sr-only-focusable:focus {
  position: static;
  width: auto;
  height: auto;
  overflow: visible;
  clip: auto;
  white-space: normal;
}

/* Reduced motion — hormati preferensi sistem */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
    scroll-behavior: auto !important;
  }
}

/* High contrast mode */
@media (forced-colors: active) {
  .btn { border: 2px solid ButtonText; }
  .card { border: 1px solid ButtonText; }
}
```

### Checklist Aksesibilitas

```
[WAJIB] Semua gambar punya atribut alt yang deskriptif
[WAJIB] Setiap form input punya <label> yang terhubung
[WAJIB] Kontrast warna teks ≥ 4.5:1 (cek: webaim.org/resources/contrastchecker)
[WAJIB] Navigasi keyboard lengkap (Tab, Shift+Tab, Enter, Escape, Arrow keys)
[WAJIB] Tidak ada konten yang bergantung HANYA pada warna untuk menyampaikan info
[WAJIB] Judul halaman (<title>) unik dan deskriptif di setiap halaman
[WAJIB] Bahasa halaman dideklarasikan: <html lang="id">
[WAJIB] Error form diumumkan ke screen reader (role="alert")
[DISARANKAN] Landmark ARIA: header, nav, main, aside, footer
[DISARANKAN] Heading hierarchy benar (h1 → h2 → h3, tidak melompat)
[DISARANKAN] Link "Skip to content" di atas setiap halaman
[DISARANKAN] Modal/dialog dengan focus trap yang benar
```

---

## 11. Performa & Optimasi

### HTML

```html
<!-- Preload font kritis -->
<link rel="preload" href="font.woff2" as="font" type="font/woff2" crossorigin>

<!-- Lazy load gambar -->
<img src="foto-mahasiswa.jpg" alt="..." loading="lazy" decoding="async"
     width="400" height="300">

<!-- Preconnect ke CDN/API -->
<link rel="preconnect" href="https://api.universitasmu.ac.id">

<!-- Script non-blocking -->
<script src="main.js" defer></script>
<script src="analytics.js" async></script>
```

### CSS

```css
/* Optimasi render */
.card {
  /* Aktifkan GPU acceleration hanya untuk elemen yang beranimasi */
  will-change: transform;
  /* Batasi area repaint */
  contain: layout style;
}

/* Hindari layout thrashing — baca/tulis DOM secara batch */

/* Gunakan transform daripada top/left untuk animasi */
/* ❌ Lambat */ .modal { top: 50%; left: 50%; }
/* ✅ Cepat  */ .modal { transform: translate(-50%, -50%); }
```

### JavaScript

```javascript
// Lazy load komponen besar
const loadChart = async () => {
  const { renderChart } = await import('./modules/chart.js');
  renderChart();
};

// Debounce untuk input search
function debounce(fn, delay = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}

const handleSearch = debounce((query) => {
  fetchMahasiswaData(query);
}, 300);

searchInput.addEventListener('input', (e) => handleSearch(e.target.value));

// Intersection Observer untuk lazy load tabel data
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      loadTableData();
      observer.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

observer.observe(document.querySelector('.data-table'));
```

### Target Performa (Lighthouse)

```
Performance:   ≥ 90
Accessibility: ≥ 95
Best Practice: ≥ 90
SEO:           ≥ 85

Core Web Vitals:
  LCP (Largest Contentful Paint): < 2.5 detik
  FID (First Input Delay):        < 100ms
  CLS (Cumulative Layout Shift):  < 0.1
```

---

## 12. Kompatibilitas Browser

### CSS Prefixes & Fallbacks

```css
/* Flexbox dengan fallback */
.flex-container {
  display: -webkit-box;      /* Lama iOS */
  display: -ms-flexbox;      /* IE 10 */
  display: flex;
}

/* Grid dengan fallback */
.grid-container {
  display: flex;             /* Fallback untuk browser lama */
  flex-wrap: wrap;
  gap: var(--space-4);
}

@supports (display: grid) {
  .grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }
}

/* CSS Custom Properties dengan fallback */
.button {
  background-color: #1a56db;                    /* Fallback */
  background-color: var(--color-primary, #1a56db);
}

/* Sticky positioning dengan fallback */
.sidebar {
  position: -webkit-sticky;  /* Safari */
  position: sticky;
}

/* Scroll behavior */
html {
  -webkit-overflow-scrolling: touch;  /* iOS momentum scrolling */
  scroll-behavior: smooth;
}

/* Overflow: untuk Chrome/Opera */
.table-wrapper::-webkit-scrollbar { height: 6px; }
.table-wrapper::-webkit-scrollbar-track { background: var(--color-neutral-100); }
.table-wrapper::-webkit-scrollbar-thumb {
  background: var(--color-neutral-200);
  border-radius: 3px;
}
```

### Browser Target Minimum

```
Chrome:  88+   (2021)
Firefox: 85+   (2021)
Safari:  14+   (2020) ← perlu perhatian khusus
Edge:    88+   (2021)
Samsung: 14+   (2021)
iOS:     14+   (2020) ← paling banyak dipakai mahasiswa!
```

### Isu Safari yang Sering Muncul

```css
/* 1. Input zoom di iOS — KRITIS */
/* Solusi: font-size input ≥ 16px */
input, select, textarea { font-size: 1rem; }

/* 2. Sticky tidak bekerja di Safari dalam overflow container */
.parent { overflow: visible; }  /* Jangan overflow: hidden pada parent sticky */

/* 3. Gap di flex Safari lama */
.flex-gap { gap: var(--space-4); }  /* Sudah support Safari 14+ */

/* 4. min-height: 100vh di Safari dengan toolbar browser */
.full-height {
  min-height: 100vh;
  min-height: -webkit-fill-available;  /* Workaround Safari */
}

/* 5. Backdrop-filter — selalu tambahkan prefix */
.overlay {
  -webkit-backdrop-filter: blur(8px);
  backdrop-filter: blur(8px);
}
```

---

## 13. Testing & QA Checklist

### Checklist Responsif

```
MOBILE (≤ 480px)
[ ] Tidak ada overflow horizontal
[ ] Semua tombol ≥ 44x44px (touch target)
[ ] Font minimum 16px (cegah zoom iOS)
[ ] Sidebar tertutup secara default, tombol hamburger terlihat
[ ] Tabel dapat di-scroll horizontal atau berubah jadi kartu
[ ] Form input nyaman diisi dengan keyboard virtual
[ ] Tidak ada elemen yang terpotong

TABLET (481px – 1023px)
[ ] Layout 2 kolom muncul dengan benar
[ ] Sidebar bisa toggle
[ ] Tabel masih terbaca
[ ] Grid kartu menyesuaikan

DESKTOP (≥ 1024px)
[ ] Sidebar selalu tampil
[ ] Hamburger button tersembunyi
[ ] Konten menggunakan ruang dengan baik (tidak terlalu lebar)
[ ] Hover state semua elemen interaktif berfungsi

SEMUA UKURAN
[ ] Logo terbaca dan tidak terpotong
[ ] Breadcrumb tidak meluber
[ ] Modal/popup tidak keluar layar
[ ] Loading state tampil dengan baik
[ ] Error state tampil dengan baik
[ ] Empty state ada (tabel kosong, hasil pencarian 0)
```

### Tools Pengujian

```
GRATIS:
- Chrome DevTools (F12) → Tab Device Toolbar → Test berbagai ukuran
- Firefox Responsive Design Mode
- https://www.responsivedesignchecker.com
- https://browserstack.com/responsive (gratis terbatas)
- https://pagespeed.web.dev (Lighthouse online)
- https://wave.webaim.org (aksesibilitas)
- https://www.webaim.org/resources/contrastchecker (kontras warna)
- https://validator.w3.org (validasi HTML)

DEVICE FISIK (prioritas):
- iPhone (Safari) ← paling banyak bug tersembunyi
- Android (Chrome)
- iPad/Tablet
```

### Cara Cepat Menemukan Bug Responsif

```javascript
// Pasang di console DevTools untuk menemukan elemen overflow
const findOverflow = () => {
  const docWidth = document.documentElement.offsetWidth;
  document.querySelectorAll('*').forEach(el => {
    if (el.offsetWidth > docWidth) {
      console.warn('Overflow detected:', el, el.offsetWidth, '>', docWidth);
    }
  });
};
findOverflow();
// Jalankan ini sambil memperkecil viewport
```

---

## 14. Referensi Cepat Breakpoint

```css
/*
  Sistem breakpoint yang direkomendasikan:
  
  xs    : < 480px   → Smartphone kecil, mode portrait
  sm    : ≥ 480px   → Smartphone besar
  md    : ≥ 768px   → Tablet portrait
  lg    : ≥ 1024px  → Tablet landscape, laptop kecil
  xl    : ≥ 1280px  → Desktop
  2xl   : ≥ 1536px  → Desktop lebar
*/

/* Mobile First — SELALU mulai dari mobile, lalu ke atas */

/* Default: mobile */
.element { ... }

/* ≥ 480px */
@media (min-width: 480px)  { .element { ... } }

/* ≥ 768px */
@media (min-width: 768px)  { .element { ... } }

/* ≥ 1024px */
@media (min-width: 1024px) { .element { ... } }

/* ≥ 1280px */
@media (min-width: 1280px) { .element { ... } }

/* Print */
@media print {
  .sidebar, .nav-toggle, .btn-logout { display: none; }
  .main-content { padding: 0; margin: 0; }
  body { font-size: 12pt; }
}
```

---

## 🏁 Urutan Implementasi yang Disarankan

Lakukan secara bertahap, satu per satu:

```
1. [ ] Tambahkan <meta name="viewport"> jika belum ada
2. [ ] Perbaiki semua overflow horizontal (gunakan script findOverflow di atas)
3. [ ] Terapkan CSS Reset & variabel (design tokens)
4. [ ] Perbaiki layout dengan Grid/Flexbox — hapus float
5. [ ] Buat navigasi/sidebar responsif dengan hamburger menu
6. [ ] Bungkus semua tabel dalam .table-wrapper (overflow-x: auto)
7. [ ] Pastikan input font-size ≥ 16px (cegah zoom iOS)
8. [ ] Pastikan semua tombol ≥ 44x44px
9. [ ] Tambahkan label pada semua form input
10. [ ] Jalankan Lighthouse → perbaiki isu prioritas tinggi
11. [ ] Test di iPhone Safari (paling banyak bug)
12. [ ] Test navigasi keyboard-only
```

---

*Dokumen ini mencakup semua aspek untuk menjadikan sistem akademik Anda production-ready, user-friendly, dan responsif di berbagai perangkat dan browser.*
