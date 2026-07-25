<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/approval_routing.php';

function showResolution(PDO $pdo, $role) {
    echo "Testing role: $role\n";
    $resolved = resolve_next_active_role($pdo, $role);
    echo "Resolved to: " . ($resolved ?? 'NULL') . "\n";
    $users = get_active_users_for_role($pdo, $resolved ?? $role);
    echo "Active users (role: " . ($resolved ?? $role) . "):\n";
    foreach ($users as $u) {
        echo " - {$u['id_user']} : {$u['nama']}\n";
    }
    echo "----\n";
}

showResolution($pdo, 'kasubag_analis');
showResolution($pdo, 'kabag_kredit');
showResolution($pdo, 'kadiv_bisnis');
showResolution($pdo, 'direktur_utama');

echo "Test complete.\n";
