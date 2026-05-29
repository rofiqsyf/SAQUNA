<?php

declare(strict_types=1);

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
