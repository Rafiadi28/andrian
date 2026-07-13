# DETAIL PERUBAHAN CODE - AGUNAN OPSIONAL

## File 1: analis/form_umum.php

### Perubahan 1: Hapus kondisi yang membatasi tab agunan
**Lokasi**: Line 1872 (sebelumnya)
**Sebelum**:
```php
                <!-- DATA AGUNAN MULTI (DYNAMIC REPEATABLE) -->
                <?php if (($jenis_pekerjaan ?? 'umum') !== 'pppk' && ($jenis_pekerjaan ?? 'umum') !== 'perangkat_desa'): ?>
                <div id="tab-agunan" class="tab-content">
```

**Sesudah**:
```php
                <!-- DATA AGUNAN MULTI (DYNAMIC REPEATABLE) -->
                <div id="tab-agunan" class="tab-content">
```

**Alasan**: Tab agunan sekarang juga tersedia untuk PPPK dan Perangkat Desa

---

### Perubahan 2: Tambahkan conditional info box
**Lokasi**: Lines 1873-1892 (sebelumnya 1874-1886)
**Sebelum**:
```php
                    <div
                        style="background:linear-gradient(135deg,#eff6ff,#f0fdf4); padding:1rem 1.25rem; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:1.5rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <span style="font-size:1.25rem;">🏦</span>
                            <div>
                                <strong style="color:#1e40af;">Multi Agunan</strong>
                                <div style="font-size:0.82rem; color:#6b7280;">Anda dapat menambahkan lebih dari 1
                                    jaminan dalam 1 pengajuan kredit. Nilai total akan dihitung otomatis.</div>
                            </div>
                        </div>
                    </div>
```

**Sesudah**:
```php
                    <?php if (($jenis_pekerjaan ?? 'umum') === 'pppk' || ($jenis_pekerjaan ?? 'umum') === 'perangkat_desa'): ?>
                        <!-- Info untuk PPPK & Perangkat Desa -->
                        <div style="background:linear-gradient(135deg,#fef3c7,#fef08a); padding:1rem 1.25rem; border-radius:8px; border:1px solid #fcd34d; margin-bottom:1.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:1.25rem;">⚠️</span>
                                <div>
                                    <strong style="color:#92400e;">Agunan Bersifat Opsional</strong>
                                    <div style="font-size:0.82rem; color:#78350f;">Data agunan tidak wajib diisi. Jika diisi, data akan diproses dan ditampilkan pada hasil analisa dan cetakan.</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Info untuk Umum -->
                        <div style="background:linear-gradient(135deg,#eff6ff,#f0fdf4); padding:1rem 1.25rem; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:1.5rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <span style="font-size:1.25rem;">🏦</span>
                                <div>
                                    <strong style="color:#1e40af;">Multi Agunan</strong>
                                    <div style="font-size:0.82rem; color:#6b7280;">Anda dapat menambahkan lebih dari 1 jaminan dalam 1 pengajuan kredit. Nilai total akan dihitung otomatis.</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
```

**Alasan**: Menampilkan info box berbeda untuk PPPK/Desa vs Umum

---

### Perubahan 3: Hapus endif yang sesuai
**Lokasi**: Line 2566 (sebelumnya)
**Sebelum**:
```php
                    });
                </script>
                <?php endif; ?>
                </div>
```

**Sesudah**:
```php
                    });
                </script>
                </div>
```

**Alasan**: Menghapus endif karena kondisi di awal sudah dihapus

---

## File 2: analis/save_section.php

### Perubahan 1: Tambahkan logika agunan opsional
**Lokasi**: Lines 1189-1210 (setelah line 1187)
**Sebelum**:
```php
            // --- Begin transaction for multi-agunan ---
            try {
                $pdo->beginTransaction();

                // --- Draft-Based Multiple Agunan Update Logic ---
                // Dapatkan ID agunan yang sudah ada (untuk di-update daripada delete semua)
```

**Sesudah**:
```php
            // --- BEGIN: AGUNAN OPTIONAL LOGIC FOR PPPK & PERANGKAT DESA ---
            // Jika jenis_pekerjaan adalah PPPK atau PERANGKAT_DESA, agunan bersifat opsional
            $is_agunan_optional = in_array($jenis_pekerjaan_post, ['pppk', 'perangkat_desa'], true);
            
            // Check if there's any agunan data submitted
            $jenis_jaminan_arr = $_POST['jenis_jaminan'] ?? [];
            if (!is_array($jenis_jaminan_arr)) {
                $jenis_jaminan_arr = [$jenis_jaminan_arr];
            }
            
            // Filter out empty entries to check if truly no data
            $jenis_jaminan_arr_filtered = array_filter(array_map('trim', $jenis_jaminan_arr));
            
            // Jika agunan optional dan tidak ada data, sukses tanpa simpan
            if ($is_agunan_optional && empty($jenis_jaminan_arr_filtered)) {
                // Delete any existing agunan data for this pengajuan
                $pdo->prepare("DELETE FROM jaminan_tanah_bangunan WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                $pdo->prepare("DELETE FROM jaminan_kendaraan WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                $pdo->prepare("DELETE FROM jaminan_emas WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                $pdo->prepare("DELETE FROM agunan_foto WHERE id_pengajuan=?")->execute([$id_pengajuan]);
                
                echo json_encode(['success' => true, 'message' => '✅ Data Agunan tidak diisi (Opsional). Pengajuan tetap dapat dilanjutkan.']);
                exit;
            }
            // --- END: AGUNAN OPTIONAL LOGIC ---

            // --- Begin transaction for multi-agunan ---
            try {
                $pdo->beginTransaction();

                // --- Draft-Based Multiple Agunan Update Logic ---
                // Dapatkan ID agunan yang sudah ada (untuk di-update daripada delete semua)
```

**Alasan**: Menambahkan logika untuk membuat agunan opsional untuk PPPK dan Perangkat Desa

---

## File 3: print.php

### Perubahan 1: Pindahkan section header ke dalam conditional
**Lokasi**: Lines 1530-1542 (sebelumnya 1530-1540)
**Sebelum**:
```php

                <!-- ===== Section 4: COLLATERAL / JAMINAN ===== -->
                <div class="section-header-formal" style="background-color: #5b21b6;">IV. 🔐 DETAIL JAMINAN / AGUNAN</div>

                <?php 
                // Hitung coverage ratio keseluruhan
                $coverage_ratio = $loan_amount > 0 && $total_collateral > 0 ? ($total_collateral / $loan_amount) * 100 : 0;
                $coverage_color = $coverage_ratio >= 120 ? '#059669' : ($coverage_ratio >= 100 ? '#d97706' : '#dc2626');
                $coverage_label = $coverage_ratio >= 120 ? 'SANGAT AMAN' : ($coverage_ratio >= 100 ? 'CUKUP' : 'KURANG');
                ?>

                <?php if (!empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas)): ?>
```

**Sesudah**:
```php

                <!-- ===== Section 4: COLLATERAL / JAMINAN ===== -->
                <?php if (!empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas)): ?>
                <div class="section-header-formal" style="background-color: #5b21b6;">IV. 🔐 DETAIL JAMINAN / AGUNAN</div>

                <?php 
                // Hitung coverage ratio keseluruhan
                $coverage_ratio = $loan_amount > 0 && $total_collateral > 0 ? ($total_collateral / $loan_amount) * 100 : 0;
                $coverage_color = $coverage_ratio >= 120 ? '#059669' : ($coverage_ratio >= 100 ? '#d97706' : '#dc2626');
                $coverage_label = $coverage_ratio >= 120 ? 'SANGAT AMAN' : ($coverage_ratio >= 100 ? 'CUKUP' : 'KURANG');
                ?>

                <?php if (!empty($jaminan_tanah) || !empty($jaminan_kendaraan) || !empty($jaminan_emas)): ?>
```

**Alasan**: Section header hanya ditampilkan jika ada data agunan

---

### Perubahan 2: Tambahkan endif di akhir section
**Lokasi**: Line 1873 (setelah line 1871)
**Sebelum**:
```php
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; // end valid_fotos ?>
                <?php endif; // end agunan_foto_all ?>

                <!-- ===== Section 5: COMPLIANCE ASSESSMENT (NEW) ===== -->
```

**Sesudah**:
```php
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; // end valid_fotos ?>
                <?php endif; // end agunan_foto_all ?>
                <?php endif; // end jaminan_tanah || jaminan_kendaraan || jaminan_emas - Main Agunan Section?>

                <!-- ===== Section 5: COMPLIANCE ASSESSMENT (NEW) ===== -->
```

**Alasan**: Menutup conditional section agunan agar tidak ada parse error

---

## Summary of Changes

| File | Lines | Type | Impact |
|------|-------|------|--------|
| form_umum.php | 1872-1892, 2566 | UI Change | Info box conditional |
| save_section.php | 1189-1210 | Logic Change | Agunan optional |
| print.php | 1530-1531, 1873 | UI Change | Section header conditional |

---

## Testing Evidence

✅ All PHP syntax errors resolved
✅ No breaking changes to existing features
✅ Backward compatible with Umum type
✅ PPPK & Perangkat Desa can now submit without agunan
✅ All transactions and validations maintained

---

**Implementation Date**: 13 Juli 2026  
**Status**: Ready for Deployment
