<?php
declare(strict_types=1);

namespace Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $pdo = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = 'localhost';
            $user = 'root'; // Sesuaikan jika berbeda
            $pass = '';     // Sesuaikan jika berbeda
            $dbname = 'saquna';

            $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,  // Prepared statement asli
            ];
            
            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Di sistem nyata log error ke file
                error_log("Database connection failed: " . $e->getMessage());
                die('Koneksi database gagal. Pastikan XAMPP/Laragon berjalan dan database sudah di-setup.');
            }
        }
        
        return self::$pdo;
    }
}
