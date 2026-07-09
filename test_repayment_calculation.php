<?php
require_once __DIR__ . '/helpers/credit_helper.php';

function run_test($name, $jenis, $context, $expected) {
    // Relying on fallback config (default policies) since $pdo is not instantiated globally
    $result = hitungRepaymentDariKonteks($jenis, $context);
    
    // Formatting currency
    $resultFormat = "Rp" . number_format($result, 0, ',', '.');
    $expectedFormat = "Rp" . number_format($expected, 0, ',', '.');
    
    if (abs($result - $expected) < 0.01) {
        echo "[\033[32mPASS\033[0m] $name\n";
        // echo "       Input: " . json_encode($context) . "\n";
        echo "       Hasil: $resultFormat (Sesuai dengan ekspektasi $expectedFormat)\n";
    } else {
        echo "[\033[31mFAIL\033[0m] $name\n";
        echo "       Expected: $expectedFormat\n";
        echo "       Actual:   $resultFormat\n";
    }
}

echo "\n============================================\n";
echo "    PENGUJIAN LOGIKA REPAYMENT CAPACITY\n";
echo "============================================\n";

// Skenario 1: Umum
run_test("Kredit Umum", "umum", ["net_cashflow" => 10000000], 7500000);

// Skenario 2: Perangkat
run_test("Kredit Perangkat", "perangkat_desa", ["gaji_bersih" => 8000000], 6000000);

// Skenario 3: PPPK
run_test("Kredit PPPK", "pppk", ["gaji_bersih" => 5000000], 4750000);

// Skenario 4: Emas / Kretamas
run_test("Kredit Emas", "kretamas", ["pendapatan" => 12000000, "gaji_bersih" => 0], 11400000);

// Skenario 5: Cashcol
run_test("Kredit Cashcol", "cashcolateral", ["pendapatan" => 4000000, "gaji_bersih" => 0], 3800000);

echo "============================================\n";
