<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT id_pengajuan, nama_debitur, nik, jenis_pekerjaan, status_pengajuan FROM pengajuan_kredit WHERE jenis_pekerjaan = 'perangkat_desa' ORDER BY id_pengajuan DESC LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
