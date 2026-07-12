<?php
// Load global form configuration
require_once __DIR__ . '/../../config/form_settings.php';
$angsuran_required = isAngsuranRequired();
$angsuran_helper = getAngsuranHelperText();
?>
<!-- 
    ========================================================================
    TAB PENGHASILAN PPPK - IMPROVED VERSION
    ========================================================================
    Fitur:
    - Date picker untuk tanggal awal & akhir perjanjian
    - Perhitungan otomatis sisa masa kerja (bulan/tahun)
    - Nomor SK dan upload SK (PDF, JPG, PNG)
    - Dynamic input untuk angsuran bank Wonosobo (OPSIONAL)
    - Validasi lengkap dan error message
    - Modern UI/UX dengan spacing konsisten
    ======================================================================== 
-->

<div id="tab-penghasilan" class="tab-content pppk-penghasilan-container">
    <h3 class="tab-title">2. Data Pekerjaan & Keuangan (PPPK)</h3>
    <p class="text-muted pppk-subtitle">Lengkapi data kontrak, penghasilan, dan keuangan PPPK. Angka dalam Rupiah per bulan kecuali dinyatakan lain.</p>

    <!-- ===================================================================== 
         SECTION 1: KONTRAK PPPK 
         ===================================================================== -->
    <div class="section-header pppk-section-header">
        <span class="section-icon">📋</span> 1. Data Kontrak PPPK
    </div>

    <div class="pppk-form-grid pppk-grid-2">
        <!-- Tanggal Awal Perjanjian -->
        <div class="pppk-form-group">
            <label class="pppk-label">Tanggal Awal Perjanjian <span class="pppk-required">*</span></label>
            <input 
                type="date" 
                id="pppk_tgl_awal" 
                name="pppk_tgl_awal" 
                class="pppk-input"
                required
                data-validate="date"
            >
            <span class="pppk-error-msg" id="error-pppk_tgl_awal"></span>
        </div>

        <!-- Tanggal Akhir Perjanjian -->
        <div class="pppk-form-group">
            <label class="pppk-label">Tanggal Akhir Perjanjian <span class="pppk-required">*</span></label>
            <input 
                type="date" 
                id="pppk_tgl_akhir" 
                name="pppk_tgl_akhir" 
                class="pppk-input"
                required
                data-validate="date"
            >
            <span class="pppk-error-msg" id="error-pppk_tgl_akhir"></span>
        </div>

        <!-- Sisa Masa Kerja (Display/Auto-calculated) -->
        <div class="pppk-form-group">
            <label class="pppk-label">Sisa Masa Kerja</label>
            <div class="pppk-display-box">
                <span id="pppk_sisa_kerja_display">-</span>
            </div>
            <input type="hidden" id="pppk_sisa_kerja_bulan" name="pppk_sisa_kerja_bulan" value="0">
            <small class="pppk-helper">Dihitung otomatis berdasarkan tanggal akhir perjanjian</small>
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 2: DATA PENGHASILAN 
         ===================================================================== -->
    <div class="section-header pppk-section-header">
        <span class="section-icon">💰</span> 2. Data Penghasilan
    </div>

    <div class="pppk-form-grid pppk-grid-2">
        <!-- Gaji Bersih -->
        <div class="pppk-form-group">
            <label class="pppk-label">Gaji Bersih / Penghasilan Tetap (Rp/bulan) <span class="pppk-required">*</span></label>
            <input 
                type="number" 
                id="pppk_gaji" 
                name="pppk_gaji" 
                class="pppk-input pppk-currency"
                min="0" 
                value="0"
                required
                data-validate="number"
                oninput="updatePPPKScoring()"
            >
            <span class="pppk-error-msg" id="error-pppk_gaji"></span>
        </div>

        <!-- Biaya Hidup -->
        <div class="pppk-form-group">
            <label class="pppk-label">Biaya Hidup / Kebutuhan RT (Rp/bulan)</label>
            <input 
                type="number" 
                id="pppk_biaya_hidup" 
                name="pppk_biaya_hidup" 
                class="pppk-input pppk-currency"
                min="0" 
                value="0"
                data-validate="number"
                oninput="updatePPPKScoring()"
            >
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 3: JAMINAN / NO SK (OPSIONAL)
         ===================================================================== -->
    <div class="section-header pppk-section-header">
        <span class="section-icon">🧾</span> 3. Jaminan / No SK (Opsional)
    </div>

    <div class="pppk-form-grid pppk-grid-1">
        <div class="pppk-form-group">
            <label class="pppk-label">Jaminan / No SK</label>
            <input
                type="text"
                id="pppk_no_sk"
                name="pppk_no_sk"
                class="pppk-input"
                style="text-transform:uppercase;"
                placeholder="Cth: SK PPPK / SK Pengangkatan"
            >
            <small class="pppk-helper">Opsional — hanya diisi jika ada nomor SK atau dokumen Jaminan.</small>
            <span class="pppk-error-msg" id="error-pppk_no_sk"></span>
        </div>
    </div>

    <!-- ===================================================================== 
         SECTION 4: ANGSURAN BANK WONOSOBO (DYNAMIC/REPEATABLE)
         ===================================================================== -->
    <div class="section-header pppk-section-header">
        <span class="section-icon">🏦</span> 4. Angsuran Bank Wonosobo 
        <?php if (!$angsuran_required): ?>
            <span style="font-size:0.75rem; color:#10b981; font-weight:600; background:#d1fae5; padding:0.25rem 0.5rem; border-radius:4px; margin-left:0.5rem;">OPSIONAL</span>
        <?php else: ?>
            <span style="font-size:0.75rem; color:#dc2626; font-weight:600; background:#fee2e2; padding:0.25rem 0.5rem; border-radius:4px; margin-left:0.5rem;">WAJIB</span>
        <?php endif; ?>
    </div>

    <p class="text-muted pppk-subtitle"><?= htmlspecialchars($angsuran_helper) ?></p>

    <!-- Container untuk Angsuran Dinamis -->
    <div id="pppk_angsuran_container" class="pppk-angsuran-container">
        <!-- Item-item angsuran akan ditambahkan di sini via JavaScript -->
    </div>

    <!-- Tombol Tambah Angsuran -->
    <div class="pppk-button-group pppk-centered">
        <button 
            type="button" 
            id="btn-tambah-angsuran" 
            class="pppk-btn pppk-btn-primary"
            onclick="pppkAddAngsuran()"
        >
            ➕ Tambah Angsuran
        </button>
    </div>

    <!-- Total Angsuran Otomatis -->
    <div class="pppk-total-box">
        <div class="pppk-total-row">
            <span class="pppk-total-label">Total Angsuran Bank Wonosobo:</span>
            <span id="pppk_total_angsuran_display" class="pppk-total-value">Rp 0</span>
        </div>
        <input type="hidden" id="pppk_total_angsuran" name="pppk_total_angsuran" value="0">
    </div>

    <!-- ===================================================================== 
         SAVE BUTTON
         ===================================================================== -->
    <button 
        type="button" 
        id="btn-save-penghasilan_pegawai" 
        class="btn-save-section pppk-btn-save"
        onclick="saveSection('penghasilan_pegawai')"
    >
        <span class="spinner"></span>
        <span class="btn-text">Simpan Data Pekerjaan & Keuangan PPPK</span>
    </button>
    <div id="toast-penghasilan_pegawai" class="toast-msg"></div>
</div>

<!-- ===================================================================== 
     INLINE STYLES - MODERN PPPK DESIGN
     ===================================================================== -->
<style>
/* Container & Grid */
.pppk-penghasilan-container {
    padding: 1.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.pppk-section-header {
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

.pppk-section-header:first-of-type {
    margin-top: 0;
}

.section-icon {
    font-size: 1.35rem;
}

.pppk-subtitle {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 1.5rem;
    line-height: 1.5;
}

/* Form Grid */
.pppk-form-grid {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.pppk-grid-1 {
    grid-template-columns: 1fr;
}

.pppk-grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}

/* Form Group */
.pppk-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.pppk-label {
    font-size: 0.9rem;
    font-weight: 500;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.pppk-required {
    color: #ef4444;
    font-weight: 600;
}

/* Input Fields */
.pppk-input,
.pppk-select {
    padding: 0.75rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.95rem;
    font-family: inherit;
    transition: all 0.2s ease;
    background: #fff;
}

.pppk-input:focus,
.pppk-select:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

.pppk-input:disabled,
.pppk-select:disabled {
    background: #f3f4f6;
    color: #9ca3af;
    cursor: not-allowed;
}

.pppk-input.pppk-error {
    border-color: #ef4444;
    background: #fef2f2;
}

/* Display Box (untuk readonly fields) */
.pppk-display-box {
    padding: 0.75rem 1rem;
    background: #f0fdf4;
    border: 1px solid #d1fae5;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #166534;
    min-height: 42px;
    display: flex;
    align-items: center;
}

/* Helper Text */
.pppk-helper {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: -0.25rem;
}

/* Error Messages */
.pppk-error-msg {
    font-size: 0.8rem;
    color: #ef4444;
    display: none;
    margin-top: -0.25rem;
    font-weight: 500;
}

.pppk-error-msg.show {
    display: block;
}

/* ===== FILE UPLOAD ===== */
.pppk-file-upload-wrapper {
    position: relative;
}

.pppk-file-input {
    display: none;
}

.pppk-file-label {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 2rem 1.5rem;
    border: 2px dashed #bfdbfe;
    border-radius: 8px;
    background: #eff6ff;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    color: #1e40af;
    font-weight: 500;
}

.pppk-file-label:hover {
    background: #dbeafe;
    border-color: #93c5fd;
}

.pppk-file-label.active {
    background: #dbeafe;
    border-color: #3b82f6;
}

.pppk-file-icon {
    font-size: 1.5rem;
}

.pppk-file-text {
    text-align: left;
}

/* File Preview */
.pppk-file-preview {
    margin-top: 0.75rem;
    padding: 0.75rem 1rem;
    background: #f0fdf4;
    border: 1px solid #d1fae5;
    border-radius: 6px;
    font-size: 0.85rem;
    color: #166534;
    display: none;
}

.pppk-file-preview.show {
    display: block;
}

.pppk-file-preview strong {
    color: #15803d;
}

/* ===== ANGSURAN DINAMIS ===== */
.pppk-angsuran-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pppk-angsuran-item {
    padding: 1.25rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fafafa;
    position: relative;
    transition: all 0.2s ease;
}

.pppk-angsuran-item:hover {
    border-color: #d1d5db;
    background: #f9fafb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
}

.pppk-angsuran-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #d1d5db;
}

.pppk-angsuran-item-title {
    font-weight: 600;
    color: #1f2937;
    font-size: 0.95rem;
}

.pppk-angsuran-item-delete {
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

.pppk-angsuran-item-delete:hover {
    background: #fecaca;
    color: #b91c1c;
}

.pppk-angsuran-item-content {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

/* ===== TOTAL BOX ===== */
.pppk-total-box {
    padding: 1.25rem;
    background: linear-gradient(135deg, #f0fdf4 0%, #dbeafe 100%);
    border: 1px solid #86efac;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.pppk-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.pppk-total-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #1f2937;
}

.pppk-total-value {
    font-size: 1.15rem;
    font-weight: 700;
    color: #059669;
}

/* ===== BUTTONS ===== */
.pppk-button-group {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.pppk-centered {
    justify-content: center;
}

.pppk-btn {
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

.pppk-btn-primary {
    background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
    color: #fff;
    box-shadow: 0 2px 4px rgba(79, 70, 229, 0.2);
}

.pppk-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
}

.pppk-btn-danger {
    background: #fee2e2;
    color: #dc2626;
    font-size: 0.85rem;
    padding: 0.5rem 1rem;
}

.pppk-btn-danger:hover {
    background: #fecaca;
}

.pppk-btn-save {
    width: 100%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    padding: 1rem;
    font-size: 1rem;
    box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
}

.pppk-btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 768px) {
    .pppk-form-grid {
        gap: 1rem;
    }

    .pppk-grid-2 {
        grid-template-columns: 1fr;
    }

    .pppk-angsuran-item-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .pppk-angsuran-item-delete {
        width: 100%;
    }

    .pppk-section-header {
        font-size: 1rem;
    }
}
</style>

<!-- ===================================================================== 
     JAVASCRIPT - LOGIC DAN FUNCTIONALITY
     ===================================================================== -->
<script>
// ===== GLOBAL VARIABLES =====
let pppkAngsuranCounter = 0;
const PPPK_MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
const PPPK_ALLOWED_FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
const PPPK_ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

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

// ===== UTILITY: Format ISO Date =====
function formatISODate(date) {
    if (!date) return '';
    if (typeof date === 'string') return date;
    return date.toISOString().split('T')[0];
}

// ===== CALCULATE SISA MASA KERJA (dari HARI INI ke akhir kontrak) =====
function calculateSisaMasaKerja() {
    const tglAwalElem  = document.getElementById('pppk_tgl_awal');
    const tglAkhirElem = document.getElementById('pppk_tgl_akhir');
    const displayElem  = document.getElementById('pppk_sisa_kerja_display');
    const hiddenElem   = document.getElementById('pppk_sisa_kerja_bulan');

    if (!tglAkhirElem || !tglAkhirElem.value) {
        displayElem.textContent = '-';
        hiddenElem.value = 0;
        return;
    }

    // Validasi: akhir >= awal (jika awal diisi)
    if (tglAwalElem && tglAwalElem.value) {
        const tglAwal  = new Date(tglAwalElem.value  + 'T00:00:00');
        const tglAkhir = new Date(tglAkhirElem.value + 'T00:00:00');
        if (tglAkhir < tglAwal) {
            showError('pppk_tgl_akhir', 'Tanggal akhir tidak boleh lebih kecil dari tanggal awal');
            displayElem.textContent = '-';
            hiddenElem.value = 0;
            return;
        }
        clearError('pppk_tgl_akhir');
    }

    // Hitung SISA dari hari ini ke tanggal akhir
    const today    = new Date();
    today.setHours(0, 0, 0, 0);
    const tglAkhir = new Date(tglAkhirElem.value + 'T00:00:00');

    if (tglAkhir <= today) {
        displayElem.textContent = '⚠️ Kontrak sudah berakhir!';
        displayElem.style.color = '#dc2626';
        hiddenElem.value = 0;
        return;
    }

    // Hitung selisih bulan secara akurat
    const diffMs   = tglAkhir - today;
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    const bulan    = Math.floor(diffDays / 30);
    const tahun    = Math.floor(bulan / 12);
    const sisaBln  = bulan % 12;

    let display = '';
    if (tahun > 0)  display += tahun + ' tahun ';
    if (sisaBln > 0 || display === '') display += sisaBln + ' bulan';

    // Warna peringatan jika sisa < 12 bulan
    if (bulan < 12) {
        displayElem.style.color = '#d97706';
        display += ' ⚠️ (< 1 tahun)';
    } else {
        displayElem.style.color = '#166534';
    }

    displayElem.textContent = display.trim();
    hiddenElem.value = bulan;

    // Perbarui max jangka waktu kredit di tab struktur (jika ada)
    const jwInput = document.getElementById('jangka_waktu');
    if (jwInput && bulan > 0) {
        if (!jwInput.getAttribute('data-pppk-max')) {
            jwInput.setAttribute('data-pppk-max', bulan);
            const hint = document.getElementById('pppk_jw_hint');
            if (!hint) {
                const small = document.createElement('small');
                small.id = 'pppk_jw_hint';
                small.style.cssText = 'color:#d97706; font-size:0.8rem; display:block; margin-top:4px;';
                small.textContent = 'Maks jangka waktu kredit: ' + bulan + ' bulan (sesuai sisa kontrak)';
                jwInput.parentNode && jwInput.parentNode.appendChild(small);
            }
        }
    }
}

// ===== VALIDASI INPUT =====
function validateField(fieldId, fieldType) {
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
        // Additional validation jika ada 2 date fields
        if (fieldId === 'pppk_tgl_akhir' && value) {
            const tglAwal = document.getElementById('pppk_tgl_awal').value;
            if (tglAwal && new Date(value) < new Date(tglAwal)) {
                errorMsg = 'Tanggal akhir harus >= tanggal awal';
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
    } else if (fieldType === 'file') {
        // File validation handled separately
    }

    if (!isValid) {
        showError(fieldId, errorMsg);
    } else {
        clearError(fieldId);
    }

    return isValid;
}

// ===== SHOW ERROR =====
function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElem = document.getElementById('error-' + fieldId);

    if (field) {
        field.classList.add('pppk-error');
    }
    if (errorElem) {
        errorElem.textContent = message;
        errorElem.classList.add('show');
    }
}

// ===== CLEAR ERROR =====
function clearError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElem = document.getElementById('error-' + fieldId);

    if (field) {
        field.classList.remove('pppk-error');
    }
    if (errorElem) {
        errorElem.textContent = '';
        errorElem.classList.remove('show');
    }
}

// ===== FILE UPLOAD HANDLER =====
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('pppk_file_sk');
    if (!fileInput) return;

    fileInput.addEventListener('change', function (e) {
        const file = this.files[0];
        const previewDiv = document.getElementById('pppk_file_preview');
        const errorDiv = document.getElementById('error-pppk_file_sk');

        if (!file) {
            previewDiv.classList.remove('show');
            clearError('pppk_file_sk');
            return;
        }

        // Validasi ukuran file
        if (file.size > PPPK_MAX_FILE_SIZE) {
            showError('pppk_file_sk', 'Ukuran file maksimal 2MB (file Anda: ' + (file.size / 1024 / 1024).toFixed(2) + 'MB)');
            previewDiv.classList.remove('show');
            this.value = '';
            return;
        }

        // Validasi tipe file
        const ext = file.name.split('.').pop().toLowerCase();
        if (!PPPK_ALLOWED_EXTENSIONS.includes(ext) || !PPPK_ALLOWED_FILE_TYPES.includes(file.type)) {
            showError('pppk_file_sk', 'Format file tidak didukung. Gunakan: PDF, JPG, atau PNG');
            previewDiv.classList.remove('show');
            this.value = '';
            return;
        }

        // Tampilkan preview
        clearError('pppk_file_sk');
        previewDiv.innerHTML = `<strong>✓ File terpilih:</strong> ${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
        previewDiv.classList.add('show');
    });

    // Add event listeners untuk tanggal
    const tglAwalElem = document.getElementById('pppk_tgl_awal');
    const tglAkhirElem = document.getElementById('pppk_tgl_akhir');

    if (tglAwalElem) {
        tglAwalElem.addEventListener('change', function () {
            validateField('pppk_tgl_awal', 'date');
            calculateSisaMasaKerja();
        });
    }

    if (tglAkhirElem) {
        tglAkhirElem.addEventListener('change', function () {
            validateField('pppk_tgl_akhir', 'date');
            calculateSisaMasaKerja();
        });
    }

    // Add event listeners untuk text input
    const noSkElem = document.getElementById('pppk_no_sk');
    if (noSkElem) {
        noSkElem.addEventListener('blur', function () {
            if (this.value.trim()) {
                validateField('pppk_no_sk', 'text');
            } else {
                clearError('pppk_no_sk');
            }
        });
    }

    // Initialize angsuran container if empty
    const container = document.getElementById('pppk_angsuran_container');
    if (container && container.children.length === 0) {
        // Optionally add default angsuran item
    }
});

// ===== ADD ANGSURAN (DYNAMIC) =====
function pppkAddAngsuran() {
    const container = document.getElementById('pppk_angsuran_container');
    const index = pppkAngsuranCounter++;

    const itemHtml = `
        <div class="pppk-angsuran-item" id="pppk-angsuran-item-${index}">
            <div class="pppk-angsuran-item-header">
                <span class="pppk-angsuran-item-title">Angsuran #${index + 1}</span>
                <button type="button" class="pppk-angsuran-item-delete" onclick="pppkRemoveAngsuran(${index})">
                    🗑️ Hapus
                </button>
            </div>
            <div class="pppk-angsuran-item-content">
                <div class="pppk-form-group">
                    <label class="pppk-label">Nama Produk / Jenis Kredit <span class="pppk-required">*</span></label>
                    <input 
                        type="text" 
                        class="pppk-input pppk-angsuran-nama" 
                        name="pppk_angsuran_nama[]"
                        placeholder="cth: Kredit Konsumtif, KMK, dll"
                        style="text-transform:uppercase;"
                        required
                    >
                </div>
                <div class="pppk-form-group">
                    <label class="pppk-label">Nominal Angsuran (Rp/bulan) <span class="pppk-required">*</span></label>
                    <input 
                        type="number" 
                        class="pppk-input pppk-angsuran-nominal pppk-currency" 
                        name="pppk_angsuran_nominal[]"
                        min="0"
                        value="0"
                        required
                        oninput="pppkUpdateTotalAngsuran()"
                    >
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', itemHtml);
}

// ===== REMOVE ANGSURAN =====
function pppkRemoveAngsuran(index) {
    const item = document.getElementById('pppk-angsuran-item-' + index);
    if (item) {
        item.remove();
        pppkUpdateTotalAngsuran();
    }
}

// ===== UPDATE TOTAL ANGSURAN =====
function pppkUpdateTotalAngsuran() {
    const nominalInputs = document.querySelectorAll('.pppk-angsuran-nominal');
    let total = 0;

    nominalInputs.forEach(input => {
        total += parseRupiah(input.value);
    });

    document.getElementById('pppk_total_angsuran').value = total;
    document.getElementById('pppk_total_angsuran_display').textContent = formatRupiah(total);

    // Update scoring jika ada
    if (typeof updateScoringSummary === 'function') {
        updateScoringSummary();
    }
}

// ===== UPDATE SCORING SUMMARY (integration dengan sistem lama) =====
function updatePPPKScoring() {
    if (typeof updateScoringSummary === 'function') {
        updateScoringSummary();
    }
}

// ===== VALIDATION SEBELUM SAVE =====
function validatePPPKForm() {
    let isValid = true;

    // Fields wajib
    const wajibFields = [
        { id: 'pppk_tgl_awal', type: 'date' },
        { id: 'pppk_tgl_akhir', type: 'date' },
        { id: 'pppk_gaji',     type: 'number' }
    ];

    wajibFields.forEach(field => {
        if (!validateField(field.id, field.type)) {
            isValid = false;
        }
    });

    // Sisa masa kerja > 0 (kontrak belum habis)
    const sisaBulan = parseInt(document.getElementById('pppk_sisa_kerja_bulan')?.value || '0');
    if (sisaBulan <= 0 && document.getElementById('pppk_tgl_akhir')?.value) {
        if (!confirm('Kontrak PPPK sudah berakhir atau hampir berakhir. Lanjutkan simpan?')) {
            return false;
        }
    }

    // Validasi angsuran HANYA jika required
    const angsuranRequired = <?php echo $angsuran_required ? 'true' : 'false'; ?>;
    const angsuranItems = document.querySelectorAll('.pppk-angsuran-item');
    
    if (angsuranRequired && angsuranItems.length === 0) {
        alert('<?= getAngsuranErrorMessage() ?>');
        isValid = false;
    }

    return isValid;
}

// Override saveSection untuk tambahan validasi PPPK
const originalSaveSection = window.saveSection;
window.saveSection = function (section) {
    if (section === 'penghasilan_pegawai') {
        if (!validatePPPKForm()) {
            return;
        }
    }
    if (typeof originalSaveSection === 'function') {
        originalSaveSection.call(this, section);
    }
};
</script>
