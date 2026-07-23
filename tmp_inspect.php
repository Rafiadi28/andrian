<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require 'config/database.php';
$stmt = $pdo->query('SELECT * FROM pengajuan_kredit WHERE id_pengajuan = 18');
print_r($stmt->fetch(PDO::FETCH_ASSOC));
