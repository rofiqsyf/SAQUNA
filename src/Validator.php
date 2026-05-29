<?php
declare(strict_types=1);

namespace Src;

use finfo;

class Validator {
    private array $errors = [];
    private array $data = [];

    public function __construct(array $postData) {
        // Otomatis trim semua input string
        foreach ($postData as $key => $value) {
            if (is_string($value)) {
                $this->data[$key] = trim($value);
            } elseif (is_array($value)) {
                $this->data[$key] = $value; // untuk multi-select matakuliah
            }
        }
    }

    public function get(string $key, $default = null) {
        return $this->data[$key] ?? $default;
    }

    public function validateRequired(string $key, string $message): void {
        $val = $this->get($key);
        if ($val === null || $val === '' || (is_array($val) && empty($val))) {
            $this->errors[$key] = $message;
        }
    }

    public function validateEmail(string $key, string $message): void {
        if (!isset($this->errors[$key])) {
            $val = $this->get($key);
            if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
                $this->errors[$key] = $message;
            }
        }
    }

    public function validateInArray(string $key, array $allowed, string $message): void {
        if (!isset($this->errors[$key])) {
            $val = $this->get($key);
            if (!in_array($val, $allowed, true)) {
                $this->errors[$key] = $message;
            }
        }
    }

    public function validateLength(string $key, int $min, int $max, string $message): void {
        if (!isset($this->errors[$key])) {
            $val = $this->get($key);
            $len = mb_strlen((string)$val);
            if ($len < $min || $len > $max) {
                $this->errors[$key] = $message;
            }
        }
    }

    public function validateImageUpload(string $fileKey, bool $required = false): ?string {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                $this->errors[$fileKey] = "File foto wajib diunggah.";
            }
            return null; // Tidak ada file yang diunggah
        }

        $file = $_FILES[$fileKey];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$fileKey] = "Terjadi kesalahan saat mengunggah file.";
            return null;
        }

        // Cek ukuran max 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            $this->errors[$fileKey] = "Ukuran file maksimal 2MB.";
            return null;
        }

        // Cek MIME type dengan finfo (AMAN)
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($mime, $allowedMimes, true)) {
            $this->errors[$fileKey] = "Hanya file JPG, PNG, atau WebP yang diperbolehkan.";
            return null;
        }

        // Generate nama aman dengan hash
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = hash('sha256', uniqid((string)mt_rand(), true)) . '.' . $ext;

        return $safeName; // Mengembalikan nama file baru jika lolos
    }

    public function handleUpload(string $fileKey, string $safeName, string $uploadDir): bool {
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $destination = rtrim($uploadDir, '/\\') . '/' . $safeName;
        return move_uploaded_file($_FILES[$fileKey]['tmp_name'], $destination);
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getError(string $key): ?string {
        return $this->errors[$key] ?? null;
    }
}
