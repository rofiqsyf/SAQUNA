<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;

// Pastikan hanya operator yang bisa mengakses endpoint ini
Auth::requireOperator();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// Basic CSRF handling
if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token keamanan tidak valid.']);
    exit;
}

$request_id = $_POST['request_id'] ?? null;
$status = $_POST['status'] ?? 'selesai';

if (!$request_id) {
    echo json_encode(['success' => false, 'message' => 'ID pengajuan tidak valid.']);
    exit;
}

try {
    $db = \Config\Database::getConnection();
    
    // Perbarui status pengajuan
    $stmt = $db->prepare("UPDATE lupa_sandi_requests SET status = ? WHERE id = ?");
    $stmt->execute([$status, $request_id]);
    
    echo json_encode(['success' => true, 'message' => 'Status pengajuan berhasil diperbarui.']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Gagal memperbarui status: ' . $e->getMessage()]);
}
