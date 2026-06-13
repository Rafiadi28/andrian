<?php
/**
 * Entri edit resmi: input.php?id=... (alias untuk konsistensi URL).
 */
require_once __DIR__ . '/../includes/functions.php';
requireSameRole('analis');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: input.php');
    exit;
}
header('Location: input.php?id=' . $id);
exit;
