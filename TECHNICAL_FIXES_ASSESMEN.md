# 🔧 TECHNICAL FIXES - SISTEM ASSESMEN KEPATUHAN

## Priority 1: CRITICAL FIXES (Esta Minggu)

---

## FIX 1: Selesaikan Duplikat Compliance Table

### Pilihan A: Use assessment_kepatuhan (RECOMMENDED)

**File: bank-kredit/analis/memo_internal.php**

Ganti baris 53:
```php
// SEBELUM:
$stmt = $pdo->prepare("INSERT INTO compliance_assessment 
    (nomor_surat, tanggal, nama_debitur, no_ktp, nama_pasangan, jenis_debitur, 
     jenis_kredit, alamat, pekerjaan, plafon, tujuan_penggunaan, jangka_waktu, 
     suku_bunga, marketing, analis, checklist_data, fasilitas_existing, 
     catatan_compliance, kesimpulan, rekomendasi, created_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// SESUDAH (gunakan assessment_kepatuhan):
$stmt = $pdo->prepare("INSERT INTO assessment_kepatuhan 
    (id_pengajuan, id_user, tanggal_assessment, checklist_data, fasilitas_existing, 
     catatan_existing, kesimpulan, rekomendasi, marketing)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
```

Ganti baris 69 (execute):
```php
// SEBELUM:
$stmt->execute([
    $_POST['nomor_surat'],
    $_POST['tanggal'],
    $_POST['nama_debitur'],
    $_POST['no_ktp'],
    $_POST['nama_pasangan'],
    $_POST['jenis_debitur'],
    $_POST['jenis_kredit'],
    $_POST['alamat'],
    $_POST['pekerjaan'],
    str_replace(',', '', $_POST['plafon']),
    $_POST['tujuan_penggunaan'],
    $_POST['jangka_waktu'],
    $_POST['suku_bunga'],
    $_POST['marketing'],
    $_POST['analis'],
    json_encode($checklist),
    json_encode($fasilitas),
    json_encode($notes),
    $_POST['kesimpulan'],
    $_POST['rekomendasi'],
    $_SESSION['user_id']
]);

// SESUDAH:
// Perlu mapping ID pengajuan dari form - PERLU TAMBAH FIELD HIDDEN!
// <input type="hidden" name="id_pengajuan" value="<?= $id_pengajuan ?>">

$stmt->execute([
    $_POST['id_pengajuan'],  // Baru
    $_SESSION['user_id'],
    $_POST['tanggal'] ?? date('Y-m-d'),
    json_encode($checklist),
    json_encode($fasilitas),
    json_encode($notes),  // Renamed to catatan_existing
    $_POST['kesimpulan'],
    $_POST['rekomendasi'],
    $_POST['marketing']
]);
```

**TAMBAHAN:** Tambah hidden field di `memo_internal.php` form:
```html
<input type="hidden" name="id_pengajuan" value="<?= $_GET['id'] ?? 0 ?>">
```

### Pilihan B: Create Ulang Tabel compliance_assessment

Jika ingin keep terpisah, jalankan SQL:
```sql
CREATE TABLE compliance_assessment (
    id_assessment INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_user INT,
    nomor_surat VARCHAR(50),
    tanggal DATE,
    nama_debitur VARCHAR(100),
    no_ktp VARCHAR(20),
    nama_pasangan VARCHAR(100),
    jenis_debitur VARCHAR(50),
    jenis_kredit VARCHAR(50),
    alamat TEXT,
    pekerjaan VARCHAR(100),
    plafon DECIMAL(15,2),
    tujuan_penggunaan TEXT,
    jangka_waktu INT,
    suku_bunga DECIMAL(5,2),
    marketing VARCHAR(255),
    analis VARCHAR(100),
    checklist_data JSON,
    fasilitas_existing JSON,
    catatan_compliance JSON,
    kesimpulan TEXT,
    rekomendasi TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE,
    FOREIGN KEY (id_user) REFERENCES users(id_user) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id_user) ON DELETE RESTRICT,
    INDEX idx_pengajuan (id_pengajuan),
    INDEX idx_user (id_user),
    INDEX idx_created (created_at)
);
```

---

## FIX 2: Add Missing Foreign Keys

Jalankan SQL berikut di database:

```sql
-- Add FK untuk id_user di assessment_kepatuhan
ALTER TABLE assessment_kepatuhan 
ADD CONSTRAINT fk_assessment_user 
FOREIGN KEY (id_user) REFERENCES users(id_user) 
ON DELETE RESTRICT 
ON UPDATE CASCADE;

-- Add/Update FK untuk id_pengajuan (jika belum ada)
ALTER TABLE assessment_kepatuhan 
ADD CONSTRAINT fk_assessment_pengajuan 
FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_kredit(id_pengajuan) 
ON DELETE CASCADE 
ON UPDATE CASCADE;

-- Add recommended indexes untuk performa
ALTER TABLE assessment_kepatuhan 
ADD INDEX idx_pengajuan (id_pengajuan),
ADD INDEX idx_user_created (id_user, created_at),
ADD INDEX idx_created (created_at);
```

**Verification:**
```sql
-- Check Foreign Keys
SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE TABLE_NAME = 'assessment_kepatuhan' AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Harusnya ada 2 FK:
-- fk_assessment_user: assessment_kepatuhan.id_user → users.id_user
-- fk_assessment_pengajuan: assessment_kepatuhan.id_pengajuan → pengajuan_kredit.id_pengajuan
```

---

## Priority 2: SECURITY FIXES

---

## FIX 3: XSS Vulnerability fix

**File: bank-kredit/kepatuhan/assesmen.php**

Pada function `checklistRow()` (sekitar line 305):

```php
// SEBELUM (VULNERABLE):
function checklistRow($checklist, $no, $label, $key, $default_ket = '')
{
    $na = cVal($checklist, $key, 'na');
    $nc = cVal($checklist, $key, 'not_comply');
    $cm = cVal($checklist, $key, 'comply', true);
    $ket = cKet($checklist, $key, $default_ket);

    echo "<tr>
        <td>$no</td>
        <td>$label</td>
        <td style='text-align:center;'><input type='radio' name='check[$key]' value='na' $na></td>
        <td style='text-align:center;'><input type='radio' name='check[$key]' value='not_comply' $nc></td>
        <td style='text-align:center;'><input type='radio' name='check[$key]' value='comply' $cm></td>
        <td><input type='text' name='ket[$key]' value='$ket'></td>
    </tr>";
}

// SESUDAH (SAFE):
function checklistRow($checklist, $no, $label, $key, $default_ket = '')
{
    $na = cVal($checklist, $key, 'na');
    $nc = cVal($checklist, $key, 'not_comply');
    $cm = cVal($checklist, $key, 'comply', true);
    $ket = htmlspecialchars($checklist[$key]['ket'] ?? $default_ket, ENT_QUOTES, 'UTF-8');
    
    $no_safe = htmlspecialchars($no, ENT_QUOTES, 'UTF-8');
    $label_safe = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $key_safe = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

    echo "<tr>
        <td>$no_safe</td>
        <td>$label_safe</td>
        <td style='text-align:center;'><input type='radio' name='check[$key_safe]' value='na' $na></td>
        <td style='text-align:center;'><input type='radio' name='check[$key_safe]' value='not_comply' $nc></td>
        <td style='text-align:center;'><input type='radio' name='check[$key_safe]' value='comply' $cm></td>
        <td><input type='text' name='ket[$key_safe]' value='$ket'></td>
    </tr>";
}
```

---

## FIX 4: Input Validation

**File: bank-kredit/kepatuhan/assesmen.php**

Replace POST handler (around line 15-70) untuk tambah validation:

```php
// SEBELUM (Line 42-55):
$fasilitas = [];
if (isset($_POST['fasilitas_rek'])) {
    foreach ($_POST['fasilitas_rek'] as $i => $rek) {
        if (!empty($rek)) {
            $fasilitas[] = [
                'rek' => $rek,
                'tgl' => $_POST['fasilitas_akad'][$i],
                'jt' => $_POST['fasilitas_jtempo'][$i],
                'kol' => $_POST['fasilitas_kol'][$i],
                'plafond' => $_POST['fasilitas_plafond'][$i],
                'saldo' => $_POST['fasilitas_saldo'][$i],
            ];
        }
    }
}

// SESUDAH (dengan validation):
$fasilitas = [];
if (isset($_POST['fasilitas_rek'])) {
    foreach ($_POST['fasilitas_rek'] as $i => $rek) {
        if (empty($rek)) continue;
        
        // Validate rekening (10-20 digit)
        if (!preg_match('/^\d{10,20}$/', trim($rek))) continue;
        
        // Validate dates
        $akad = $_POST['fasilitas_akad'][$i] ?? '';
        $jtempo = $_POST['fasilitas_jtempo'][$i] ?? '';
        if (!strtotime($akad) || !strtotime($jtempo)) continue;
        
        // Validate numeric fields
        $plafond = $_POST['fasilitas_plafond'][$i] ?? 0;
        $saldo = $_POST['fasilitas_saldo'][$i] ?? 0;
        if (!is_numeric($plafond) || !is_numeric($saldo)) continue;
        if ($plafond < 0 || $saldo < 0) continue;
        
        $fasilitas[] = [
            'rek' => htmlspecialchars(trim($rek)),
            'tgl' => htmlspecialchars($akad),
            'jt' => htmlspecialchars($jtempo),
            'kol' => htmlspecialchars($_POST['fasilitas_kol'][$i] ?? ''),
            'plafond' => floatval($plafond),
            'saldo' => floatval($saldo),
        ];
    }
}
```

---

## Priority 3: ENHANCEMENT FIXES

---

## FIX 5: Fix Hardcoded Memo Number

**File: bank-kredit/kepatuhan/assesmen.php**

Tambah function di atas (`<?php` tag):

```php
function generateMemoNumber() {
    // Format: 137/SEQUENCE/GRG/MONTH/YEAR
    // Example: 137/00001/GRG/04/2026
    
    $year = date('Y');
    $month = date('m');
    
    // Get sequence number dari assessment records
    $stmt = $GLOBALS['pdo']->prepare("
        SELECT IFNULL(MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(tanggal_assessment, '/', 2), '/', -1) AS UNSIGNED)), 0) + 1 as seq
        FROM assessment_kepatuhan 
        WHERE YEAR(tanggal_assessment) = ?
    ");
    $stmt->execute([$year]);
    $seq = $stmt->fetchColumn() ?? 1;
    
    return sprintf("137/%05d/GRG/%s/%s", $seq, $month, $year);
}
```

Update form (line 358):

```php
// SEBELUM:
<input type="text" value="137/60557/GRG/IX/2025" readonly>

// SESUDAH:
<input type="text" name="nomor_surat" value="<?= generateMemoNumber() ?>" readonly>
```

---

## FIX 6: Update Timestamp Properly

**File: bank-kredit/kepatuhan/assesmen.php**

Update baris 66-72 (UPDATE query):

```php
// SEBELUM:
$up = $pdo->prepare("UPDATE assessment_kepatuhan SET checklist_data=?, fasilitas_existing=?, catatan_existing=?, kesimpulan=?, rekomendasi=?, marketing=? WHERE id_pengajuan=?");

// SESUDAH:
$up = $pdo->prepare("UPDATE assessment_kepatuhan 
    SET checklist_data=?, fasilitas_existing=?, catatan_existing=?, 
        kesimpulan=?, rekomendasi=?, marketing=?, updated_at=NOW() 
    WHERE id_pengajuan=?");
```

---

## FIX 7: Fix Button Label Typo

**File: bank-kredit/kepatuhan/assesmen.php**

Baris 716:

```php
// SEBELUM:
<button type="button" class="btn-print" onclick="addFas()" 
    style="margin-top:8px; padding:4px 8px; font-size:12px; cursor:pointer;">
    + Tambah Baris Baris
</button>

// SESUDAH:
<button type="button" class="btn-print" onclick="addFas()" 
    style="margin-top:8px; padding:4px 8px; font-size:12px; cursor:pointer;">
    + Tambah Baris
</button>
```

---

## FIX 8: Add Permission Check (Optional)

**File: bank-kredit/kepatuhan/assesmen.php**

Di line 250 (setelah fetch assessment):

```php
$sa = $pdo->prepare("SELECT * FROM assessment_kepatuhan WHERE id_pengajuan = ?");
$sa->execute([$id]);
if($a = $sa->fetch()) {
    $has_assessment = true;
    
    // TAMBAH: Permission check (optional tapi recommended)
    // $can_edit = ($_SESSION['user_id'] === $a['id_user']) || isAdmin();
    // if (!$can_edit) {
    //     echo "<p style='color:red;'><strong>Anda tidak berhak mengakses assessment ini.</strong></p>";
    //     exit;
    // }
    
    $checklist = json_decode($a['checklist_data'], true) ?: [];
    // ... rest of code
}
```

---

## Testing Checklist

Setelah apply fixes, test berikut:

- [ ] **Duplikat Table Fix**
  - [ ] Analis bisa buka memo_internal.php
  - [ ] Analis bisa submit form
  - [ ] Data masuk ke assessment_kepatuhan
  - [ ] Data visible di kepatuhan/assesmen.php

- [ ] **Foreign Key Fix**
  - [ ] Run DESCRIBE assessment_kepatuhan (check FK ada)
  - [ ] Try delete user → bisa/tidak sesuai FK?
  - [ ] Try delete pengajuan → assessment ikut delete

- [ ] **XSS Fix**
  - [ ] Input dengan `<script>alert('xss')</script>` di form
  - [ ] Script tidak execute (rendered as text)
  - [ ] Nilai terlihat escaped di HTML

- [ ] **Validation Fix**
  - [ ] Masukkan nomor rekening invalid → skip/error
  - [ ] Masukkan plafon negatif → skip/error
  - [ ] Masukkan date invalid → skip/error
  - [ ] Valid data tersimpan dengan baik

- [ ] **Memo Number & Timestamp**
  - [ ] Nomor surat auto-generated sesuai format
  - [ ] Tanggal bisa diedit (jika requirement)
  - [ ] Updated_at berubah saat UPDATE
  - [ ] Created_at tetap sesuai creation time

---

## IMPLEMENTATION ORDER

```
Week 1 (CRITICAL):
  Day 1: FIX 1 + FIX 2 (Duplikat table + FK)
  Day 2-3: Test & QA
  Day 4: FIX 3 + FIX 4 (XSS + Validation)
  Day 5: Test & Deploy

Week 2 (MAJOR):
  Day 1-2: FIX 5 + FIX 6 + FIX 7 (UI/UX fixes)
  Day 3-4: Test & Deploy
  Day 5: FIX 8 (Permission, optional)

Week 3+:
  Monitoring & feedback
  Additional enhancement requests
```

---

**Status:** Ready untuk Implementation  
**Priority:** P1 fixes ASAP sebelum go-live
