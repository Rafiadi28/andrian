<?php
$content = file_get_contents('d:/laragon/www/andrian/bank-kredit/detail.php');

$pattern_repayment = '/(\<\?php if \(\$canRepaymentOverride\):\s*\?\>.*?\<\?php endif; \?\>)/s';
$pattern_jaminan = '/(            \<\!\-\- IV\. MULTI AGUNAN SECTION \-\-\>.*?            \<\!\-\- III\. Struktur Kredit \& IV\. Data Agunan \-\-\>)/s';
$pattern_neraca = '/(            \<\!\-\- III\. NERACA \-\-\>\s*\<\?php if \(\$neraca\):\s*\?\>.*?\<\?php endif; \?\>)/s';
$pattern_struktur = '/(            \<\!\-\- V\. STRUKTUR KREDIT \-\-\>\s*\<div style="margin-bottom: 2rem;"\>.*?\<\/table\>\s*\<\/div\>)/s';

preg_match($pattern_jaminan, $content, $m_jaminan);
preg_match($pattern_neraca, $content, $m_neraca);
preg_match($pattern_struktur, $content, $m_struktur);

$jaminan_block = $m_jaminan[1] ?? '';
$neraca_block = $m_neraca[1] ?? '';
$struktur_block = $m_struktur[1] ?? '';

if ($jaminan_block && $neraca_block && $struktur_block) {
    // Replace with placeholders
    $content = str_replace($jaminan_block, '[[JAMINAN_BLOCK]]', $content);
    $content = str_replace($neraca_block, '[[NERACA_BLOCK]]', $content);
    $content = str_replace($struktur_block, '[[STRUKTUR_BLOCK]]', $content);

    preg_match($pattern_repayment, $content, $m_repayment);
    $repayment_block = $m_repayment[1] ?? '';

    $new_order_blocks = $repayment_block . "\n\n" . $struktur_block . "\n\n" . $neraca_block . "\n\n" . $jaminan_block;
    
    // Check if repayment block exists (some users might not have it displayed, but it's hardcoded with if in detail.php)
    if ($repayment_block) {
        $content = str_replace($repayment_block, $new_order_blocks, $content);
    } else {
        // Fallback: put them after I. Data Pribadi grid
        $pattern_grid = '/(\<\/div\>\s*\<\/div\>\s*\<\?php if \(\$canRepaymentOverride\):\s*\?\>)/s';
        preg_match($pattern_grid, $content, $m_grid);
        if(!empty($m_grid[1])) {
            $content = str_replace($m_grid[1], "</div>\n</div>\n\n" . $struktur_block . "\n\n" . $neraca_block . "\n\n" . $jaminan_block . "\n\n" . '<?php if ($canRepaymentOverride): ?>', $content);
        }
    }

    $content = str_replace('[[JAMINAN_BLOCK]]', '', $content);
    $content = str_replace('[[NERACA_BLOCK]]', '', $content);
    $content = str_replace('[[STRUKTUR_BLOCK]]', '', $content);

    $content = str_replace('III. Neraca (Posisi Keuangan', 'IV. Neraca (Posisi Keuangan', $content);
    $content = str_replace('V. Struktur Kredit', 'III. Struktur Kredit', $content);
    $content = str_replace('IV. Analisa Jaminan', 'V. Analisa Jaminan', $content);

    file_put_contents('d:/laragon/www/andrian/bank-kredit/detail.php', $content);
    echo "Reordered successfully.\n";
} else {
    echo "Failed to match blocks.\n";
}
