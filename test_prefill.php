<?php
require 'config/database.php';
require 'includes/analis_prefill_data.php';
$stmt = $pdo->prepare("SELECT id_pengajuan FROM pengajuan_kredit WHERE jenis_pekerjaan = 'perangkat_desa' ORDER BY id_pengajuan DESC LIMIT 1");
$stmt->execute();
$id = $stmt->fetchColumn();
echo "Checking ID: $id\n";
$data = analisLoadPrefillBundle($pdo, $id);
echo json_encode($data, JSON_PRETTY_PRINT);
