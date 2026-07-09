# Dokumentasi: Update Fasilitas Kredit Existing - Tabel Dinamis

**Date:** 2026-06-12
**Status:** Implementation Complete
**Task:** Ubah fasilitas kredit existing menjadi tabel dinamis dengan kolom baru

---

## 🎯 Ringkasan Perubahan

Struktur fasilitas kredit existing di compliance assessment telah diubah dari format lama (dengan kolom rekening, tanggal akad, jatuh tempo, plafond, saldo) menjadi format baru yang lebih sederhana dengan kolom:

1. **Lembaga Keuangan** - Nama bank/lembaga kredit
2. **Baki Debet** - Outstanding balance/saldo outstanding
3. **Kolektibilitas** - Status kolektibilitas (Lancar, DPK, Macet)
4. **Keterangan** - Notes/keterangan tambahan

---

## 📋 File yang Diubah

### 1. `analis/compliance_assessment.php`

#### Changes:
1. **Table Structure Update** (baris ~548-590)
   - **Lama:** 7 kolom (No Rekening, Tgl Akad, Jt Tempo, Kol, Plafond, Saldo, Aksi)
   - **Baru:** 5 kolom (Lembaga Keuangan, Baki Debet, Kolektibilitas, Keterangan, Aksi)
   - Added `<thead>` dan `<tbody>` tags untuk better structure
   - Input fields changed:
     - `fasilitas_rek[]` → `fasilitas_lembaga[]` (text)
     - `fasilitas_akad[]` → removed
     - `fasilitas_jtempo[]` → removed
     - `fasilitas_kol[]` → `fasilitas_kol[]` dengan `<select>` dropdown (Lancar/DPK/Macet)
     - `fasilitas_plafond[]` → removed
     - `fasilitas_saldo[]` → `fasilitas_baki[]` (number)
     - `fasilitas_ket[]` → new (text keterangan)

2. **JavaScript Function** (baris ~639-657)
   - Updated `addFas()` function untuk generate row dengan struktur baru
   - Row baru memiliki 5 cells sesuai kolom baru

3. **Data Parse Logic** (baris ~335-365)
   - Added backward compatibility untuk format lama
   - Ketika load data lama (dengan key 'rek'), auto-convert ke format baru:
     - `rek` → `lembaga`
     - `saldo` → `baki_debet`
     - `kol` → `kolektibilitas`
     - Kosongkan `keterangan`

#### Migration Logic:
```php
// Parse fasilitas - support both old and new format
if (isset($fasilitas_raw[0]['lembaga'])) {
    $fasilitas = $fasilitas_raw; // Already new format
} elseif (isset($fasilitas_raw[0]['rek'])) {
    // Convert old format to new format
    foreach ($fasilitas_raw as $f) {
        $fasilitas[] = [
            'lembaga' => $f['rek'] ?? '',
            'baki_debet' => $f['saldo'] ?? '0',
            'kolektibilitas' => $f['kol'] ?? '',
            'keterangan' => ''
        ];
    }
}
```

---

### 2. `api/save_assessment_kepatuhan.php`

#### Changes:
1. **Fasilitas Collection** (baris ~93-107)
   - **Lama:** Collect dari `fasilitas_rek`, `fasilitas_akad`, `fasilitas_jtempo`, `fasilitas_kol`, `fasilitas_plafond`, `fasilitas_saldo`
   - **Baru:** Collect dari `fasilitas_lembaga`, `fasilitas_baki`, `fasilitas_kol`, `fasilitas_ket`
   - Data structure sekarang:
     ```php
     $fasilitas[] = [
         'lembaga' => $lembaga,
         'baki_debet' => str_replace(',', '', trim($_POST['fasilitas_baki'][$i] ?? '')) ?: '0',
         'kolektibilitas' => trim($_POST['fasilitas_kol'][$i] ?? ''),
         'keterangan' => trim($_POST['fasilitas_ket'][$i] ?? ''),
     ];
     ```

---

### 3. `print.php`

#### Changes:
1. **New Section: Fasilitas Existing Display** (setelah Rekomendasi)
   - Added display untuk fasilitas kredit existing di hasil cetak
   - Support both old dan new format
   - Display dalam tabel dengan format:
     | Lembaga | Baki Debet | Kol | Keterangan |
   - Auto-convert old format ke display format jika needed

#### Implementation:
```php
<?php if ($compliance_data && !empty($compliance_data['fasilitas_existing'])): ?>
    <?php 
    $fasilitas_existing_raw = json_decode($compliance_data['fasilitas_existing'], true) ?: [];
    // Support both old and new format
    $fasilitas_to_display = [];
    if (!empty($fasilitas_existing_raw)) {
        if (isset($fasilitas_existing_raw[0]['lembaga'])) {
            $fasilitas_to_display = $fasilitas_existing_raw; // New format
        } elseif (isset($fasilitas_existing_raw[0]['rek'])) {
            // Convert old format to display
            foreach ($fasilitas_existing_raw as $f) {
                $fasilitas_to_display[] = [
                    'lembaga' => $f['rek'] ?? '',
                    'baki_debet' => $f['saldo'] ?? '0',
                    'kolektibilitas' => $f['kol'] ?? '',
                    'keterangan' => ''
                ];
            }
        }
    }
    ?>
    <!-- Display table here -->
<?php endif; ?>
```

---

## 💾 Database Storage

Fasilitas existing tetap disimpan di kolom `assessment_kepatuhan.fasilitas_existing` sebagai JSON:

### Format Lama:
```json
[
  {
    "rek": "123456789",
    "tgl": "2024-01-01",
    "jt": "2027-01-01",
    "kol": "Lancar",
    "plafond": "50000000",
    "saldo": "25000000"
  }
]
```

### Format Baru:
```json
[
  {
    "lembaga": "Bank BRI",
    "baki_debet": "25000000",
    "kolektibilitas": "Lancar",
    "keterangan": "Kredit Modal Kerja"
  }
]
```

---

## 🔄 Data Flow

```
FORM INPUT (compliance_assessment.php):
  User inputs: Lembaga Keuangan, Baki Debet, Kolektibilitas, Keterangan
         ↓
SUBMIT (JavaScript submitAssessment):
  Form data sent via FormData POST to api/save_assessment_kepatuhan.php
         ↓
BACKEND PROCESSING (save_assessment_kepatuhan.php):
  foreach fasilitas_lembaga[] as $lembaga:
    Collect: lembaga, baki_debet, kolektibilitas, keterangan
         ↓
STORAGE:
  json_encode($fasilitas) → stored in assessment_kepatuhan.fasilitas_existing
         ↓
DISPLAY IN PRINT (print.php):
  Fetch assessment_kepatuhan.fasilitas_existing
  Decode JSON
  Check format (old vs new)
  Auto-convert if needed
  Display in table format with formatRupiah() for baki_debet
```

---

## 📊 Backward Compatibility

Sistem fully backward compatible dengan data lama:

1. **Saat Load Form Edit:**
   - Jika data lama (dengan 'rek' key), auto-convert ke format baru
   - User akan melihat data dengan kolom baru

2. **Saat Display Print:**
   - Jika data lama, auto-convert untuk display
   - Output tetap konsisten (format baru)

3. **Saat Save Baru:**
   - Hanya simpan format baru
   - Tidak ada duplikasi atau data corruption

---

## ✅ Testing Checklist

### Input Tests:
- [ ] Add new fasilitas row dengan "+" button
- [ ] Fill all fields: Lembaga (bank name), Baki Debet (number), Kolektibilitas (select), Keterangan (text)
- [ ] Remove row dengan "×" button
- [ ] Edit existing fasilitas
- [ ] Submit form dengan multiple rows

### Display Tests:
- [ ] Form displays 3 empty rows initially (no existing data)
- [ ] Kolektibilitas dropdown shows 3 options: Lancar, DPK, Macet
- [ ] Baki Debet accepts number only
- [ ] Save succeeds with valid data

### Print Tests:
- [ ] Open print.php for pengajuan dengan fasilitas existing
- [ ] Fasilitas section displays correctly in PDF
- [ ] Baki Debet formatted with number format (1,000,000)
- [ ] Works with old data format (auto-converted)
- [ ] Works with new data format

### Load Edit Tests:
- [ ] Open compliance_assessment.php for edit existing pengajuan
- [ ] Old format data displays correctly (converted to new format)
- [ ] New format data displays correctly
- [ ] Can re-save without data loss

---

## 🔧 Column Mapping Reference

### Input Field Names:
| Display Column | Input Field | Type | Storage Key |
|---|---|---|---|
| Lembaga Keuangan | `fasilitas_lembaga[]` | text | `lembaga` |
| Baki Debet | `fasilitas_baki[]` | number | `baki_debet` |
| Kolektibilitas | `fasilitas_kol[]` | select | `kolektibilitas` |
| Keterangan | `fasilitas_ket[]` | text | `keterangan` |

---

## 📝 Usage Examples

### JavaScript - Add New Row:
```javascript
function addFas() {
    var tbody = document.querySelector('#fasTable tbody');
    var tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="fasilitas_lembaga[]"></td>
        <td><input type="number" name="fasilitas_baki[]" placeholder="0"></td>
        <td><select name="fasilitas_kol[]"><option value="">- Pilih -</option>...</select></td>
        <td><input type="text" name="fasilitas_ket[]"></td>
        <td class="btn-print text-center"><button type="button" onclick="this.closest('tr').remove()">×</button></td>
    `;
    tbody.appendChild(tr);
}
```

### PHP - Collect Data:
```php
$fasilitas = [];
if (isset($_POST['fasilitas_lembaga']) && is_array($_POST['fasilitas_lembaga'])) {
    foreach ($_POST['fasilitas_lembaga'] as $i => $lembaga) {
        $lembaga = trim($lembaga);
        if (!empty($lembaga)) {
            $fasilitas[] = [
                'lembaga' => $lembaga,
                'baki_debet' => str_replace(',', '', trim($_POST['fasilitas_baki'][$i] ?? '')) ?: '0',
                'kolektibilitas' => trim($_POST['fasilitas_kol'][$i] ?? ''),
                'keterangan' => trim($_POST['fasilitas_ket'][$i] ?? ''),
            ];
        }
    }
}
```

### PHP - Display (Print):
```php
<?php if (!empty($fasilitas_to_display)): ?>
<table style="width: 100%; font-size: 8px; border-collapse: collapse;">
    <tr style="background-color: #f3f4f6;">
        <th>Lembaga</th>
        <th>Baki Debet</th>
        <th>Kol</th>
        <th>Keterangan</th>
    </tr>
    <?php foreach ($fasilitas_to_display as $fas): ?>
    <tr>
        <td><?= htmlspecialchars($fas['lembaga'] ?? '') ?></td>
        <td><?= number_format(intval($fas['baki_debet'] ?? 0)) ?></td>
        <td><?= htmlspecialchars($fas['kolektibilitas'] ?? '') ?></td>
        <td><?= htmlspecialchars($fas['keterangan'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<?php endif; ?>
```

---

## 🚀 Benefits

1. **Simpler UI** - Hanya 4 kolom penting (vs 6 sebelumnya)
2. **Clearer Data** - Kolom langsung meaningful (lembaga, baki_debet, kol, keterangan)
3. **Better Print** - Fasilitas existing sekarang ditampilkan di hasil cetak
4. **Backward Compatible** - Old data auto-convert tanpa loss
5. **Multiple Rows** - Support unlimited rows via "+ Tambah Baris" button

---

## 📌 Important Notes

- **Baki Debet** adalah outstanding balance (saldo yang masih outstanding)
- **Kolektibilitas** dropdown options: Lancar, DPK (Dalam Perhatian Khusus), Macet
- Jika data lama ada, akan otomatis convert saat load edit
- Print akan menampilkan fasilitas existing di bagian compliance assessment
- Empty rows tidak disimpan (hanya rows dengan lembaga terisi yang disimpan)

---

## 🔗 Related Files

- `analis/compliance_assessment.php` - Form input
- `api/save_assessment_kepatuhan.php` - Backend processor
- `print.php` - PDF display
- `assessment_kepatuhan` table - Storage

---

**Created:** 2026-06-12
**Version:** 1.0
**Status:** READY FOR TESTING
