<?php
declare(strict_types=1);

namespace Src;

use Config\Database;
use PDO;

class Auth {
    public static function startSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Inisialisasi CSRF Token jika belum ada
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function login(string $username, string $password, string $portalType = 'any'): array {
        self::startSession();

        // Rate limiting sederhana (max 3x percobaan per menit)
        if (isset($_SESSION['login_attempts']) && $_SESSION['login_attempts'] >= 3) {
            $lastAttempt = $_SESSION['last_login_attempt'] ?? 0;
            if (time() - $lastAttempt < 60) {
                return ['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 1 menit.'];
            } else {
                // Reset setelah 1 menit
                $_SESSION['login_attempts'] = 0;
            }
        }

        $pdo = Database::getConnection();
        // Cek username, nim, atau nidn
        $sql = "SELECT u.id, u.username, u.password_hash, u.role 
                FROM users u 
                LEFT JOIN mahasiswa m ON u.id = m.user_id 
                LEFT JOIN dosen d ON u.id = d.user_id 
                WHERE u.username = ? OR m.nim = ? OR d.nidn = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Pengecekan Portal Ekstra Ketat
            if ($portalType !== 'any' && $user['role'] !== $portalType) {
                return ['success' => false, 'message' => "Akses Ditolak: Anda mencoba login ke portal yang salah dengan akun {$user['role']}."];
            }

            // Mencegah Session Fixation
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Fetch tambahan data profil (nama, foto) agar sinkron di seluruh dashboard
            if ($user['role'] === 'mahasiswa') {
                $stmtP = $pdo->prepare("SELECT nama, foto FROM mahasiswa WHERE user_id = ?");
                $stmtP->execute([$user['id']]);
                if ($p = $stmtP->fetch()) {
                    $_SESSION['nama_lengkap'] = $p['nama'];
                    $_SESSION['foto'] = $p['foto'];
                }
            } elseif ($user['role'] === 'dosen') {
                $stmtP = $pdo->prepare("SELECT nama, foto FROM dosen WHERE user_id = ?");
                $stmtP->execute([$user['id']]);
                if ($p = $stmtP->fetch()) {
                    $_SESSION['nama_lengkap'] = $p['nama'];
                    // Gunakan foto yang tersimpan jika ada (path lokal maupun URL)
                    // Fallback ke avatar generator lokal akan ditangani oleh header.php
                    $_SESSION['foto'] = $p['foto'];
                }
            }

            // Reset login attempts
            unset($_SESSION['login_attempts']);
            unset($_SESSION['last_login_attempt']);

            // Catat log aktivitas (Level 3)
            self::logActivity((int)$user['id'], 'login', 'system', null, 'User login');

            return ['success' => true];
        }

        // Catat kegagalan login
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['last_login_attempt'] = time();

        return ['success' => false, 'message' => 'Username atau password salah.'];
    }

    public static function logout(): void {
        self::startSession();
        
        if (isset($_SESSION['user_id'])) {
            self::logActivity((int)$_SESSION['user_id'], 'logout', 'system', null, 'User logout');
        }

        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool {
        self::startSession();
        if (!isset($_SESSION['user_id'])) return false;
        
        // Mitigasi Ghost Session: Pastikan user masih ada di database, role-nya tidak berubah, dan statusnya aktif
        $pdo = Database::getConnection();
        $sql = "SELECT u.role, 
                       m.deleted_at as m_deleted,
                       d.status as d_status,
                       d.deleted_at as d_deleted
                FROM users u
                LEFT JOIN mahasiswa m ON u.id = m.user_id
                LEFT JOIN dosen d ON u.id = d.user_id
                WHERE u.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        
        if (!$row || $row['role'] !== $_SESSION['role']) {
            $_SESSION = [];
            session_destroy();
            return false;
        }

        if ($row['role'] === 'mahasiswa' && $row['m_deleted'] !== null) {
            $_SESSION = [];
            session_destroy();
            return false;
        }

        if ($row['role'] === 'dosen' && ($row['d_status'] !== 'aktif' || $row['d_deleted'] !== null)) {
            $_SESSION = [];
            session_destroy();
            return false;
        }
        
        return true;
    }

    public static function requireLogin(): void {
        if (!self::check()) {
            // Preserve the requested URL for redirect after login
            $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
            header("Location: login.php" . ($redirect ? "?redirect={$redirect}" : ''));
            exit;
        }
    }

    public static function getRole(): ?string {
        self::startSession();
        return $_SESSION['role'] ?? null;
    }

    public static function isOperator(): bool {
        return self::getRole() === 'operator';
    }

    public static function requireOperator(): void {
        self::requireLogin();
        if (!self::isOperator()) {
            http_response_code(403);
            die("<strong>403 Forbidden</strong><br>Akses ditolak. Fitur ini hanya untuk Operator.");
        }
    }

    public static function isMahasiswa(): bool {
        return self::getRole() === 'mahasiswa';
    }

    public static function requireMahasiswa(): void {
        self::requireLogin();
        if (!self::isMahasiswa()) {
            http_response_code(403);
            die("<strong>403 Forbidden</strong><br>Akses ditolak. Fitur ini khusus untuk Mahasiswa.");
        }
    }

    public static function isDosen(): bool {
        return self::getRole() === 'dosen';
    }

    public static function requireDosen(): void {
        self::requireLogin();
        if (!self::isDosen()) {
            http_response_code(403);
            die("<strong>403 Forbidden</strong><br>Akses ditolak. Fitur ini khusus untuk Dosen.");
        }
    }

    public static function validateCsrf(string $token): bool {
        self::startSession();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function csrfField(): string {
        self::startSession();
        $token = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
        return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
    }

    /**
     * Alias untuk mendapatkan nilai CSRF token (bukan full HTML field).
     * Gunakan ini jika hanya butuh nilai token untuk dimasukkan ke value atribut secara manual.
     */
    public static function generateCsrf(): string {
        self::startSession();
        return htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
    }

    // Fungsi utilitas untuk Audit Log (Level 3)
    public static function logActivity(?int $userId, string $aksi, string $entitas, ?int $entitasId, ?string $keterangan = null, ?PDO $pdoTransaction = null): void {
        try {
            $pdo = $pdoTransaction ?? Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, aksi, entitas, entitas_id, keterangan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $aksi, $entitas, $entitasId, $keterangan]);
        } catch (\PDOException $e) {
            // Abaikan error log agar tidak mengganggu transaksi utama
            error_log("Failed to insert activity log: " . $e->getMessage());
        }
    }
}
