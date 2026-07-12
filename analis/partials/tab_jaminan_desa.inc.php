<div id="tab-jaminan" class="tab-content desa-penghasilan-container">
    <h3 class="tab-title">3. Jaminan</h3>
    <p class="text-muted desa-subtitle">Formulir Jaminan untuk PPPK dan Perangkat Desa bersifat <strong>opsional</strong>. Data hanya akan muncul di cetakan akhir jika bagian ini diisi.</p>

    <div class="section-header desa-section-header">
        <span class="section-icon">📋</span> Jaminan / SK Pekerjaan
    </div>

    <div class="desa-form-grid desa-grid-2">
        <div class="desa-form-group">
            <label class="desa-label">Jaminan / SK Jabatan</label>
            <input
                type="text"
                id="jaminan_sk_jabatan_display"
                name="jaminan_bidang_usaha"
                class="desa-input"
                <?= $pegawai_tipe_save === 'perangkat_desa' ? 'readonly' : '' ?>
                style="background:#f3f4f6; text-transform:uppercase;"
                placeholder="<?= $pegawai_tipe_save === 'perangkat_desa' ? 'Isi di tab Analisa (field Jaminan)' : 'Cth: SK PPPK / SK Pengangkatan' ?>"
            >
            <small class="desa-helper"><?= $pegawai_tipe_save === 'perangkat_desa' ? 'Diambil otomatis dari field Jaminan pada tab Analisa' : 'Isi Jaminan PPPK di sini jika tersedia' ?></small>
        </div>

        <div class="desa-form-group">
            <label class="desa-label">Pihak Avalis</label>
            <input
                type="text"
                id="jaminan_sk_avalis"
                name="jaminan_sk_avalis"
                class="desa-input"
                style="text-transform:uppercase;"
                placeholder="Cth: CAMAT, BPD, KEPALA DESA"
            >
        </div>

        <div class="desa-form-group">
            <label class="desa-label">No SK Agunan</label>
            <input
                type="text"
                id="jaminan_no_sk_agunan"
                name="jaminan_no_sk_agunan"
                class="desa-input"
                style="text-transform:uppercase;"
            >
        </div>

        <div class="desa-form-group">
            <label class="desa-label">Upload File SK / Avalis</label>
            <div class="desa-file-upload-wrapper">
                <input
                    type="file"
                    id="jaminan_file_sk"
                    name="jaminan_file_sk"
                    class="desa-file-input"
                    accept=".pdf,.jpg,.jpeg,.png"
                >
                <label for="jaminan_file_sk" class="desa-file-label" style="padding: 1rem;">
                    <span class="desa-file-icon">📎</span>
                    <span class="desa-file-text">Pilih File (PDF, JPG, PNG)</span>
                </label>
                <div id="jaminan_file_preview" class="desa-file-preview"></div>
            </div>
        </div>
    </div>

    <button
        type="button"
        id="btn-save-jaminan_pegawai"
        class="btn-save-section desa-btn-save"
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
    function syncDesaSkJabatanDisplay() {
        var source = document.getElementById('desk_jaminan');
        var target = document.getElementById('jaminan_sk_jabatan_display');
        if (!source || !target) return;
        target.value = source.value || '';
    }

    syncDesaSkJabatanDisplay();

    var deskJaminan = document.getElementById('desk_jaminan');
    if (deskJaminan) {
        deskJaminan.addEventListener('input', syncDesaSkJabatanDisplay);
        deskJaminan.addEventListener('change', syncDesaSkJabatanDisplay);
    }

    var fileInput = document.getElementById('jaminan_file_sk');
    if (fileInput) {
        fileInput.addEventListener('change', function (e) {
            var fileName = e.target.value.split('\\').pop();
            var label = e.target.nextElementSibling;
            if (fileName) {
                label.classList.add('active');
                label.innerHTML = '<span class="desa-file-icon">📄</span><span class="desa-file-text"><strong>File terpilih:</strong> ' + fileName + '</span>';
            } else {
                label.classList.remove('active');
                label.innerHTML = '<span class="desa-file-icon">📎</span><span class="desa-file-text">Pilih File (PDF, JPG, PNG)</span>';
            }
        });
    }
});
</script>
