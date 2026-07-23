<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config/database.php';
require 'includes/analis_prefill_data.php';

$id = 18;
$bundle = analisLoadPrefillBundle($pdo, $id);
header('Content-Type: application/json');
echo json_encode($bundle);
