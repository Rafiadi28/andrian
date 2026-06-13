# Logika Pemblokiran Approval Berdasarkan Compliance Assessment

## Ringkasan Fitur

Sistem sekarang memblokir proses pengajuan (approval) jika Dept. Kepatuhan belum menyelesaikan assessment form hasil analisis. Fitur ini mencegah:
- **Kasubag Analis** - dari melanjutkan ke kabag kredit
- **Kabag Kredit** - dari melanjutkan ke kadiv bisnis  
- **Kadiv Bisnis** - dari melanjutkan ke direktur utama
- **Direktur Utama** - dari memberi keputusan final

## Alur Kerja (Workflow)

### Sebelum Fitur:
```
Analis Submit 
   ↓
Kasubag Analis ← BISA langsung approve tanpa compliance assessment
   ↓
Kabag Kredit ← BISA langsung approve
   ↓
...
```

### Sesudah Fitur:
```
Analis Submit 
   ↓
Compliance Assessment (MANDATORY)
   ├─ Check: Apakah assessment_kepatuhan ada dan lengkap?
   └─ Kondisi Lengkap: checklist_data != NULL AND kesimpulan != NULL
   ↓
Kasubag Analis ← HANYA BISA approve jika compliance assessment lengkap
   ↓
Kabag Kredit ← HANYA BISA approve jika compliance assessment lengkap
   ↓
Kadiv Bisnis ← HANYA BISA approve jika compliance assessment lengkap
   ↓
Direktur Utama ← HANYA BISA approve jika compliance assessment lengkap
```

## Implementasi Teknis

### 1. Fungsi Helper: `checkComplianceAssessmentStatus()` (functions.php)

```php
checkComplianceAssessmentStatus($pdo, $id_pengajuan)
```

**Keluaran:**
```php
[
    'exists' => bool,           // Assessment record ada atau tidak
    'is_complete' => bool,      // Assessment lengkap (checklist + kesimpulan)
    'message' => string         // Pesan user-friendly
]
```

**Kondisi Lengkap:**
- `assessment_kepatuhan` record ada untuk `id_pengajuan` ini
- `checklist_data` != NULL dan tidak kosong (JSON array dengan data)
- `kesimpulan` != NULL dan tidak kosong

**Pesan Error:**
- Jika assessment belum ada: `"Kepatuhan belum melakukan assessment untuk pengajuan ini."`
- Jika assessment kosong: `"Assessment kepatuhan masih kosong atau belum lengkap..."`

---

### 2. Modifikasi: `processApproval()` (functions.php)

**Tambahan Validasi:**
- **Trigger:** Ketika `$role` adalah salah satu dari: `kasubag_analis`, `kabag_kredit`, `kadiv_bisnis`, `direktur_utama`
- **Kondisi:** Keputusan adalah `'setuju'` (approve)
- **Action:** Jalankan `checkComplianceAssessmentStatus()` sebelum proses approval

**Jika Compliance Assessment BELUM LENGKAP:**
1. **Transaksi dibatalkan** (rollback)
2. **Error response:**
   ```
   🔐 Tidak dapat melanjutkan approval: Kepatuhan belum melakukan assessment...
   
   Silakan minta Dept. Kepatuhan untuk menyelesaikan assessment terlebih dahulu.
   ```
3. **Approval record TIDAK disimpan** - pengajuan tetap di position saat ini

**BYPASS:** Superadmin TIDAK terpengaruh oleh check ini (emergency override).

**Keputusan LAIN tidak diblokir:**
- `revisi` (kembalikan) - TETAP BISA (approver bisa minta revisi ke analis)
- `tolak` (reject) - TETAP BISA (approver bisa menolak)
- Hanya `setuju` (approve) yang diblokir

---

### 3. UI Indicators: `proses_template.php`

**Komponen Tambahan:**

#### A. Kolom "Compliance" di Tabel Proses
Menampilkan status compliance untuk setiap pengajuan:
- ✓ **Compliance OK** (hijau) - Assessment lengkap, BISA approve
- ⚠ **Compliance Partial** (kuning) - Assessment ada tapi incomplete
- ✗ **Waiting Compliance** (merah) - Belum ada assessment

#### B. Row Highlight
Baris pengajuan yang compliance belum lengkap di-highlight dengan background pink (#fff5f5) untuk visibilitas lebih tinggi.

#### C. Tombol "Proses"
- **NORMAL:** Tombol biru interaktif → klik untuk buka modal approval
- **BLOCKED:** Tombol abu-abu disabled
  - Text: `"Proses (Blokir)"`
  - Disabled state
  - Alert on click: `"⚠️ Pengajuan ini TIDAK BISA diproses sampai Dept. Kepatuhan menyelesaikan assessment..."`

---

## Tabel Database: assessment_kepatuhan

```sql
CREATE TABLE assessment_kepatuhan (
    id_assessment INT AUTO_INCREMENT PRIMARY KEY,
    id_pengajuan INT NOT NULL,
    id_user INT NOT NULL,
    tanggal_assessment DATE NOT NULL,
    
    -- Column yang di-check untuk "lengkap" status
    checklist_data JSON,          -- ← WAJIB tidak NULL
    kesimpulan TEXT,              -- ← WAJIB tidak NULL
    
    rekomendasi TEXT,
    marketing VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_assessment_pengajuan (id_pengajuan),
    CONSTRAINT fk_assessment_pengajuan FOREIGN KEY (id_pengajuan) 
        REFERENCES pengajuan_kredit(id_pengajuan) ON DELETE CASCADE
);
```

---

## Keputusan yang TIDAK Diblokir

### 1. Analis
Analis TIDAK terpengaruh - dapat submit pengajuan tanpa compliance assessment (compliance akan mengisi nanti).

### 2. Kasubag Analis - Keputusan "Revisi"
```
Kasubag Analis: Approval = REVISI
↓
Pengajuan kembali ke Analis (tidak diblokir)
```
Approver tetap bisa minta analis untuk revisi, walaupun compliance belum isi assessment. Compliance bisa isi assessment kemudian.

### 3. Kasubag Analis - Keputusan "Tolak"
```
Kasubag Analis: Approval = TOLAK
↓
Pengajuan ditolak, kembali ke Analis (tidak diblokir)
```
Approver tetap bisa menolak pengajuan.

---

## Contoh Skenario

### ✅ SUKSES - Compliance Lengkap

```
1. Analis submit pengajuan ID=5
   → posisi_saat_ini = 'kasubag_analis'

2. Kepatuhan isi assessment untuk ID=5
   → assessment_kepatuhan record created
   → checklist_data filled
   → kesimpulan filled

3. Kasubag Analis klik "Proses" → "SETUJUI"
   ↓ checkComplianceAssessmentStatus() di-jalankan
   ↓ Status: is_complete = TRUE
   ↓ APPROVAL DIPROSES ✓
   → posisi_saat_ini = 'kabag_kredit'
   → approval_kredit record created with keputusan='setuju'
```

### ❌ GAGAL - Compliance Belum Isi

```
1. Analis submit pengajuan ID=6
   → posisi_saat_ini = 'kasubag_analis'

2. Kepatuhan BELUM isi assessment untuk ID=6
   → assessment_kepatuhan table kosong untuk ID=6

3. Kasubag Analis klik "Proses" → "SETUJUI"
   ↓ checkComplianceAssessmentStatus() di-jalankan
   ↓ Status: is_complete = FALSE
   ↓ APPROVAL DITOLAK ✗
   → Error: "🔐 Tidak dapat melanjutkan approval..."
   → Transaction ROLLBACK
   → Pengajuan TETAP di posisi_saat_ini = 'kasubag_analis'
   → approval_kredit TIDAK ada record baru
```

### ⚠️ PARTIAL - Assessment Ada Tapi Kosong

```
1. Kepatuhan bikin assessment untuk ID=7 tapi belum isi checklist
   → assessment_kepatuhan created
   → checklist_data = NULL (atau empty JSON)
   → kesimpulan = NULL

2. Kasubag Analis klik "Proses" → "SETUJUI"
   ↓ Status: is_complete = FALSE (checklist atau kesimpulan kosong)
   ↓ APPROVAL DITOLAK ✗
   → Error: "Assessment kepatuhan masih kosong atau belum lengkap..."
   → Pengajuan tetap di antrian kasubag_analis
```

---

## File yang Dimodifikasi

1. **includes/functions.php**
   - ✅ Tambah: `checkComplianceAssessmentStatus()` function
   - ✅ Modifikasi: `processApproval()` - tambah compliance check

2. **includes/proses_template.php**
   - ✅ Tambah: Query untuk load compliance status
   - ✅ Tambah: Fungsi `getComplianceStatusBadge()`
   - ✅ Modifikasi: Tabel - tambah kolom "Compliance"
   - ✅ Modifikasi: Tombol "Proses" - conditional disable

---

## Sistem yang TIDAK Diubah

- ✅ Approval workflow routing (findNextTarget)
- ✅ Revision/rejection logic (tetap seperti sebelumnya)
- ✅ Audit log system
- ✅ Auto-skip inactive staff
- ✅ Database schema (hanya menggunakan kolom yang ada)
- ✅ Session/authentication system
- ✅ Proses analis input/edit form

---

## Testing Checklist

- [ ] **Compliance lengkap** → Kasubag analis BISA approve
- [ ] **Compliance kosong** → Kasubag analis TIDAK BISA approve (error message muncul)
- [ ] **Compliance partial** → Kasubag analis TIDAK BISA approve
- [ ] **Revisi** → TETAP BISA di-trigger walaupun compliance belum lengkap
- [ ] **Tolak** → TETAP BISA di-trigger walaupun compliance belum lengkap
- [ ] **Superadmin** → TIDAK terpengaruh oleh check ini
- [ ] **UI Badge** → Menampilkan status compliance dengan benar
- [ ] **UI Tombol** → Disabled dengan benar saat compliance belum lengkap
- [ ] **Error Message** → User-friendly dan jelas

---

## Log/Error Messages

### Jika Compliance Belum Ada
```
🔐 Tidak dapat melanjutkan approval: Kepatuhan belum melakukan 
assessment untuk pengajuan ini.

Silakan minta Dept. Kepatuhan untuk menyelesaikan assessment 
terlebih dahulu.
```

### Jika Compliance Kosong
```
🔐 Tidak dapat melanjutkan approval: Assessment kepatuhan masih 
kosong atau belum lengkap. Silakan tunggu kepatuhan menyelesaikan 
assessment.

Silakan minta Dept. Kepatuhan untuk menyelesaikan assessment 
terlebih dahulu.
```

---

## Notes & Future Enhancement

1. **Email Notification** - Kirim notifikasi otomatis ke Kepatuhan jika ada pengajuan yang menunggu assessment
2. **Dashboard Widget** - Tambah widget di dashboard compliance menunjukkan "Pending Assessments"
3. **SLA Tracking** - Track berapa lama compliance butuh untuk isi assessment
4. **Batch Approval** - Jika ada multiple pengajuan, hanya yang compliance lengkap yang bisa di-approve

---

## Support & Questions

Untuk pertanyaan atau issue dengan logika ini, hubungi developer/admin sistem.
