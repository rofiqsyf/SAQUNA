<?php
// Script to localize external assets
$baseDir = __DIR__ . '/public/assets';
$jsDir = $baseDir . '/js';
$cssDir = $baseDir . '/css';
$fontsDir = $baseDir . '/fonts';

if (!is_dir($jsDir)) mkdir($jsDir, 0777, true);
if (!is_dir($cssDir)) mkdir($cssDir, 0777, true);
if (!is_dir($fontsDir)) mkdir($fontsDir, 0777, true);

$assets = [
    'tailwindcss.js' => 'https://cdn.tailwindcss.com?plugins=forms,container-queries',
    'chart.min.js' => 'https://cdn.jsdelivr.net/npm/chart.js',
    'html5-qrcode.min.js' => 'https://unpkg.com/html5-qrcode',
    'html2canvas.min.js' => 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js'
];

$context = stream_context_create([
    'http' => [
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36\r\n"
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

echo "Downloading JS libraries...\n";
foreach ($assets as $file => $url) {
    echo "Downloading $file from $url...\n";
    $content = file_get_contents($url, false, $context);
    if ($content) {
        file_put_contents($jsDir . '/' . $file, $content);
        echo "Saved $file\n";
    } else {
        echo "FAILED to download $url\n";
    }
}

echo "\nDownloading Fonts...\n";
$fontUrls = [
    'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@500;700&display=swap',
    'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap'
];

$combinedCss = "";

foreach ($fontUrls as $fUrl) {
    echo "Fetching CSS from $fUrl...\n";
    $cssContent = file_get_contents($fUrl, false, $context);
    
    // Find all woff2 urls
    preg_match_all('/url\((https:\/\/[^)]+)\)/i', $cssContent, $matches);
    
    foreach ($matches[1] as $fontUrl) {
        $fontUrl = trim($fontUrl, "'\"");
        $fontName = basename(parse_url($fontUrl, PHP_URL_PATH));
        
        // Some google fonts have the same basename, let's make it unique
        $fontName = md5($fontUrl) . '_' . $fontName;
        
        $localPath = $fontsDir . '/' . $fontName;
        if (!file_exists($localPath)) {
            echo "Downloading font: $fontName...\n";
            $fontContent = file_get_contents($fontUrl, false, $context);
            if ($fontContent) {
                file_put_contents($localPath, $fontContent);
            }
        }
        
        // Rewrite CSS
        $cssContent = str_replace($fontUrl, '../fonts/' . $fontName, $cssContent);
    }
    
    $combinedCss .= $cssContent . "\n\n";
}

file_put_contents($cssDir . '/fonts.css', $combinedCss);
echo "Saved combined fonts.css\n";


// Scan files and replace references
echo "\nScanning PHP files for replacements...\n";
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/public'));

$replacements = [
    'https://cdn.tailwindcss.com?plugins=forms,container-queries' => 'assets/js/tailwindcss.js',
    'https://cdn.tailwindcss.com' => 'assets/js/tailwindcss.js',
    'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap' => 'assets/css/fonts.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@500;600;700&display=swap' => 'assets/css/fonts.css',
    'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap' => 'assets/css/fonts.css',
    'https://cdn.jsdelivr.net/npm/chart.js' => 'assets/js/chart.min.js',
    'https://unpkg.com/html5-qrcode' => 'assets/js/html5-qrcode.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js' => 'assets/js/html2canvas.min.js',
    // specifically handle html-encoded ampersands
    'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&amp;family=Inter:wght@400;500;600&amp;display=swap' => 'assets/css/fonts.css',
    'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap' => 'assets/css/fonts.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&amp;family=Outfit:wght@500;600;700&amp;display=swap' => 'assets/css/fonts.css'
];

foreach ($iterator as $file) {
    if ($file->isFile() && ($file->getExtension() === 'php' || $file->getExtension() === 'css')) {
        $filePath = $file->getRealPath();
        $content = file_get_contents($filePath);
        $originalContent = $content;
        
        foreach ($replacements as $search => $replace) {
            // For CSS, we need relative path from assets to the resource or absolute from public
            // Assuming all PHP files are in public/ directly or one level deep.
            // Since most files are directly in public/ (except components), we can use relative or absolute from root.
            // It's safer to use `/assets/...` if they are accessed from subdirectories, but saquna runs in a subfolder `uts-pemweb/saquna/public`.
            // Wait, the original code used `assets/style.css` without leading slash. So we will just replace string directly.
            // But if a file is in `public/components/header.php`, it might need `../assets/`.
            // Currently, `header.php` is included from `public/index.php`, so `assets/` is correct relative to the running script `index.php`.
            $content = str_replace($search, $replace, $content);
        }
        
        // Also remove duplicate fonts.css link if there are multiple google fonts requested in the same file
        $content = preg_replace('/(<link[^>]*href="assets\/css\/fonts\.css"[^>]*>\s*){2,}/', '<link href="assets/css/fonts.css" rel="stylesheet">' . "\n", $content);
        
        // Also handle the style.css font import
        if ($file->getFilename() === 'style.css') {
            $content = preg_replace("/@import url\('https:\/\/fonts.googleapis.com[^']+'\);/", "@import url('fonts.css');", $content);
        }

        if ($content !== $originalContent) {
            file_put_contents($filePath, $content);
            echo "Updated {$file->getFilename()}\n";
        }
    }
}

// Special case for build_ui.php which generates header.php
$buildUiPath = __DIR__ . '/build_ui.php';
if (file_exists($buildUiPath)) {
    $content = file_get_contents($buildUiPath);
    foreach ($replacements as $search => $replace) {
        $content = str_replace($search, $replace, $content);
    }
    $content = preg_replace('/(<link[^>]*href="assets\/css\/fonts\.css"[^>]*>\s*){2,}/', '<link href="assets/css/fonts.css" rel="stylesheet">' . "\n", $content);
    file_put_contents($buildUiPath, $content);
    echo "Updated build_ui.php\n";
    
    // Re-run build_ui.php to ensure components are updated
    include $buildUiPath;
}

echo "\nDone!\n";
