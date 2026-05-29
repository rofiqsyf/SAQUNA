<?php
require 'autoload.php';
$db = Config\Database::getConnection();

try {
    $stmt = $db->query("SHOW COLUMNS FROM mata_kuliah");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    
    echo "\n\n";
    $stmt2 = $db->query("SHOW COLUMNS FROM dosen_matakuliah");
    print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
