<?php
require 'config/database.php';

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$schema = [];

foreach ($tables as $table) {
    if (in_array($table, ['pengajuan_kredit', 'analisa_5c', 'analisa_neraca', 'approval_kredit', 'users', 'audit_log', 'master_pejabat'])) {
        $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $schema[$table] = $cols;
    }
}

echo json_encode($schema, JSON_PRETTY_PRINT);
