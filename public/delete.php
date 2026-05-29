<?php
require_once __DIR__ . '/../autoload.php';

use Src\Auth;
use Src\DosenRepository;

// requireOperator() sudah mencakup requireLogin() secara internal
Auth::requireOperator();

// Validasi CSRF — endpoint ini hanya boleh diakses via POST atau GET dengan token
// Karena ini GET-based delete, gunakan validasi token dari query string
// Namun pendekatan terbaik adalah via POST dengan CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect ke halaman master_dosen yang aman
    header("Location: master_dosen.php?msg=invalid_method");
    exit;
}

if (!Auth::validateCsrf($_POST['csrf_token'] ?? '')) {
    header("Location: master_dosen.php?msg=csrf_error");
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    header("Location: master_dosen.php");
    exit;
}

$repo = new DosenRepository();
$success = $repo->softDelete($id, (int)$_SESSION['user_id']);

if ($success) {
    header("Location: master_dosen.php?msg=deleted");
} else {
    header("Location: master_dosen.php?msg=delete_failed");
}
exit;
