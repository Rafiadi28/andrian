<?php
if (!isset($RPC_PERSEN_MAKS) || !isset($RPC_DASAR_LABEL)) {
    require_once __DIR__ . '/../../helpers/credit_helper.php';
    $rpcJenis = $jenis_pekerjaan ?? ($pegawai_tipe_save ?? 'umum');
    $rpcPengajuanId = (int) ($EDIT_ID_PENGAJUAN ?? ($edit_id_pengajuan ?? 0));
    /** @var PDO $pdo */
    extract(bootstrapRepaymentFormConfig($pdo, $rpcJenis, $rpcPengajuanId > 0 ? $rpcPengajuanId : null));
}
?>
<!-- TAB 3: STRUKTUR KREDIT — Expanded -->
<div id="tab-struktur" class="tab-content">
    <h3 class="tab-title">3. Struktur Kredit</h3>

    <!-- A. DATA KREDIT -->
    <div class="section-header">A. DATA KREDIT</div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Skema Kredit</label>
            <select name="jenis_kredit">
                <option value="KMK">KMK (Kredit Modal Kerja)</option>
                <option value="KI">KI (Kredit Investasi)</option>
                <option value="KK">KK (Kredit Konsumtif)</option>
            </select>
        </div>
        <div class="custom-form-group"><label>Tujuan Kredit</label><input type="text" name="tujuan_kredit"
                style="text-transform:uppercase;" placeholder="cth: MODAL KERJA DAGANG"></div>
    </div>

    <!-- B. PLAFOND & TENOR -->
    <div class="section-header">B. PLAFOND & TENOR</div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Plafond Kredit (Rp) <span style="color:red">*</span></label><input
                type="number" name="jumlah_kredit" min="0" value="0" oninput="calcStruktur()"></div>
        <div class="custom-form-group"><label>Suku Bunga / Margin (% per tahun)</label><input type="number"
                name="suku_bunga" min="0" max="100" step="0.01" value="0" oninput="calcStruktur()"></div>
    </div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Jangka Waktu / Tenor (Bulan)</label><input type="number"
                name="jangka_waktu" min="1" value="0" oninput="calcStruktur()"></div>
        <div class="custom-form-group"><label>Jangka Tempo (Sistem Angsuran)</label>
            <select name="jangka_tempo" onchange="calcStruktur()">
                <option value="1">Bulanan</option>
                <option value="3">Triwulan (3 Bulan)</option>
                <option value="6">Semesteran (6 Bulan)</option>
                <option value="12">Tahunan (12 Bulan)</option>
            </select>
        </div>
    </div>
    <div class="grid-2">
        <div class="custom-form-group"><label>Grace Period (Bulan) <small style="color:#6b7280;">(0 =
                    tanpa grace)</small></label><input type="number" name="grace_period" min="0" value="0"
                oninput="calcStruktur()"></div>
        <div class="custom-form-group">
            <label>Jenis Bunga</label>
            <select name="jenis_bunga" onchange="calcStruktur()">
                <option value="flat">Flat</option>
                <option value="anuitas">Anuitas</option>
            </select>
        </div>
    </div>

    <!-- C. SIMULASI ANGSURAN -->
    <div class="section-header">C. SIMULASI ANGSURAN KREDIT</div>
    <div style="background:#eff6ff; padding:1.5rem; border-radius:10px; border:1px solid #bfdbfe;">
        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem;">
            <span
                style="background:#2563eb; color:#fff; padding:3px 8px; border-radius:4px; font-size:0.75rem; font-weight:700;">SIMULASI</span>
            <small style="color:#6b7280;">Perhitungan bersifat estimasi — mengikuti kebijakan bank yang
                berlaku</small>
        </div>

        <!-- Grace Period Info -->
        <div id="box_grace_info"
            style="display:none; background:#fffbeb; padding:0.75rem 1rem; border-radius:6px; border-left:3px solid #f59e0b; margin-bottom:1rem; font-size:0.88rem; color:#92400e;">
        </div>

        <!-- Rincian Simulasi -->
        <div style="background:#fff; border-radius:8px; padding:1rem; border:1px solid #dbeafe;">
            <table style="width:100%; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Plafond Kredit</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_plafond">Rp 0
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Suku Bunga</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_bunga_persen">
                        0% p.a.</td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Tenor</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_tenor">0 bulan
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Jangka Tempo</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_tempo">Bulanan
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Grace Period</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_grace">0 bulan
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280;">Masa Angsuran Efektif</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_efektif">0
                        bulan</td>
                </tr>
                <tr style="border-bottom:2px solid #1e293b;">
                    <td colspan="2" style="padding:10px 10px 5px; font-weight:700; color:#1e293b; font-size:0.9rem;"
                        id="sim_rincian_header">RINCIAN ANGSURAN PER PERIODE</td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280; padding-left:1.5rem;" id="sim_pokok_label">
                        Angsuran Pokok</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_angsuran_pokok">Rp 0</td>
                </tr>
                <tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:10px; color:#6b7280; padding-left:1.5rem;" id="sim_bunga_label">
                        Bunga Per Periode</td>
                    <td style="padding:10px; text-align:right; font-weight:600;" id="sim_bunga_bulan">Rp
                        0</td>
                </tr>
            </table>
        </div>

        <!-- Angsuran Per Bulan (highlight) -->
        <div
            style="background:linear-gradient(135deg,#1e293b,#334155); color:#fff; padding:1.25rem; border-radius:10px; margin-top:1rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                <div>
                    <div style="font-size:0.8rem; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">
                        Estimasi Angsuran / Periode</div>
                    <div style="font-size:0.7rem; opacity:0.5; margin-top:2px;" id="sim_angsuran_note">
                        Pokok + Bunga (Flat)</div>
                </div>
                <div style="font-size:1.75rem; font-weight:800;" id="sim_angsuran_bulanan">Rp 0</div>
            </div>
        </div>

        <!-- Grace Period Angsuran -->
        <div id="box_grace_angsuran"
            style="display:none; background:#fef3c7; padding:1rem; border-radius:8px; margin-top:0.75rem; border:1px solid #fde68a;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                <div>
                    <div style="font-weight:600; color:#92400e; font-size:0.9rem;">Angsuran Selama Grace
                        Period</div>
                    <div style="font-size:0.75rem; color:#a16207;">Hanya bunga (tanpa pokok)</div>
                </div>
                <div style="font-size:1.25rem; font-weight:800; color:#92400e;" id="sim_grace_angsuran">
                    Rp 0</div>
            </div>
        </div>

        <!-- Total Kewajiban -->
        <div style="background:#f0fdf4; padding:1rem; border-radius:8px; margin-top:0.75rem; border:1px solid #bbf7d0;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                <div>
                    <div style="font-weight:600; color:#059669; font-size:0.9rem;">Total Kewajiban
                        Selama Tenor</div>
                    <div style="font-size:0.75rem; color:#6b7280;" id="sim_total_note">Pokok + Total
                        Bunga</div>
                </div>
                <div style="font-size:1.25rem; font-weight:800; color:#059669;" id="sim_total_kewajiban">Rp 0</div>
            </div>
        </div>

        <!-- Catatan -->
        <div
            style="margin-top:1rem; padding:0.75rem; background:#f8fafc; border-radius:6px; border-left:3px solid #94a3b8; font-size:0.8rem; color:#64748b; line-height:1.6;">
            <strong>Catatan:</strong> Perhitungan ini menggunakan metode <em>flat rate</em> dan bersifat
            <strong>simulasi informatif</strong>.
            Nilai angsuran aktual mengikuti kebijakan suku bunga dan ketentuan bank yang berlaku.
        </div>
    </div>

    <button type="button" id="btn-save-struktur" class="btn-save-section" onclick="saveSection('struktur')"
        style="margin-top:2rem;">
        <span class="spinner"></span>
        <span class="btn-text">Simpan Struktur Kredit</span>
    </button>
    <div id="toast-struktur" class="toast-msg"></div>
</div>

<script>
    function calcStruktur() {
        let plafond = parseFloat(document.querySelector('[name=jumlah_kredit]').value) || 0;
        let bungaTahun = parseFloat(document.querySelector('[name=suku_bunga]').value) || 0;
        let tenor = parseInt(document.querySelector('[name=jangka_waktu]').value) || 0;
        let grace = parseInt(document.querySelector('[name=grace_period]').value) || 0;
        let tempo = parseInt(document.querySelector('[name=jangka_tempo]').value) || 1;

        let elJenisBunga = document.querySelector('#tab-struktur [name=jenis_bunga]');
        let jenisBunga = elJenisBunga ? elJenisBunga.value : 'flat';

        // Label tempo
        let tempoLabels = { 1: 'Bulanan', 3: 'Triwulan', 6: 'Semesteran', 12: 'Tahunan' };
        let tempoLabel = tempoLabels[tempo] || 'Bulanan';

        // Validate grace period <= tenor
        if (grace >= tenor && tenor > 0) {
            grace = tenor - 1;
            document.querySelector('#tab-struktur [name=grace_period]').value = grace;
        }

        let masaEfektifBulan = Math.max(tenor - grace, 0);
        // Jumlah kali pembayaran = masa efektif / tempo
        let jumlahPembayaran = tempo > 0 ? Math.ceil(masaEfektifBulan / tempo) : 0;
        let jumlahGracePembayaran = tempo > 0 ? Math.floor(grace / tempo) : 0;

        let angsuranPokokPerTempo = 0;
        let bungaPerTempo = 0;
        let angsuranPerTempo = 0;
        let totalKewajiban = 0;

        if (jenisBunga === 'flat' || bungaTahun === 0 || plafond === 0 || jumlahPembayaran === 0) {
            let bungaBulan = plafond * (bungaTahun / 100) / 12;
            bungaPerTempo = bungaBulan * tempo;
            angsuranPokokPerTempo = jumlahPembayaran > 0 ? plafond / jumlahPembayaran : 0;
            angsuranPerTempo = angsuranPokokPerTempo + bungaPerTempo;
            totalKewajiban = (angsuranPerTempo * jumlahPembayaran) + (bungaPerTempo * jumlahGracePembayaran);
        } else {
            // Anuitas
            let i = (bungaTahun / 100) / 12;
            let iTempo = i * tempo;
            angsuranPerTempo = (plafond * iTempo) / (1 - Math.pow(1 + iTempo, -jumlahPembayaran));
            bungaPerTempo = plafond * iTempo; // Ilustrasi bulan 1
            angsuranPokokPerTempo = angsuranPerTempo - bungaPerTempo;
            totalKewajiban = (angsuranPerTempo * jumlahPembayaran) + (plafond * iTempo * jumlahGracePembayaran);
        }

        // Angsuran bulanan (estimasi untuk RC)
        let angsuranBulananEstimasi = tempo > 0 ? angsuranPerTempo / tempo : 0;

        // Update displays
        document.getElementById('sim_plafond').textContent = formatRupiah(plafond);
        document.getElementById('sim_bunga_persen').textContent = bungaTahun.toFixed(2) + '% p.a. (' + (jenisBunga === 'flat' ? 'Flat' : 'Anuitas') + ')';
        document.getElementById('sim_tenor').textContent = tenor + ' bulan';
        document.getElementById('sim_tempo').textContent = tempoLabel;
        document.getElementById('sim_grace').textContent = grace + ' bulan';
        document.getElementById('sim_efektif').textContent = masaEfektifBulan + ' bulan (' + jumlahPembayaran + '× ' + tempoLabel + ')';
        document.getElementById('sim_rincian_header').textContent = 'RINCIAN ANGSURAN PER ' + tempoLabel.toUpperCase() + (jenisBunga === 'anuitas' ? ' (BULAN 1)' : '');
        document.getElementById('sim_pokok_label').textContent = 'Angsuran Pokok / ' + tempoLabel;
        document.getElementById('sim_bunga_label').textContent = 'Bunga / ' + tempoLabel;
        document.getElementById('sim_angsuran_pokok').textContent = formatRupiah(angsuranPokokPerTempo);
        document.getElementById('sim_bunga_bulan').textContent = formatRupiah(bungaPerTempo);
        document.getElementById('sim_angsuran_bulanan').textContent = formatRupiah(angsuranPerTempo);
        document.getElementById('sim_total_kewajiban').textContent = formatRupiah(totalKewajiban);
        document.getElementById('sim_total_note').textContent = 'Pokok + Total Bunga (' + tenor + ' bulan)';
        document.getElementById('sim_angsuran_note').textContent = 'Pokok + Bunga / ' + tempoLabel + ' (' + (jenisBunga === 'flat' ? 'Flat' : 'Anuitas*') + ')';

        // Grace period info
        let boxGrace = document.getElementById('box_grace_info');
        let boxGraceAngsuran = document.getElementById('box_grace_angsuran');
        if (grace > 0) {
            boxGrace.style.display = 'block';
            let graceBunga = (jenisBunga === 'flat') ? bungaPerTempo : (plafond * ((bungaTahun / 100) / 12) * tempo);
            boxGrace.innerHTML = '⏳ <strong>Grace Period: ' + grace + ' bulan</strong> — Selama masa grace period, debitur hanya membayar bunga sebesar <strong>' + formatRupiah(graceBunga) + '</strong>/' + tempoLabel.toLowerCase() + '. Angsuran pokok dimulai pada bulan ke-' + (grace + 1) + '.';
            if (boxGraceAngsuran) {
                boxGraceAngsuran.style.display = 'block';
                document.getElementById('sim_grace_angsuran').textContent = formatRupiah(graceBunga);
                document.getElementById('sim_angsuran_note').textContent = 'Pokok + Bunga / ' + tempoLabel + ' (' + (jenisBunga === 'flat' ? 'Flat' : 'Anuitas*') + ' setelah grace)';
            }
        } else {
            if (boxGrace) boxGrace.style.display = 'none';
            if (boxGraceAngsuran) boxGraceAngsuran.style.display = 'none';
        }

        let angsuranField = document.querySelector('[name=angsuran_diajukan]');
        if (angsuranField && plafond > 0 && tenor > 0) {
            if (angsuranField.classList.contains('rp-input') && typeof toRupiahStr === 'function') {
                angsuranField.value = toRupiahStr(Math.round(angsuranBulananEstimasi).toString());
            } else {
                angsuranField.value = Math.round(angsuranBulananEstimasi);
            }
            if (typeof calcUsaha === 'function') calcUsaha();
        }
    }
</script>

<!-- TAB 4: AGUNAN MULTI (DYNAMIC REPEATABLE) -->
<div id="tab-agunan" class="tab-content">
    <h3 class="tab-title">4. Data Agunan</h3>

    <div
        style="background:linear-gradient(135deg,#eff6ff,#f0fdf4); padding:1rem 1.25rem; border-radius:8px; border:1px solid #bfdbfe; margin-bottom:1.5rem;">
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <span style="font-size:1.25rem;">🏦</span>
            <div>
                <strong style="color:#1e40af;">Multi Agunan</strong>
                <div style="font-size:0.82rem; color:#6b7280;">Anda dapat menambahkan lebih dari 1
                    jaminan dalam 1 pengajuan kredit. Nilai total akan dihitung otomatis.</div>
            </div>
        </div>
    </div>

    <!-- CONTAINER FOR DYNAMIC AGUNAN ENTRIES -->
    <div id="agunan-container"></div>

    <!-- ADD BUTTON -->
    <div style="text-align:center; margin:1.5rem 0;">
        <button type="button" onclick="addAgunan()"
            style="background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; padding:0.75rem 1.5rem; border-radius:8px; font-weight:600; cursor:pointer; font-size:0.95rem; transition:all 0.2s;">
            ➕ Tambah Agunan
        </button>
    </div>

    <!-- AGGREGATE TOTALS -->
    <div id="agunan-totals"
        style="background:linear-gradient(135deg,#1e293b,#334155); color:#fff; padding:1.5rem; border-radius:12px; margin-bottom:1.5rem;">
        <h4 style="margin:0 0 1rem 0; font-size:0.85rem; text-transform:uppercase; letter-spacing:1px; opacity:0.7;">
            📊 REKAPITULASI TOTAL NILAI JAMINAN</h4>
        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
            <div style="background:rgba(255,255,255,0.1); padding:1rem; border-radius:8px;">
                <div style="font-size:0.75rem; opacity:0.7; margin-bottom:0.25rem;">Total Nilai Pasar
                </div>
                <div style="font-size:1.25rem; font-weight:800;" id="total_nilai_pasar">Rp 0</div>
            </div>
            <div style="background:rgba(255,255,255,0.1); padding:1rem; border-radius:8px;">
                <div style="font-size:0.75rem; opacity:0.7; margin-bottom:0.25rem;">Total Nilai Taksasi
                </div>
                <div style="font-size:1.25rem; font-weight:800; color:#fbbf24;" id="total_nilai_taksasi">Rp 0</div>
            </div>
            <div style="background:rgba(255,255,255,0.1); padding:1rem; border-radius:8px;">
                <div style="font-size:0.75rem; opacity:0.7; margin-bottom:0.25rem;">Total Nilai
                    Likuidasi</div>
                <div style="font-size:1.25rem; font-weight:800; color:#34d399;" id="total_nilai_likuidasi">Rp 0</div>
            </div>
        </div>
        <div style="margin-top:0.75rem; font-size:0.8rem; opacity:0.6; text-align:center;" id="total_count_agunan">Belum
            ada agunan ditambahkan</div>
    </div>

    <!-- FOTO USAHA (not per-agunan) -->
    <div style="margin-top: 1.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
        <h4 style="margin-bottom:1rem; color:var(--primary);">Dokumen & Foto Usaha</h4>
        <div class="grid-2">
            <div class="custom-form-group">
                <label>Foto Agunan</label>
                <input type="file" name="foto_usaha">
            </div>
            <div class="custom-form-group">
                <label>Data Pendukung Analisa Agunan (Bon/Nota/Laporan)</label>
                <input type="file" name="file_pendukung">
            </div>
        </div>
    </div>

    <button type="button" id="btn-save-agunan" class="btn-save-section" onclick="saveSection('agunan')">
        <span class="spinner"></span>
        <span class="btn-text">Simpan Data Agunan</span>
    </button>
    <div id="toast-agunan" class="toast-msg"></div>
</div>

<script>
    var agunanCounter = 0;

    function addAgunan(jenis) {
        jenis = jenis || 'tanah_bangunan';
        var idx = agunanCounter++;
        var container = document.getElementById('agunan-container');
        var card = document.createElement('div');
        card.className = 'agunan-card';
        card.id = 'agunan-card-' + idx;
        card.style.cssText = 'background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.5rem; margin-bottom:1.25rem; position:relative; transition:all 0.3s;';

        var html = '';
        // Header
        html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:0.75rem; border-bottom:2px solid #e2e8f0;">';
        html += '  <div style="display:flex; align-items:center; gap:0.5rem;">';
        html += '    <span style="background:linear-gradient(135deg,#2563eb,#7c3aed); color:#fff; border-radius:50%; width:28px; height:28px; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.8rem;">' + (idx + 1) + '</span>';
        html += '    <strong style="color:#1e293b;">Agunan #' + (idx + 1) + '</strong>';
        html += '  </div>';
        html += '  <button type="button" onclick="removeAgunan(' + idx + ')" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:0.4rem 0.75rem; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.8rem; transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.color=\'#fff\';" onmouseout="this.style.background=\'#fee2e2\';this.style.color=\'#dc2626\';">✕ Hapus</button>';
        html += '</div>';

        // Jenis Jaminan Selector
        html += '<div class="custom-form-group" style="margin-bottom:1rem;">';
        html += '  <label>Jenis Jaminan</label>';
        html += '  <select name="jenis_jaminan[]" id="jenis_jaminan_' + idx + '" onchange="toggleAgunanForm(' + idx + ')" style="font-weight:600;">';
        html += '    <option value="tanah_bangunan"' + (jenis === 'tanah_bangunan' ? ' selected' : '') + '>🏠 Tanah & Bangunan</option>';
        html += '    <option value="kendaraan"' + (jenis === 'kendaraan' ? ' selected' : '') + '>🚗 Kendaraan</option>';
        <?php if (($jenis_pekerjaan ?? 'umum') === 'kretamas'): ?>
        html += '    <option value="emas"' + (jenis === 'emas' ? ' selected' : '') + '>🥇 Emas</option>';
        <?php endif; ?>
        html += '  </select>';
        html += '</div>';

        // --- FORM TANAH ---
        html += '<div id="form_tanah_' + idx + '" style="' + (jenis === 'tanah_bangunan' ? '' : 'display:none;') + '">';
        // SPPT
        html += '<div class="section-header">SPPT</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Luas Tanah SPPT (m²)</label><input type="number" name="luas_tanah_sppt[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '  <div class="custom-form-group"><label>Harga Tanah SPPT / m²</label><input type="number" name="harga_tanah_sppt[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Nilai Wajar SPPT</label><div class="calc-display" id="disp_sppt_wajar_' + idx + '">Rp 0</div></div>';
        html += '  <div class="custom-form-group"><label>Taksasi SPPT</label><div class="calc-display" id="disp_sppt_taksasi_' + idx + '">Rp 0</div></div>';
        html += '</div>';
        html += '<div class="custom-form-group"><label>Likuidasi SPPT (70%)</label><div class="calc-display" id="disp_sppt_likuidasi_' + idx + '">Rp 0</div></div>';
        // Pasar
        html += '<div class="section-header">DATA FISIK & PASAR (SHM)</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group" id="wrap_luas_tanah_' + idx + '"><label>Luas Tanah (m²)</label><input type="number" name="luas_tanah[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '  <div class="custom-form-group"><label>Harga Tanah Pasar / m²</label><input type="number" name="harga_tanah_pasar[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Luas Bangunan 1 (m²)</label><input type="number" name="luas_bangunan[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '  <div class="custom-form-group"><label>Luas Bangunan 2 (m²)</label><input type="number" name="luas_bangunan_2[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        html += '</div>';
        html += '<div class="custom-form-group"><label>Harga Bangunan / m²</label><input type="number" name="harga_bangunan_m2[]" oninput="calcAgunanTanah(' + idx + ')"></div>';
        // Total pasar
        html += '<div style="background:#e0e7ff; padding:0.75rem 1rem; border-radius:8px; margin:1rem 0;">';
        html += '  <div class="grid-2" style="margin:0;">';
        html += '    <div><small style="color:#6b7280;">Nilai Pasar Total</small><div style="font-weight:700; font-size:1.1rem; color:#1e40af;" id="disp_pasar_total_' + idx + '">Rp 0</div></div>';
        html += '    <div><small style="color:#6b7280;">Taksasi (Safety)</small><div style="font-weight:700; font-size:1.1rem; color:#059669;" id="disp_pasar_taksasi_' + idx + '">Rp 0</div></div>';
        html += '  </div>';
        html += '  <div style="margin-top:0.5rem;"><small style="color:#6b7280;">Likuidasi (Quick Sale)</small><div style="font-weight:700; color:#d97706;" id="disp_pasar_likuidasi_' + idx + '">Rp 0</div></div>';
        html += '</div>';
        // Legalitas
        html += '<div class="section-header">DETAIL LEGALITAS</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Kategori Agunan</label>';
        html += '    <select name="kategori_agunan[]" onchange="calcAgunanTanah(' + idx + ')"><option value="rumah_tinggal">Tanah dan Bangunan</option><option value="ruko">Ruko</option><option value="sawah_tegal">Sawah/Tegal</option><option value="tanah_kosong">Tanah Kosong</option></select></div>';
        html += '  <div class="custom-form-group"><label>Jenis Surat</label>';
        html += '    <select name="jenis_surat[]" id="jenis_surat_' + idx + '" onchange="toggleLegalitasMulti(' + idx + ')"><option value="SHM">SHM</option><option value="SHGB">SHGB</option><option value="AJB">AJB</option><option value="Letter C">Letter C / Petok D</option><option value="Covernote">Covernote</option></select></div>';
        html += '</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Nomor Surat</label><input type="text" name="nomor_surat[]"></div>';
        html += '  <div class="custom-form-group"><label>Atas Nama Sertifikat</label><input type="text" name="atas_nama[]"></div>';
        html += '</div>';
        html += '<div class="custom-form-group" id="wrap_covernote_' + idx + '" style="display:none;"><label>Masa Berlaku Covernote (Tanggal)</label><input type="date" name="masa_covernote_multi[]" class="covernote-multi-' + idx + '"></div>';
        html += '<div class="custom-form-group"><label>Alamat Agunan</label><textarea name="alamat[]"></textarea></div>';
        html += '</div>'; // end form_tanah

        // --- FORM KENDARAAN ---
        html += '<div id="form_kendaraan_' + idx + '" style="' + (jenis === 'kendaraan' ? '' : 'display:none;') + '">';
        html += '<h4 style="margin-bottom:1rem; color:var(--primary);">Detail Kendaraan</h4>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Merk</label><input type="text" name="merk[]"></div>';
        html += '  <div class="custom-form-group"><label>Tipe</label><input type="text" name="tipe[]"></div>';
        html += '</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Tahun</label><input type="number" name="tahun[]" oninput="calcAgunanKendaraan(' + idx + ')"></div>';
        html += '  <div class="custom-form-group"><label>No Polisi</label><input type="text" name="nopol[]"></div>';
        html += '</div>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>No Rangka</label><input type="text" name="norangka[]"></div>';
        html += '  <div class="custom-form-group"><label>No Mesin</label><input type="text" name="nomesin[]"></div>';
        html += '</div>';
        html += '<div class="custom-form-group"><label>Pemilik BPKB</label><input type="text" name="bpkb_nama[]"></div>';
        html += '<div class="custom-form-group"><label>Nilai Pasar (Rp)</label><input type="number" name="nilai_pasar[]" oninput="calcAgunanKendaraan(' + idx + ')"></div>';
        // Kendaraan valuasi
        html += '<div style="background:#e0e7ff; padding:0.75rem 1rem; border-radius:8px; margin:1rem 0;">';
        html += '  <div class="grid-2" style="margin:0;">';
        html += '    <div><small style="color:#6b7280;">Taksasi Kendaraan</small><div style="font-weight:700; color:#059669;" id="disp_kend_taksasi_' + idx + '">Rp 0</div></div>';
        html += '    <div><small style="color:#6b7280;">Likuidasi (70%)</small><div style="font-weight:700; color:#d97706;" id="disp_kend_likuidasi_' + idx + '">Rp 0</div></div>';
        html += '  </div>';
        html += '</div>';
        html += '</div>'; // end form_kendaraan

        <?php if (($jenis_pekerjaan ?? 'umum') === 'kretamas'): ?>
        // --- FORM EMAS ---
        html += '<div id="form_emas_' + idx + '" style="' + (jenis === 'emas' ? '' : 'display:none;') + '">';
        html += '<h4 style="margin-bottom:1rem; color:var(--primary);">Detail Agunan Emas</h4>';
        html += '<div class="grid-2">';
        html += '  <div class="custom-form-group"><label>Berat Emas (Gram)</label><input type="number" step="0.01" name="emas_berat[]" oninput="calcAgunanEmas(' + idx + ')"></div>';
        html += '  <div class="custom-form-group"><label>Harga Emas Hari Ini (Rp/Gram)</label><input type="number" name="emas_harga_per_gram[]" oninput="calcAgunanEmas(' + idx + ')"></div>';
        html += '</div>';
        // Emas valuasi
        html += '<div style="background:#fef3c7; padding:0.75rem 1rem; border-radius:8px; margin:1rem 0;">';
        html += '  <div class="grid-2" style="margin:0;">';
        html += '    <div><small style="color:#6b7280;">Total Nilai Pasar (Hari Ini)</small><div style="font-weight:700; color:#b45309;" id="disp_emas_total_' + idx + '">Rp 0</div></div>';
        html += '    <div><small style="color:#6b7280;">Nilai Taksasi (95%)</small><div style="font-weight:700; color:#059669;" id="disp_emas_taksasi_' + idx + '">Rp 0</div></div>';
        html += '  </div>';
        html += '</div>';
        html += '</div>'; // end form_emas
        <?php endif; ?>

        // Hidden fields for kendaraan (to keep array indexes consistent)
        // When tanah is selected, kendaraan fields are hidden but still in DOM as empty
        // This ensures array index consistency

        card.innerHTML = html;
        container.appendChild(card);

        // Animate in
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
        setTimeout(function () {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 50);

        toggleAgunanForm(idx);
        toggleLegalitasMulti(idx);
        recalcAgunanTotals();
    }

    function removeAgunan(idx) {
        var card = document.getElementById('agunan-card-' + idx);
        if (card) {
            card.style.opacity = '0';
            card.style.transform = 'translateY(-10px)';
            setTimeout(function () {
                card.remove();
                recalcAgunanTotals();
            }, 200);
        }
    }

    function toggleAgunanForm(idx) {
        var sel = document.getElementById('jenis_jaminan_' + idx);
        if (!sel) return;
        var val = sel.value;
        var formTanah = document.getElementById('form_tanah_' + idx);
        var formKendaraan = document.getElementById('form_kendaraan_' + idx);
        if (formTanah) formTanah.style.display = (val === 'tanah_bangunan') ? 'block' : 'none';
        if (formKendaraan) formKendaraan.style.display = (val === 'kendaraan') ? 'block' : 'none';
        var formEmas = document.getElementById('form_emas_' + idx);
        if (formEmas) formEmas.style.display = (val === 'emas') ? 'block' : 'none';
        recalcAgunanTotals();
    }

    function toggleLegalitasMulti(idx) {
        var card = document.getElementById('agunan-card-' + idx);
        if (!card) return;
        var jsEl = document.getElementById('jenis_surat_' + idx);
        if (!jsEl) return;

        var val = jsEl.value;
        var wrapLT = document.getElementById('wrap_luas_tanah_' + idx);
        var wrapCV = document.getElementById('wrap_covernote_' + idx);

        if (wrapLT) {
            if (val === 'SHM' || val === 'SHGB') {
                wrapLT.style.display = 'block';
            } else {
                wrapLT.style.display = 'none';
                var inp = wrapLT.querySelector('input');
                if (inp) inp.value = '';
            }
        }

        if (wrapCV) {
            if (val === 'Covernote') {
                wrapCV.style.display = 'block';
            } else {
                wrapCV.style.display = 'none';
                var cv = wrapCV.querySelector('input');
                if (cv) cv.value = '';
            }
        }
        calcAgunanTanah(idx);
    }

    function calcAgunanTanah(idx) {
        var card = document.getElementById('agunan-card-' + idx);
        if (!card) return;

        var luasShm = parseFloat(card.querySelector('[name="luas_tanah[]"]').value) || 0;
        var luasSppt = parseFloat(card.querySelector('[name="luas_tanah_sppt[]"]').value) || 0;
        var hargaSppt = parseFloat(card.querySelector('[name="harga_tanah_sppt[]"]').value) || 0;
        var hargaPasar = parseFloat(card.querySelector('[name="harga_tanah_pasar[]"]').value) || 0;
        var luasB1 = parseFloat(card.querySelector('[name="luas_bangunan[]"]').value) || 0;
        var luasB2 = parseFloat(card.querySelector('[name="luas_bangunan_2[]"]').value) || 0;
        var hargaBangunan = parseFloat(card.querySelector('[name="harga_bangunan_m2[]"]').value) || 0;

        var katSel = card.querySelector('[name="kategori_agunan[]"]');
        var surSel = card.querySelector('[name="jenis_surat[]"]');

        var persen = 0.50; // default for unknown
        var kat = katSel ? katSel.value : '';
        var sur = surSel ? surSel.value : '';

        if (kat === 'sawah_tegal') {
            persen = 0.70;
        } else {
            if (sur === 'SHM' || sur === 'SHGB') {
                persen = 0.75;
            } else {
                persen = 0.50; // AJB / Letter C fallback
            }
        }

        // SPPT
        var wajarSppt = luasSppt * hargaSppt;
        var taksasiSppt = wajarSppt * persen;
        var likuidasiSppt = taksasiSppt * 0.70;
        var dispSpptWajar = document.getElementById('disp_sppt_wajar_' + idx);
        if (dispSpptWajar) dispSpptWajar.textContent = formatRupiah(wajarSppt);
        var dispSpptTak = document.getElementById('disp_sppt_taksasi_' + idx);
        if (dispSpptTak) dispSpptTak.textContent = formatRupiah(taksasiSppt);
        var dispSpptLik = document.getElementById('disp_sppt_likuidasi_' + idx);
        if (dispSpptLik) dispSpptLik.textContent = formatRupiah(likuidasiSppt);

        // Pasar — use luas SPPT as fallback when luas SHM is 0 (non-SHM types)
        var luasForPasar = luasShm > 0 ? luasShm : luasSppt;
        var wajarTanahPasar = luasForPasar * hargaPasar;
        var valBangunan = (luasB1 + luasB2) * hargaBangunan;
        var totalPasar = wajarTanahPasar + valBangunan;
        var taksasiPasar = totalPasar * persen;
        var likuidasiPasar = taksasiPasar * 0.70;

        document.getElementById('disp_pasar_total_' + idx).textContent = formatRupiah(totalPasar);
        document.getElementById('disp_pasar_taksasi_' + idx).textContent = formatRupiah(taksasiPasar);
        document.getElementById('disp_pasar_likuidasi_' + idx).textContent = formatRupiah(likuidasiPasar);

        recalcAgunanTotals();
    }

    function calcAgunanKendaraan(idx) {
        var card = document.getElementById('agunan-card-' + idx);
        if (!card) return;

        var tahun = parseInt(card.querySelector('[name="tahun[]"]').value) || 0;
        var nilaiPasar = parseFloat(card.querySelector('[name="nilai_pasar[]"]').value) || 0;

        var umur = 0;
        if (tahun > 0) {
            umur = new Date().getFullYear() - tahun;
        }

        var persen = 0;
        if (tahun > 0 && nilaiPasar > 0) {
            if (umur <= 5) persen = 0.85;
            else if (umur <= 10) persen = 0.75;
            else persen = 0.65;
        }

        var taksasi = nilaiPasar * persen;
        var likuidasi = taksasi * 0.70;

        document.getElementById('disp_kend_taksasi_' + idx).textContent = formatRupiah(taksasi);
        document.getElementById('disp_kend_likuidasi_' + idx).textContent = formatRupiah(likuidasi);

        recalcAgunanTotals();
    }

    function calcAgunanEmas(idx) {
        var card = document.getElementById('agunan-card-' + idx);
        if (!card) return;

        var berat = parseFloat(card.querySelector('[name="emas_berat[]"]').value) || 0;
        var harga = parseFloat(card.querySelector('[name="emas_harga_per_gram[]"]').value) || 0;

        var total = berat * harga;
        var taksasi = total * 0.95; // 95% dari harga hari ini

        var dispTotal = document.getElementById('disp_emas_total_' + idx);
        if (dispTotal) dispTotal.textContent = formatRupiah(total);
        var dispTaksasi = document.getElementById('disp_emas_taksasi_' + idx);
        if (dispTaksasi) dispTaksasi.textContent = formatRupiah(taksasi);

        recalcAgunanTotals();
    }

    function recalcAgunanTotals() {
        var cards = document.querySelectorAll('#agunan-container .agunan-card');
        var totalPasar = 0;
        var totalTaksasi = 0;
        var totalLikuidasi = 0;
        var count = 0;

        cards.forEach(function (card) {
            var jenisSel = card.querySelector('[name="jenis_jaminan[]"]');
            if (!jenisSel) return;
            var jenis = jenisSel.value;
            count++;

            if (jenis === 'tanah_bangunan') {
                var luasShm = parseFloat(card.querySelector('[name="luas_tanah[]"]').value) || 0;
                var luasSppt = parseFloat(card.querySelector('[name="luas_tanah_sppt[]"]').value) || 0;
                var luasForPasar = luasShm > 0 ? luasShm : luasSppt;
                var hargaPasar = parseFloat(card.querySelector('[name="harga_tanah_pasar[]"]').value) || 0;
                var luasB1 = parseFloat(card.querySelector('[name="luas_bangunan[]"]').value) || 0;
                var luasB2 = parseFloat(card.querySelector('[name="luas_bangunan_2[]"]').value) || 0;
                var hargaB = parseFloat(card.querySelector('[name="harga_bangunan_m2[]"]').value) || 0;
                var np = (luasForPasar * hargaPasar) + ((luasB1 + luasB2) * hargaB);

                var katSel = card.querySelector('[name="kategori_agunan[]"]');
                var surSel = card.querySelector('[name="jenis_surat[]"]');

                var persen = 0.50;
                var kat = katSel ? katSel.value : '';
                var sur = surSel ? surSel.value : '';

                if (kat === 'sawah_tegal') {
                    persen = 0.70;
                } else {
                    if (sur === 'SHM' || sur === 'SHGB') {
                        persen = 0.75;
                    } else {
                        persen = 0.50;
                    }
                }

                var nt = np * persen;
                var nl = nt * 0.70;
                totalPasar += np;
                totalTaksasi += nt;
                totalLikuidasi += nl;
            } else if (jenis === 'kendaraan') {
                var np2 = parseFloat(card.querySelector('[name="nilai_pasar[]"]').value) || 0;
                var tahun = parseInt(card.querySelector('[name="tahun[]"]').value) || 0;
                var umur = (tahun > 0) ? (new Date().getFullYear() - tahun) : 0;
                var pKend = 0;
                if (tahun > 0 && np2 > 0) {
                    if (umur <= 5) pKend = 0.85;
                    else if (umur <= 10) pKend = 0.75;
                    else pKend = 0.65;
                }

                var nt2 = np2 * pKend;
                var nl2 = nt2 * 0.70;
                totalPasar += np2;
                totalTaksasi += nt2;
                totalLikuidasi += nl2;
            } else if (jenis === 'emas') {
                var berat = parseFloat(card.querySelector('[name="emas_berat[]"]').value) || 0;
                var harga = parseFloat(card.querySelector('[name="emas_harga_per_gram[]"]').value) || 0;
                var npEmas = berat * harga;
                var ntEmas = npEmas * 0.95;
                var nlEmas = ntEmas; // Likuidasi sama dengan taksasi untuk emas
                totalPasar += npEmas;
                totalTaksasi += ntEmas;
                totalLikuidasi += nlEmas;
            }
        });

        document.getElementById('total_nilai_pasar').textContent = formatRupiah(totalPasar);
        document.getElementById('total_nilai_taksasi').textContent = formatRupiah(totalTaksasi);
        document.getElementById('total_nilai_likuidasi').textContent = formatRupiah(totalLikuidasi);
        document.getElementById('total_count_agunan').textContent = count > 0
            ? 'Total ' + count + ' agunan tercatat'
            : 'Belum ada agunan ditambahkan';
    }

    // Auto-add first agunan entry on load
    document.addEventListener('DOMContentLoaded', function () {
        if (document.querySelectorAll('#agunan-container .agunan-card').length === 0) {
            addAgunan('tanah_bangunan');
        }
    });
</script>

<!-- TAB 4 CLOSED: AGUNAN -->

<!-- TAB 5: NERACA - Only for non-pegawai (regular employees) -->
<?php if (($jenis_pekerjaan ?? 'umum') === 'umum'): ?>
<div id="tab-neraca" class="tab-content">
    <h3 class="tab-title">5. Neraca (Data Aset & Kewajiban)</h3>
    <p class="text-muted">Neraca sebelum & sesudah kredit. Modal dihitung otomatis (Total Aktiva - Total Pasiva).</p>

    <div class="grid-2" style="margin-bottom:1rem;">
        <div>
            <label>Plafon Baru (Kredit Bawon)</label>
            <input type="text" id="neraca_info_plafon" readonly
                style="background:#f3f4f6; font-weight:bold; color:#1e40af;">
        </div>
        <div>
            <label>Pencairan ke Tabungan (Penambahan Dana)</label>
            <input type="number" id="neraca_pencairan" oninput="calcNeraca()" value="0">
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
        <!-- AKTIVA -->
        <div class="neraca-box">
            <h4
                style="color:#059669; text-align:center; border-bottom:2px solid #059669; padding-bottom:0.8rem; margin-top:0;">
                AKTIVA</h4>
            <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;" class="neraca-table">
                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left; padding:8px 4px;">Rekening</th>
                    <th style="padding:8px 4px; text-align:right;">Sebelum Kredit</th>
                    <th style="padding:8px 4px; text-align:right;">Sesudah Kredit</th>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Kas</td>
                    <td style="padding:4px;"><input type="number" name="neraca_kas" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="kas_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Tabungan</td>
                    <td style="padding:4px;"><input type="number" name="neraca_bank" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="tabungan_sesudah" readonly
                            style="background:#f3f4f6; color:#059669; width:100%; text-align:right; font-weight:600;">
                    </td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Tanah & Bgn</td>
                    <td style="padding:4px;">
                        <input type="text" id="disp_neraca_tanah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                        <input type="hidden" name="neraca_tanah">
                    </td>
                    <td style="padding:4px;"><input type="text" id="tanah_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Kendaraan</td>
                    <td style="padding:4px;">
                        <input type="text" id="disp_neraca_kend" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                        <input type="hidden" name="neraca_kendaraan">
                    </td>
                    <td style="padding:4px;"><input type="text" id="kend_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Stok</td>
                    <td style="padding:4px;"><input type="number" name="neraca_stok" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="stok_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <td style="padding:8px 4px;">Lainnya</td>
                    <td style="padding:4px;"><input type="number" name="neraca_lain" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="lainnya_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="font-weight:700; background:#ecfdf5; color:#065f46;">
                    <td style="padding:12px 4px;">TOTAL AKTIVA</td>
                    <td style="padding:12px 4px; text-align:right;" id="lbl_total_aktiva_seb">Rp 0</td>
                    <td style="padding:12px 4px; text-align:right;" id="lbl_total_aktiva_ses">Rp 0</td>
                </tr>
            </table>

            <div style="margin-top:1.5rem;">
                <strong style="color:#4b5563;">Rincian Tanah & Bangunan</strong>
                <div id="tanah-container"
                    style="background:#f9fafb; padding:10px; border-radius:6px; margin-top:8px; border:1px solid #e5e7eb;">
                </div>
                <button type="button" class="btn-save-section" onclick="addTanah()"
                    style="padding:6px 12px; font-size:0.85rem; margin-top:8px;">➕ Tambah Aset Tanah/Bgn</button>

                <strong style="display:block; margin-top:1.5rem; color:#4b5563;">Rincian Kendaraan</strong>
                <div id="kend-container"
                    style="background:#f9fafb; padding:10px; border-radius:6px; margin-top:8px; border:1px solid #e5e7eb;">
                </div>
                <button type="button" class="btn-save-section" onclick="addKendaraan()"
                    style="padding:6px 12px; font-size:0.85rem; margin-top:8px;">➕ Tambah Aset Kendaraan</button>
            </div>
        </div>

        <!-- PASIVA -->
        <div class="neraca-box">
            <h4
                style="color:#dc2626; text-align:center; border-bottom:2px solid #dc2626; padding-bottom:0.8rem; margin-top:0;">
                PASIVA</h4>
            <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;" class="neraca-table">
                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                    <th style="text-align:left; padding:8px 4px;">Rekening</th>
                    <th style="padding:8px 4px; text-align:right;">Sebelum Kredit</th>
                    <th style="padding:8px 4px; text-align:right;">Sesudah Kredit</th>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Pajak dan PBB</td>
                    <td style="padding:4px;"><input type="number" name="neraca_hutang_lain" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="pajak_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:1px solid #f3f4f6;">
                    <td style="padding:8px 4px;">Pinjaman Lain</td>
                    <td style="padding:4px;"><input type="number" name="neraca_pinjaman_bri" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="bri_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;"></td>
                </tr>
                <tr style="border-bottom:1px solid #e5e7eb;">
                    <td style="padding:8px 4px;">Pinjaman Bawon</td>
                    <td style="padding:4px;"><input type="number" name="neraca_pinjaman_bawon" oninput="calcNeraca()"
                            style="width:100%; text-align:right; font-weight:600;"></td>
                    <td style="padding:4px;"><input type="text" id="bawon_sesudah" readonly
                            style="background:#f3f4f6; color:#dc2626; width:100%; text-align:right; font-weight:600;">
                    </td>
                </tr>
                <tr style="background:#fee2e2;">
                    <td style="padding:8px 4px; font-weight:700;">TOTAL PINJAMAN</td>
                    <td style="padding:8px 4px; text-align:right; font-weight:700;" id="tot_pinj_seb">Rp 0</td>
                    <td style="padding:8px 4px; text-align:right; font-weight:700;" id="tot_pinj_ses">Rp 0</td>
                </tr>
                <tr style="border-bottom:2px solid #e5e7eb;">
                    <td style="padding:10px 4px; font-weight:700;">MODAL <small
                            style="font-weight:normal; color:#6b7280;"><br>(Otomatis)</small></td>
                    <td style="padding:4px;"><input type="text" id="modal_sebelum" name="neraca_modal" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:700; color:#4f46e5; font-size:1.05rem;">
                    </td>
                    <td style="padding:4px;"><input type="text" id="modal_sesudah" readonly
                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:700; color:#4f46e5; font-size:1.05rem;">
                    </td>
                </tr>
                <tr style="font-weight:700; background:#fef2f2; color:#b91c1c;">
                    <td style="padding:12px 4px;">TOTAL PASIVA</td>
                    <td style="padding:12px 4px; text-align:right;" id="lbl_total_pasiva_seb">Rp 0</td>
                    <td style="padding:12px 4px; text-align:right;" id="lbl_total_pasiva_ses">Rp 0</td>
                </tr>
            </table>

            <div
                style="margin-top:1.5rem; padding:1.2rem; background:#eff6ff; border-radius:8px; border-left:4px solid #3b82f6; font-size:0.9rem; line-height:1.6;">
                <strong style="color:#1e3a8a;">Informasi Neraca:</strong>
                <ul style="margin:5px 0 0 20px; padding:0; color:#1e40af;">
                    <li><b>Modal</b> otomatis dihitung agar Total Aktiva & Pasiva selalu seimbang (Balance).</li>
                    <li>Sistem otomatis menjumlahkan <b>Plafon Baru</b> pada Neraca <b>Sesudah Kredit</b> di bagian
                        Pinjaman Bawon.</li>
                </ul>
            </div>

            <!-- Hidden field untuk value modal yg disimpan -->
            <input type="hidden" name="neraca_modal" id="hidden_neraca_modal">
        </div>
    </div>

    <button type="button" id="btn-save-neraca" class="btn-save-section" onclick="saveSection('neraca')"
        style="margin-top:2rem; width:100%; padding:14px; font-size:1.1rem;">
        <span class="spinner"></span>
        <span class="btn-text">Simpan Neraca</span>
    </button>
    <div id="toast-neraca" class="toast-msg"></div>
</div>
<?php endif; // End of neraca tab (only for umum jenis_pekerjaan) ?>

<!-- TAB 6: ANALISA 6C -->
<div id="tab-6c" class="tab-content">
    <h3 class="tab-title">6. Analisa 6C — Credit Assessment</h3>

    <!-- ===== 1. CHARACTER ===== -->
    <div class="card-6c">
        <div class="card-6c-header character-header">
            <span class="card-6c-number">1</span>
            <div>
                <h4 class="card-6c-title">CHARACTER (Karakter Debitur)</h4>
                <p class="card-6c-subtitle">Menilai itikad baik dan perilaku pembayaran debitur</p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Riwayat pembayaran kredit (SLIK OJK / Kolektibilitas)</li>
                <li>Kejujuran dan keterbukaan saat wawancara</li>
                <li>Reputasi di lingkungan usaha dan tempat tinggal</li>
                <li>Kesesuaian gaya hidup dengan penghasilan</li>
                <li>Kedisplinan Membayar Kewajiban Lain</li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Riwayat pembayaran kredit (SLIK OJK / Kolektibilitas)</td>
                        <td><select class="character-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Kejujuran dan keterbukaan saat wawancara</td>
                        <td><select class="character-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Reputasi di lingkungan usaha dan tempat tinggal</td>
                        <td><select class="character-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Kesesuaian gaya hidup dengan penghasilan</td>
                        <td><select class="character-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Komitmen terhadap kewajiban keuangan</td>
                        <td><select class="character-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
            <input type="hidden" name="score_character" id="hidden_score_character">
            <div class="summary-row">
                <label>Skor Numerik</label>
                <input type="text" name="skor_character" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_character" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_character" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Risiko</label>
                <textarea name="catatan_character" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== 2. CAPACITY ===== -->
    <div class="card-6c">
        <div class="card-6c-header capacity-header">
            <span class="card-6c-number">2</span>
            <div>
                <h4 class="card-6c-title">CAPACITY (Kemampuan Bayar)</h4>
                <p class="card-6c-subtitle">Menilai kemampuan debitur menghasilkan cashflow untuk membayar kredit</p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Stabilitas dan keberlanjutan omzet usaha</li>
                <li>Laba usaha Per bulan</li>
                <li>Cashflow usaha per bulan</li>
                <li>Net cashflow setelah biaya hidup dan cicilan lain</li>
                <li>Kemampuan Membayar Angsuran Perbulan </li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Stabilitas dan keberlanjutan omzet usaha</td>
                        <td><select class="capacity-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Laba usaha Per bulan</td>
                        <td><select class="capacity-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Cashflow usaha per bulan</td>
                        <td><select class="capacity-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Net cashflow setelah biaya hidup dan cicilan lain</td>
                        <td><select class="capacity-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Kemampuan Membayar Angsuran Perbulan </td>
                        <td><select class="capacity-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
                            <input type="hidden" name="score_capacity">
                            <div class="summary-row">
                                <label>Skor Numerik</label>
                                <input type="text" name="skor_capacity" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_capacity" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_capacity" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Hasil Perhitungan Cashflow / DSCR</label>
                <textarea name="catatan_capacity" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== 3. CAPITAL ===== -->
    <div class="card-6c">
        <div class="card-6c-header capital-header">
            <span class="card-6c-number">3</span>
            <div>
                <h4 class="card-6c-title">CAPITAL (Permodalan)</h4>
                <p class="card-6c-subtitle">Menilai kekuatan modal sendiri dan komitmen debitur dalam usaha</p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Besarnya Modal Sendiri Dalam Usaha</li>
                <li>Struktur permodalan usaha</li>
                <li>Ketersediaan dana cadangan (buffer)</li>
                <li>Persediaan/Asset Lancar Usaha</li>
                <li>Keterlibatan dana pribadi dalam usaha</li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Besarnya Modal Sendiri Dalam Usaha</td>
                        <td><select class="capital-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Struktur permodalan usaha</td>
                        <td><select class="capital-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Ketersediaan dana cadangan (buffer)</td>
                        <td><select class="capital-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Persediaan/Asset Lancar Usaha</td>
                        <td><select class="capital-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Keterlibatan dana pribadi dalam usaha</td>
                        <td><select class="capital-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
            <input type="hidden" name="score_capital">
            <div class="summary-row">
                <label>Skor Numerik</label>
                <input type="text" name="skor_capital" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_capital" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_capital" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Penilaian Tingkat Permodalan</label>
                <textarea name="catatan_capital" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== 4. COLLATERAL ===== -->
    <div class="card-6c">
        <div class="card-6c-header collateral-header">
            <span class="card-6c-number">4</span>
            <div>
                <h4 class="card-6c-title">COLLATERAL (Agunan)</h4>
                <p class="card-6c-subtitle">Menilai kualitas agunan sebagai mitigasi risiko kredit</p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Nilai pasar agunan (market value)</li>
                <li>Legalitas dan keabsahan dokumen agunan</li>
                <li>Kemudahan likuidasi agunan</li>
                <li>Coverage agunan terhadap plafond kredit</li>
                <li>Risiko penurunan nilai agunan</li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Nilai pasar agunan (market value)</td>
                        <td><select class="collateral-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Legalitas dan keabsahan dokumen agunan</td>
                        <td><select class="collateral-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Kemudahan likuidasi agunan</td>
                        <td><select class="collateral-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Coverage agunan terhadap plafond kredit</td>
                        <td><select class="collateral-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Risiko penurunan nilai agunan</td>
                        <td><select class="collateral-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
            <input type="hidden" name="score_collateral">
            <div class="summary-row">
                <label>Skor Numerik</label>
                <input type="text" name="skor_collateral" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_collateral" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_collateral" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Kesimpulan Kecukupan Agunan</label>
                <textarea name="catatan_collateral" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== 5. CONDITION ===== -->
    <div class="card-6c">
        <div class="card-6c-header condition-header">
            <span class="card-6c-number">5</span>
            <div>
                <h4 class="card-6c-title">CONDITION OF ECONOMY / BUSINESS</h4>
                <p class="card-6c-subtitle">Menilai pengaruh faktor eksternal terhadap kelangsungan usaha</p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Prospek Usaha Kedepan</li>
                <li>Tingkat persaingan pasar</li>
                <li>Stabilitas Permintaan Pasar</li>
                <li>Ketergantungan pada supplier dan pelanggan tertentu</li>
                <li>Sensitivitas terhadap perubahan regulasi dan harga</li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Prospek Usaha Kedepan</td>
                        <td><select class="condition-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Tingkat persaingan pasar</td>
                        <td><select class="condition-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Stabilitas Permintaan Pasar</td>
                        <td><select class="condition-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Ketergantungan pada supplier dan pelanggan tertentu</td>
                        <td><select class="condition-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Sensitivitas terhadap perubahan regulasi dan harga</td>
                        <td><select class="condition-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
            <input type="hidden" name="score_condition">
            <div class="summary-row">
                <label>Skor Numerik</label>
                <input type="text" name="skor_condition" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_condition" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_condition" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Ringkasan Risiko Eksternal Usaha</label>
                <textarea name="catatan_condition" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== 6. CONSTRAINT ===== -->
    <div class="card-6c">
        <div class="card-6c-header constraint-header">
            <span class="card-6c-number">6</span>
            <div>
                <h4 class="card-6c-title">CONSTRAINT (Hambatan / Risiko Khusus)</h4>
                <p class="card-6c-subtitle">Mengidentifikasi risiko non-keuangan yang dapat mengganggu kelancaran kredit
                </p>
            </div>
        </div>

        <div class="card-6c-indicators">
            <strong>Indikator Penilaian:</strong>
            <ul>
                <li>Lokasi usaha dan risiko lingkungan</li>
                <li>Potensi konflik keluarga dan partner usaha</li>
                <li>Risiko sosial, politik, dan force majeure</li>
            </ul>
        </div>

        <div class="card-6c-table">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="col-no">No</th>
                        <th class="col-indicator">Indikator</th>
                        <th class="col-nilai">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Lokasi usaha dan risiko lingkungan</td>
                        <td><select class="constraint-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Potensi konflik keluarga dan partner usaha</td>
                        <td><select class="constraint-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Risiko sosial, politik, dan force majeure</td>
                        <td><select class="constraint-6c" onchange="calc6C()">
                                <option value="">Pilih</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                                <option value="4">4</option>
                                <option value="5">5</option>
                            </select></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-6c-summary">
            <input type="hidden" name="score_constraint">
            <div class="summary-row">
                <label>Skor Numerik</label>
                <input type="text" name="skor_constraint" readonly>
            </div>
            <div class="summary-row">
                <label>Grade</label>
                <input type="text" name="grade_constraint" readonly>
            </div>
            <div class="summary-full">
                <label>Penilaian Kualitatif</label>
                <input type="text" name="kual_constraint" readonly>
            </div>
            <div class="summary-full">
                <label>Catatan & Daftar Risiko Khusus</label>
                <textarea name="catatan_constraint" readonly rows="2"></textarea>
            </div>
        </div>
    </div>

    <!-- ===== TOTAL SCORE 6C ===== -->
    <div
        style="background:linear-gradient(135deg,#1e293b,#334155); color:#fff; border-radius:12px; padding:1.5rem; margin-bottom:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
            <div>
                <div style="font-size:0.85rem; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">
                    Total Score 6C</div>
                <div style="font-size:2.5rem; font-weight:800;"><span id="total_score_5c">5.0</span>
                    <span style="font-size:1rem; opacity:0.6;">/ 5.0</span>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:0.85rem; opacity:0.7;">Kesimpulan</div>
                <div style="font-size:1.5rem; font-weight:700;" id="msg_score_5c">SANGAT LAYAK</div>
            </div>
        </div>
    </div>

    <!-- REKOMENDASI & CATATAN UMUM -->
    <div class="grid-2">
        <div class="custom-form-group">
            <label style="font-weight:700; color:var(--primary);">Rekomendasi Akhir</label>
            <select name="rekomendasi_6c" style="font-weight:600;">
                <option value="LAYAK">✅ LAYAK</option>
                <option value="LAYAK DENGAN SYARAT">⚠️ LAYAK DENGAN SYARAT</option>
                <option value="TIDAK LAYAK">❌ TIDAK LAYAK</option>
            </select>
        </div>
        <div class="custom-form-group">
            <label>Catatan Umum Analis</label>
            <textarea name="catatan_5c" rows="2" placeholder="Catatan tambahan analis..."></textarea>
        </div>
    </div>

    <button type="button" id="btn-save-6c" class="btn-save-section" onclick="saveSection('6c')">
        <span class="spinner"></span>
        <span class="btn-text">Simpan Analisa 6C</span>
    </button>
    <div id="toast-6c" class="toast-msg"></div>
</div>

<!-- TAB 7: SCORING -->
<div id="tab-scoring" class="tab-content">
    <h3 class="tab-title">7. Scoring & Summary</h3>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
        <div class="score-card">
            <p>Skor 6C</p>
            <div class="score-value" id="score_summary_5c">30 / 30 (Sangat Layak)</div>
        </div>
        <div class="score-card">
            <p>Repayment Capacity</p>
            <small class="text-muted">(<?= number_format($RPC_PERSEN_MAKS, 0) ?>% × <?= htmlspecialchars($RPC_DASAR_LABEL) ?>)</small>
            <small class="text-muted">(<?= number_format($RPC_PERSEN_MAKS, 0) ?>% × <?= htmlspecialchars($RPC_DASAR_LABEL) ?>)</small>
        </div>
    </div>

    <div style="background:#fff7ed; padding:1.5rem; border-radius:8px; border-left:4px solid #f97316; margin:2rem 0;">
        <p>Pastikan semua data di semua Tab telah terisi dengan benar sebelum menyimpan.</p>
    </div>

    <button type="button" id="btn-save-submit" class="btn-save-section"
        style="width:100%; padding:1rem; font-size:1.05rem; background-color: var(--primary); border-color: var(--primary);" onclick="saveSection('submit')">
        <span class="spinner"
            style="display:none;width:20px;height:20px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;margin:0 auto;"></span>
        <span class="btn-text">Submit Pengajuan Lengkap</span>
    </button>
    <div id="toast-submit" class="toast-msg"></div>
</div>
