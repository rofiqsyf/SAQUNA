<?php

declare(strict_types=1);

// ===== PENGATURAN PRODUCTION =====
// 1. Matikan error display agar tidak bocor ke user, arahkan ke error.log
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/error.log');

// 2. Keamanan Cookie Sesi (Session Security)
ini_set('session.cookie_httponly', '1'); // Cegah XSS mencuri cookie
ini_set('session.use_only_cookies', '1'); // Wajibkan penggunaan cookie untuk session ID
ini_set('session.cookie_samesite', 'Lax'); // Cegah CSRF eksternal
// ini_set('session.cookie_secure', '1'); // Buka komentar ini JIKA server sudah menggunakan HTTPS (SSL)
// =================================

spl_autoload_register(function ($class) {
    // Prefix namespace
    $prefix = '';

    // Base directory untuk namespace prefix
    $base_dir = __DIR__ . '/';

    // Ganti separator namespace dengan directory separator
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';

    // Jika file ada, require
    if (file_exists($file)) {
        require $file;
    }
});
