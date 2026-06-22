<?php
// Load global form configuration
require_once __DIR__ . '/../../config/form_settings.php';
$angsuran_required = isAngsuranRequired();
$angsuran_helper = getAngsuranHelperText();
?>
<!-- 
    ========================================================================
    TAB PENGHASILAN PERANGKAT DESA - IMPROVED VERSION
    ========================================================================
    Fitur:
    - Date picker untuk tanggal mulai & akhir jabatan
    - Perhitungan otomatis sisa masa kerja (bulan/tahun)
    - Nomor SK dan upload SK (PDF, JPG, PNG)
    - Dynamic input untuk angsuran bank Wonosobo (OPSIONAL)
    - Validasi lengkap dan error message
    - Modern UI/UX dengan spacing konsisten
    ======================================================================== 
-->

<div id="tab-penghasilan" class="tab-content desa-penghasilan-container">
    <h3 class="tab-title">2. Data Pekerjaan & Keuangan (Perangkat Desa)</h3>
    <p class="text-muted desa-subtitle">Lengkapi data jabatan, kontrak, penghasilan, dan keuangan Perangkat Desa. Angka dalam Rupiah per bulan kecuali dinyatakan lain.</p>

    <!-- ===================================================================== 
         SECTION 1: JABATAN & SK 
         ===================================================================== -->
    <div class="section-header desa-section-header">
        <span class="section-icon">👔</span> 1. Data Jabatan & SK
    </div>

    <div class="desa-form-grid desa-grid-2">
        <!-- Jabatan - Dropdown -->
        <div class="desa-form-group">
            <label class="desa-label">Jabatan <span class="desa-required">*</span></label>
            <select 
                id="desk_jabatan" 
                name="desk_jabatan" 
                class="desa-input"
                required
                onchange="toggleDesaJabatanFields()"
            >
                <option value="">-- Pilih Jabatan --</option>
                <option value="KEPALA DESA">Kepala Desa</option>
                <option value="SEKRETARIS DESA">Sekretaris Desa</option>
                <option value="KEPALA DUSUN">Kepala Dusun</option>
                <option value="KAUR">Kaur</option>
            </select>
            <span class="desa-error-msg" id="error-desk_jabatan"></span>
        </div>

        <!-- Nomor SK -->
        <div class="desa-form-group">
            <label class="desa-label">Nomor SK (Surat Keputusan) <span class="desa-required">*</span></label>
            <input 
                type="text" 
                id="desk_no_sk" 
                name="desk_no_sk" 
                class="desa-input"
                placeholder="cth: SK/DESA/2024/001"
                required
                style="text-transform:uppercase;"
                data-validate="text"
            >
            <span class="desa-error-msg" id="error-desk_no_sk"></span>
        </div>

        <!-- Tanggal Mulai Jabatan -->
        <div class="desa-form-group" id="desa-tgl-mulai-group">
            <label class="desa-label">Tanggal Mulai Jabatan <span class="desa-required">*</span></label>
            <input 
                type="date" 
                id="desk_tgl_mulai" 
                name="desk_tgl_mulai" 
                class="desa-input"
                required
                data-validate="date"
                oninput="calculateSisaMasaJabatan()"
            >
            <span class="desa-error-msg" id="error-desk_tgl_mulai"></span>
        </div>

        <!-- Tanggal Akhir Jabatan (Hanya untuk Kepala Desa) -->
        <div class="desa-form-group" id="desa-tgl-akhir-group" style="display: none;">
            <label class="desa-label">Tanggal Akhir Masa Jabatan <span class="desa-required">*</span></label>
            <input 
                type="date" 
                id="desk_tgl_akhir" 
                name="desk_tgl_akhir" 
                class="desa-input"
                data-validate="date"
                oninput="calculateSisaMasaJabatan()"
            >
            <span class="desa-error-msg" id="error-desk_tgl_akhir"></span>
        </div>

        <!-- Tanggal Lahir (Untuk non-Kepala Desa) - LINKED FROM TAB PEMOHON -->
        <div class="desa-form-group" id="desa-tgl-lahir-group" style="display: none;">
            <label class="desa-label">Tanggal Lahir <span class="desa-required">*</span></label>
            <div class="desa-display-box" style="display: flex; align-items: center; gap: 0.75rem;">
                <span id="desk_tgl_lahir_display">-</span>
                <small style="color: #666; font-style: italic;">(dari Data Pribadi)</small>
            </div>
            <input 
                type="hidden" 
                id="desk_tgl_lahir" 
                name="desk_tgl_lahir" 
                class="desa-input"
                data-validate="date"
            >
            <small class="desa-helper">Tanggal lahir diambil otomatis dari Data Pribadi untuk perhitungan usia maksimal 60 tahun</small>
            <span class="desa-error-msg" id="error-desk_tgl_lahir"></span>
        </div>

        <!-- Sisa Masa Jabatan (Display) -->
        <div class="desa-form-group">
            <label class="desa-label">Sisa Masa Jabatan</label>
            <div class="desa-display-box">
                <span id="desk_sisa_jabatan_display">-</span>
            </div>
            <input type="hidden" id="desk_sisa_jabatan_bulan" name="desk_sisa_jabatan_bulan" value="0">
            <small class="desa-helper" id="desa-sisa-jabatan-note">Dihitung otomatis berdasarkan tanggal akhir jabatan</small>
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 2: PENGHASILAN 
         ===================================================================== -->
    <div class="section-header desa-section-header">
        <span class="section-icon">💰</span> 2. Data Penghasilan
    </div>

    <div class="desa-form-grid desa-grid-2">
        <!-- Penghasilan Tetap -->
        <div class="desa-form-group">
            <label class="desa-label">Penghasilan Tetap (Rp/bulan) <span class="desa-required">*</span></label>
            <input 
                type="number" 
                id="desk_penghasilan_tetap" 
                name="desk_penghasilan_tetap" 
                class="desa-input desa-currency"
                min="0" 
                value="0"
                required
                data-validate="number"
                oninput="updateDesaScoring()"
            >
            <span class="desa-error-msg" id="error-desk_penghasilan_tetap"></span>
        </div>

        <!-- Tambahan Penghasilan -->
        <div class="desa-form-group">
            <label class="desa-label">Tambahan Penghasilan (Rp/bulan)</label>
            <input 
                type="number" 
                id="desk_tambahan_penghasilan" 
                name="desk_tambahan_penghasilan" 
                class="desa-input desa-currency"
                min="0" 
                value="0"
                data-validate="number"
                oninput="updateDesaScoring()"
            >
        </div>

        <!-- Biaya Hidup -->
        <div class="desa-form-group">
            <label class="desa-label">Biaya Hidup / Kebutuhan RT (Rp/bulan)</label>
            <input 
                type="number" 
                id="desk_biaya_hidup" 
                name="desk_biaya_hidup" 
                class="desa-input desa-currency"
                min="0" 
                value="0"
                data-validate="number"
                oninput="updateDesaScoring()"
            >
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 3: AGUNAN - NOMOR SK & UPLOAD FILE SK
         ===================================================================== -->
    <div class="section-header desa-section-header">
        <span class="section-icon">📄</span> 3. Surat Keputusan (SK) - Agunan
    </div>

    <div class="desa-form-grid desa-grid-1">
        <!-- Upload File SK -->
        <div class="desa-form-group">
            <label class="desa-label">Upload File SK <span class="desa-required">*</span></label>
            <div class="desa-file-upload-wrapper">
                <input 
                    type="file" 
                    id="desk_file_sk" 
                    name="desk_file_sk" 
                    class="desa-file-input"
                    accept=".pdf,.jpg,.jpeg,.png"
                    data-validate="file"
                >
                <label for="desk_file_sk" class="desa-file-label">
                    <span class="desa-file-icon">📎</span>
                    <span class="desa-file-text">Pilih File (PDF, JPG, PNG • Max 2MB)</span>
                </label>
                <div id="desk_file_preview" class="desa-file-preview"></div>
                <span class="desa-error-msg" id="error-desk_file_sk"></span>
            </div>
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 4: ANGSURAN BANK WONOSOBO (DYNAMIC/REPEATABLE)
         ===================================================================== -->
    <div class="section-header desa-section-header">
        <span class="section-icon">🏦</span> 4. Angsuran Bank Wonosobo 
        <?php if (!$angsuran_required): ?>
            <span style="font-size:0.75rem; color:#10b981; font-weight:600; background:#d1fae5; padding:0.25rem 0.5rem; border-radius:4px; margin-left:0.5rem;">OPSIONAL</span>
        <?php else: ?>
            <span style="font-size:0.75rem; color:#dc2626; font-weight:600; background:#fee2e2; padding:0.25rem 0.5rem; border-radius:4px; margin-left:0.5rem;">WAJIB</span>
        <?php endif; ?>
    </div>

    <p class="text-muted desa-subtitle"><?= htmlspecialchars($angsuran_helper) ?></p>

    <!-- Container untuk Angsuran Dinamis -->
    <div id="desk_angsuran_container" class="desa-angsuran-container">
        <!-- Item-item angsuran akan ditambahkan di sini via JavaScript -->
    </div>

    <!-- Tombol Tambah Angsuran -->
    <div class="desa-button-group desa-centered">
        <button 
            type="button" 
            id="btn-tambah-angsuran" 
            class="desa-btn desa-btn-primary"
            onclick="desaAddAngsuran()"
        >
            ➕ Tambah Angsuran
        </button>
    </div>

    <!-- Total Angsuran Otomatis -->
    <div class="desa-total-box">
        <div class="desa-total-row">
            <span class="desa-total-label">Total Angsuran Bank Wonosobo:</span>
            <span id="desk_total_angsuran_display" class="desa-total-value">Rp 0</span>
        </div>
        <input type="hidden" id="desk_total_angsuran" name="desk_total_angsuran" value="0">
    </div>

    <!-- ===================================================================== 
         SAVE BUTTON
         ===================================================================== -->
    <button 
        type="button" 
        id="btn-save-penghasilan_pegawai" 
        class="btn-save-section desa-btn-save"
        onclick="saveSection('penghasilan_pegawai')"
    >
        <span class="spinner"></span>
        <span class="btn-text">Simpan Data Pekerjaan & Keuangan Perangkat Desa</span>
    </button>
    <div id="toast-penghasilan_pegawai" class="toast-msg"></div>
</div>

<!-- ===================================================================== 
     INLINE STYLES - MODERN DESA DESIGN
     ===================================================================== -->
<style>
/* Container & Grid */
.desa-penghasilan-container {
    padding: 1.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.desa-section-header {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1f2937;
    margin-top: 2rem;
    margin-bottom: 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e5e7eb;
}

.desa-section-header:first-of-type {
    margin-top: 0;
}

.section-icon {
    font-size: 1.35rem;
}

.desa-subtitle {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

/* Form Grid */
.desa-form-grid {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.desa-grid-1 {
    grid-template-columns: 1fr;
}

.desa-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

/* Form Group */
.desa-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.desa-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.desa-required {
    color: #ef4444;
    font-weight: 600;
}

/* Input Fields */
.desa-input,
.desa-select {
    padding: 0.75rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    transition: all 0.2s ease;
    background: #fff;
}

.desa-input:focus,
.desa-select:focus {
    outline: none;
    border-color: #f59e0b;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
}

.desa-input:disabled,
.desa-select:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

.desa-input.desa-error {
    border-color: #ef4444;
    background: #fef2f2;
}

/* Display Box */
.desa-display-box {
    padding: 0.75rem 1rem;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #92400e;
    min-height: 42px;
    display: flex;
    align-items: center;
}

/* Helper Text */
.desa-helper {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: -0.25rem;
}

/* Error Messages */
.desa-error-msg {
    font-size: 0.8rem;
    color: #ef4444;
    display: none;
    margin-top: -0.25rem;
    font-weight: 500;
}

.desa-error-msg.show {
    display: block;
}

/* ===== FILE UPLOAD ===== */
.desa-file-upload-wrapper {
    position: relative;
}

.desa-file-input {
    display: none;
}

.desa-file-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem 1.5rem;
    border: 2px dashed #fcd34d;
    border-radius: 8px;
    background: #fffbeb;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    color: #92400e;
    font-weight: 500;
}

.desa-file-label:hover {
    background: #fef3c7;
    border-color: #fbbf24;
}

.desa-file-label.active {
    background: #fef3c7;
    border-color: #f59e0b;
}

.desa-file-icon {
    font-size: 1.5rem;
}

.desa-file-text {
    text-align: left;
}

/* File Preview */
.desa-file-preview {
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #92400e;
    display: none;
}

.desa-file-preview.show {
    display: block;
}

.desa-file-preview strong {
    color: #b45309;
}

/* ===== ANGSURAN DINAMIS ===== */
.desa-angsuran-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.desa-angsuran-item {
    padding: 1.25rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fafafa;
    position: relative;
    transition: all 0.2s ease;
}

.desa-angsuran-item:hover {
    border-color: #d1d5db;
    background: #f9fafb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.desa-angsuran-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #d1d5db;
}

.desa-angsuran-item-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.desa-angsuran-item-delete {
    background: #fee2e2;
    color: #dc2626;
    border: none;
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.desa-angsuran-item-delete:hover {
    background: #fecaca;
    color: #b91c1c;
}

.desa-angsuran-item-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

/* ===== TOTAL BOX ===== */
.desa-total-box {
    padding: 1.25rem;
    background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
    border: 1px solid #fcd34d;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.desa-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.desa-total-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f2937;
}

.desa-total-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #b45309;
}

/* ===== BUTTONS ===== */
.desa-button-group {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.desa-centered {
    justify-content: center;
}

.desa-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.desa-btn-primary {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
}

.desa-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
}

.desa-btn-danger {
    background: #fee2e2;
    color: #dc2626;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}

.desa-btn-danger:hover {
    background: #fecaca;
}

.desa-btn-save {
    width: 100%;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    padding: 1rem;
    font-size: 1rem;
    box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
}

.desa-btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(245, 158, 11, 0.3);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .desa-form-grid {
        gap: 1rem;
    }

    .desa-grid-2 {
        grid-template-columns: 1fr;
    }

    .desa-angsuran-item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .desa-angsuran-item-delete {
        width: 100%;
    }

    .desa-section-header {
        font-size: 1rem;
    }
}
</style>

<!-- ===================================================================== 
     JAVASCRIPT - LOGIC DAN FUNCTIONALITY
     ===================================================================== -->
<script>
// ===== GLOBAL VARIABLES =====
let desaAngsuranCounter = 0;
const DESA_MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const DESA_ALLOWED_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
const DESA_ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

// ===== UTILITY: Format Rupiah =====
function formatRupiah(value) {
    value = parseFloat(value) || 0;
    return 'Rp ' + value.toLocaleString('id-ID', { maximumFractionDigits: 0 });
}

// ===== UTILITY: Parse Rupiah =====
function parseRupiah(value) {
    if (typeof value === 'string') {
        return parseFloat(value.replace(/\D/g, '')) || 0;
    }
    return parseFloat(value) || 0;
}

// ===== TOGGLE JABATAN FIELDS =====
function toggleDesaJabatanFields() {
    const jabatanElem = document.getElementById('desk_jabatan');
    const tglAkhirGroupElem = document.getElementById('desa-tgl-akhir-group');
    const tglLahirGroupElem = document.getElementById('desa-tgl-lahir-group');
    const sisaJabatanNoteElem = document.getElementById('desa-sisa-jabatan-note');
    const tglAkhirElem = document.getElementById('desk_tgl_akhir');
    const tglLahirElem = document.getElementById('desk_tgl_lahir');
    
    const jabatan = jabatanElem.value;
    
    if (jabatan === 'KEPALA DESA') {
        // Kepala Desa: Tampilkan tanggal akhir jabatan, sembunyikan tanggal lahir
        tglAkhirGroupElem.style.display = 'flex';
        tglLahirGroupElem.style.display = 'none';
        tglAkhirElem.required = true;
        tglLahirElem.required = false;
        sisaJabatanNoteElem.textContent = 'Dihitung otomatis berdasarkan tanggal akhir jabatan';
    } else if (['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'].includes(jabatan)) {
        // Sekretaris Desa, Kepala Dusun, Kaur: Tampilkan tanggal lahir, sembunyikan tanggal akhir
        tglAkhirGroupElem.style.display = 'none';
        tglLahirGroupElem.style.display = 'flex';
        tglAkhirElem.required = false;
        tglLahirElem.required = true;
        sisaJabatanNoteElem.textContent = 'Dihitung otomatis dari usia maksimal 60 tahun';
    } else {
        // Default: sembunyikan keduanya
        tglAkhirGroupElem.style.display = 'none';
        tglLahirGroupElem.style.display = 'none';
        tglAkhirElem.required = false;
        tglLahirElem.required = false;
    }
    
    // Hitung ulang sisa masa jabatan
    calculateSisaMasaJabatan();
}

// ===== CALCULATE SISA MASA JABATAN =====
function calculateSisaMasaJabatan() {
    const jabatanElem = document.getElementById('desk_jabatan');
    const tglMulaiElem = document.getElementById('desk_tgl_mulai');
    const tglAkhirElem = document.getElementById('desk_tgl_akhir');
    const tglLahirElem = document.getElementById('desk_tgl_lahir');
    const displayElem = document.getElementById('desk_sisa_jabatan_display');
    const hiddenElem = document.getElementById('desk_sisa_jabatan_bulan');
    
    const jabatan = jabatanElem.value;
    
    if (jabatan === 'KEPALA DESA') {
        // Kepala Desa: Perhitungan menggunakan tanggal akhir jabatan
        if (!tglMulaiElem.value || !tglAkhirElem.value) {
            displayElem.textContent = '-';
            hiddenElem.value = 0;
            return;
        }

        const tglMulai = new Date(tglMulaiElem.value + 'T00:00:00');
        const tglAkhir = new Date(tglAkhirElem.value + 'T00:00:00');

        // Validasi: Akhir >= Mulai
        if (tglAkhir < tglMulai) {
            showDesaError('desk_tgl_akhir', 'Tanggal akhir tidak boleh lebih kecil dari tanggal mulai');
            displayElem.textContent = '-';
            hiddenElem.value = 0;
            return;
        }

        // Hitung selisih hari
        const diffTime = tglAkhir - tglMulai;
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

        // Konversi ke bulan dan tahun
        const bulan = Math.floor(diffDays / 30);
        const tahun = Math.floor(bulan / 12);
        const sisaBulan = bulan % 12;

        // Format display
        let display = '';
        if (tahun > 0) {
            display += tahun + ' tahun ';
        }
        if (sisaBulan > 0 || display === '') {
            display += sisaBulan + ' bulan';
        }

        displayElem.textContent = display.trim();
        hiddenElem.value = bulan;
    } else if (['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'].includes(jabatan)) {
        // Sekretaris Desa, Kepala Dusun, Kaur: Perhitungan berdasarkan usia maksimal 60 tahun
        if (!tglLahirElem.value) {
            displayElem.textContent = '-';
            hiddenElem.value = 0;
            return;
        }

        const tglLahir = new Date(tglLahirElem.value + 'T00:00:00');
        const tglAkhirUsia = new Date(tglLahir);
        tglAkhirUsia.setFullYear(tglAkhirUsia.getFullYear() + 60); // Usia maksimal 60 tahun
        
        const hariIni = new Date();
        hariIni.setHours(0, 0, 0, 0);

        // Jika sudah melampaui usia 60 tahun
        if (hariIni > tglAkhirUsia) {
            displayElem.textContent = '0 bulan (Sudah melampaui usia 60 tahun)';
            hiddenElem.value = 0;
            return;
        }

        // Hitung selisih hari dari hari ini hingga usia 60 tahun
        const diffTime = tglAkhirUsia - hariIni;
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

        // Konversi ke bulan dan tahun
        const bulan = Math.floor(diffDays / 30);
        const tahun = Math.floor(bulan / 12);
        const sisaBulan = bulan % 12;

        // Format display
        let display = '';
        if (tahun > 0) {
            display += tahun + ' tahun ';
        }
        if (sisaBulan > 0 || display === '') {
            display += sisaBulan + ' bulan';
        }

        displayElem.textContent = display.trim();
        hiddenElem.value = bulan;
    } else {
        // Jika jabatan belum dipilih atau tidak dikenali
        displayElem.textContent = '-';
        hiddenElem.value = 0;
    }
}

// ===== VALIDASI INPUT =====
function validateDesaField(fieldId, fieldType) {
    const field = document.getElementById(fieldId);
    if (!field) return true;

    const value = field.value.trim();
    let isValid = true;
    let errorMsg = '';

    if (fieldType === 'text') {
        if (!value) {
            errorMsg = 'Field wajib diisi';
            isValid = false;
        } else if (value.length > 100) {
            errorMsg = 'Maksimal 100 karakter';
            isValid = false;
        }
    } else if (fieldType === 'date') {
        if (!value) {
            errorMsg = 'Tanggal wajib diisi';
            isValid = false;
        }
        if (fieldId === 'desk_tgl_akhir' && value) {
            const tglMulai = document.getElementById('desk_tgl_mulai').value;
            if (tglMulai && new Date(value) < new Date(tglMulai)) {
                errorMsg = 'Tanggal akhir harus >= tanggal mulai';
                isValid = false;
            }
        }
    } else if (fieldType === 'number') {
        if (!value) {
            errorMsg = 'Angka wajib diisi';
            isValid = false;
        } else if (isNaN(parseFloat(value)) || parseFloat(value) < 0) {
            errorMsg = 'Harus berupa angka positif';
            isValid = false;
        }
    }

    if (!isValid) {
        showDesaError(fieldId, errorMsg);
    } else {
        clearDesaError(fieldId);
    }

    return isValid;
}

// ===== SHOW ERROR =====
function showDesaError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElem = document.getElementById('error-' + fieldId);

    if (field) {
        field.classList.add('desa-error');
    }
    if (errorElem) {
        errorElem.textContent = message;
        errorElem.classList.add('show');
    }
}

// ===== CLEAR ERROR =====
function clearDesaError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElem = document.getElementById('error-' + fieldId);

    if (field) {
        field.classList.remove('desa-error');
    }
    if (errorElem) {
        errorElem.textContent = '';
        errorElem.classList.remove('show');
    }
}

// ===== FILE UPLOAD HANDLER =====
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('desk_file_sk');
    if (!fileInput) return;

    fileInput.addEventListener('change', function (e) {
        const file = this.files[0];
        const previewDiv = document.getElementById('desk_file_preview');

        if (!file) {
            previewDiv.classList.remove('show');
            clearDesaError('desk_file_sk');
            return;
        }

        // Validasi ukuran file
        if (file.size > DESA_MAX_FILE_SIZE) {
            showDesaError('desk_file_sk', 'Ukuran file maksimal 2MB (file Anda: ' + (file.size / 1024 / 1024).toFixed(2) + 'MB)');
            previewDiv.classList.remove('show');
            this.value = '';
            return;
        }

        // Validasi tipe file
        const ext = file.name.split('.').pop().toLowerCase();
        if (!DESA_ALLOWED_EXTENSIONS.includes(ext) || !DESA_ALLOWED_FILE_TYPES.includes(file.type)) {
            showDesaError('desk_file_sk', 'Format file tidak didukung. Gunakan: PDF, JPG, atau PNG');
            previewDiv.classList.remove('show');
            this.value = '';
            return;
        }

        // Tampilkan preview
        clearDesaError('desk_file_sk');
        previewDiv.innerHTML = `<strong>✓ File terpilih:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        previewDiv.classList.add('show');
    });

    // Add event listeners untuk tanggal
    const tglMulaiElem = document.getElementById('desk_tgl_mulai');
    const tglAkhirElem = document.getElementById('desk_tgl_akhir');
    const tglLahirElem = document.getElementById('desk_tgl_lahir');

    if (tglMulaiElem) {
        tglMulaiElem.addEventListener('change', function () {
            validateDesaField('desk_tgl_mulai', 'date');
            calculateSisaMasaJabatan();
        });
    }

    if (tglAkhirElem) {
        tglAkhirElem.addEventListener('change', function () {
            validateDesaField('desk_tgl_akhir', 'date');
            calculateSisaMasaJabatan();
        });
    }

    if (tglLahirElem) {
        tglLahirElem.addEventListener('change', function () {
            validateDesaField('desk_tgl_lahir', 'date');
            calculateSisaMasaJabatan();
        });
    }

    // Add event listeners untuk text input
    ['desk_jabatan', 'desk_no_sk'].forEach(id => {
        const elem = document.getElementById(id);
        if (elem) {
            elem.addEventListener('blur', function () {
                validateDesaField(id, 'text');
            });
        }
    });

    // Initialize angsuran container if empty
    const container = document.getElementById('desk_angsuran_container');
    if (container && container.children.length === 0) {
        // Optionally add default angsuran item
    }

    // Sync Tanggal Lahir dari form Pemohon
    const sourceTglLahir = document.querySelector('input[name="tanggal_lahir"]');
    if (sourceTglLahir) {
        const syncTglLahir = function() {
            const val = sourceTglLahir.value;
            const tglLahirElem = document.getElementById('desk_tgl_lahir');
            const displayElem = document.getElementById('desk_tgl_lahir_display');
            if (tglLahirElem && displayElem) {
                tglLahirElem.value = val;
                
                // Format tgl untuk display
                if(val) {
                    const dateObj = new Date(val);
                    const options = { year: 'numeric', month: 'long', day: 'numeric' };
                    displayElem.textContent = dateObj.toLocaleDateString('id-ID', options);
                } else {
                    displayElem.textContent = '-';
                }
                
                validateDesaField('desk_tgl_lahir', 'date');
                calculateSisaMasaJabatan();
            }
        };
        // Sync pada saat value form Pemohon berubah
        sourceTglLahir.addEventListener('change', syncTglLahir);
        sourceTglLahir.addEventListener('blur', syncTglLahir);
        
        // Initial sync saat halaman dimuat
        setTimeout(syncTglLahir, 500);
    }

    // Initialize jabatan fields based on current selection
    toggleDesaJabatanFields();
});

// ===== ADD ANGSURAN (DYNAMIC) =====
function desaAddAngsuran() {
    const container = document.getElementById('desk_angsuran_container');
    const index = desaAngsuranCounter++;

    const itemHtml = `
        <div class="desa-angsuran-item" id="desa-angsuran-item-${index}">
            <div class="desa-angsuran-item-header">
                <span class="desa-angsuran-item-title">Angsuran #${index + 1}</span>
                <button type="button" class="desa-angsuran-item-delete" onclick="desaRemoveAngsuran(${index})">
                    🗑️ Hapus
                </button>
            </div>
            <div class="desa-angsuran-item-content">
                <div class="desa-form-group">
                    <label class="desa-label">Jenis Kredit / Produk <span class="desa-required">*</span></label>
                    <input 
                        type="text" 
                        class="desa-input desa-angsuran-nama" 
                        name="desk_angsuran_nama[]"
                        placeholder="cth: KMK, Kredit Konsumtif, dll"
                        style="text-transform:uppercase;"
                        required
                    >
                </div>
                <div class="desa-form-group">
                    <label class="desa-label">Nominal Angsuran (Rp/bulan) <span class="desa-required">*</span></label>
                    <input 
                        type="number" 
                        class="desa-input desa-angsuran-nominal desa-currency" 
                        name="desk_angsuran_nominal[]"
                        min="0"
                        value="0"
                        required
                        oninput="desaUpdateTotalAngsuran()"
                    >
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', itemHtml);
}

// ===== REMOVE ANGSURAN =====
function desaRemoveAngsuran(index) {
    const item = document.getElementById('desa-angsuran-item-' + index);
    if (item) {
        item.remove();
        desaUpdateTotalAngsuran();
    }
}

// ===== UPDATE TOTAL ANGSURAN =====
function desaUpdateTotalAngsuran() {
    const nominalInputs = document.querySelectorAll('.desa-angsuran-nominal');
    let total = 0;

    nominalInputs.forEach(input => {
        total += parseRupiah(input.value);
    });

    document.getElementById('desk_total_angsuran').value = total;
    document.getElementById('desk_total_angsuran_display').textContent = formatRupiah(total);

    // Update scoring jika ada
    if (typeof updateScoringSummary === 'function') {
        updateScoringSummary();
    }
}

// ===== UPDATE SCORING SUMMARY =====
function updateDesaScoring() {
    if (typeof updateScoringSummary === 'function') {
        updateScoringSummary();
    }
}

// ===== VALIDATION SEBELUM SAVE =====
function validateDesaForm() {
    let isValid = true;

    // Validasi fields wajib
    const wajibFields = [
        { id: 'desk_jabatan', type: 'text' },
        { id: 'desk_no_sk', type: 'text' },
        { id: 'desk_tgl_mulai', type: 'date' },
        { id: 'desk_penghasilan_tetap', type: 'number' }
    ];

    wajibFields.forEach(field => {
        if (!validateDesaField(field.id, field.type)) {
            isValid = false;
        }
    });

    // Validasi tanggal akhir/lahir berdasarkan jabatan
    const jabatan = document.getElementById('desk_jabatan')?.value || '';
    if (jabatan === 'KEPALA DESA') {
        if (!document.getElementById('desk_tgl_akhir')?.value) {
            showDesaError('desk_tgl_akhir', 'Tanggal akhir wajib diisi untuk Kepala Desa');
            isValid = false;
        }
    } else if (['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'].includes(jabatan)) {
        if (!document.getElementById('desk_tgl_lahir')?.value) {
            showDesaError('desk_tgl_lahir', 'Tanggal lahir wajib diisi');
            isValid = false;
        }
    }

    // Validasi file SK - hanya wajib jika belum ada file dan form baru
    const fileInput = document.getElementById('desk_file_sk');
    const idPengajuan = document.getElementById('id_pengajuan')?.value || '0';
    if (parseInt(idPengajuan) === 0 && (!fileInput?.files || fileInput.files.length === 0)) {
        showDesaError('desk_file_sk', 'File SK wajib diisi untuk pengajuan baru');
        isValid = false;
    }

    // Validasi minimal 1 angsuran HANYA jika dibutuhkan
    const angsuranItems = document.querySelectorAll('.desa-angsuran-item');
    const angsuranRequired = <?php echo $angsuran_required ? 'true' : 'false'; ?>;
    
    if (angsuranRequired && angsuranItems.length === 0) {
        alert('<?= getAngsuranErrorMessage() ?>');
        isValid = false;
    }

    return isValid;
}

// Override saveSection untuk tambahan validasi DESA
const originalSaveSection = window.saveSection;
window.saveSection = function (section) {
    if (section === 'penghasilan_pegawai') {
        if (!validateDesaForm()) {
            return;
        }
    }
    if (typeof originalSaveSection === 'function') {
        originalSaveSection.call(this, section);
    }
};
</script>
