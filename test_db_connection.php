<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $host = 'localhost';
    $user = 'root';
    $pass = 'rian123';
    $db   = 'bank_kredit_db';
    
    echo "Mencoba koneksi ke DB: $db@$host\n";
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Database berhasil terkoneksi\n";
    
    // Check approval_routing helper
    echo "\n--- Checking approval_routing.php ---\n";
    require_once __DIR__ . '/includes/approval_routing.php';
    echo "✓ approval_routing.php loaded\n";
    
    // Test resolve_next_active_role
    echo "\n--- Testing resolve_next_active_role ---\n";
    $result = resolve_next_active_role($pdo, 'kasubag_analis');
    echo "kasubag_analis => " . ($result ?? 'NULL') . "\n";
    
    // Test get_active_users_for_role
    echo "\n--- Testing get_active_users_for_role ---\n";
    $users = get_active_users_for_role($pdo, 'kasubag_analis');
    echo "Active users untuk kasubag_analis: " . count($users) . "\n";
    foreach ($users as $u) {
        echo "  - {$u['id_user']}: {$u['nama']}\n";
    }
    
    echo "\n✓ Semua test OK\n";
    
} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    die(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    die(1);
}
?>
