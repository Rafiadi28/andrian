<?php
require_once __DIR__ . '/config/database.php';
try {
    $stmt = $pdo->query("SHOW CREATE TABLE pengajuan_kredit");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
