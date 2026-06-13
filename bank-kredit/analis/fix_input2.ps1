$file = "d:\laragon\www\andrian\bank-kredit\analis\input.php"
$lines = [System.IO.File]::ReadAllLines($file)
Write-Host "Total lines before: $($lines.Count)"

# Find the H. UJI KELAYAKAN section start and end
$startIdx = -1
$endIdx = -1
for ($i = 0; $i -lt $lines.Count; $i++) {
    if ($lines[$i] -match '<!-- H\. UJI KELAYAKAN') {
        $startIdx = $i
    }
    if ($startIdx -ge 0 -and $endIdx -lt 0 -and $i -gt $startIdx) {
        # Find the closing </div> of the section (the one after box_status_kelayakan)
        if ($lines[$i] -match '^\s*</div>\s*$' -and $lines[$i-1] -notmatch 'section-header') {
            # Check if previous content has box_status_kelayakan
            $block = ($lines[$startIdx..$i] -join "`n")
            if ($block -match 'box_status_kelayakan') {
                $endIdx = $i
                break
            }
        }
    }
}

Write-Host "Section H found at lines $($startIdx+1) to $($endIdx+1)"

if ($startIdx -ge 0 -and $endIdx -ge 0) {
    # Also remove the blank line before the section if exists
    if ($startIdx -gt 0 -and $lines[$startIdx - 1].Trim() -eq '') {
        $startIdx = $startIdx - 1
    }
    
    # Remove those lines
    $newLines = @()
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($i -lt $startIdx -or $i -gt $endIdx) {
            $newLines += $lines[$i]
        }
    }
    $lines = $newLines
    Write-Host "Removed H. UJI KELAYAKAN section ($($endIdx - $startIdx + 1) lines)"
}

# Join back to content string
$content = $lines -join "`r`n"

# Re-letter: I -> H
$content = $content.Replace('<!-- I. KESIMPULAN ANALISA -->', '<!-- H. KESIMPULAN ANALISA -->')
$content = $content.Replace('>I. KESIMPULAN ANALISA USAHA<', '>H. KESIMPULAN ANALISA USAHA<')

# Remove angsuranDiajukan input reading from calcUsaha
$content = $content.Replace("                        let angsuranDiajukan = parseFloat(document.querySelector('[name=angsuran_diajukan]').value) || 0;`r`n", "")

# Remove H. Uji Kelayakan JS block (disp_rc_for_uji, disp_angsuran_recap, box_status_kelayakan)
# This block spans from "// H. Uji Kelayakan" to just before "// I. Kesimpulan"
$ujiStart = "                        // H. Uji Kelayakan"
$ujiContent = @"
                        // H. Uji Kelayakan
                        document.getElementById('disp_rc_for_uji').textContent = formatRupiah(rc);
                        document.getElementById('disp_angsuran_recap').textContent = formatRupiah(angsuranDiajukan);

                        let boxStatus = document.getElementById('box_status_kelayakan');
                        if (omzet <= 0 && angsuranDiajukan <= 0) {
                            boxStatus.style.background = '#f1f5f9';
                            boxStatus.style.color = '#64748b';
                            boxStatus.innerHTML = '— Masukkan data untuk melihat status kelayakan —';
                        } else if (rc >= angsuranDiajukan && angsuranDiajukan > 0) {
                            boxStatus.style.background = '#dcfce7';
                            boxStatus.style.color = '#166534';
                            boxStatus.innerHTML = '✅ LAYAK — Repayment Capacity (' + formatRupiah(rc) + ') ≥ Angsuran (' + formatRupiah(angsuranDiajukan) + ')';
                        } else {
                            boxStatus.style.background = '#fee2e2';
                            boxStatus.style.color = '#991b1b';
                            boxStatus.innerHTML = '❌ TIDAK LAYAK — Repayment Capacity (' + formatRupiah(rc) + ') < Angsuran (' + formatRupiah(angsuranDiajukan) + ')';
                        }
"@

$ujiContentCRLF = $ujiContent.Replace("`n", "`r`n")
if ($content.Contains($ujiContentCRLF)) {
    $content = $content.Replace($ujiContentCRLF, "")
    Write-Host "Removed H. Uji Kelayakan JS block (CRLF)"
} elseif ($content.Contains($ujiContent)) {
    $content = $content.Replace($ujiContent, "")
    Write-Host "Removed H. Uji Kelayakan JS block (LF)"
} else {
    Write-Host "WARNING: Could not find H. Uji Kelayakan JS block to remove"
    # Try partial removal
    if ($content.Contains("// H. Uji Kelayakan")) {
        Write-Host "Found comment, trying regex removal..."
        $content = [regex]::Replace($content, "(?s)\s*// H\. Uji Kelayakan.*?TIDAK LAYAK.*?\);[`r`n\s]*\}", "")
        # Actually let's be more careful
    }
}

# Re-letter: // I. Kesimpulan -> // H. Kesimpulan
$content = $content.Replace('// I. Kesimpulan Analisa', '// H. Kesimpulan Analisa')

# Remove the angsuranDiajukan references in Kesimpulan
# Since angsuranDiajukan was removed, we need to simplify the kesimpulan
# The kesimpulan still references angsuranDiajukan, statusLayak, selisih
# We need to remove those references since the input field is gone
# But wait - the user only removed the UI section, the angsuran_diajukan might still be computed
# from the struktur kredit section. Let me check if it's auto-filled from there.
# Looking at the code, there's: "// Auto-fill angsuran_diajukan in tab-usaha (monthly equivalent for RC)"
# So the input still exists conceptually. Actually NO - we removed the HTML input field too.
# So we need to remove angsuranDiajukan from the logic entirely, or keep a hidden field.

# Actually, the simplest approach: since the user removed the UJI KELAYAKAN UI,
# the Kesimpulan should show analysis without angsuran comparison.
# Let's simplify: remove angsuranDiajukan variable, statusLayak uses only RC,
# and simplify kesimpulan to just show the financial summary.

# Replace the kesimpulan logic
$oldKesimpulan = @"
                        let statusLayak = (rc >= angsuranDiajukan && angsuranDiajukan > 0) ? 'LAYAK' : 'TIDAK LAYAK';
                        let selisih = rc - angsuranDiajukan;
"@
$newKesimpulan = @"
                        let angsuranDiajukan = 0; // placeholder, uji kelayakan removed
                        let statusLayak = rc > 0 ? 'LAYAK' : 'TIDAK LAYAK';
                        let selisih = rc;
"@

$content = $content.Replace($oldKesimpulan.Replace("`n", "`r`n"), $newKesimpulan.Replace("`n", "`r`n"))
$content = $content.Replace($oldKesimpulan, $newKesimpulan)

# Remove duplicate Laba Usaha row in kesimpulan table (line 1182 area)
$dupRow = "                        html += '<tr style=""border-bottom:1px solid #e2e8f0;""><td style=""padding:8px; color:#6b7280;"">Laba Usaha</td><td style=""padding:8px; text-align:right; font-weight:600;"">' + formatRupiah(labaUsaha) + '</td></tr>';"
$content = $content.Replace($dupRow + "`r`n", "")
$content = $content.Replace($dupRow + "`n", "")

# Write
[System.IO.File]::WriteAllText($file, $content)

# Verify
$verify = [System.IO.File]::ReadAllText($file)
$newLineCount = ($verify.Split("`n")).Count
Write-Host "`nTotal lines after: $newLineCount"

if ($verify.Contains("UJI KELAYAKAN")) {
    Write-Host "WARNING: UJI KELAYAKAN still present!"
} else {
    Write-Host "SUCCESS: UJI KELAYAKAN removed!"
}

# Show section headers
$headerMatches = [regex]::Matches($verify, 'class="section-header">(.*?)<')
Write-Host "`nTab-usaha section headers:"
foreach ($m in $headerMatches) {
    $val = $m.Groups[1].Value
    if ($val -match '^[A-Z]\.' -and $val -match 'USAHA|PENDAPATAN|BIAYA|LABA|PENGELUARAN|CASHFLOW|REPAYMENT|KELAYAKAN|KESIMPULAN') {
        Write-Host "  - $val"
    }
}
