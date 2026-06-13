<?php
require 'bank-kredit/config/database.php';

echo "
╔═══════════════════════════════════════════════════════════════╗
║  VERIFICATION: Assessment Integration ke Detail.php           ║
║  Test Date: " . date('Y-m-d H:i:s') . "                                  ║
╚═══════════════════════════════════════════════════════════════╝

";

$id = 7; // Test dengan pengajuan ID 7

echo "📋 Testing Pengajuan ID: {$id}\n";
echo str_repeat("-", 65) . "\n\n";

// Check 1: Pengajuan Exists
$stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur FROM pengajuan_kredit WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$pengajuan = $stmt->fetch();

if ($pengajuan) {
    echo "✅ Pengajuan: {$pengajuan['nama_debitur']} (ID: {$id})\n";
} else {
    echo "❌ Pengajuan tidak ditemukan\n";
    exit(1);
}

// Check 2: Assessment Exists
$stmt = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
$stmt->execute([$id]);
$assessment = $stmt->fetch();

if ($assessment) {
    echo "✅ Assessment ditemukan\n";
    
    // Parse Checklist
    $checklist = json_decode($assessment['checklist_data'], true) ?: [];
    $comply_count = 0;
    $not_comply_count = 0;
    
    foreach ($checklist as $item) {
        if ($item['val'] === 'comply') $comply_count++;
        elseif ($item['val'] === 'not_comply') $not_comply_count++;
    }
    
    echo "   • Checklist Items: " . count($checklist) . "\n";
    if ($comply_count > 0 || $not_comply_count > 0) {
        echo "   • Comply: {$comply_count}, Not Comply: {$not_comply_count}\n";
    }
    
    // Check Fasilitas
    $fasilitas = json_decode($assessment['fasilitas_existing'], true) ?: [];
    echo "   • Fasilitas Existing: " . count($fasilitas) . "\n";
    
    // Check Kesimpulan & Rekomendasi
    echo "   • Kesimpulan: " . (strlen($assessment['kesimpulan'] ?? '') > 0 ? "Ada" : "Kosong") . "\n";
    echo "   • Rekomendasi: " . (strlen($assessment['rekomendasi'] ?? '') > 0 ? "Ada" : "Kosong") . "\n";
    echo "   • Marketing: " . (strlen($assessment['marketing'] ?? '') > 0 ? htmlspecialchars($assessment['marketing']) : "-") . "\n";
    echo "   • Tanggal: " . date('d F Y', strtotime($assessment['tanggal_assessment'])) . "\n";
} else {
    echo "⚠️  Assessment belum ada untuk pengajuan ini\n";
    echo "   (Ini normal jika belum ada assessment dari kepatuhan)\n";
}

echo "\n";

// Check 3: Detail.php File
echo "📄 File Verification:\n";
echo str_repeat("-", 65) . "\n";

$detail_file = file_get_contents('bank-kredit/detail.php');

$checks = [
    'Assessment Query' => strpos($detail_file, 'SELECT * FROM assessment_kepatuhan'),
    'Assessment Variable' => strpos($detail_file, '$assessment'),
    'Checklist Display' => strpos($detail_file, 'json_decode($assessment[\'checklist_data\']'),
    'Hasil Assessment Section' => strpos($detail_file, 'Hasil Assessment Kepatuhan'),
    'Kesimpulan Display' => strpos($detail_file, 'Kesimpulan'),
];

$all_good = true;
foreach ($checks as $check_name => $result) {
    if ($result !== false) {
        echo "✅ {$check_name}: FOUND\n";
    } else {
        echo "❌ {$check_name}: NOT FOUND\n";
        $all_good = false;
    }
}

echo "\n" . str_repeat("=", 65) . "\n";

if ($all_good) {
    echo "✅ ALL CHECKS PASSED - Integration is complete!\n\n";
    echo "📌 Next Steps:\n";
    echo "   1. Open browser: http://localhost/andrian/bank-kredit/detail.php?id={$id}\n";
    echo "   2. Verify \"Hasil Assessment Kepatuhan\" section appears\n";
    echo "   3. Check if assessment data displays correctly\n\n";
    
    if ($assessment) {
        echo "📊 Sample Data Preview:\n";
        if (!empty($checklist)) {
            echo "   ✓ Checklist items will be shown\n";
        }
        if ($comply_count > 0) {
            echo "   ✓ Summary: {$comply_count} items complied\n";
        }
        if (!empty($fasilitas)) {
            echo "   ✓ Fasilitas existing will be displayed\n";
        }
    }
} else {
    echo "❌ Some checks failed - Review integration\n";
}

echo str_repeat("=", 65) . "\n";
?>
