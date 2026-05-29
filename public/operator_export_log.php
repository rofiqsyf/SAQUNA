<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\OperatorRepository;

Auth::requireOperator();

$repo = new OperatorRepository();
// Export up to 2000 logs for history tracking
$logs = $repo->getActivityLogs(2000); 

$filename = "audit_log_saquna_" . date('Y-m-d_His') . ".csv";

// Set headers to trigger file download
header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$output = fopen("php://output", "w");

// Write CSV Headers
fputcsv($output, ['Waktu (Timestamp)', 'Aksi', 'Entitas / Tabel', 'ID Entitas', 'User Pelaku', 'Role User', 'Keterangan Lengkap']);

// Write CSV Data Rows
if (!empty($logs)) {
    foreach ($logs as $log) {
        fputcsv($output, [
            date('Y-m-d H:i:s', strtotime($log['created_at'])),
            strtoupper($log['aksi']),
            $log['entitas'],
            $log['entitas_id'],
            $log['username'] ?? 'System',
            strtoupper($log['role'] ?? 'SYSTEM'),
            $log['keterangan'] ?? '-'
        ]);
    }
}

fclose($output);
exit;
