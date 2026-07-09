# 📊 DIAGRAM VISUAL - ALUR APPROVAL LENGKAP

## 🎯 DIAGRAM 1: ALUR APPROVAL CHAIN (OVERVIEW)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                    SISTEM APPROVAL KREDIT - ALUR LENGKAP                        │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  PHASE 1: INPUT & COMPLIANCE ASSESSMENT                                        │
│  ═══════════════════════════════════════════════════════════════════════════   │
│                                                                                 │
│     ANALIS                           KEPATUHAN                                 │
│     ▌Input Form 6 Section            ▌Assessment Form                          │
│     ├─ Data Pemohon ✓               ├─ Checklist Items                        │
│     ├─ Penghasilan ✓                ├─ Kesesuaian Dokumen                     │
│     ├─ Agunan/Jaminan ✓             ├─ Kelengkapan Dokumen                    │
│     ├─ Analisa Neraca ✓             ├─ Kualitas Dokumen                       │
│     ├─ Analisa 5C ✓                 ├─ Status Kepegawaian                     │
│     ├─ Cashflow ✓                   ├─ Analisa Risiko                         │
│     └─ KLIK SUBMIT                  ├─ Dampak Risiko                          │
│          │                          ├─ Mitigasi Risiko                        │
│          │                          ├─ KESIMPULAN ⭐ (REQUIRED)              │
│          ↓                          ├─ REKOMENDASI ⭐ (REQUIRED)              │
│     ┌─────────────────┐            └─ SUBMIT ASSESSMENT                       │
│     │ AUTO-ROUTE:     │                 │                                     │
│     │ posisi →        │                 ↓                                     │
│     │ 'kepatuhan'     │            ┌──────────────────┐                      │
│     │                 │            │ AUTO-ROUTE:      │                      │
│     │ status →        │            │ posisi →         │                      │
│     │ 'kepatuhan'     │            │ 'kasubag_analis' │                      │
│     │                 │            │                  │                      │
│     │ approval_kredit:│            │ status →         │                      │
│     │ level=analis    │            │ 'diajukan'       │                      │
│     │ keputusan=      │            │                  │                      │
│     │ setuju          │            │ approval_kredit: │                      │
│     └─────────────────┘            │ level=kepatuhan  │                      │
│                                    │ keputusan=       │                      │
│                                    │ setuju           │                      │
│                                    └──────────────────┘                      │
│                                                                               │
│  PHASE 2: APPROVAL CHAIN (SEQUENTIAL)                                        │
│  ═══════════════════════════════════════════════════════════════════════════ │
│                                                                               │
│     KASUBAG_ANALIS          KABAG_KREDIT          KADIV_BISNIS              │
│     ▌Level 1 Review        ▌Level 2 Review       ▌Level 3 Review            │
│     ├─ Review detail       ├─ Review detail      ├─ Review detail           │
│     ├─ Check kepatuhan ✓   ├─ Check kepatuhan ✓  ├─ Check kepatuhan ✓      │
│     └─ Keputusan:          └─ Keputusan:         ├─ CHECK NOMINAL ⭐       │
│        ├─ SETUJU           ├─ SETUJU             │  IF < 500 Juta:         │
│        │ → kabag_kredit    │ → kadiv_bisnis      │   └─ FINAL APPROVAL     │
│        ├─ REVISI           ├─ REVISI             │      → selesai           │
│        │ → analis          │ → analis            │                         │
│        └─ TOLAK            └─ TOLAK              │  IF >= 500 Juta:        │
│          → analis            → analis            │   └─ GO TO DIREKTUR     │
│                                                  │                         │
│                                    ↓ (only >= 500M)                         │
│                                                                              │
│                            DIREKTUR_UTAMA                                  │
│                            ▌Level 4 Review (FINAL)                         │
│                            ├─ Review detail                                │
│                            ├─ Check kepatuhan ✓                           │
│                            └─ FINAL DECISION:                             │
│                               ├─ SETUJU → FINAL APPROVED                  │
│                               │   └─ status = 'disetujui'                 │
│                               │   └─ posisi = 'selesai'                   │
│                               ├─ REVISI → analis                          │
│                               └─ TOLAK → analis                           │
│                                                                            │
│  PHASE 3: RESULT                                                           │
│  ═══════════════════════════════════════════════════════════════════════ │
│                                                                           │
│     ✓ PENGAJUAN DISETUJUI                                               │
│       ├─ status_pengajuan = 'disetujui'                                │
│       ├─ posisi_saat_ini = 'selesai'                                  │
│       └─ Ready untuk disbursement (pencairan dana)                    │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 DIAGRAM 2: NOMINAL ROUTING LOGIC

```
┌─────────────────────────────────────────────────────────────────┐
│           NOMINAL AMOUNT BASED APPROVAL ROUTING                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  PENGAJUAN KREDIT MASUK (dari analis submit)                   │
│         │                                                       │
│         ├─ Cek: jumlah_kredit < 500,000,000 ?               │
│         │   (Rp 500 Juta)                                      │
│         │                                                       │
│         ├─ YES (< Rp 500 Juta)                              │
│         │  └─ Alur:                                            │
│         │     Analis → Kepatuhan → Kasubag → Kabag → Kadiv  │
│         │                                        │            │
│         │                                        ↓            │
│         │                                    KADIV_BISNIS     │
│         │                                    (FINAL LEVEL)    │
│         │                                        │            │
│         │                              ┌─────────┴────────┐  │
│         │                              │                  │  │
│         │                          SETUJU          REVISI/TOLAK
│         │                              │                  │  │
│         │                              ↓                  ↓  │
│         │                         ┌─────────┐      ┌──────────┐
│         │                         │ SELESAI │      │ KEMBALI  │
│         │                         │ (FINAL) │      │ KE ANALIS│
│         │                         └─────────┘      └──────────┘
│         │
│         └─ NO (>= Rp 500 Juta)                              │
│            └─ Alur:                                           │
│               Analis → Kepatuhan → Kasubag → Kabag → Kadiv   │
│                                                    │          │
│                                                    ↓          │
│                                            KADIV_BISNIS      │
│                                            (NOT FINAL)       │
│                                                    │          │
│                                        ┌───────────┴──────────┐
│                                        │                      │
│                                    SETUJU              REVISI/TOLAK
│                                        │                      │
│                                        ↓                      ↓
│                                  ┌──────────────┐      ┌──────────┐
│                                  │ DIREKTUR_    │      │ KEMBALI  │
│                                  │ UTAMA        │      │ KE ANALIS│
│                                  │ (FINAL STEP) │      └──────────┘
│                                  └──────┬───────┘
│                                         │
│                           ┌─────────────┴──────────────┐
│                           │                            │
│                       SETUJU                   REVISI/TOLAK
│                           │                            │
│                           ↓                            ↓
│                      ┌─────────┐              ┌──────────────┐
│                      │ SELESAI │              │ KEMBALI      │
│                      │ (FINAL) │              │ KE ANALIS    │
│                      └─────────┘              └──────────────┘
│
│  ═════════════════════════════════════════════════════════════
│  
│  SUMMARY:
│  ────────
│  Rp 1 Juta - Rp 500 Juta
│    → Stop di Kadiv Bisnis
│    → NO need Direktur Utama
│
│  Rp 500 Juta - Rp 1 Milyar
│    → Must continue ke Direktur Utama
│    → Mandatory final approval
│
└─────────────────────────────────────────────────────────────────┘
```

---

## 🎯 DIAGRAM 3: COMPLIANCE BLOCKING LOGIC

```
┌──────────────────────────────────────────────────────────────────┐
│         COMPLIANCE CHECK - BLOCKING AT KASUBAG LEVEL             │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  KASUBAG_ANALIS LIHAT ANTRIAN (proses.php)                     │
│         │                                                        │
│         └─→ Database Query:                                     │
│             SELECT assessment_kepatuhan.*                       │
│             WHERE id_pengajuan = ???                            │
│                                                                  │
│             CHECK:                                              │
│             IF id_assessment IS NOT NULL                        │
│               AND checklist_data IS NOT NULL                    │
│               AND kesimpulan IS NOT NULL                        │
│             THEN compliance_status = 'LENGKAP' ✓              │
│             ELSE compliance_status ≠ 'LENGKAP' ✗              │
│                                                                  │
│         ├─ SCENARIO 1: compliance_status = 'LENGKAP' ✓        │
│         │  └─ UI Display:                                       │
│         │     ┌──────────────────────────────────────────┐    │
│         │     │ Row Background: WHITE (normal)           │    │
│         │     │ Badge: ✓ Compliance OK (green)          │    │
│         │     │ Button: "Proses" (ENABLED)              │    │
│         │     │                                          │    │
│         │     │ Kasubag bisa:                            │    │
│         │     │ ├─ KLIK "Detail" → lihat full form    │    │
│         │     │ └─ KLIK "Proses" → buat keputusan      │    │
│         │     │    ├─ SETUJU → lanjut ke kabag          │    │
│         │     │    ├─ REVISI → kembali ke analis       │    │
│         │     │    └─ TOLAK → tolak & back to analis   │    │
│         │     └──────────────────────────────────────────┘    │
│         │
│         └─ SCENARIO 2: compliance_status ≠ 'LENGKAP' ✗        │
│            └─ UI Display:                                       │
│               ┌──────────────────────────────────────────┐    │
│               │ Row Background: LIGHT RED (#fff5f5)     │    │
│               │ Badge 1: ⚠ Compliance Partial (amber) │    │
│               │ Badge 2: OR ✗ Waiting Compliance (red)│    │
│               │ Alert: ⚠ Blokir approval               │    │
│               │                                          │    │
│               │ Button: "Proses (Blokir)"              │    │
│               │ ├─ DISABLED (cannot click)              │    │
│               │ ├─ opacity = 0.6                        │    │
│               │ └─ title/tooltip:                       │    │
│               │    "Approval diblokir: menunggu         │    │
│               │     assessment kepatuhan"               │    │
│               │                                          │    │
│               │ ONCLICK Alert:                          │    │
│               │ "⚠️ Pengajuan ini TIDAK BISA            │    │
│               │  diproses sampai Dept. Kepatuhan      │    │
│               │  menyelesaikan assessment.             │    │
│               │                                          │    │
│               │  Silakan hubungi Dept. Kepatuhan      │    │
│               │  untuk menyelesaikan compliance       │    │
│               │  assessment terlebih dahulu."         │    │
│               │                                          │    │
│               │ ACTION REQUIRED:                        │    │
│               │ ├─ Hubungi Dept. Kepatuhan             │    │
│               │ ├─ Kepatuhan buka form & isi data     │    │
│               │ ├─ Kepatuhan submit assessment        │    │
│               │ ├─ System auto-move posisi            │    │
│               │ └─ Kasubag refresh dashboard          │    │
│               │    → Tombol sekarang ENABLED ✓        │    │
│               └──────────────────────────────────────────┘    │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 🎯 DIAGRAM 4: STATUS & POSISI STATE MACHINE

```
┌──────────────────────────────────────────────────────────────────┐
│    PENGAJUAN STATE TRANSITIONS - APPROVAL WORKFLOW                │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  STATE = (status_pengajuan, posisi_saat_ini)                    │
│                                                                  │
│  ┌──────────────────┐                                            │
│  │ START: (draft,   │                                            │
│  │        analis)   │                                            │
│  └────────┬─────────┘                                            │
│           │ Analis klik SUBMIT                                   │
│           ↓                                                      │
│  ┌──────────────────────────┐                                   │
│  │ (kepatuhan,              │  ← KEPATUHAN ASSESSMENT PHASE     │
│  │  kepatuhan)              │                                    │
│  │                          │  Kepatuhan isi form &             │
│  │ Kepatuhan complete       │  submit assessment                │
│  └────────┬─────────────────┘                                   │
│           │ AUTO-ROUTE                                           │
│           ↓                                                      │
│  ┌──────────────────────────┐                                   │
│  │ (diajukan,               │  ← KASUBAG_ANALIS QUEUE           │
│  │  kasubag_analis)         │                                    │
│  │                          │  [COMPLIANCE BLOCKING]            │
│  │ WAITING KASUBAG DECISION │                                    │
│  └────┬─────────────────┬───┘                                   │
│       │ SETUJU          │ REVISI/TOLAK                          │
│       │                 │                                       │
│       ↓                 ↓                                       │
│  ┌─────────────┐   ┌─────────────────────┐                    │
│  │ (kabag,     │   │ (revisi/ditolak,    │                    │
│  │  kabag_     │   │  analis)            │                    │
│  │  kredit)    │   │ BACK TO ANALIS      │                    │
│  │             │   │                     │                    │
│  │ KABAG QUEUE │   │ Analis edit & resubmit                   │
│  └────┬────────┘   └──────────┬──────────┘                    │
│       │                       │                               │
│       │ SETUJU                │ (re-submit)                   │
│       │                       │ Auto-route to kepatuhan again │
│       ↓                       │                               │
│  ┌─────────────┐              │                               │
│  │ (kadiv,     │              │                               │
│  │  kadiv_     │              │                               │
│  │  bisnis)    │              │                               │
│  │             │              │                               │
│  │ KADIV QUEUE │←─────────────┘                               │
│  └────┬────────┘                                              │
│       │ NOMINAL CHECK                                         │
│       │                                                       │
│       ├─ IF < 500M:                                           │
│       │  └─ SETUJU → (disetujui, selesai) [FINAL ✓]        │
│       │
│       └─ IF >= 500M:                                          │
│          ├─ SETUJU → (direksi, direktur_utama)             │
│          │           [GO TO DIREKTUR]                        │
│          │           ↓                                       │
│          │       ┌──────────────────┐                       │
│          │       │ (direksi,        │                       │
│          │       │  direktur_utama) │                       │
│          │       │                  │                       │
│          │       │ DIREKTUR QUEUE   │                       │
│          │       └────┬──────────┬──┘                       │
│          │            │ SETUJU   │ REVISI/TOLAK             │
│          │            │          │                          │
│          │            ↓          ↓                          │
│          │     ┌────────────┐ ┌────────────┐              │
│          │     │(disetujui, │ │ (revisi/   │              │
│          │     │ selesai)   │ │  ditolak,  │              │
│          │     │ [FINAL ✓]  │ │  analis)   │              │
│          │     └────────────┘ └────────────┘              │
│          │
│          └─ REVISI/TOLAK → (revisi/ditolak, analis)      │
│                [BACK TO ANALIS]                            │
│                                                            │
│  ╔════════════════════════════════════════════════════╗   │
│  ║ FINAL STATE: (disetujui, selesai)                  ║   │
│  ║ ✓ Pengajuan APPROVED                              ║   │
│  ║ ✓ Ready untuk disbursement (pencairan dana)       ║   │
│  ╚════════════════════════════════════════════════════╝   │
│                                                            │
└──────────────────────────────────────────────────────────────┘
```

---

## 🎯 DIAGRAM 5: DATA FLOW - APPROVAL_KREDIT TABLE

```
┌──────────────────────────────────────────────────────────────────┐
│       APPROVAL_KREDIT RECORD CREATION - DATA FLOW                 │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  STEP 1: Analis Submit
│  ┌─────────────────────────────────────────────────────┐        │
│  │ INSERT INTO approval_kredit:                        │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ id_pengajuan: 123                                  │        │
│  │ id_user: 5 (analis staff id)                       │        │
│  │ level_approval: 'analis'                           │        │
│  │ keputusan: 'setuju'                                │        │
│  │ catatan: 'Pengajuan lengkap'                       │        │
│  │ is_auto_skip: 0                                    │        │
│  │ tanggal_approval: NOW()                            │        │
│  └─────────────────────────────────────────────────────┘        │
│                                                                  │
│  STEP 2: Kepatuhan Submit Assessment (AUTO-ROUTE)
│  ┌─────────────────────────────────────────────────────┐        │
│  │ INSERT INTO approval_kredit:                        │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ id_pengajuan: 123                                  │        │
│  │ id_user: 8 (kepatuhan staff id)                    │        │
│  │ level_approval: 'kepatuhan'                        │        │
│  │ keputusan: 'setuju'                                │        │
│  │ catatan: 'Assessment lengkap: [kesimpulan]'        │        │
│  │ is_auto_skip: 0                                    │        │
│  │ tanggal_approval: NOW()                            │        │
│  └─────────────────────────────────────────────────────┘        │
│                                                                  │
│  STEP 3: Kasubag Analis Keputusan
│  ┌─────────────────────────────────────────────────────┐        │
│  │ OPTION A: SETUJU                                   │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ INSERT INTO approval_kredit:                        │        │
│  │ id_pengajuan: 123                                  │        │
│  │ id_user: 12 (kasubag staff id)                     │        │
│  │ level_approval: 'kasubag_analis'                   │        │
│  │ keputusan: 'setuju'                                │        │
│  │ catatan: 'Approved - kelengkapan dokumen OK'       │        │
│  │ is_auto_skip: 0                                    │        │
│  │ tanggal_approval: NOW()                            │        │
│  │                                                    │        │
│  │ OPTION B: REVISI                                   │        │
│  │ level_approval: 'kasubag_analis'                   │        │
│  │ keputusan: 'revisi'                                │        │
│  │ catatan: 'Diperlukan perbaikan: ...'              │        │
│  │ is_auto_skip: 0                                    │        │
│  │                                                    │        │
│  │ OPTION C: TOLAK                                    │        │
│  │ level_approval: 'kasubag_analis'                   │        │
│  │ keputusan: 'tolak'                                 │        │
│  │ catatan: 'Pengajuan ditolak: ...'                  │        │
│  │ is_auto_skip: 0                                    │        │
│  └─────────────────────────────────────────────────────┘        │
│                                                                  │
│  STEP 4: [IF INACTIVE] - AUTO-SKIP RECORD
│  ┌─────────────────────────────────────────────────────┐        │
│  │ Example: Kabag Kredit inactive                     │        │
│  │ System auto-create record:                         │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ INSERT INTO approval_kredit:                        │        │
│  │ id_pengajuan: 123                                  │        │
│  │ id_user: NULL (no user - system created)           │        │
│  │ level_approval: 'kabag_kredit'                     │        │
│  │ keputusan: 'eskalasi_otomatis'                      │        │
│  │ catatan: 'Auto-skipped: Kabag Kredit tidak aktif' │        │
│  │ is_auto_skip: 1 ⭐ MARKED AS AUTO-SKIP              │        │
│  │ tanggal_approval: NOW()                            │        │
│  └─────────────────────────────────────────────────────┘        │
│                                                                  │
│  FINAL QUERY: Show Approval History
│  ┌─────────────────────────────────────────────────────┐        │
│  │ SELECT * FROM approval_kredit                       │        │
│  │ WHERE id_pengajuan = 123                            │        │
│  │ ORDER BY tanggal_approval ASC;                      │        │
│  │                                                    │        │
│  │ EXPECTED OUTPUT (untuk < 500 Juta & APPROVED):    │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ 1. analis      | setuju           | No skip         │        │
│  │ 2. kepatuhan   | setuju           | No skip         │        │
│  │ 3. kasubag_... | setuju           | No skip         │        │
│  │ 4. kabag_...   | setuju           | No skip         │        │
│  │ 5. kadiv_...   | setuju           | No skip         │        │
│  │ └─ FINAL ✓ (status=disetujui, posisi=selesai)    │        │
│  │                                                    │        │
│  │ EXPECTED OUTPUT (untuk >= 500 Juta & APPROVED):   │        │
│  ├─────────────────────────────────────────────────────┤        │
│  │ 1. analis      | setuju           | No skip         │        │
│  │ 2. kepatuhan   | setuju           | No skip         │        │
│  │ 3. kasubag_... | setuju           | No skip         │        │
│  │ 4. kabag_...   | setuju           | No skip         │        │
│  │ 5. kadiv_...   | setuju           | No skip         │        │
│  │ 6. direktur... | setuju           | No skip         │        │
│  │ └─ FINAL ✓ (status=disetujui, posisi=selesai)    │        │
│  └─────────────────────────────────────────────────────┘        │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

---

## 📝 LEGEND & SYMBOLS

```
✓  = Complete / OK / Success
✗  = Incomplete / Not OK / Blocked
⭐ = Important / Required
→  = Flow direction
↓  = Down
|  = Branch / Alternative
□  = System / Database
◆  = Decision point
●  = Action / Process
```

---

**Last Updated**: 29 May 2026  
**Status**: ✅ COMPLETE VISUAL DOCUMENTATION
