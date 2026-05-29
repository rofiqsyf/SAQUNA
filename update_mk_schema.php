<?php
require 'autoload.php';
$db = Config\Database::getConnection();

try {
    $db->exec("ALTER TABLE mata_kuliah ADD COLUMN prodi VARCHAR(150) NULL AFTER sks");
    $db->exec("ALTER TABLE mata_kuliah ADD COLUMN semester INT(11) NULL AFTER prodi");
    echo "Tabel mata_kuliah berhasil diubah.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
