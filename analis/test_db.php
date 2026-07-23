<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/analis_prefill_data.php';

$id_pengajuan = 18;
$bundle = analisLoadPrefillBundle($pdo, $id_pengajuan);
echo json_encode($bundle['pengajuan']);
