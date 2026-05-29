<?php
require_once __DIR__ . '/autoload.php';
$db = Config\Database::getConnection();
$stmt = $db->query('SHOW TABLES');
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "TABLE: " . $row[0] . "\n";
    $desc = $db->query("DESCRIBE " . $row[0]);
    while ($col = $desc->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $col['Field'] . " - " . $col['Type'] . "\n";
    }
}
