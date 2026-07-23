<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/analis_prefill_data.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 17;
$prefill_bundle = analisLoadPrefillBundle($pdo, $id);
$json = json_encode($prefill_bundle, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

header('Content-Type: application/json');
echo $json;
