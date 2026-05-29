<?php
declare(strict_types=1);

namespace Src;

use Config\Database;
use PDO;
use Exception;

class KalenderRepository {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    public function getAllEvents(): array {
        $stmt = $this->pdo->query("SELECT * FROM kalender_akademik ORDER BY tanggal_mulai ASC");
        return $stmt->fetchAll();
    }

    public function getActiveEvents(): array {
        $stmt = $this->pdo->query("SELECT * FROM kalender_akademik WHERE CURRENT_DATE BETWEEN tanggal_mulai AND tanggal_akhir ORDER BY tanggal_mulai ASC");
        return $stmt->fetchAll();
    }

    public function isKrsPeriodOpen(): bool {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM periode_krs WHERE status = 'Buka' AND NOW() BETWEEN tanggal_buka AND tanggal_tutup");
        return (bool)$stmt->fetchColumn();
    }

    public function createEvent(array $data, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();
            $sql = "INSERT INTO kalender_akademik (nama_event, jenis_event, tanggal_mulai, tanggal_akhir, semester, tahun_ajaran) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nama_event'], 
                $data['jenis_event'], 
                $data['tanggal_mulai'], 
                $data['tanggal_akhir'], 
                $data['semester'], 
                $data['tahun_ajaran']
            ]);
            $eventId = (int)$this->pdo->lastInsertId();

            Auth::logActivity($userId, 'create', 'kalender_akademik', $eventId, "Membuat event akademik: {$data['nama_event']}", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function updateEvent(int $id, array $data, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();
            $sql = "UPDATE kalender_akademik SET nama_event = ?, jenis_event = ?, tanggal_mulai = ?, tanggal_akhir = ?, semester = ?, tahun_ajaran = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $data['nama_event'], 
                $data['jenis_event'], 
                $data['tanggal_mulai'], 
                $data['tanggal_akhir'], 
                $data['semester'], 
                $data['tahun_ajaran'],
                $id
            ]);

            Auth::logActivity($userId, 'update', 'kalender_akademik', $id, "Mengubah event akademik: {$data['nama_event']}", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }

    public function deleteEvent(int $id, ?int $userId): bool {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("DELETE FROM kalender_akademik WHERE id = ?");
            $stmt->execute([$id]);

            Auth::logActivity($userId, 'delete', 'kalender_akademik', $id, "Menghapus event akademik ID: $id", $this->pdo);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
