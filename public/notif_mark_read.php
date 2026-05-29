<?php
declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Config\Database;

Auth::startSession();
header('Content-Type: application/json');

// Harus login
if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validasi CSRF
if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF token tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

if ($action === 'mark_all_read') {
    try {
        $pdo = Database::getConnection();
        // Tandai semua pesan yang diterima user ini sebagai sudah dibaca
        $stmt = $pdo->prepare("UPDATE pesan_tanya_jawab SET is_read = 1 WHERE penerima_user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true, 'message' => 'Semua notifikasi telah ditandai sebagai dibaca.']);
    } catch (\PDOException $e) {
        error_log('notif_mark_read error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui notifikasi.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Aksi tidak valid.']);
}
