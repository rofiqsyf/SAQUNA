<?php
require 'autoload.php';
$db = \Config\Database::getConnection();
$stmt = $db->query('DESCRIBE dosen_matakuliah');
print_r($stmt->fetchAll(\PDO::FETCH_ASSOC));
