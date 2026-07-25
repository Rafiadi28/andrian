<?php
/**
 * Logic Validation Test - Test routing fallback logic tanpa database
 */

echo "=== TESTING APPROVAL ROUTING LOGIC ===\n\n";

// Simulasi fallback mapping
$fallback_map = [
    'kasubag_analis' => 'analis',
    'kabag_kredit' => 'kadiv_bisnis',
    'kadiv_bisnis' => 'direktur_utama',
    'direktur_utama' => null  // Final - no fallback
];

$escalation_chain = ['kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];

echo "Fallback Mapping:\n";
foreach ($fallback_map as $k => $v) {
    echo "  $k -> " . ($v ?? 'FINAL') . "\n";
}

echo "\nEscalation Chain: " . implode(' -> ', $escalation_chain) . " -> [BLOCK]\n";

echo "\n--- Test Case 1: All active (no cuti) ---\n";
$active_roles = ['kasubag_analis', 'kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
$preferred = 'kasubag_analis';
if (in_array($preferred, $active_roles)) {
    echo "✓ Preferred role '$preferred' is active -> Route to: $preferred\n";
} else {
    echo "✗ Preferred role not active, would fall back\n";
}

echo "\n--- Test Case 2: kasubag_analis is cuti ---\n";
$active_roles = ['kabag_kredit', 'kadiv_bisnis', 'direktur_utama'];
$preferred = 'kasubag_analis';
$fallback = $fallback_map[$preferred];
echo "Preferred: $preferred (NOT in active_roles)\n";
echo "Fallback mapping: $preferred -> " . ($fallback ?? 'NULL') . "\n";
if ($fallback && in_array($fallback, $active_roles)) {
    echo "✓ Fallback role '$fallback' is active -> Route to: $fallback\n";
} else {
    echo "✗ Fallback not available\n";
}

echo "\n--- Test Case 3: kabag_kredit is cuti ---\n";
$active_roles = ['kasubag_analis', 'kadiv_bisnis', 'direktur_utama'];
$preferred = 'kasubag_analis';
echo "Preferred: $preferred\n";
if (in_array($preferred, $active_roles)) {
    echo "✓ Preferred role '$preferred' is active -> Route to: $preferred\n";
}

echo "\n--- Test Case 4: Chain resolution when kasubag_analis -> kadiv_bisnis cuti ---\n";
$active_roles = ['analis', 'direktur_utama']; // kasubag_analis & kabag_kredit & kadiv_bisnis = cuti
$preferred = 'kasubag_analis';
echo "Preferred: $preferred (NOT active)\n";
$fallback = $fallback_map[$preferred];
echo "Step 1: Try fallback -> $fallback (analis)\n";
if (in_array($fallback, $active_roles)) {
    echo "✓ Fallback '$fallback' is active -> Route to: $fallback\n";
} else {
    echo "✗ Fallback not active either\n";
}

echo "\n--- Test Case 5: All except direktur_utama are cuti ---\n";
$active_roles = ['direktur_utama'];
$preferred = 'kasubag_analis';
echo "Preferred: $preferred (NOT active)\n";
$current = $preferred;
while ($current) {
    $fallback = $fallback_map[$current] ?? null;
    if (!$fallback) {
        echo "✓ No more fallbacks, escalate to direktur_utama (final chain): direktur_utama\n";
        break;
    }
    if (in_array($fallback, $active_roles)) {
        echo "✓ Fallback '$fallback' is active -> Route to: $fallback\n";
        break;
    }
    echo "  Fallback '$fallback' not active, continue chain...\n";
    $current = $fallback;
}

echo "\n=== AUTO-SKIP AUDIT RECORD LOGIC ===\n\n";
echo "When fallback is used (preferred != resolved):\n";
echo "  INSERT approval_kredit (id_pengajuan, level_approval, keputusan, catatan, is_auto_skip)\n";
echo "  VALUES (?, preferred_role, 'eskalasi_otomatis', 'Auto-skip: routed to resolved_role', 1)\n\n";

echo "Example:\n";
echo "  INSERT approval_kredit (12, 'kasubag_analis', 'eskalasi_otomatis', 'Auto-skip: routed to analis', 1)\n";
echo "  ^ This records that kasubag_analis level was auto-skipped\n";

echo "\n=== PREFILL LOGIC TEST ===\n\n";
echo "Client-side prefill normalization:\n";
echo "  1. Check if job_type matches perangkat desa variants\n";
echo "  2. If match, prefill fields:\n";
echo "     - desk_jabatan, desk_tgl_mulai, desk_penghasilan_tetap, desk_penghasilan_lain\n";
echo "     - desk_total_penghasilan, desk_usia, desk_masa_kerja, desk_kapasitas_bayar\n\n";

$test_variants = [
    'perangkat desa',
    'Perangkat Desa',
    'PERANGKAT DESA',
    'perangkat_desa',
    'Perangkat_Desa',
    'perangkat-desa'
];

echo "Variants that should match:\n";
foreach ($test_variants as $v) {
    // Normalize: lowercase, replace underscore/dash with space
    $norm = strtolower(preg_replace('/[-_]/', ' ', $v));
    $matches = (strpos($norm, 'perangkat desa') !== false);
    echo "  '" . $v . "' -> normalized: '" . $norm . "' -> " . ($matches ? "✓ MATCH" : "✗ NO MATCH") . "\n";
}

echo "\n=== ALL LOGIC TESTS COMPLETE ===\n";
?>
