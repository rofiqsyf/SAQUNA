<?php

$dir = __DIR__ . '/public/';
$files = glob($dir . '*.php');

$replacements = [
    // Layout & Forms
    '/class="card\b[^"]*"/' => 'class="glass-panel rounded-3xl p-stack-md mb-stack-md shadow-sm border border-white/40"',
    '/class="card-body"/' => 'class=""',
    '/class="form-group"/' => 'class="mb-stack-sm"',
    '/class="form-label"/' => 'class="block font-label-md text-label-md text-on-surface-variant mb-2"',
    '/class="form-control"/' => 'class="w-full bg-white/60 border border-outline-variant/50 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-container focus:border-primary transition-all font-body-md placeholder:text-outline-variant"',
    
    // Buttons
    '/class="btn btn-primary( btn-sm| btn-lg)?"/' => 'class="bg-primary hover:bg-on-primary-fixed-variant text-on-primary font-label-md px-6 py-3 rounded-xl shadow-lg shadow-primary-container/20 transition-all active:scale-95 inline-block text-center"',
    '/class="btn btn-secondary( btn-sm| btn-lg)?"/' => 'class="bg-surface-variant hover:bg-outline-variant text-on-surface font-label-md px-6 py-3 rounded-xl transition-all active:scale-95 inline-block text-center"',
    '/class="btn btn-danger( btn-sm| btn-lg)?"/' => 'class="bg-error hover:bg-on-error-container text-on-error font-label-md px-6 py-3 rounded-xl shadow-lg transition-all active:scale-95 inline-block text-center"',
    '/class="btn btn-warning( btn-sm| btn-lg)?"/' => 'class="bg-tertiary hover:bg-on-tertiary-container text-on-tertiary font-label-md px-6 py-3 rounded-xl shadow-lg transition-all active:scale-95 inline-block text-center"',
    '/class="btn btn-info( btn-sm| btn-lg)?"/' => 'class="bg-secondary hover:bg-on-secondary-container text-on-secondary font-label-md px-6 py-3 rounded-xl shadow-lg transition-all active:scale-95 inline-block text-center"',
    
    // Alerts
    '/class="alert alert-success"/' => 'class="bg-secondary-fixed text-on-secondary-fixed p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"',
    '/class="alert alert-danger"/' => 'class="bg-error-container text-on-error-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"',
    '/class="alert alert-info"/' => 'class="bg-tertiary-container text-on-tertiary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"',
    '/class="alert alert-warning"/' => 'class="bg-primary-container text-on-primary-container p-stack-sm rounded-xl mb-stack-md font-body-md flex items-center gap-2"',
    
    // Typography
    '/class="text-muted"/' => 'class="text-on-surface-variant opacity-80"',
    
    // Tables
    '/class="table-responsive"/' => 'class="overflow-x-auto rounded-xl border border-outline-variant/30"',
    '/class="table"/' => 'class="w-full text-left border-collapse"',
    '/<th>/' => '<th class="px-4 py-3 bg-surface-container-low text-on-surface font-label-md border-b border-outline-variant/30">',
    '/<td>/' => '<td class="px-4 py-3 border-b border-outline-variant/20 font-body-md">',
    
    // Badges
    '/class="badge badge-success"/' => 'class="bg-secondary-fixed text-on-secondary-fixed px-3 py-1 rounded-full font-label-md text-xs"',
    '/class="badge badge-primary"/' => 'class="bg-primary-container text-on-primary-container px-3 py-1 rounded-full font-label-md text-xs"',
    '/class="badge badge-warning"/' => 'class="bg-tertiary-container text-on-tertiary-container px-3 py-1 rounded-full font-label-md text-xs"',
    '/class="badge badge-danger"/' => 'class="bg-error-container text-on-error-container px-3 py-1 rounded-full font-label-md text-xs"',
    
    // Flex & Grid (Basic polyfills)
    '/class="d-flex justify-content-between align-items-center([^"]*)"/' => 'class="flex justify-between items-center$1"',
    '/class="grid-2([^"]*)"/' => 'class="grid grid-cols-1 md:grid-cols-2 gap-stack-md$1"',
    '/class="grid-3([^"]*)"/' => 'class="grid grid-cols-1 md:grid-cols-3 gap-stack-md$1"',
    '/class="grid-4([^"]*)"/' => 'class="grid grid-cols-1 md:grid-cols-4 gap-stack-md$1"',
];

$skipFiles = ['login.php', 'login_mahasiswa.php', 'login_dosen.php', 'login_operator.php', 'mahasiswa_dashboard.php'];

foreach ($files as $file) {
    $basename = basename($file);
    if (in_array($basename, $skipFiles)) continue;
    
    $content = file_get_contents($file);
    
    // Strip <!DOCTYPE html> block if present AND the file includes header.php
    if (strpos($content, 'include \'components/header.php\';') !== false || strpos($content, 'include __DIR__ . \'/components/header.php\';') !== false) {
        if (strpos($content, '<!DOCTYPE html>') !== false) {
            // Extract everything after header.php include
            if (preg_match('/(<\?php.*?include\s+[\'"]components\/header\.php[\'"];.*?\?>)(.*)/s', $content, $m)) {
                $phpTop = $m[1];
                $htmlBody = $m[2];
                // remove trailing </body></html>
                $htmlBody = preg_replace('/<\/body>\s*<\/html>\s*$/i', '', $htmlBody);
                $content = $phpTop . $htmlBody;
            } else {
                // Try alternate pattern
                 $content = preg_replace('/<!DOCTYPE html>.*?<body>/is', '', $content);
                 $content = preg_replace('/<\/body>\s*<\/html>\s*$/i', '', $content);
            }
        }
    }
    
    // Apply Tailwind replacements
    foreach ($replacements as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }
    
    file_put_contents($file, $content);
    echo "Converted UI for $basename\n";
}
echo "Done!\n";
