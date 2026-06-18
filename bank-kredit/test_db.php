<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>🔍 Diagnosis VPS Bank Kredit</h2>";
echo "<hr>";

// 1. Cek PHP
echo "<h3>1. PHP</h3>";
echo "✅ PHP Version: " . phpversion() . "<br>";

// 2. Cek ekstensi yang dibutuhkan
echo "<h3>2. Ekstensi PHP</h3>";
$extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session', 'fileinfo'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ $ext <br>";
    } else {
        echo "❌ $ext — <b>BELUM AKTIF!</b><br>";
    }
}

// 3. Cek koneksi database
echo "<h3>3. Koneksi Database</h3>";

// Coba tanpa password dulu (Laragon default)
$passwords = ['', 'root', 'mysql'];
$connected = false;
$pdo = null;

foreach ($passwords as $tryPass) {
    try {
        $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", $tryPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✅ Koneksi MySQL berhasil dengan password: <b>'" . ($tryPass === '' ? '(kosong)' : $tryPass) . "'</b><br>";
        $connected = true;
        break;
    } catch (PDOException $e) {
        echo "❌ Gagal dengan password '$tryPass': " . $e->getMessage() . "<br>";
    }
}

if (!$connected) {
    echo "<br><b style='color:red'>⛔ Tidak bisa connect ke MySQL! Cek password MySQL Anda.</b><br>";
    echo "Caranya: buka Laragon → klik kanan MySQL → my.cnf → cek konfigurasi.<br>";
    exit;
}

// 4. Cek database bank_kredit_db
echo "<h3>4. Database bank_kredit_db</h3>";
$stmt = $pdo->query("SHOW DATABASES LIKE 'bank_kredit_db'");
if ($stmt->rowCount() > 0) {
    echo "✅ Database <b>bank_kredit_db</b> ditemukan<br>";
    
    $pdo->exec("USE bank_kredit_db");
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($tables) > 0) {
        echo "✅ Jumlah tabel: <b>" . count($tables) . "</b><br>";
        echo "<ul>";
        foreach ($tables as $t) {
            echo "<li>$t</li>";
        }
        echo "</ul>";
    } else {
        echo "❌ Database ada tapi <b>KOSONG</b> — belum import database.sql<br>";
    }
} else {
    echo "❌ Database <b>bank_kredit_db</b> BELUM ADA!<br>";
    echo "<br><b>Solusi:</b> Buat database baru via HeidiSQL dengan nama <code>bank_kredit_db</code>, lalu import file <code>database.sql</code><br>";
}

// 5. Cek file config
echo "<h3>5. Config File</h3>";
$configFile = __DIR__ . '/config/database.php';
if (file_exists($configFile)) {
    echo "✅ config/database.php ditemukan<br>";
} else {
    echo "❌ config/database.php TIDAK DITEMUKAN!<br>";
}

// 6. Cek folder permissions
echo "<h3>6. Folder Logs</h3>";
$logDir = __DIR__ . '/logs';
if (is_dir($logDir)) {
    echo "✅ Folder logs/ ada<br>";
    if (is_writable($logDir)) {
        echo "✅ Folder logs/ writable<br>";
    } else {
        echo "❌ Folder logs/ TIDAK writable<br>";
    }
} else {
    echo "⚠️ Folder logs/ belum ada (akan dibuat otomatis)<br>";
}

echo "<hr>";
echo "<h3>🎯 Rekomendasi Password untuk config/database.php:</h3>";
if ($connected) {
    $correctPass = $tryPass;
    echo "<pre style='background:#f0f0f0;padding:10px;'>";
    echo "\$host = 'localhost';\n";
    echo "\$user = 'root';\n";
    echo "\$pass = '$correctPass';\n";
    echo "\$db   = 'bank_kredit_db';\n";
    echo "</pre>";
}
?>
