                <div id="tab-penghasilan" class="tab-content">
                    <h3 class="tab-title">2. Penghasilan &amp; Kontrak (PPPK)</h3>
                    <p class="text-muted" style="margin-bottom:1.25rem;font-size:0.92rem;">Data kontrak dan penghasilan bulanan PPPK
                        (tanpa pendapatan usaha). Angka dalam Rupiah per bulan kecuali dinyatakan lain.</p>

                    <div class="section-header">Kontrak PPPK</div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nomor SK PPPK <span style="color:red">*</span></label><input
                                type="text" id="pppk_no_sk" name="pppk_no_sk" required style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Masa kontrak (uraian)</label><input type="text" id="pppk_masa_kontrak"
                                name="pppk_masa_kontrak" placeholder="cth: 31 DES 2025"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Sisa kontrak (uraian)</label><input type="text" id="pppk_sisa_kontrak"
                                name="pppk_sisa_kontrak" placeholder="cth: 12 BULAN"></div>
                        <div class="custom-form-group"><label>Gaji bersih / penghasilan tetap (Rp/bln)</label><input type="number"
                                id="pppk_gaji" name="pppk_gaji" min="0" value="0" oninput="updateScoringSummary()"></div>
                    </div>

                    <div class="section-header">Pengeluaran Tetap</div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Biaya hidup / kebutuhan rumah tangga (Rp/bln)</label><input
                                type="number" id="pppk_biaya_hidup" name="pppk_biaya_hidup" min="0" value="0"
                                oninput="updateScoringSummary()"></div>
                        <div class="custom-form-group"><label>Angsuran kredit lain di luar bank ini (Rp/bln)</label><input
                                type="number" id="pppk_angsuran_lain" name="pppk_angsuran_lain" min="0" value="0"
                                oninput="updateScoringSummary()"></div>
                    </div>

                    <button type="button" id="btn-save-penghasilan_pegawai" class="btn-save-section"
                        onclick="saveSection('penghasilan_pegawai')" style="margin-top:1.5rem;">
                        <span class="spinner"></span>
                        <span class="btn-text">Simpan data penghasilan PPPK</span>
                    </button>
                    <div id="toast-penghasilan_pegawai" class="toast-msg"></div>
                </div>
