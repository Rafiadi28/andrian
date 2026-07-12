<div id="tab-jaminan" class="tab-content pppk-penghasilan-container">
    <h3 class="tab-title">3. Jaminan PPPK</h3>
    <p class="text-muted pppk-subtitle">Formulir jaminan untuk PPPK bersifat <strong>opsional</strong>. Data hanya akan muncul di cetakan akhir jika bagian ini diisi.</p>

    <!-- SECTION: JAMINAN PPPK -->
    <div class="section-header pppk-section-header">
        <span class="section-icon">📋</span> Jaminan PPPK
    </div>

    <div class="pppk-form-grid pppk-grid-2">
        <!-- Nomor SK -->
        <div class="pppk-form-group">
            <label class="pppk-label">Jaminan</label>
            <input 
                type="text" 
                id="jaminan_bidang_usaha" 
                name="jaminan_bidang_usaha" 
                class="pppk-input"
                style="text-transform:uppercase;"
            >
            <small class="pppk-helper">Cth: SK PPPK / SK Pengangkatan ...</small>
        </div>

        <!-- SK / Avalis -->
        <div class="pppk-form-group">
            <label class="pppk-label">SK / Avalis</label>
            <input 
                type="text" 
                id="jaminan_sk_avalis" 
                name="jaminan_sk_avalis" 
                class="pppk-input"
                style="text-transform:uppercase;"
            >
        </div>

        <!-- No SK Agunan -->
        <div class="pppk-form-group">
            <label class="pppk-label">No SK Agunan</label>
            <input 
                type="text" 
                id="jaminan_no_sk_agunan" 
                name="jaminan_no_sk_agunan" 
                class="pppk-input"
                style="text-transform:uppercase;"
            >
        </div>
        
        <!-- Upload File SK -->
        <div class="pppk-form-group">
            <label class="pppk-label">Upload File SK Jaminan</label>
            <div class="pppk-file-upload-wrapper">
                <input 
                    type="file" 
                    id="jaminan_file_sk" 
                    name="jaminan_file_sk" 
                    class="pppk-file-input"
                    accept=".pdf,.jpg,.jpeg,.png"
                >
                <label for="jaminan_file_sk" class="pppk-file-label" style="padding: 1rem;">
                    <span class="pppk-file-icon">📎</span>
                    <span class="pppk-file-text">Pilih File (PDF, JPG, PNG)</span>
                </label>
                <div id="jaminan_file_preview" class="pppk-file-preview"></div>
            </div>
        </div>
    </div>

    <!-- SAVE BUTTON -->
    <button 
        type="button" 
        id="btn-save-jaminan_pegawai" 
        class="btn-save-section pppk-btn-save"
        onclick="saveSection('jaminan_pegawai')"
        style="margin-top: 1.5rem;"
    >
        <span class="spinner"></span>
        <span class="btn-text">Simpan Data Jaminan</span>
    </button>
    <div id="toast-jaminan_pegawai" class="toast-msg"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Handle file input visually
    var fileInput = document.getElementById('jaminan_file_sk');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            var fileName = e.target.value.split('\\').pop();
            var label = e.target.nextElementSibling;
            if (fileName) {
                label.classList.add('active');
                label.innerHTML = '<span class="pppk-file-icon">📄</span><span class="pppk-file-text"><strong>File terpilih:</strong> ' + fileName + '</span>';
            } else {
                label.classList.remove('active');
                label.innerHTML = '<span class="pppk-file-icon">📎</span><span class="pppk-file-text">Pilih File (PDF, JPG, PNG)</span>';
            }
        });
    }
});
</script>
