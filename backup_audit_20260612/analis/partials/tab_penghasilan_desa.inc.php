<div id="tab-penghasilan" class="tab-content">
    <h3 class="tab-title">2. Penghasilan Perangkat Desa</h3>
    <p class="text-muted" style="margin-bottom:1.25rem;font-size:0.92rem;">Honorarium dan penghasilan resmi perangkat
        desa (bukan usaha wiraswasta).</p>

    <div class="section-header">Jabatan &amp; SK</div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Jabatan <span style="color:red">*</span></label><input type="text"
                id="desk_jabatan" name="desk_jabatan" required style="text-transform:uppercase;"></div>
        <div class="custom-form-group"><label>Nomor SK <span style="color:red">*</span></label><input type="text"
                id="desk_no_sk" name="desk_no_sk" required style="text-transform:uppercase;"></div>
    </div>
    <div class="custom-form-group"><label>Masa jabatan (uraian)</label><input type="text" id="desk_masa_jabatan"
            name="desk_masa_jabatan" placeholder="cth: 2023–2029"></div>

    <div class="section-header">Penghasilan</div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Penghasilan tetap (Rp/bln)</label><input type="number"
                id="desk_penghasilan_tetap" name="desk_penghasilan_tetap" min="0" value="0"
                oninput="updateScoringSummary()"></div>
        <div class="custom-form-group"><label>Tambahan penghasilan (Rp/bln)</label><input type="number"
                id="desk_tambahan_penghasilan" name="desk_tambahan_penghasilan" min="0" value="0"
                oninput="updateScoringSummary()"></div>
    </div>

    <div class="section-header">Pengeluaran Tetap</div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Biaya hidup / kebutuhan rumah tangga (Rp/bln)</label><input
                type="number" id="desk_biaya_hidup" name="desk_biaya_hidup" min="0" value="0"
                oninput="updateScoringSummary()"></div>
        <div class="custom-form-group"><label>Angsuran kredit lain di luar bank ini (Rp/bln)</label><input
                type="number" id="desk_angsuran_lain" name="desk_angsuran_lain" min="0" value="0"
                oninput="updateScoringSummary()"></div>
    </div>

    <button type="button" id="btn-save-penghasilan_pegawai" class="btn-save-section"
        onclick="saveSection('penghasilan_pegawai')" style="margin-top:1.5rem;">
        <span class="spinner"></span>
        <span class="btn-text">Simpan data penghasilan</span>
    </button>
    <div id="toast-penghasilan_pegawai" class="toast-msg"></div>
</div>