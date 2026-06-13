# LAPORAN HASIL TESTING PRE-RELEASE

**Proyek:** Bank Wonosobo — Sistem Pengajuan Kredit  
**Tanggal:** 12 Juni 2026  
**Environment:** Laragon (Development)  
**Tester:** QA Otomatis + Review Kode  
**Data Test:** Pengajuan #29 (Rp 250 juta), #30 (Rp 600 juta)

---

## Ringkasan Eksekutif

| Metrik | Nilai | Status |
|--------|-------|--------|
| Modul diuji | 11 | — |
| Test otomatis dijalankan | 55 | ✅ |
| Test otomatis PASS | 55 | ✅ |
| Test otomatis FAIL | 0 | ✅ |
| Bug ditemukan | 3 | 🔧 Diperbaiki |
| Pass rate otomatis | 100% | ✅ |
| Rekomendasi | **SIAP UAT MANUAL** | ⚠️ |

**Kesimpulan:** Semua test otomatis (logika bisnis, database, approval workflow, cetak) **LULUS**. Tiga bug infrastruktur/data test telah diperbaiki tanpa mengubah logika bisnis bank. Upload foto di browser perlu verifikasi manual UAT.

---

## Hasil per Modul (11 Area Uji)

### 1. Input Analis — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Entry point (dashboard, input, form, save) | ✅ | Semua file PHP valid (syntax OK) |
| API penyimpanan (`save_section.php`) | ✅ | Section pemohon, usaha, 6c, neraca, agunan tersedia |
| Validasi submit | ✅ | Wajib jumlah kredit & jangka waktu > 0 |

**UAT Manual:** Login `analis_test` / `password123` → buka pengajuan #29 → uji simpan tiap tab.

---

### 2. Persetujuan Kabag — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Queue approval (`kabag_kredit/proses.php`) | ✅ | File valid, terhubung `processApproval()` |
| Approve dengan catatan | ✅ | Workflow simulasi: kabag_kredit OK |
| Blokir tanpa kepatuhan | ✅ | Kabag ditolak jika assessment belum ada |

**UAT Manual:** Login `kabag_test` → antrian pengajuan → SETUJU/TOLAK/REVISI.

---

### 3. Persetujuan Kadiv — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Routing 250M (< 500M) | ✅ | Setelah kadiv → status `disetujui`, posisi `selesai` |
| Routing 600M (≥ 500M) | ✅ | Setelah kadiv → lanjut ke `direktur_utama` |
| Workflow end-to-end 250M | ✅ | kepatuhan → kasubag → kabag → kadiv → selesai |

**Catatan:** Gunakan folder `kadiv_bisnis/` (bukan `kadiv_kredit/`) — keduanya valid, role sama.

---

### 4. Persetujuan Direksi — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Threshold ≥ 500M | ✅ | `findNextTarget()` mengarahkan ke direktur_utama |
| Entry point (`direksi/proses.php`) | ✅ | Syntax valid |

**UAT Manual:** Login `direktur_test` → approve pengajuan #30 (600M) setelah melewati kadiv.

---

### 5. Kepatuhan — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Fetch data analis untuk review | ✅ | `fetch_data_analis_untuk_kepatuhan()` — **diperbaiki** |
| Data agunan di review | ✅ | Tanah + kendaraan tampil |
| Compliance blocking | ✅ | Kabag/Kadiv/Direksi diblokir tanpa assessment |
| Kolom `hasil_kepatuhan` | ✅ | Migrasi skema ditambahkan — **diperbaiki** |

**UAT Manual:** Login `kepatuhan_test` → assessment → COMPLY/NOT_COMPLY + validasi catatan.

---

### 6. Hasil Cetak — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Render `print.php?id=30` | ✅ | 52.598 bytes, tanpa fatal error |
| Section 6C/5C | ✅ | Character, Capacity, dll. tampil |
| Section agunan | ✅ | Jaminan tanah & kendaraan tampil |
| Master pejabat | ✅ | Data pejabat terisi di DB |

**UAT Manual:** Buka `print.php?id=29` → klik "Simpan sebagai PDF" → cek 2 halaman.

---

### 7. Upload Foto — ✅ PASS (otomatis) / ⚠️ UAT Manual

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Direktori `assets/uploads/` | ✅ | Writable |
| Validasi MIME (save_section) | ✅ | JPG/PNG/WEBP, max 2MB |
| Upload agunan multi (`agunan_foto`) | ✅ | Tabel & kolom `id` valid |

**UAT Manual:** Upload 3–5 foto JPG di tab agunan → cek preview & tampil di cetak.

---

### 8. Repayment — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| `hitungRepayment()` | ✅ | Penghasilan bersih × 0.75 |
| `hitung_repayment()` | ✅ | Gaji − (pengeluaran + angsuran) |
| `klasifikasi_repayment()` | ✅ | Sangat Layak / Layak / Cukup / Tidak Layak |

**Formula bisnis (tidak diubah):** Repayment Capacity = Net Cashflow × 75%

---

### 9. Scoring 5C (6C) — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Skala 1–5 per komponen | ✅ | Validasi menolak skor di luar range |
| Rata-rata & klasifikasi | ✅ | 4.67 → Sangat Baik |
| Status kelayakan | ✅ | ≥4.0 LAYAK, 3.0–3.9 LAYAK DGN CATATAN, <3.0 TIDAK LAYAK |
| Penyimpanan DB | ✅ | `total_score` = rata-rata (bukan jumlah) |

**Ketentuan bisnis (tidak diubah):** Skala 1–5, 6 komponen (termasuk Constraint).

---

### 10. Neraca — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Tabel `analisa_neraca` | ✅ | Ada |
| Balance equation pada data test | ✅ | Total aktiva = total pasiva (525 juta) |
| Kolom lengkap | ✅ | aktiva_kas, pasiva_modal, dll. |

**UAT Manual:** Input neraca → pastikan validasi aktiva = pasiva di form.

---

### 11. Agunan — ✅ PASS

| Aspek | Hasil | Keterangan |
|-------|-------|------------|
| Jaminan tanah (`alamat_agunan`, `jenis_surat`, dll.) | ✅ | Skema sesuai aplikasi |
| Jaminan kendaraan (`merk`, `no_polisi`, dll.) | ✅ | Skema sesuai aplikasi |
| Data test #29/#30 | ✅ | Tanah 500M + Kendaraan 150M |
| `insert_test_data.php` | ✅ | **Diperbaiki** — kolom lama (`lokasi`) diganti |

---

## Bug Ditemukan & Perbaikan

### BUG #1 — `insert_test_data.php` gagal insert agunan
- **Severity:** High
- **Gejala:** `Unknown column 'lokasi' in 'field list'`
- **Penyebab:** Script test memakai nama kolom lama yang tidak sesuai skema DB
- **Perbaikan:** Kolom disesuaikan (`alamat_agunan`, `jenis_surat`, `merk`, `no_polisi`, skor 6C skala 1–5)
- **Status:** ✅ Fixed

### BUG #2 — `fetch_data_analis_untuk_kepatuhan()` selalu return null
- **Severity:** High (modul Kepatuhan)
- **Gejala:** Halaman assessment kepatuhan tidak bisa load data analis
- **Penyebab:** Query `ORDER BY id_foto` — kolom tidak ada (nama kolom: `id`)
- **Perbaikan:** `helpers/credit_helper.php` baris 635: `id_foto` → `id`
- **Status:** ✅ Fixed (logika bisnis tidak diubah)

### BUG #3 — Kolom `hasil_kepatuhan` hilang di DB lama
- **Severity:** Medium
- **Gejala:** Save assessment kepatuhan gagal di environment yang DB-nya dibuat sebelum fitur Hasil Kepatuhan
- **Penyebab:** Migrasi skema tidak menambah kolom ke tabel existing
- **Perbaikan:** `schema_realtime_migrate.php` — ALTER TABLE idempotent untuk `hasil_kepatuhan` & `catatan_hasil`
- **Status:** ✅ Fixed

---

## Workflow Approval (Terverifikasi)

```
analis → kepatuhan → kasubag_analis → kabag_kredit → kadiv_bisnis → [direktur_utama jika ≥500M] → selesai
```

| Plafon | Level akhir | Test |
|--------|-------------|------|
| Rp 250 juta | Kadiv Bisnis | ✅ Simulasi end-to-end PASS |
| Rp 600 juta | Direktur Utama | ✅ Routing logic PASS |

---

## Kredensial Test

| Role | Username | Password |
|------|----------|----------|
| Analis | `analis_test` | `password123` |
| Kabag Kredit | `kabag_test` | `password123` |
| Kadiv Bisnis | `kadiv_test` | `password123` |
| Direktur | `direktur_test` | `password123` |
| Kepatuhan | `kepatuhan_test` | `password123` |

**URL:** http://localhost/andrian/bank-kredit/auth/login.php

---

## Cara Menjalankan Ulang Test

```bash
cd d:\laragon\www\andrian\bank-kredit
php insert_test_data.php      # Muat data test
php run_release_tests.php     # Jalankan 55 test otomatis
```

---

## Rekomendasi Release

| Item | Status |
|------|--------|
| Test otomatis (55/55) | ✅ Lulus |
| Bug kritis | ✅ 0 terbuka |
| UAT manual browser (foto, PDF, UI) | ⚠️ Perlu dilakukan |
| Backup database sebelum deploy | 📋 Wajib |

**Keputusan:** **CONDITIONAL GO** — Siap UAT manual & staging. Production setelah sign-off UAT browser (terutama upload foto & cetak PDF).

---

## Sign-Off

| Role | Nama | Tanggal | Status |
|------|------|---------|--------|
| QA Otomatis | run_release_tests.php | 12/06/2026 | ✅ Approved |
| QA Manual | _[diisi]_ | _[diisi]_ | ⬜ Pending |
| Business Owner | _[diisi]_ | _[diisi]_ | ⬜ Pending |

---

*Laporan ini dihasilkan otomatis oleh `run_release_tests.php` dan dilengkapi review manual.*
