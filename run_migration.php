<?php
$urls = [
    'http://localhost/andrian/migrate_checkpoint.php',
    'http://localhost/migrate_checkpoint.php',
    'https://localhost/andrian/migrate_checkpoint.php'
];

$ctx = stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false]]);
foreach ($urls as $url) {
    echo "Trying $url ...\n";
    $result = @file_get_contents($url, false, $ctx);
    if ($result !== false) {
        echo "Success: $result\n";
        break;
    }
}
