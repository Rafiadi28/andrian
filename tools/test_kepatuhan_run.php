<?php
// Read-only diagnostic for kepatuhan routing and approval history
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/approval_routing.php';

$id = (int)($argv[1] ?? 21);

echo "== Diagnostic read-only for pengajuan ID: {$id} ==\n";

// Fetch pengajuan basic
$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, jumlah_kredit, posisi_saat_ini, status_pengajuan FROM pengajuan_kredit WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$pk = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$pk) {
    echo "Pengajuan not found (ID={$id}).\n";
    exit(0);
}

echo "Debitur: " . ($pk['nama_debitur'] ?? '-') . "\n";
echo "Jumlah Kredit: " . number_format((float)($pk['jumlah_kredit'] ?? 0),0,',','.') . "\n";
echo "Posisi saat ini: " . ($pk['posisi_saat_ini'] ?? '-') . "\n";
echo "Status pengajuan: " . ($pk['status_pengajuan'] ?? '-') . "\n\n";

// Show approval history
echo "-- Approval history (all records) --\n";
$stmt = $pdo->prepare("SELECT id_approval, level_approval, keputusan, id_user, tanggal_approval, catatan, is_auto_skip, nama_approver FROM approval_kredit LEFT JOIN users u ON approval_kredit.id_user = u.id_user ON TRUE WHERE id_pengajuan = ? ORDER BY id_approval ASC");
// Note: join above uses a harmless LEFT JOIN to get user names when available
try {
    $stmt = $pdo->prepare("SELECT id_approval, level_approval, keputusan, id_user, tanggal_approval, catatan, is_auto_skip FROM approval_kredit WHERE id_pengajuan = ? ORDER BY id_approval ASC");
    $stmt->execute([$id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        echo "(no approval records)\n\n";
    } else {
        foreach ($rows as $r) {
            echo "[{$r['id_approval']}] role={$r['level_approval']} keputusan={$r['keputusan']} user={$r['id_user']} tanggal={$r['tanggal_approval']} auto_skip={$r['is_auto_skip']}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Failed to read approval_kredit: " . $e->getMessage() . "\n";
}

// Determine next target from kepatuhan role
$jumlah = (float)($pk['jumlah_kredit'] ?? 0);
$next = findNextTarget('kepatuhan', $pdo, $jumlah);
echo "-- findNextTarget('kepatuhan') => role: " . json_encode($next) . "\n\n";

// Resolve final active role if next is not selesai
if (!empty($next['role']) && $next['role'] !== 'selesai') {
    $resolved = resolve_next_active_role($pdo, $next['role']);
    echo "-- resolve_next_active_role for preferred '{$next['role']}' => " . var_export($resolved, true) . "\n\n";
}

// Show master pejabat statuses
$roles = ['analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama','kepatuhan'];
echo "-- master_pejabat status --\n";
$stmt = $pdo->prepare("SELECT role, nama, jabatan, status FROM master_pejabat WHERE role IN ('analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama','kepatuhan') ORDER BY FIELD(role, 'analis','kasubag_analis','kabag_kredit','kadiv_bisnis','direktur_utama','kepatuhan')");
$stmt->execute();
$mp = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($mp as $m) {
    echo "role={$m['role']} nama={$m['nama']} jabatan={$m['jabatan']} status={$m['status']}\n";
}

// Active user counts per role
echo "\n-- active users count per role --\n";
foreach ($roles as $r) {
    $s = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND status_jabatan = 'aktif'");
    $s->execute([$r]);
    $c = (int)$s->fetchColumn();
    echo "{$r}: {$c}\n";
}

echo "\nDiagnostic complete.\n";
