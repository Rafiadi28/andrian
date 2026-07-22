<?php
$pdo = new PDO('mysql:host=localhost;dbname=bank_kredit_db;charset=utf8mb4','root','rian123');
$stmt = $pdo->prepare('SELECT id_pengajuan,foto_usaha FROM pengajuan_kredit WHERE id_pengajuan=16');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
var_export($row);
