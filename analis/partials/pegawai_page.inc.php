<?php
/**
 * Halaman form analis untuk PPPK / Perangkat Desa (tanpa tab usaha).
 * Variabel dari input.php: $jenis_pekerjaan, $form_banner_title, $catatan_revisi_display,
 * $edit_id_pengajuan, $prefill_json. Wajib set $pegawai_tipe_save sebelum include.
 */
if (!isset($pegawai_tipe_save) || !in_array($pegawai_tipe_save, ['pppk', 'perangkat_desa'], true)) {
    $pegawai_tipe_save = 'pppk';
}
$FORM_BANNER = $form_banner_title ?? '';
$CATATAN_REVISI_UI = $catatan_revisi_display ?? '';
$EDIT_ID_PENGAJUAN = isset($edit_id_pengajuan) ? (int) $edit_id_pengajuan : 0;
$PREFILL_JSON_OUT = $prefill_json ?? 'null';

/** @var array $RPC_CONFIG */
/** @var float $RPC_PERSEN_MAKS */
/** @var string $RPC_DASAR_LABEL */
/** @var string $RPC_AS_OF_DATE */
include __DIR__ . '/pegawai_head_raw.inc.php';
?>

<body>
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <div class="container form-content">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; flex-wrap:wrap;">
            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <button type="button" class="btn btn-secondary" onclick="if (document.referrer) { history.back(); } else { window.location.href = '../dashboard.php'; }">← Kembali</button>
                <h2 style="margin:0;">Input Analisa Kredit</h2>
            </div>
            <a href="riwayat.php" class="btn btn-secondary">Lihat Riwayat</a>
        </div>

        <?php if ($FORM_BANNER !== ''): ?>
            <div
                style="margin-top:1rem;padding:0.85rem 1.1rem;background:#eff6ff;border:1px solid #93c5fd;border-radius:10px;color:#1e3a5f;font-size:0.95rem;">
                <strong><?= htmlspecialchars($FORM_BANNER) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($CATATAN_REVISI_UI !== ''): ?>
            <div
                style="margin-top:1rem;padding:1rem 1.15rem;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;color:#78350f;font-size:0.92rem;line-height:1.5;">
                <strong>Catatan revisi / penolakan dari atasan:</strong><br>
                <?= nl2br(htmlspecialchars($CATATAN_REVISI_UI)) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($RPC_CONFIG['id_parameter'])): ?>
            <div style="margin-top:1rem;padding:0.85rem 1.1rem;background:#fefce8;border:1px solid #fef08a;border-radius:10px;color:#854d0e;font-size:0.95rem;">
                <strong>&#9888; Peringatan Parameter Belum Ada/Aktif:</strong> Sistem menggunakan logika fallback standar (<?= $RPC_PERSEN_MAKS ?>% × <?= $RPC_DASAR_LABEL ?>) karena parameter pengajuan <strong><?= htmlspecialchars($jenis_pekerjaan ?? $pegawai_tipe_save) ?></strong> ini belum dikonfigurasi / disetujui.
            </div>
        <?php else: ?>
            <div style="margin-top:1rem;padding:0.85rem 1.1rem;background:#f0fdfa;border:1px solid #5eead4;border-radius:10px;color:#134e4a;font-size:0.95rem;">
                <strong>&#10004; Repayment Parameter Tersinkronisasi:</strong> Menggunakan dasar <strong><?= $RPC_PERSEN_MAKS ?>% × <?= $RPC_DASAR_LABEL ?></strong> (As-of: <?= htmlspecialchars($RPC_AS_OF_DATE) ?>). <?= !empty($RPC_CONFIG['locked']) ? '<strong>[TERKUNCI]</strong>' : '' ?>
            </div>
        <?php endif; ?>

        <div class="form-stepper">
            <a href="#tab-pemohon" class="nav-link-step active" data-target="tab-pemohon">1. Data Pribadi</a>
            <a href="#tab-penghasilan" class="nav-link-step" data-target="tab-penghasilan">2. Analisa</a>
            <a href="#tab-jaminan" class="nav-link-step" data-target="tab-jaminan">3. SK/Avalis</a>
            <a href="#tab-agunan" class="nav-link-step" data-target="tab-agunan">4. Jaminan Agunan</a>
            <a href="#tab-struktur" class="nav-link-step" data-target="tab-struktur">5. Struktur Kredit</a>
            <a href="#tab-6c" class="nav-link-step" data-target="tab-6c">6. Analisa 6C</a>
            <a href="#tab-scoring" class="nav-link-step" data-target="tab-scoring">7. Review & Submit</a>
        </div>

        <form method="POST" enctype="multipart/form-data" onsubmit="return false;">
            <input type="hidden" id="id_pengajuan" name="id_pengajuan"
                value="<?= $EDIT_ID_PENGAJUAN > 0 ? (int) $EDIT_ID_PENGAJUAN : '' ?>">
            <input type="hidden" name="jenis_pekerjaan" id="jenis_pekerjaan_hidden"
                value="<?= htmlspecialchars($jenis_pekerjaan ?? 'umum', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="angsuran_diajukan" value="0">

            <div class="form-area">
                <?php if (isset($success)): ?>
                    <div style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1.5rem; border-radius:8px;">
                        &#10004; <?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div style="background:#fee2e2; color:#991b1b; padding:1rem; margin-bottom:1.5rem; border-radius:8px;">
                        &#9888; <?= $error ?></div>
                <?php endif; ?>

                <?php include __DIR__ . '/tab_pemohon_pegawai.inc.php'; ?>

                <?php
                if ($pegawai_tipe_save === 'pppk') {
                    include __DIR__ . '/tab_penghasilan_pppk_improved.inc.php';
                } else {
                    include __DIR__ . '/tab_penghasilan_desa_improved.inc.php';
                }
                ?>
                
                <?php
                if ($pegawai_tipe_save === 'pppk') {
                    include __DIR__ . '/tab_jaminan_pppk.inc.php';
                } else {
                    include __DIR__ . '/tab_jaminan_desa.inc.php';
                }
                ?>

                <?php include __DIR__ . '/tabs_kredit_lanjutan.inc.php'; ?>
            </div>
        </form>
    </div>
    <script>
        window.__ANALIS_PREFILL__ = <?= $PREFILL_JSON_OUT ?>;
    </script>
    <script>
        (function () {
            function escSel(s) {
                return String(s).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
            }
            function setId(id, value) {
                if (value === null || value === undefined) return;
                var el = document.getElementById(id);
                if (el) el.value = value;
            }
            function setField(name, value) {
                if (value === null || value === undefined) return;
                var el = document.querySelector('form [name="' + escSel(name) + '"]');
                if (!el || el.type === 'file') return;
                el.value = value;
            }
            document.addEventListener('DOMContentLoaded', function () {
                var P = window.__ANALIS_PREFILL__;
                if (!P || !P.pengajuan) return;
                var pg = P.pengajuan;
                var jenis = (pg.jenis_pekerjaan || '').toString().trim();

                Object.keys(pg).forEach(function (k) {
                    if (k.indexOf('[') !== -1) return;
                    setField(k, pg[k]);
                });
                var selStat = document.querySelector('select[name="status_perkawinan"]');
                if (selStat && pg.status_perkawinan) selStat.value = pg.status_perkawinan;
                if (typeof togglePasangan === 'function' && selStat) togglePasangan(selStat.value);

                var marker = (pg.nama_usaha || '').toString().trim().toUpperCase();

                if (jenis === 'pppk' || marker === 'PPPK') {
                    setId('jaminan_bidang_usaha', pg.bidang_usaha);
                    setId('jaminan_sk_avalis', pg.sk_avalis);
                    setId('jaminan_no_sk_agunan', pg.pppk_agunan_no_sk);
                    
                    setId('pppk_gaji', pg.omset_per_bulan);
                    setId('pppk_biaya_hidup', pg.biaya_operasional);
                    // Tanggal kontrak (stored in lama_usaha & departemen_bagian for backward compat)
                    if (pg.lama_usaha) setId('pppk_tgl_awal', pg.lama_usaha);
                    if (pg.departemen_bagian) setId('pppk_tgl_akhir', pg.departemen_bagian);
                    if (pg.lama_usaha && pg.departemen_bagian) {
                        setTimeout(function() {
                            if (typeof calculateSisaMasaKerja === 'function') calculateSisaMasaKerja();
                        }, 100);
                    }
                    // Prefill angsuran Bank Wonosobo (total cicilan lain)
                    var totalAngsuranLama = parseFloat(pg.cicilan_lain) || 0;
                    if (totalAngsuranLama > 0) {
                        setTimeout(function() {
                            if (typeof pppkAddAngsuran === 'function') {
                                pppkAddAngsuran();
                                var nominalInput = document.querySelector('.pppk-angsuran-nominal');
                                if (nominalInput) {
                                    nominalInput.value = totalAngsuranLama;
                                    if (typeof pppkUpdateTotalAngsuran === 'function') pppkUpdateTotalAngsuran();
                                }
                            }
                        }, 200);
                    }
                } else if (jenis === 'perangkat_desa' || marker === 'PERANGKAT_DESA') {
                    var jabatan = pg.jabatan || '';
                    setId('desk_jabatan', jabatan);
                    setId('desk_no_sk', pg.bidang_usaha);
                    setId('jaminan_sk_avalis', pg.sk_avalis);
                    setId('jaminan_no_sk_agunan', pg.pppk_agunan_no_sk);
                    setId('jaminan_sk_jabatan_display', pg.bidang_usaha);
                    // Tanggal kontrak (stored in lama_usaha & departemen_bagian)
                    if (pg.lama_usaha) setId('desk_tgl_mulai', pg.lama_usaha);
                    
                    // Set tanggal akhir/lahir berdasarkan jabatan
                    if (jabatan === 'KEPALA DESA') {
                        if (pg.departemen_bagian) setId('desk_tgl_akhir', pg.departemen_bagian);
                    } else if (['SEKRETARIS DESA', 'KEPALA DUSUN', 'KAUR'].indexOf(jabatan) !== -1) {
                        if (pg.departemen_bagian) setId('desk_tgl_lahir', pg.departemen_bagian);
                    }
                    
                    if (pg.lama_usaha || (pg.departemen_bagian && jabatan)) {
                        setTimeout(function() {
                            if (typeof toggleDesaJabatanFields === 'function') toggleDesaJabatanFields();
                            if (typeof calculateSisaMasaJabatan === 'function') calculateSisaMasaJabatan();
                        }, 100);
                    }
                    // Prefill angsuran Bank Wonosobo (total cicilan lain)
                    var totalAngsuranLamaDesa = parseFloat(pg.cicilan_lain) || 0;
                    if (totalAngsuranLamaDesa > 0) {
                        setTimeout(function() {
                            if (typeof desaAddAngsuran === 'function') {
                                desaAddAngsuran();
                                var nominalInput = document.querySelector('.desa-angsuran-nominal');
                                if (nominalInput) {
                                    nominalInput.value = totalAngsuranLamaDesa;
                                    if (typeof desaUpdateTotalAngsuran === 'function') desaUpdateTotalAngsuran();
                                }
                            }
                        }, 200);
                    }
                    var om = parseFloat(pg.omset_per_bulan) || 0;
                    var tamb = parseFloat(pg.cashflow_usaha) || 0;
                    setId('desk_penghasilan_tetap', Math.round(Math.max(0, om - tamb)));
                    setId('desk_tambahan_penghasilan', Math.round(tamb));
                }

                if (P.neraca && P.neraca.aktiva_kas != null) {
                    setField('neraca_kas', P.neraca.aktiva_kas);
                    setField('neraca_bank', P.neraca.aktiva_tabungan);
                    setField('neraca_tanah', P.neraca.aktiva_tanah);
                    setField('neraca_kendaraan', P.neraca.aktiva_kendaraan);
                    setField('neraca_stok', P.neraca.aktiva_stok);
                    setField('neraca_lain', P.neraca.aktiva_lainnya);
                    setField('neraca_hutang_bank', P.neraca.pasiva_hutang_bank);
                    setField('neraca_hutang_lain', P.neraca.pasiva_hutang_lain);
                    setField('neraca_modal', P.neraca.pasiva_modal);
                }
                if (P.analisa_5c) {
                    var a = P.analisa_5c;
                    setField('score_character', a.character_score);
                    setField('score_capacity', a.capacity_score);
                    setField('score_capital', a.capital_score);
                    setField('score_condition', a.condition_score);
                    setField('score_collateral', a.collateral_score);
                    setField('score_constraint', a.constraint_score);
                    setField('catatan_5c', a.catatan_5c);
                    setField('catatan_character', a.catatan_character);
                    setField('catatan_capacity', a.catatan_capacity);
                    setField('catatan_capital', a.catatan_capital);
                    setField('catatan_collateral', a.catatan_collateral);
                    setField('catatan_condition', a.catatan_condition);
                    setField('catatan_constraint', a.catatan_constraint_risk);
                    if (a.rekomendasi) {
                        var rs = document.querySelector('select[name="rekomendasi_6c"]');
                        if (rs) rs.value = a.rekomendasi;
                    }
                }
                (P.jaminan_tanah || []).forEach(function (row) {
                    if (typeof addAgunan === 'function') {
                        addAgunan('tanah_bangunan');
                        var idx = agunanCounter - 1;
                        var card = document.getElementById('agunan-card-' + idx);
                        if (!card) return;
                        var setN = function (nm, v) {
                            if (v == null) return;
                            var inp = card.querySelector('[name="' + nm + '"]');
                            if (inp) inp.value = v;
                        };
                        setN('luas_tanah[]', row.luas_tanah);
                        setN('harga_tanah_sppt[]', row.harga_tanah_sppt);
                        setN('harga_tanah_pasar[]', row.harga_tanah_pasar);
                        setN('luas_bangunan[]', row.luas_bangunan);
                        setN('luas_bangunan_2[]', row.luas_bangunan_2);
                        setN('harga_bangunan_m2[]', row.harga_bangunan_m2);
                        setN('nomor_surat[]', row.nomor_surat);
                        setN('atas_nama[]', row.atas_nama);
                        setN('alamat[]', row.alamat_agunan);
                        var ks = card.querySelector('select[name="kategori_agunan[]"]');
                        if (ks && row.kategori_agunan) ks.value = row.kategori_agunan;
                        var js = card.querySelector('select[name="jenis_surat[]"]');
                        if (js && row.jenis_surat) js.value = row.jenis_surat;
                        if (typeof calcAgunanTanah === 'function') calcAgunanTanah(idx);
                    }
                });
                (P.jaminan_kendaraan || []).forEach(function (row) {
                    if (typeof addAgunan === 'function') {
                        addAgunan('kendaraan');
                        var idx = agunanCounter - 1;
                        var card = document.getElementById('agunan-card-' + idx);
                        if (!card) return;
                        var setN = function (nm, v) {
                            if (v == null) return;
                            var inp = card.querySelector('[name="' + nm + '"]');
                            if (inp) inp.value = v;
                        };
                        setN('merk[]', row.merk);
                        setN('tipe[]', row.tipe);
                        setN('tahun[]', row.tahun_pembuatan);
                        setN('nopol[]', row.no_polisi);
                        setN('norangka[]', row.no_rangka);
                        setN('nomesin[]', row.no_mesin);
                        setN('bpkb_nama[]', row.nama_pemilik);
                        setN('nilai_pasar[]', row.nilai_pasar);
                        if (typeof calcAgunanKendaraan === 'function') calcAgunanKendaraan(idx);
                    }
                });
                if (typeof calcStruktur === 'function') calcStruktur();
                if (typeof calc6C === 'function') calc6C();
                if (typeof recalcAgunanTotals === 'function') recalcAgunanTotals();
                if (typeof updateScoringSummary === 'function') updateScoringSummary();
                
                // ===== LINK TANGGAL LAHIR: Tab Pemohon → Tab Penghasilan (Desa) =====
                syncTanggalLahirToDesa();
            });
        })();
        
        /**
         * Function: Link tanggal lahir dari Tab Pemohon ke Tab Penghasilan (Perangkat Desa)
         * Sehingga user tidak perlu input tanggal lahir 2 kali
         */
        function syncTanggalLahirToDesa() {
            var tanggalLahirInput = document.querySelector('[name="tanggal_lahir"]');
            var deskTglLahirHidden = document.getElementById('desk_tgl_lahir');
            var deskTglLahirDisplay = document.getElementById('desk_tgl_lahir_display');
            
            if (!tanggalLahirInput || !deskTglLahirHidden) return;
            
            // Function untuk update display dan hidden value
            var updateDeskTglLahir = function() {
                var value = tanggalLahirInput.value;
                deskTglLahirHidden.value = value;
                
                if (value) {
                    // Format tampilan: DD Bulan YYYY
                    var date = new Date(value + 'T00:00:00');
                    var bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                    var formatted = ('0' + date.getDate()).slice(-2) + ' ' + 
                                  bulan[date.getMonth()] + ' ' + 
                                  date.getFullYear();
                    deskTglLahirDisplay.textContent = formatted;
                } else {
                    deskTglLahirDisplay.textContent = '-';
                }
                
                // Trigger perhitungan usia jika jabatan sudah dipilih
                if (typeof calculateSisaMasaJabatan === 'function') {
                    setTimeout(function() {
                        calculateSisaMasaJabatan();
                    }, 50);
                }
            };
            
            // Sync on initial load
            updateDeskTglLahir();
            
            // Sync ketika tanggal_lahir berubah
            tanggalLahirInput.addEventListener('change', updateDeskTglLahir);
            tanggalLahirInput.addEventListener('input', updateDeskTglLahir);
        }
