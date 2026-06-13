<?php
/**
 * Form umum analisa — di-include dari input.php / form_pppk / form_desa.
 * Parent mengisi: $jenis_pekerjaan, $form_banner_title, $catatan_revisi_display,
 * $edit_id_pengajuan, $prefill_json (string JSON atau 'null').
 */
if (!isset($jenis_pekerjaan)) {
    $jenis_pekerjaan = 'umum';
}
$FORM_BANNER = $form_banner_title ?? '';
$CATATAN_REVISI_UI = $catatan_revisi_display ?? '';
$EDIT_ID_PENGAJUAN = isset($edit_id_pengajuan) ? (int) $edit_id_pengajuan : 0;
$PREFILL_JSON_OUT = $prefill_json ?? 'null';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Input Analisa Kredit</title>
    <!-- GLOBAL STYLE & FONTS -->
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>window.__CSRF_TOKEN__ = <?= json_encode(generateCsrfToken(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>

    <style>
        .form-content {
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
        }

        /* Main Form Area: Full width now */
        .form-area {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            border: 1px solid #e2e8f0;
            margin-top: 1.5rem;
        }

        /* Tab Contents */
        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        .tab-title {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            color: var(--primary);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Controls */
        .custom-form-group {
            margin-bottom: 1.25rem;
        }

        .custom-form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #64748b;
        }

        .custom-form-group input,
        .custom-form-group select,
        .custom-form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
        }

        .custom-form-group input:focus {
            border-color: var(--primary);
            background: #fff;
            outline: none;
        }

        /* Neraca & 5C Styling */
        .neraca-wrapper {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .table-clean th {
            background: #f1f5f9;
            padding: 0.75rem;
            text-align: left;
        }

        .table-clean td {
            padding: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Scoring Card */
        .score-card {
            background: #f0f9ff;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #bae6fd;
            margin-bottom: 1rem;
        }

        .score-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0.5rem 0;
        }

        .section-header {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            font-weight: 700;
            margin: 1.5rem 0 1rem 0;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .calc-display {
            background: #f1f5f9;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 700;
            text-align: right;
            border: 1px solid #cbd5e1;
            color: #334155;
        }

        /* Save Button per Tab - Modern Minimalist */
        .btn-save-section {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background-color: #18181b; /* Zinc 900 */
            color: #ffffff;
            border: 1px solid #18181b;
            padding: 0.65rem 1.75rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            letter-spacing: 0.3px;
            cursor: pointer;
            margin-top: 1.5rem;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .btn-save-section:hover {
            background-color: #27272a; /* Zinc 800 */
            border-color: #27272a;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-save-section:active {
            transform: scale(0.98);
        }

        .btn-save-section:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-save-section .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        .btn-save-section.loading .spinner {
            display: inline-block;
        }

        .btn-save-section.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Form Stepper Navigation */
        .form-stepper {
            margin-top: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .form-stepper .nav-link-step {
            text-decoration: none;
            color: #334155;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 0.5rem 0.75rem;
            border-radius: 999px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.15s;
        }

        .form-stepper .nav-link-step:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f0f7ff;
        }

        .form-stepper .nav-link-step.active {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }

        /* Toast Notification */
        .toast-msg {
            display: none;
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            margin-top: 1rem;
            font-weight: 500;
            animation: fadeIn 0.3s ease;
        }

        .toast-msg.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .toast-msg.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
    </style>
    <script>
        function showTabFromHash() {
            var hash = window.location.hash;
            var tabId = 'tab-pemohon'; // default
            if (hash && hash.startsWith('#tab-')) {
                tabId = hash.substring(1); // remove #
            }

            // Validate exist
            if (!document.getElementById(tabId)) tabId = 'tab-pemohon';

            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            // Show selected
            document.getElementById(tabId).classList.add('active');

            // --- UPDATE SIDEBAR ACTIVE STATE ---
            // Remove active from all steps first
            document.querySelectorAll('.nav-link-step').forEach(el => el.classList.remove('active'));

            // Find link corresponding to this tab
            var activeLink = document.querySelector(`.nav-link-step[data-target="${tabId}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
            }

            if (tabId === 'tab-scoring') updateScoringSummary();
        }

        // Listen for changes
        window.addEventListener('hashchange', showTabFromHash);
        window.addEventListener('DOMContentLoaded', showTabFromHash);

        // Legacy toggleJaminan — now handled per-card by toggleAgunanForm(idx)
        function toggleJaminan() {
            // No-op: multi-agunan uses toggleAgunanForm per card
        }

        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
        }

        /* ── Rupiah input formatting helpers ── */
        function toRupiahStr(val) {
            if (!val && val !== 0) return '';
            var n = parseInt(String(val).replace(/[^0-9]/g, ''), 10);
            if (isNaN(n)) return '';
            return n.toLocaleString('id-ID');
        }
        function parseRupiahInput(str) {
            if (!str) return 0;
            return parseInt(String(str).replace(/[^0-9]/g, ''), 10) || 0;
        }
        function initRupiahField(el) {
            if (!el || el.dataset.rpInit) return;
            el.dataset.rpInit = '1';
            el.addEventListener('input', function () {
                var pos = this.selectionStart;
                var oldLen = this.value.length;
                var raw = this.value.replace(/[^0-9]/g, '');
                this.value = raw ? parseInt(raw, 10).toLocaleString('id-ID') : '';
                var newLen = this.value.length;
                var newPos = pos + (newLen - oldLen);
                this.setSelectionRange(newPos, newPos);
            });
            // Format initial value
            var v = parseInt(String(el.value).replace(/[^0-9]/g, ''), 10);
            if (v > 0) el.value = v.toLocaleString('id-ID');
            else el.value = '';
        }

        // Legacy calcTanah — now handled per-card by calcAgunanTanah(idx)
        function calcTanah() {
            // No-op: multi-agunan uses calcAgunanTanah per card
        }

        // ===== Dynamic Neraca rows (Tanah & Kendaraan) =====
        function createTanahRow() {
            var div = document.createElement('div');
            div.className = 'neraca-row';
            div.style = 'display:flex; gap:8px; margin-bottom:6px; align-items:center;';
            div.innerHTML = '<input type="text" name="tanah_lokasi[]" placeholder="Nama Aset" style="flex:2; padding:6px;">'
                + '<input type="number" name="tanah_luas[]" placeholder="Tahun Perolehan" style="width:130px; padding:6px;">'
                + '<input type="number" name="tanah_nilai[]" placeholder="Nilai (Rp)" style="width:160px; padding:6px;" oninput="calcNeraca()">'
                + '<button type="button" onclick="removeRow(this)" style="padding:6px 8px;">🗑</button>';
            return div;
        }

        function createKendRow() {
            var div = document.createElement('div');
            div.className = 'neraca-row';
            div.style = 'display:flex; gap:8px; margin-bottom:6px; align-items:center;';
            div.innerHTML = '<input type="text" name="kendaraan_jenis[]" placeholder="Nama Kendaraan" style="flex:2; padding:6px;">'
                + '<input type="text" name="kendaraan_tahun[]" placeholder="Tahun" style="width:130px; padding:6px;">'
                + '<input type="number" name="kendaraan_nilai[]" placeholder="Nilai (Rp)" style="width:160px; padding:6px;" oninput="calcNeraca()">'
                + '<button type="button" onclick="removeRow(this)" style="padding:6px 8px;">🗑</button>';
            return div;
        }

        function addTanah() {
            var c = document.getElementById('tanah-container');
            if (!c) return;
            c.appendChild(createTanahRow());
            calcNeraca();
        }

        function addKendaraan() {
            var c = document.getElementById('kend-container');
            if (!c) return;
            c.appendChild(createKendRow());
            calcNeraca();
        }

        function removeRow(btn) {
            var row = btn.closest('.neraca-row');
            if (row) { row.parentNode.removeChild(row); calcNeraca(); }
        }

        function calcNeraca() {
            let plafon = 0;
            let plafEl = document.querySelector('[name=jumlah_kredit]');
            if (plafEl) plafon = parseFloat(plafEl.value) || 0;

            let info = document.getElementById('neraca_info_plafon');
            if (info) info.value = formatRupiah(plafon);

            let pencairan = parseFloat(document.getElementById('neraca_pencairan') ? document.getElementById('neraca_pencairan').value : 0) || 0;

            let kas = parseFloat(document.querySelector('[name=neraca_kas]').value) || 0;
            let bank = parseFloat(document.querySelector('[name=neraca_bank]').value) || 0;

            let tanah = 0;
            var tanahInputs = document.querySelectorAll('input[name="tanah_nilai[]"]');
            if (tanahInputs.length > 0) {
                tanahInputs.forEach(function (el) { tanah += parseFloat(el.value) || 0; });
            }
            var fTanah = document.querySelector('[name=neraca_tanah]'); if (fTanah) fTanah.value = tanah;
            var dTanah = document.getElementById('disp_neraca_tanah'); if (dTanah) dTanah.value = formatRupiah(tanah);

            let kend = 0;
            var kendInputs = document.querySelectorAll('input[name="kendaraan_nilai[]"]');
            if (kendInputs.length > 0) {
                kendInputs.forEach(function (el) { kend += parseFloat(el.value) || 0; });
            }
            var fKend = document.querySelector('[name=neraca_kendaraan]'); if (fKend) fKend.value = kend;
            var dKend = document.getElementById('disp_neraca_kend'); if (dKend) dKend.value = formatRupiah(kend);

            let stok = parseFloat(document.querySelector('[name=neraca_stok]').value) || 0;
            let lain = parseFloat(document.querySelector('[name=neraca_lain]').value) || 0;

            let totalAktivaSeb = kas + bank + tanah + kend + stok + lain;

            let bankSesudah = bank + pencairan;
            let totalAktivaSes = kas + bankSesudah + tanah + kend + stok + lain;

            let pajak = parseFloat(document.querySelector('[name=neraca_hutang_lain]').value) || 0;
            let pinjBri = parseFloat(document.querySelector('[name=neraca_pinjaman_bri]').value) || 0;
            let pinjBawon = parseFloat(document.querySelector('[name=neraca_pinjaman_bawon]').value) || 0;

            let totalPinjSeb = pinjBri + pinjBawon;
            let bawonSesudah = pinjBawon + plafon;
            let totalPinjSes = pinjBri + bawonSesudah;

            let pasivaTanpaModalSeb = pajak + totalPinjSeb;
            // Modal = Total Aktiva - (Pajak + Total Pinjaman)
            // Modal menjadi value keseimbangan
            let modalSeb = totalAktivaSeb - pasivaTanpaModalSeb;
            let modalSes = totalAktivaSes - (pajak + totalPinjSes);

            // Fungsi set text content atau input value
            let setVal = function (id, val) {
                let e = document.getElementById(id);
                if (e) {
                    if (e.tagName === 'INPUT') e.value = formatRupiah(val);
                    else e.textContent = formatRupiah(val);
                }
            };

            setVal('kas_sesudah', kas);
            setVal('tabungan_sesudah', bankSesudah);
            setVal('tanah_sesudah', tanah);
            setVal('kend_sesudah', kend);
            setVal('stok_sesudah', stok);
            setVal('lainnya_sesudah', lain);
            setVal('lbl_total_aktiva_seb', totalAktivaSeb);
            setVal('lbl_total_aktiva_ses', totalAktivaSes);

            setVal('pajak_sesudah', pajak);
            setVal('bri_sesudah', pinjBri);
            setVal('bawon_sesudah', bawonSesudah);
            setVal('tot_pinj_seb', totalPinjSeb);
            setVal('tot_pinj_ses', totalPinjSes);

            setVal('modal_sebelum', modalSeb);
            setVal('modal_sesudah', modalSes);

            setVal('lbl_total_pasiva_seb', totalAktivaSeb);
            setVal('lbl_total_pasiva_ses', totalAktivaSes);

            let hiddenModal = document.getElementById('hidden_neraca_modal');
            if (hiddenModal) hiddenModal.value = modalSeb;
        }

        function calc6C() {
            // Compute average per category based on indicator dropdowns
            var categories = ['character', 'capacity', 'capital', 'collateral', 'condition', 'constraint'];
            var catScores = {};
            var catCounts = {};

            categories.forEach(function (cat) {
                var elems = document.querySelectorAll('.' + cat + '-6c');
                var sum = 0, cnt = 0;
                elems.forEach(function (el) {
                    var v = parseInt(el.value);
                    if (!isNaN(v) && v > 0) { sum += v; cnt++; }
                });
                var avg = cnt > 0 ? sum / cnt : 0;
                avg = Math.round(avg * 100) / 100;
                catScores[cat] = avg;
                catCounts[cat] = cnt;

                // Write skor to readonly field
                var out = document.querySelector('[name="skor_' + cat + '"]');
                if (out) out.value = avg;

                // Update hidden legacy score if needed
                var hid = document.querySelector('input[name="score_' + cat + '"]');
                if (hid) {
                    hid.value = cnt > 0 ? Math.min(5, Math.max(1, Math.round(avg))) : '';
                }

                // Auto-compute grade for all categories
                var grade = '';
                if (avg <= 0) grade = '';
                else if (avg <= 1.5) grade = '1 — Sangat Baik';
                else if (avg <= 2.5) grade = '2 — Baik';
                else if (avg <= 3.5) grade = '3 — Cukup';
                else if (avg <= 4.5) grade = '4 — Kurang';
                else grade = '5 — Sangat Kurang/Jelek';
                var outGrade = document.querySelector('[name="grade_' + cat + '"]');
                if (outGrade) outGrade.value = grade;

                // Auto-compute qualitative text for all categories
                var qualText = '';
                if (avg <= 0) qualText = '';
                else if (avg <= 1.5) qualText = 'Kategori sangat kuat, risiko sangat rendah.';
                else if (avg <= 2.5) qualText = 'Kategori baik dan masih dalam batas aman.';
                else if (avg <= 3.5) qualText = 'Kategori cukup, perlu perhatian dan monitoring.';
                else if (avg <= 4.5) qualText = 'Kategori kurang, terdapat risiko yang perlu mitigasi.';
                else qualText = 'Kategori lemah, risiko tinggi.';
                var outQual = document.querySelector('[name="kual_' + cat + '"]');
                if (outQual) outQual.value = qualText;

                // Auto-populate catatan (notes) with same as qualitative
                var outNote = document.querySelector('[name="catatan_' + cat + '"]');
                if (outNote) outNote.value = qualText;
            });

            // Total average across populated categories
            var sumCat = 0, cntCat = 0;
            categories.forEach(function (cat) {
                if (catCounts[cat] > 0) { sumCat += catScores[cat]; cntCat++; }
            });
            var total = cntCat > 0 ? sumCat / cntCat : 0;
            total = Math.round(total * 100) / 100;
            var outtot = document.querySelector('[name="skor_total_6c"]');
            if (outtot) outtot.value = total;
            var grade = '';
            if (total <= 0) grade = '';
            else if (total <= 1.5) grade = 'Sangat Baik';
            else if (total <= 2.5) grade = 'Baik';
            else if (total <= 3.5) grade = 'Cukup';
            else if (total <= 4.5) grade = 'Kurang';
            else grade = 'Sangat Jelek';
            var outgrade = document.querySelector('[name="grade_total_6c"]');
            if (outgrade) outgrade.value = grade;

            // Update visual feedback elements
            var summ = document.getElementById('total_score_5c');
            if (summ) summ.textContent = total.toFixed(2);
            var msgElem = document.getElementById('msg_score_5c');
            if (msgElem) msgElem.textContent = grade.toUpperCase();

            return { total: total, grade: grade, msg: grade };
        }

        function updateScoringSummary() {
            // Get 6C
            let res6c = calc6C();
            var gtxt = res6c.grade || res6c.msg || '';
            document.getElementById('score_summary_5c').textContent = res6c.total.toFixed(2) + " / 5.0 (" + gtxt + ")";

            // Get Repayment Capacity (75% of Net Cashflow)
            let omzet = parseRupiahInput(document.querySelector('[name=omset_per_bulan]').value);

            let bBaku = parseRupiahInput(document.querySelector('[name=biaya_bahan_baku]').value);
            let bGaji = parseRupiahInput(document.querySelector('[name=biaya_gaji]').value);
            let bListrik = parseRupiahInput(document.querySelector('[name=biaya_listrik]').value);
            let bAir = parseRupiahInput(document.querySelector('[name=biaya_air]').value);
            let bSewa = parseRupiahInput(document.querySelector('[name=biaya_sewa]').value);
            let bTransport = parseRupiahInput(document.querySelector('[name=biaya_transportasi]').value);
            let bLain = parseRupiahInput(document.querySelector('[name=biaya_lainnya]').value);
            let totalBiaya = bBaku + bGaji + bListrik + bAir + bSewa + bTransport + bLain;

            let laba = omzet - totalBiaya;

            let biayaHidup = parseRupiahInput(document.querySelector('[name=biaya_hidup]').value);
            let cicilanLain = parseRupiahInput(document.querySelector('[name=cicilan_lain]').value);
            let totalPengeluaran = biayaHidup + cicilanLain;

            let netCashflow = laba - totalPengeluaran;
            let rpc = netCashflow * 0.75;

            document.getElementById('score_summary_rpc').textContent = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(rpc);
        }

        document.addEventListener('DOMContentLoaded', function () {
            toggleJaminan();
            // initialise 6C calculations on load
            calc6C();
        });

        // ========== AJAX SAVE PER SECTION ==========
        function saveSection(section) {
            let idPengajuan = document.getElementById('id_pengajuan').value || '0';

            // For non-pemohon sections, require id_pengajuan
            if (section !== 'pemohon' && (!idPengajuan || idPengajuan === '0')) {
                showToast(section, false, 'Simpan Data Pemohon terlebih dahulu!');
                return;
            }

            let formData = new FormData();
            formData.append('section', section);
            formData.append('id_pengajuan', idPengajuan);
            formData.append('csrf_token', window.__CSRF_TOKEN__ || '');
            let jh = document.getElementById('jenis_pekerjaan_hidden');
            if (jh && jh.value) formData.append('jenis_pekerjaan', jh.value);

            // Collect fields based on section
            let tabMap = {
                'pemohon': '#tab-pemohon',
                'usaha': '#tab-usaha',
                'struktur': '#tab-struktur',
                'neraca': '#tab-neraca',
                '6c': '#tab-6c',
            };

            if (section === 'submit') {
                // Submit — no extra fields needed
            } else if (tabMap[section]) {
                let tab = document.querySelector(tabMap[section]);

                let inputs = tab.querySelectorAll('input, select, textarea');
                inputs.forEach(function (el) {
                    if (el.name) {
                        if (el.type === 'file') {
                            if (el.files.length > 0) formData.append(el.name, el.files[0]);
                        } else if (el.classList.contains('rp-input')) {
                            // Strip Rupiah formatting before sending
                            formData.append(el.name, String(el.value).replace(/[^0-9]/g, '') || '0');
                        } else {
                            formData.append(el.name, el.value);
                        }
                    }
                });
            }

            // Button UX
            let btn = document.getElementById('btn-save-' + section);
            if (btn) { btn.classList.add('loading'); btn.disabled = true; }

            fetch('save_section.php', {
                method: 'POST',
                body: formData
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
                    showToast(section, data.success, data.message);

                    if (data.success && data.id_pengajuan) {
                        document.getElementById('id_pengajuan').value = data.id_pengajuan;
                    }
                    if (data.success && section === 'submit') {
                        // Redirect after successful submit
                        setTimeout(function () { window.location.href = 'riwayat.php'; }, 1500);
                    }
                })
                .catch(function (err) {
                    if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
                    showToast(section, false, 'Terjadi kesalahan koneksi.');
                });
        }

        function showToast(section, success, message) {
            let toastId = 'toast-' + section;
            let toast = document.getElementById(toastId);
            if (!toast) return;
            toast.className = 'toast-msg ' + (success ? 'success' : 'error');
            toast.innerHTML = (success ? '&#10004; ' : '&#9888; ') + message;
            toast.style.display = 'block';
            if (success) {
                setTimeout(function () { toast.style.display = 'none'; }, 4000);
            }
        }
    </script>
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container form-content">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h2>Input Analisa Kredit</h2>
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

        <div class="form-stepper">
            <a href="#tab-pemohon" class="nav-link-step active" data-target="tab-pemohon">1. Data Pemohon</a>
            <a href="#tab-usaha" class="nav-link-step" data-target="tab-usaha">2. Data Usaha</a>
            <a href="#tab-struktur" class="nav-link-step" data-target="tab-struktur">3. Struktur Kredit</a>
            <a href="#tab-agunan" class="nav-link-step" data-target="tab-agunan">4. Data Agunan</a>
            <a href="#tab-neraca" class="nav-link-step" data-target="tab-neraca">5. Neraca</a>
            <a href="#tab-6c" class="nav-link-step" data-target="tab-6c">6. Analisa 6C</a>
            <a href="#tab-scoring" class="nav-link-step" data-target="tab-scoring">7. Review & Submit</a>
        </div>

        <form method="POST" enctype="multipart/form-data" onsubmit="return false;">
            <input type="hidden" id="id_pengajuan" name="id_pengajuan"
                value="<?= $EDIT_ID_PENGAJUAN > 0 ? (int) $EDIT_ID_PENGAJUAN : '' ?>">
            <input type="hidden" name="jenis_pekerjaan" id="jenis_pekerjaan_hidden"
                value="<?= htmlspecialchars($jenis_pekerjaan, ENT_QUOTES, 'UTF-8') ?>">

            <!-- FORM AREA (Controlled by Stepper Navigation) -->
            <div class="form-area">
                <?php if (isset($success)): ?>
                    <div style="background:#dcfce7; color:#166534; padding:1rem; margin-bottom:1.5rem; border-radius:8px;">
                        &#10004; <?= $success ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div style="background:#fee2e2; color:#991b1b; padding:1rem; margin-bottom:1.5rem; border-radius:8px;">
                        &#9888; <?= $error ?></div>
                <?php endif; ?>

                <!-- TAB 1: PEMOHON -->
                <div id="tab-pemohon" class="tab-content active">
                    <h3 class="tab-title">1. Data Pemohon</h3>

                    <h4
                        style="margin-top:1.5rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        A. Data Debitur</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nama Lengkap <span
                                    style="color:red">*</span></label><input type="text" name="nama_debitur" required
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>ID Nasabah</label><input type="text" name="id_nasabah" 
                                style="text-transform:uppercase;"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Tempat Lahir</label><input type="text" name="tempat_lahir"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Tanggal Lahir</label><input type="date"
                                name="tanggal_lahir"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Status Perkawinan</label>
                            <select name="status_perkawinan" onchange="togglePasangan(this.value)">
                                <option value="lajang">Lajang</option>
                                <option value="menikah">Menikah</option>
                                <option value="janda">Janda</option>
                                <option value="duda">Duda</option>
                            </select>
                        </div>
                        <div></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Pekerjaan</label><input type="text" name="pekerjaan"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Alamat Pekerjaan Debitur</label><textarea
                                name="alamat_pekerjaan" rows="2" style="text-transform:uppercase;"></textarea></div>
                    </div>

                    <div id="section-pasangan"
                        style="display:none; margin-top:1rem; padding:1.5rem; background:#f8fafc; border-radius:8px; border:1px solid #e2e8f0;">
                        <h4
                            style="color:var(--primary-color); border-bottom:1px solid #cbd5e1; padding-bottom:5px; margin-top:0;">
                            B. Data Pasangan</h4>
                        <div class="grid-2" style="margin-top:1rem;">
                            <div class="custom-form-group"><label>Nama Suami/Istri</label><input type="text"
                                    name="nama_pasangan" style="text-transform:uppercase;"></div>
                            <div class="custom-form-group"><label>Tempat Lahir Pasangan</label><input type="text"
                                    name="tempat_lahir_pasangan" style="text-transform:uppercase;"></div>
                        </div>
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Tanggal Lahir Pasangan</label><input type="date"
                                    name="tanggal_lahir_pasangan"></div>
                            <div class="custom-form-group"><label>Pekerjaan Pasangan</label><input type="text"
                                    name="pekerjaan_pasangan" style="text-transform:uppercase;"></div>
                        </div>
                        <div class="custom-form-group"><label>Alamat Pekerjaan Pasangan</label><textarea
                                name="alamat_pekerjaan_pasangan" rows="2" style="text-transform:uppercase;"></textarea>
                        </div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        C. Data Identitas & Domisili</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nomor KTP <span style="color:red">*</span></label><input
                                type="text" name="nik" required maxlength="16" minlength="16" pattern="\d{16}"
                                title="Harus 16 digit angka"></div>
                        <div class="custom-form-group"><label>NPWP</label><input type="text" name="npwp" 
                                style="text-transform:uppercase;" placeholder="Opsional"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nomor HP <span style="color:red">*</span></label><input
                                type="text" name="no_hp" required maxlength="15" minlength="10" pattern="\d{10,15}"
                                title="Harus 10-15 digit angka"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Sesuai KTP <span
                                style="color:red">*</span></label><textarea name="alamat_ktp" rows="2" required
                            style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Dukuh</label><input type="text" name="dukuh"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Desa</label><input type="text" name="desa"
                                style="text-transform:uppercase;"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Kecamatan</label><input type="text" name="kecamatan"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Kota/Kabupaten</label><input type="text"
                                name="kota_kabupaten" style="text-transform:uppercase;"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Rumah (Domisili)</label><textarea
                            name="alamat_domisili" rows="2" style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Jumlah Tanggungan (Orang)</label><input type="number"
                                name="jumlah_tanggungan" min="0" value="0"></div>
                        <div class="custom-form-group"><label>Nama Ibu Kandung</label><input type="text"
                                name="nama_ibu_kandung" style="text-transform:uppercase;"></div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        D. Data Pekerjaan / Instansi / Usaha</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nomor Induk Berusaha (NIB) / SKU</label><input type="text"
                                name="nib" style="text-transform:uppercase;" placeholder="Opsional"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nama Instansi/Perusahaan</label><input type="text"
                                name="nama_instansi" style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Nomor Telepon Kantor</label><input type="text"
                                name="telepon_kantor" pattern="[\d\-+]*"></div>
                    </div>
                    <div class="custom-form-group"><label>Alamat Instansi/Perusahaan</label><textarea
                            name="alamat_instansi" rows="2" style="text-transform:uppercase;"></textarea></div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Departemen/Bagian</label><input type="text"
                                name="departemen_bagian" style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Jabatan</label><input type="text" name="jabatan"
                                style="text-transform:uppercase;"></div>
                    </div>

                    <h4
                        style="margin-top:2rem; color:var(--primary-color); border-bottom:1px solid #ddd; padding-bottom:5px;">
                        E. Data Kredit</h4>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Pinjaman Ke-</label><input type="number"
                                name="pinjaman_ke" min="1" value="1"></div>
                        <div class="custom-form-group"><label>Upload Dokumen Pendukung (KTP/KK)</label><input
                                type="file" name="file_pendukung"></div>
                    </div>

                    <button type="button" id="btn-save-pemohon" class="btn-save-section"
                        onclick="saveSection('pemohon')" style="margin-top:2rem;">
                        <span class="spinner"></span>
                        <span class="btn-text">Simpan Data Pemohon</span>
                    </button>
                    <div id="toast-pemohon" class="toast-msg"></div>
                </div>

                <script>
                    function togglePasangan(status) {
                        const section = document.getElementById('section-pasangan');
                        if (status === 'menikah') {
                            section.style.display = 'block';
                        } else {
                            section.style.display = 'none';
                            // clear values if not menikah
                            section.querySelectorAll('input, textarea').forEach(el => el.value = '');
                        }
                    }
                    document.addEventListener('DOMContentLoaded', () => {
                        let sel = document.querySelector('select[name="status_perkawinan"]');
                        if (sel) togglePasangan(sel.value);
                    });
                </script>

                <!-- TAB 2: USAHA — Analisa Usaha & Repayment Capacity -->
                <div id="tab-usaha" class="tab-content">
                    <h3 class="tab-title">2. Data Usaha & Analisa Kemampuan Bayar</h3>

                    <!-- A. DATA USAHA -->
                    <div class="section-header">A. DATA USAHA</div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Nama Usaha</label><input type="text" name="nama_usaha"
                                style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Bidang Usaha</label><input type="text" name="bidang_usaha"
                                style="text-transform:uppercase;"></div>
                    </div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Lama Usaha</label><input type="text" name="lama_usaha"
                                placeholder="cth: 5 Tahun" style="text-transform:uppercase;"></div>
                        <div class="custom-form-group"><label>Alamat Usaha</label><input type="text" name="alamat_usaha"
                                style="text-transform:uppercase;"></div>
                    </div>

                    <!-- B. ANALISA PENDAPATAN -->
                    <div class="section-header">B. ANALISA PENDAPATAN (OMZET)</div>
                    <div class="custom-form-group">
                        <label>Omzet Usaha Rata-rata Per Bulan (Rp)</label>
                        <input type="text" name="omset_per_bulan" class="rp-input" value="" placeholder="0"
                            oninput="calcUsaha()">
                    </div>

                    <!-- C. RINCIAN BIAYA USAHA -->
                    <div class="section-header">C. RINCIAN BIAYA USAHA PER BULAN</div>
                    <div style="background:#f8fafc; padding:1.5rem; border-radius:8px; border:1px solid #e2e8f0;">
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Biaya Bahan Baku (Rp)</label><input type="text"
                                    name="biaya_bahan_baku" class="rp-input" value="" placeholder="0"
                                    oninput="calcUsaha()"></div>
                            <div class="custom-form-group"><label>Biaya Gaji Karyawan (Rp)</label><input type="text"
                                    name="biaya_gaji" class="rp-input" value="" placeholder="0" oninput="calcUsaha()">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Biaya Listrik (Rp)</label><input type="text"
                                    name="biaya_listrik" class="rp-input" value="" placeholder="0"
                                    oninput="calcUsaha()"></div>
                            <div class="custom-form-group"><label>Biaya Air (Rp)</label><input type="text"
                                    name="biaya_air" class="rp-input" value="" placeholder="0" oninput="calcUsaha()">
                            </div>
                        </div>
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Biaya Sewa Tempat (Rp)</label><input type="text"
                                    name="biaya_sewa" class="rp-input" value="" placeholder="0" oninput="calcUsaha()">
                            </div>
                            <div class="custom-form-group"><label>Biaya Transportasi (Rp)</label><input type="text"
                                    name="biaya_transportasi" class="rp-input" value="" placeholder="0"
                                    oninput="calcUsaha()"></div>
                        </div>
                        <div class="custom-form-group"><label>Biaya Operasional Lainnya (Rp)</label><input type="text"
                                name="biaya_lainnya" class="rp-input" value="" placeholder="0" oninput="calcUsaha()">
                        </div>
                        <hr style="border-color:#cbd5e1; margin:1rem 0;">
                        <div class="custom-form-group">
                            <label style="color:var(--primary); font-weight:700;">TOTAL BIAYA USAHA PER BULAN</label>
                            <div class="calc-display" id="disp_total_biaya">Rp 0</div>
                            <input type="hidden" name="biaya_operasional" id="hid_biaya_operasional" value="0">
                        </div>
                    </div>

                    <!-- D. LABA USAHA -->
                    <div class="section-header">D. LABA USAHA</div>
                    <div style="background:#f0fdf4; padding:1.5rem; border-radius:8px; border:1px solid #bbf7d0;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                            <span style="color:#6b7280;">Omzet Per Bulan</span>
                            <span style="font-weight:600;" id="disp_omzet_recap">Rp 0</span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                            <span style="color:#6b7280;">(-) Total Biaya Usaha</span>
                            <span style="font-weight:600; color:#dc2626;" id="disp_biaya_recap">Rp 0</span>
                        </div>
                        <hr style="border-color:#86efac; margin:0.75rem 0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; color:#059669; font-size:1.05rem;">LABA USAHA</span>
                            <span style="font-weight:800; color:#059669; font-size:1.15rem;" id="disp_laba_usaha">Rp
                                0</span>
                        </div>
                    </div>

                    <!-- E. PENGELUARAN TETAP DEBITUR -->
                    <div class="section-header">E. PENGELUARAN TETAP DEBITUR</div>
                    <div style="background:#fef2f2; padding:1.5rem; border-radius:8px; border:1px solid #fecaca;">
                        <div class="grid-2">
                            <div class="custom-form-group"><label>Biaya Hidup Keluarga Per Bulan (Rp)</label><input
                                    type="text" name="biaya_hidup" class="rp-input" value="" placeholder="0"
                                    oninput="calcUsaha()"></div>
                            <div class="custom-form-group"><label>Cicilan Kredit/Leasing Berjalan (Rp)</label><input
                                    type="text" name="cicilan_lain" id="usaha_cicilan_lain" class="rp-input" value=""
                                    placeholder="0" readonly style="background:#f1f5f9; cursor:not-allowed;"></div>
                        </div>

                        <div
                            style="background:#fff; padding:1.25rem; border-radius:8px; border:1px dashed #cbd5e1; margin-top:0.5rem; margin-bottom:1rem;">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                                <div style="font-weight:700; font-size:0.95rem; color:#475569;">KREDIT BANK LAIN
                                    (EXISTING LOAN)</div>
                                <button type="button" onclick="addBankLain()"
                                    style="background:linear-gradient(135deg,#2563eb,#3b82f6); color:#fff; border:none; padding:0.5rem 1rem; border-radius:6px; font-size:0.85rem; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:0.35rem; transition:all 0.2s;"
                                    onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 12px rgba(37,99,235,0.35)'"
                                    onmouseout="this.style.transform='';this.style.boxShadow=''">➕ Tambah Bank</button>
                            </div>
                            <div id="bl_container">
                                <!-- Bank cards will be inserted here -->
                            </div>
                            <div id="bl_empty_msg"
                                style="text-align:center; color:#94a3b8; padding:1.5rem 0; font-size:0.9rem;">Belum ada
                                data kredit bank lain. Klik <strong>"Tambah Bank"</strong> untuk menambahkan.</div>
                            <div id="bl_total_row"
                                style="display:none; margin-top:0.75rem; padding:0.75rem 1rem; background:#f1f5f9; border-radius:6px; border:1px solid #e2e8f0;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-weight:700; color:#1e40af; font-size:0.95rem;">TOTAL ANGSURAN
                                        KREDIT BANK LAIN</span>
                                    <span style="font-weight:800; color:#1e40af; font-size:1.1rem;"
                                        id="bl_total_angsuran">Rp 0</span>
                                </div>
                            </div>
                        </div>
                        <hr style="border-color:#fca5a5; margin:0.75rem 0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; color:#dc2626; font-size:1.05rem;">TOTAL PENGELUARAN
                                TETAP</span>
                            <span style="font-weight:800; color:#dc2626; font-size:1.15rem;"
                                id="disp_total_pengeluaran">Rp 0</span>
                        </div>
                    </div>

                    <!-- F. NET CASHFLOW -->
                    <div class="section-header">F. NET CASHFLOW</div>
                    <div style="background:#f5f3ff; padding:1.5rem; border-radius:8px; border:1px solid #ddd6fe;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
                            <span style="color:#6b7280;">Laba Usaha</span>
                            <span style="font-weight:600;" id="disp_laba_for_net">Rp 0</span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.5rem;">
                            <span style="color:#6b7280;">(-) Total Pengeluaran Tetap</span>
                            <span style="font-weight:600; color:#dc2626;" id="disp_pengeluaran_for_net">Rp 0</span>
                        </div>
                        <hr style="border-color:#c4b5fd; margin:0.75rem 0;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-weight:700; color:#7c3aed; font-size:1.05rem;">NET CASHFLOW</span>
                            <span style="font-weight:800; color:#7c3aed; font-size:1.15rem;" id="disp_net_cashflow">Rp
                                0</span>
                        </div>
                    </div>

                    <!-- G. REPAYMENT CAPACITY -->
                    <div class="section-header">G. REPAYMENT CAPACITY (KEMAMPUAN BAYAR)</div>
                    <div
                        style="background:linear-gradient(135deg,#1e293b,#334155); color:#fff; padding:2rem; border-radius:12px;">
                        <div style="text-align:center;">
                            <div
                                style="font-size:0.85rem; opacity:0.7; text-transform:uppercase; letter-spacing:1px; margin-bottom:0.25rem;">
                                95% × Net Cashflow</div>
                            <div style="font-size:2.25rem; font-weight:800;" id="disp_repayment_capacity">Rp 0</div>
                            <div style="font-size:0.8rem; opacity:0.6; margin-top:0.25rem;">Kemampuan bayar maksimal per
                                bulan (konservatif)</div>
                        </div>
                    </div>

                    <!-- H. UJI KELAYAKAN ANGSURAN -->
                    <div class="section-header">H. UJI KELAYAKAN ANGSURAN KREDIT</div>
                    <div style="background:#fffbeb; padding:1.5rem; border-radius:8px; border:1px solid #fde68a;">
                        <div class="custom-form-group">
                            <label>Angsuran Kredit Yang Diajukan Per Bulan (Rp)</label>
                            <input type="text" name="angsuran_diajukan" class="rp-input" value="" placeholder="0"
                                oninput="calcUsaha()">
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-top:1rem; margin-bottom:0.5rem;">
                            <span style="color:#6b7280;">Repayment Capacity</span>
                            <span style="font-weight:600;" id="disp_rc_for_uji">Rp 0</span>
                        </div>
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem;">
                            <span style="color:#6b7280;">Angsuran Diajukan</span>
                            <span style="font-weight:600;" id="disp_angsuran_recap">Rp 0</span>
                        </div>
                        <hr style="border-color:#fcd34d; margin:0.75rem 0;">
                        <div id="box_status_kelayakan"
                            style="text-align:center; padding:1rem; border-radius:8px; margin-top:0.75rem; font-weight:700; font-size:1.1rem; background:#f1f5f9; color:#64748b;">
                            — Masukkan data untuk melihat status kelayakan —
                        </div>
                    </div>

                    <!-- I. UPLOAD FOTO USAHA & DATA PENDUKUNG -->
                    <div class="section-header">I. UPLOAD FOTO USAHA & DATA PENDUKUNG</div>
                    <div
                        style="background:#f8fafc; padding:1.5rem; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:1.5rem;">
                        <p style="color:#64748b; margin-top:0; font-size:0.9rem;">Upload foto usaha dan foto data
                            pendukung lainnya. (Maksimal 2 MB per file. Format: JPG, PNG, WEBP, PDF)</p>
                        <div class="grid-2" style="margin-top:1rem;">
                            <div class="custom-form-group">
                                <label>Foto Usaha (Max 2 MB)</label>
                                <div id="container-foto-usaha" style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <div style="display:flex; gap:0.5rem; align-items:center;">
                                        <input type="file" name="foto_usaha[]" accept="image/jpeg,image/png,image/webp"
                                            style="flex:1;">
                                        <button type="button" onclick="addFotoUsaha()"
                                            style="background:#3b82f6; color:#fff; border:none; padding:0.5rem 1rem; border-radius:4px; font-weight:bold; cursor:pointer;"
                                            title="Tambah Form Upload">+</button>
                                    </div>
                                </div>
                            </div>
                            <div class="custom-form-group">
                                <label>Foto Data Pendukung (Max 2 MB)</label>
                                <div id="container-pendukung" style="display:flex; flex-direction:column; gap:0.5rem;">
                                    <div style="display:flex; gap:0.5rem; align-items:center;">
                                        <input type="file" name="foto_data_pendukung[]"
                                            accept="image/jpeg,image/png,image/webp,application/pdf" style="flex:1;">
                                        <button type="button" onclick="addFilePendukung()"
                                            style="background:#3b82f6; color:#fff; border:none; padding:0.5rem 1rem; border-radius:4px; font-weight:bold; cursor:pointer;"
                                            title="Tambah Form Upload">+</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                        function addFotoUsaha() {
                            const container = document.getElementById('container-foto-usaha');
                            const div = document.createElement('div');
                            div.style.display = 'flex';
                            div.style.gap = '0.5rem';
                            div.style.alignItems = 'center';
                            div.innerHTML = `
                                <input type="file" name="foto_usaha[]" accept="image/jpeg,image/png,image/webp" style="flex:1;">
                                <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:#fff; border:none; padding:0.5rem 1rem; border-radius:4px; font-weight:bold; cursor:pointer;" title="Hapus Form">-</button>
                            `;
                            container.appendChild(div);
                        }

                        function addFilePendukung() {
                            const container = document.getElementById('container-pendukung');
                            const div = document.createElement('div');
                            div.style.display = 'flex';
                            div.style.gap = '0.5rem';
                            div.style.alignItems = 'center';
                            div.innerHTML = `
                                <input type="file" name="foto_data_pendukung[]" accept="image/jpeg,image/png,image/webp,application/pdf" style="flex:1;">
                                <button type="button" onclick="this.parentElement.remove()" style="background:#ef4444; color:#fff; border:none; padding:0.5rem 1rem; border-radius:4px; font-weight:bold; cursor:pointer;" title="Hapus Form">-</button>
                            `;
                            container.appendChild(div);
                        }
                    </script>

                    <!-- J. KESIMPULAN ANALISA -->
                    <div class="section-header">J. KESIMPULAN ANALISA USAHA</div>
                    <div id="box_kesimpulan"
                        style="background:#f8fafc; padding:1.5rem; border-radius:8px; border:1px solid #e2e8f0; font-size:0.92rem; line-height:1.8; color:#334155;">
                        <p style="color:#94a3b8; text-align:center;">Kesimpulan analisa akan tampil otomatis setelah
                            data diisi.</p>
                    </div>

                    <button type="button" id="btn-save-usaha" class="btn-save-section" onclick="saveSection('usaha')"
                        style="margin-top:2rem;">
                        <span class="spinner"></span>
                        <span class="btn-text">💾 Simpan Data Usaha & Analisa</span>
                    </button>
                    <div id="toast-usaha" class="toast-msg"></div>
                </div>

                <script>
                    var _blCounter = 0;

                    function addBankLain() {
                        _blCounter++;
                        var idx = _blCounter;
                        var container = document.getElementById('bl_container');
                        var emptyMsg = document.getElementById('bl_empty_msg');
                        if (emptyMsg) emptyMsg.style.display = 'none';

                        var card = document.createElement('div');
                        card.id = 'bl_card_' + idx;
                        card.style.cssText = 'background:#f8fafc; padding:1rem 1.25rem; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:0.75rem; position:relative; transition:all 0.3s ease;';
                        card.innerHTML = ''
                            + '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">'
                            + '  <span style="font-weight:600; color:#334155; font-size:0.9rem;">🏦 Kredit Bank #' + idx + '</span>'
                            + '  <button type="button" onclick="removeBankLain(' + idx + ')" style="background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; padding:0.3rem 0.65rem; border-radius:5px; font-size:0.8rem; font-weight:600; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.background=\'#dc2626\';this.style.color=\'#fff\'" onmouseout="this.style.background=\'#fee2e2\';this.style.color=\'#dc2626\'">✕ Hapus</button>'
                            + '</div>'
                            + '<div class="custom-form-group" style="margin-bottom:0.75rem;"><label>Nama Bank</label><input type="text" id="bl_nama_' + idx + '" placeholder="cth: BRI, BCA, Mandiri..." style="text-transform:uppercase;"></div>'
                            + '<div class="grid-2">'
                            + '  <div class="custom-form-group"><label>Plafon Kredit (Rp)</label><input type="number" id="bl_plafon_' + idx + '" min="0" value="0" oninput="hitungAngsuranBank(' + idx + ')"></div>'
                            + '  <div class="custom-form-group"><label>Jangka Waktu (bulan)</label><input type="number" id="bl_tenor_' + idx + '" min="0" value="0" oninput="hitungAngsuranBank(' + idx + ')"></div>'
                            + '</div>'
                            + '<div class="grid-2">'
                            + '  <div class="custom-form-group"><label>Suku Bunga (% /tahun)</label><input type="number" step="0.01" id="bl_bunga_' + idx + '" min="0" value="0" oninput="hitungAngsuranBank(' + idx + ')"></div>'
                            + '  <div class="custom-form-group"><label>Jenis Bunga</label>'
                            + '    <select id="bl_jenis_' + idx + '" onchange="hitungAngsuranBank(' + idx + ')">'
                            + '      <option value="Flat">Flat</option>'
                            + '      <option value="Anuitas">Anuitas</option>'
                            + '    </select>'
                            + '  </div>'
                            + '</div>'
                            + '<div class="custom-form-group" style="margin-bottom:0;">'
                            + '  <label>Angsuran Per Bulan (Rp)</label>'
                            + '  <input type="number" id="bl_angsuran_' + idx + '" value="0" readonly style="background:#e0f2fe; font-weight:700; color:#1e40af; border:1px solid #93c5fd;">'
                            + '</div>';
                        container.appendChild(card);
                        // Animate in
                        card.style.opacity = '0'; card.style.transform = 'translateY(-10px)';
                        setTimeout(function () { card.style.opacity = '1'; card.style.transform = 'translateY(0)'; }, 30);
                        calcKreditLain();
                    }

                    function removeBankLain(idx) {
                        var card = document.getElementById('bl_card_' + idx);
                        if (!card) return;
                        card.style.opacity = '0'; card.style.transform = 'translateY(-10px)';
                        setTimeout(function () {
                            card.parentNode.removeChild(card);
                            // Show empty message if no cards left
                            var remaining = document.querySelectorAll('#bl_container > div');
                            if (remaining.length === 0) {
                                var emptyMsg = document.getElementById('bl_empty_msg');
                                if (emptyMsg) emptyMsg.style.display = 'block';
                            }
                            calcKreditLain();
                        }, 250);
                    }

                    function hitungAngsuranBank(idx) {
                        var elPlafon = document.getElementById('bl_plafon_' + idx);
                        var elTenor = document.getElementById('bl_tenor_' + idx);
                        var elBunga = document.getElementById('bl_bunga_' + idx);
                        var elJenis = document.getElementById('bl_jenis_' + idx);
                        var elAngsuran = document.getElementById('bl_angsuran_' + idx);
                        if (!elPlafon || !elTenor || !elBunga || !elJenis || !elAngsuran) return;

                        var plafon = parseFloat(elPlafon.value) || 0;
                        var tenor = parseInt(elTenor.value) || 0;
                        var bunga = parseFloat(elBunga.value) || 0;
                        var jenis = elJenis.value;
                        var angsuran = 0;

                        if (plafon > 0 && tenor > 0) {
                            if (jenis === 'Flat') {
                                var bungaBulanan = (plafon * (bunga / 100)) / 12;
                                var angsuranPokok = plafon / tenor;
                                angsuran = angsuranPokok + bungaBulanan;
                            } else if (jenis === 'Anuitas') {
                                if (bunga === 0) {
                                    angsuran = plafon / tenor;
                                } else {
                                    var i = (bunga / 100) / 12;
                                    angsuran = plafon * i / (1 - Math.pow(1 + i, -tenor));
                                }
                            }
                        }

                        angsuran = Math.round(angsuran);
                        elAngsuran.value = angsuran;
                        calcKreditLain();
                    }

                    function calcKreditLain() {
                        var cards = document.querySelectorAll('#bl_container > div');
                        var totalAngsuran = 0;

                        cards.forEach(function (card) {
                            var angField = card.querySelector('input[id^="bl_angsuran_"]');
                            if (angField) totalAngsuran += (parseFloat(angField.value) || 0);
                        });

                        // Update total display
                        var totalRow = document.getElementById('bl_total_row');
                        var totalDisp = document.getElementById('bl_total_angsuran');
                        if (cards.length > 0) {
                            if (totalRow) totalRow.style.display = 'block';
                            if (totalDisp) totalDisp.textContent = formatRupiah(totalAngsuran);
                        } else {
                            if (totalRow) totalRow.style.display = 'none';
                        }

                        // Feed total into cicilan_lain (formatted as Rupiah)
                        var cicilanField = document.getElementById('usaha_cicilan_lain');
                        if (cicilanField) cicilanField.value = totalAngsuran > 0 ? toRupiahStr(totalAngsuran) : '';

                        calcUsaha();
                    }

                    function calcUsaha() {
                        // Read inputs (parse Rupiah-formatted text)
                        let omzet = parseRupiahInput(document.querySelector('[name=omset_per_bulan]').value);
                        let bBaku = parseRupiahInput(document.querySelector('[name=biaya_bahan_baku]').value);
                        let bGaji = parseRupiahInput(document.querySelector('[name=biaya_gaji]').value);
                        let bListrik = parseRupiahInput(document.querySelector('[name=biaya_listrik]').value);
                        let bAir = parseRupiahInput(document.querySelector('[name=biaya_air]').value);
                        let bSewa = parseRupiahInput(document.querySelector('[name=biaya_sewa]').value);
                        let bTransport = parseRupiahInput(document.querySelector('[name=biaya_transportasi]').value);
                        let bLain = parseRupiahInput(document.querySelector('[name=biaya_lainnya]').value);
                        let biayaHidup = parseRupiahInput(document.querySelector('[name=biaya_hidup]').value);
                        let cicilanLain = parseRupiahInput(document.querySelector('[name=cicilan_lain]').value);
                        let angsuranDiajukan = parseRupiahInput(document.querySelector('[name=angsuran_diajukan]').value);

                        // C. Total Biaya Usaha
                        let totalBiaya = bBaku + bGaji + bListrik + bAir + bSewa + bTransport + bLain;
                        document.getElementById('disp_total_biaya').textContent = formatRupiah(totalBiaya);
                        document.getElementById('hid_biaya_operasional').value = totalBiaya;

                        // D. Laba Usaha
                        let labaUsaha = omzet - totalBiaya;
                        document.getElementById('disp_omzet_recap').textContent = formatRupiah(omzet);
                        document.getElementById('disp_biaya_recap').textContent = formatRupiah(totalBiaya);
                        document.getElementById('disp_laba_usaha').textContent = formatRupiah(labaUsaha);


                        // E. Total Pengeluaran Tetap
                        let totalPengeluaran = biayaHidup + cicilanLain;
                        document.getElementById('disp_total_pengeluaran').textContent = formatRupiah(totalPengeluaran);

                        // F. NET CASHFLOW
                        let netCashflow = labaUsaha - totalPengeluaran;
                        document.getElementById('disp_laba_for_net').textContent = formatRupiah(labaUsaha);
                        document.getElementById('disp_pengeluaran_for_net').textContent = formatRupiah(totalPengeluaran);
                        document.getElementById('disp_net_cashflow').textContent = formatRupiah(netCashflow);

                        // G. Repayment Capacity (95%)
                        let rc = netCashflow * 0.95;
                        document.getElementById('disp_repayment_capacity').textContent = formatRupiah(rc);

                        // H. Uji Kelayakan
                        document.getElementById('disp_rc_for_uji').textContent = formatRupiah(rc);
                        document.getElementById('disp_angsuran_recap').textContent = formatRupiah(angsuranDiajukan);

                        let boxStatus = document.getElementById('box_status_kelayakan');
                        let boxKesimpulan = document.getElementById('box_kesimpulan');
                        if (omzet <= 0 && angsuranDiajukan <= 0) {
                            boxStatus.style.background = '#f1f5f9';
                            boxStatus.style.color = '#64748b';
                            boxStatus.innerHTML = '— Masukkan data untuk melihat status kelayakan —';
                            boxKesimpulan.innerHTML = '<p style="color:#94a3b8; text-align:center;">Kesimpulan analisa akan tampil otomatis setelah data diisi.</p>';
                        } else if (rc >= angsuranDiajukan && angsuranDiajukan > 0) {
                            boxStatus.style.background = '#dcfce7';
                            boxStatus.style.color = '#166534';
                            boxStatus.innerHTML = '✅ LAYAK — Repayment Capacity (' + formatRupiah(rc) + ') ≥ Angsuran (' + formatRupiah(angsuranDiajukan) + ')';
                            boxKesimpulan.innerHTML = 'Berdasarkan hasil analisa keuangan, debitur memiliki <strong>Laba Usaha (Bersih)</strong> sebesar <span style="color:#059669; font-weight:bold;">' + formatRupiah(labaUsaha) + '</span>. Setelah dikurangi pengeluaran tetap, didapat <strong>Net Cashflow</strong> sebesar <span style="color:#059669; font-weight:bold;">' + formatRupiah(netCashflow) + '</span>. Dengan rasio perhitungan maksimal 95%, <strong>Repayment Capacity</strong> debitur adalah <span style="color:#059669; font-weight:bold;">' + formatRupiah(rc) + '</span>. Karena kemampuan mengangsur ini <strong>LEBIH BESAR</strong> dari angsuran yang diajukan yaitu ' + formatRupiah(angsuranDiajukan) + ', maka permohonan kredit dinyatakan <strong style="color:#166534; font-size:1.1rem;">LAYAK</strong>.';
                        } else {
                            boxStatus.style.background = '#fee2e2';
                            boxStatus.style.color = '#991b1b';
                            boxStatus.innerHTML = '❌ TIDAK LAYAK — Repayment Capacity (' + formatRupiah(rc) + ') < Angsuran (' + formatRupiah(angsuranDiajukan) + ')';
                            boxKesimpulan.innerHTML = 'Berdasarkan hasil analisa keuangan, debitur memiliki <strong>Laba Usaha (Bersih)</strong> sebesar <span style="color:#059669; font-weight:bold;">' + formatRupiah(labaUsaha) + '</span>. Setelah dikurangi pengeluaran tetap, didapat <strong>Net Cashflow</strong> sebesar <span style="color:#059669; font-weight:bold;">' + formatRupiah(netCashflow) + '</span>. Dengan rasio perhitungan maksimal 95%, <strong>Repayment Capacity</strong> debitur hanya sebesar <span style="color:#dc2626; font-weight:bold;">' + formatRupiah(rc) + '</span>. Karena kemampuan mengangsur ini <strong>LEBIH KECIL</strong> dari angsuran yang diajukan yaitu ' + formatRupiah(angsuranDiajukan) + ', maka permohonan kredit dinyatakan <strong style="color:#991b1b; font-size:1.1rem;">TIDAK LAYAK</strong>.';
                        }

                        // J. Kesimpulan Analisa
                        boxKesimpulan = document.getElementById('box_kesimpulan');
                        if (omzet <= 0) {
                            boxKesimpulan.innerHTML = '<p style="color:#94a3b8; text-align:center;">Kesimpulan analisa akan tampil otomatis setelah data diisi.</p>';
                            return;
                        }

                        let marginPersen = omzet > 0 ? ((labaUsaha / omzet) * 100).toFixed(1) : 0;
                        let statusLayak = (rc >= angsuranDiajukan && angsuranDiajukan > 0) ? 'LAYAK' : 'TIDAK LAYAK';
                        let selisih = rc - angsuranDiajukan;

                        // Build kesimpulan without table
                        let html = '';
                        html += '<div style="padding:1rem; border-radius:8px; margin-bottom:1rem; background:#f0fdf4; border-left:4px solid #059669;">';
                        html += '<strong style="color:#065f46;">📊 Ringkasan Analisa Usaha:</strong>';
                        html += '<ul style="margin:0.75rem 0 0 1.5rem; line-height:1.8; color:#334155; font-size:0.95rem;">';
                        html += '<li>Omzet Usaha / Bulan: <strong>' + formatRupiah(omzet) + '</strong></li>';
                        html += '<li>Total Biaya Usaha: <strong>' + formatRupiah(totalBiaya) + '</strong></li>';
                        html += '<li>Laba Usaha: <strong style="color:#059669;">' + formatRupiah(labaUsaha) + '</strong> (' + marginPersen + '%)</li>';
                        html += '<li>Total Pengeluaran Tetap: <strong>' + formatRupiah(totalPengeluaran) + '</strong></li>';
                        html += '<li>Net Cashflow: <strong style="color:#7c3aed;">' + formatRupiah(netCashflow) + '</strong></li>';
                        html += '<li>Repayment Capacity (95%): <strong style="color:#2563eb; font-size:1.05rem;">' + formatRupiah(rc) + '</strong></li>';
                        if (angsuranDiajukan > 0) {
                            html += '<li>Angsuran Diajukan: <strong>' + formatRupiah(angsuranDiajukan) + '</strong></li>';
                            let selisihColor = selisih >= 0 ? '#059669' : '#dc2626';
                            html += '<li>Selisih / Buffer: <strong style="color:' + selisihColor + ';">' + formatRupiah(selisih) + '</strong></li>';
                        }
                        html += '</ul>';
                        html += '</div>';

                        html += '<div style="padding:1rem; border-radius:8px; ' + (statusLayak === 'LAYAK' ? 'background:#dcfce7; border-left:4px solid #059669;' : 'background:#fee2e2; border-left:4px solid #dc2626;') + '">';
                        html += '<strong>' + (statusLayak === 'LAYAK' ? '✅ KESIMPULAN: LAYAK' : '❌ KESIMPULAN: TIDAK LAYAK') + '</strong><br><br>';
                        if (statusLayak === 'LAYAK') {
                            html += 'Debitur memiliki kemampuan bayar (Repayment Capacity) sebesar <strong>' + formatRupiah(rc) + '</strong> per bulan, yang <strong>mencukupi</strong> untuk memenuhi angsuran kredit sebesar <strong>' + formatRupiah(angsuranDiajukan) + '</strong> per bulan dengan margin keamanan <strong>' + formatRupiah(selisih) + '</strong>. Usaha menunjukkan margin laba <strong>' + marginPersen + '%</strong>. <strong style="color:#059669;">Pengajuan kredit ini LAYAK diproses lebih lanjut dari sisi kemampuan bayar.</strong>';
                        } else if (angsuranDiajukan > 0) {
                            html += 'Debitur memiliki kemampuan bayar (Repayment Capacity) sebesar <strong>' + formatRupiah(rc) + '</strong> per bulan, yang <strong>tidak mencukupi</strong> untuk memenuhi angsuran kredit sebesar <strong>' + formatRupiah(angsuranDiajukan) + '</strong> per bulan. Terdapat kekurangan sebesar <strong>' + formatRupiah(Math.abs(selisih)) + '</strong>. <strong style="color:#dc2626;">Pengajuan kredit ini TIDAK LAYAK dari sisi kemampuan bayar.</strong>';
                        } else {
                            html += 'Data angsuran kredit yang diajukan belum diisi. Silakan isi angsuran untuk mendapatkan kesimpulan kelayakan lengkap.';
                        }
                        html += '</div>';

                        boxKesimpulan.innerHTML = html;
                    }
                </script>

                <script>
                    // Initialize Rupiah formatting on all .rp-input fields
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('.rp-input').forEach(function (el) {
                            initRupiahField(el);
                        });
                    });
                </script>

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
                        <div class="custom-form-group"><label>Tujuan Kredit</label><input type="text"
                                name="tujuan_kredit" style="text-transform:uppercase;"
                                placeholder="cth: MODAL KERJA DAGANG"></div>
                    </div>

                    <!-- B. PLAFOND & TENOR -->
                    <div class="section-header">B. PLAFOND & TENOR</div>
                    <div class="grid-2">
                        <div class="custom-form-group"><label>Plafond Kredit (Rp) <span
                                    style="color:red">*</span></label><input type="number" name="jumlah_kredit" min="0"
                                value="0" oninput="calcStruktur()"></div>
                        <div class="custom-form-group"><label>Suku Bunga / Margin (% per tahun)</label><input
                                type="number" name="suku_bunga" min="0" max="100" step="0.01" value="0"
                                oninput="calcStruktur()"></div>
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
                                    tanpa grace)</small></label><input type="number" name="grace_period" min="0"
                                value="0" oninput="calcStruktur()"></div>
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
                                    <td colspan="2"
                                        style="padding:10px 10px 5px; font-weight:700; color:#1e293b; font-size:0.9rem;"
                                        id="sim_rincian_header">RINCIAN ANGSURAN PER PERIODE</td>
                                </tr>
                                <tr style="border-bottom:1px solid #e2e8f0;">
                                    <td style="padding:10px; color:#6b7280; padding-left:1.5rem;" id="sim_pokok_label">
                                        Angsuran Pokok</td>
                                    <td style="padding:10px; text-align:right; font-weight:600;"
                                        id="sim_angsuran_pokok">Rp 0</td>
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
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                <div>
                                    <div
                                        style="font-size:0.8rem; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">
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
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
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
                        <div
                            style="background:#f0fdf4; padding:1rem; border-radius:8px; margin-top:0.75rem; border:1px solid #bbf7d0;">
                            <div
                                style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                                <div>
                                    <div style="font-weight:600; color:#059669; font-size:0.9rem;">Total Kewajiban
                                        Selama Tenor</div>
                                    <div style="font-size:0.75rem; color:#6b7280;" id="sim_total_note">Pokok + Total
                                        Bunga</div>
                                </div>
                                <div style="font-size:1.25rem; font-weight:800; color:#059669;"
                                    id="sim_total_kewajiban">Rp 0</div>
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

                    <button type="button" id="btn-save-struktur" class="btn-save-section"
                        onclick="saveSection('struktur')" style="margin-top:2rem;">
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
                            boxGrace.style.display = 'none';
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
                <?php if (($jenis_pekerjaan ?? 'umum') !== 'pppk' && ($jenis_pekerjaan ?? 'umum') !== 'perangkat_desa'): ?>
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
                        <h4 style="margin-bottom:1rem; color:var(--primary);">Dokumen & Foto Agunan</h4>
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
                        var jenisPekerjaan = document.getElementById('jenis_pekerjaan_hidden') ? document.getElementById('jenis_pekerjaan_hidden').value : 'umum';
                        if (jenisPekerjaan === 'kretamas') {
                            html += '    <option value="emas"' + (jenis === 'emas' ? ' selected' : '') + '>🥇 Emas</option>';
                        }
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

                        // --- FORM EMAS ---
                        var jenisPekerjaan2 = document.getElementById('jenis_pekerjaan_hidden') ? document.getElementById('jenis_pekerjaan_hidden').value : 'umum';
                        if (jenisPekerjaan2 === 'kretamas') {
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
                        }

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
                        var formEmas = document.getElementById('form_emas_' + idx);
                        if (formTanah) formTanah.style.display = (val === 'tanah_bangunan') ? 'block' : 'none';
                        if (formKendaraan) formKendaraan.style.display = (val === 'kendaraan') ? 'block' : 'none';
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
                        var hargaPerGram = parseFloat(card.querySelector('[name="emas_harga_per_gram[]"]').value) || 0;

                        var nilaiTotal = berat * hargaPerGram;
                        var taksasi = nilaiTotal * 0.95;  // 95% untuk emas

                        document.getElementById('disp_emas_total_' + idx).textContent = formatRupiah(nilaiTotal);
                        document.getElementById('disp_emas_taksasi_' + idx).textContent = formatRupiah(taksasi);

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
                                var hargaPerGram = parseFloat(card.querySelector('[name="emas_harga_per_gram[]"]').value) || 0;
                                var npEmas = berat * hargaPerGram;
                                var ntEmas = npEmas * 0.95;  // 95% untuk emas
                                totalPasar += npEmas;
                                totalTaksasi += ntEmas;
                                totalLikuidasi += ntEmas; // Untuk emas, likuidasi sama dengan taksasi (95%)
                            }
                        });

                        var elPasar = document.getElementById('total_nilai_pasar');
                        if (elPasar) elPasar.textContent = formatRupiah(totalPasar);
                        var elTaksasi = document.getElementById('total_nilai_taksasi');
                        if (elTaksasi) elTaksasi.textContent = formatRupiah(totalTaksasi);
                        var elLikuidasi = document.getElementById('total_nilai_likuidasi');
                        if (elLikuidasi) elLikuidasi.textContent = formatRupiah(totalLikuidasi);
                        var elCount = document.getElementById('total_count_agunan');
                        if (elCount) {
                            elCount.textContent = count > 0
                                ? 'Total ' + count + ' agunan tercatat'
                                : 'Belum ada agunan ditambahkan';
                        }
                    }

                    // Auto-add first agunan entry on load
                    document.addEventListener('DOMContentLoaded', function () {
                        if (document.querySelectorAll('#agunan-container .agunan-card').length === 0) {
                            addAgunan('tanah_bangunan');
                        }
                    });
                </script>
                <?php endif; ?>

                <!-- TAB 5: NERACA -->
                <div id="tab-neraca" class="tab-content">
                    <h3 class="tab-title">5. Neraca (Data Aset & Kewajiban)</h3>
                    <p class="text-muted">Neraca sebelum & sesudah kredit. Modal dihitung otomatis (Total Aktiva - Total
                        Pasiva).</p>

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
                            <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;"
                                class="neraca-table">
                                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                    <th style="text-align:left; padding:8px 4px;">Rekening</th>
                                    <th style="padding:8px 4px; text-align:right;">Sebelum Kredit</th>
                                    <th style="padding:8px 4px; text-align:right;">Sesudah Kredit</th>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Kas</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_kas"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="kas_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Tabungan</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_bank"
                                            oninput="calcNeraca()"
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
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Kendaraan</td>
                                    <td style="padding:4px;">
                                        <input type="text" id="disp_neraca_kend" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                        <input type="hidden" name="neraca_kendaraan">
                                    </td>
                                    <td style="padding:4px;"><input type="text" id="kend_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Stok</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_stok"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="stok_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:2px solid #e5e7eb;">
                                    <td style="padding:8px 4px;">Lainnya</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_lain"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="lainnya_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
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
                                    style="padding:6px 12px; font-size:0.85rem; margin-top:8px;">➕ Tambah Aset
                                    Tanah/Bgn</button>

                                <strong style="display:block; margin-top:1.5rem; color:#4b5563;">Rincian
                                    Kendaraan</strong>
                                <div id="kend-container"
                                    style="background:#f9fafb; padding:10px; border-radius:6px; margin-top:8px; border:1px solid #e5e7eb;">
                                </div>
                                <button type="button" class="btn-save-section" onclick="addKendaraan()"
                                    style="padding:6px 12px; font-size:0.85rem; margin-top:8px;">➕ Tambah Aset
                                    Kendaraan</button>
                            </div>
                        </div>

                        <!-- PASIVA -->
                        <div class="neraca-box">
                            <h4
                                style="color:#dc2626; text-align:center; border-bottom:2px solid #dc2626; padding-bottom:0.8rem; margin-top:0;">
                                PASIVA</h4>
                            <table style="width:100%; border-collapse:collapse; margin-top:0.5rem;"
                                class="neraca-table">
                                <tr style="background:#f9fafb; border-bottom:1px solid #e5e7eb;">
                                    <th style="text-align:left; padding:8px 4px;">Rekening</th>
                                    <th style="padding:8px 4px; text-align:right;">Sebelum Kredit</th>
                                    <th style="padding:8px 4px; text-align:right;">Sesudah Kredit</th>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Pajak dan PBB</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_hutang_lain"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="pajak_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:1px solid #f3f4f6;">
                                    <td style="padding:8px 4px;">Pinjaman Bank Lainya</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_pinjaman_bri"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="bri_sesudah" readonly
                                            style="background:#f3f4f6; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="border-bottom:1px solid #e5e7eb;">
                                    <td style="padding:8px 4px;">Pinjaman Bawon</td>
                                    <td style="padding:4px;"><input type="number" name="neraca_pinjaman_bawon"
                                            oninput="calcNeraca()"
                                            style="width:100%; text-align:right; font-weight:600;"></td>
                                    <td style="padding:4px;"><input type="text" id="bawon_sesudah" readonly
                                            style="background:#f3f4f6; color:#dc2626; width:100%; text-align:right; font-weight:600;">
                                    </td>
                                </tr>
                                <tr style="background:#fee2e2;">
                                    <td style="padding:8px 4px; font-weight:700;">TOTAL PINJAMAN</td>
                                    <td style="padding:8px 4px; text-align:right; font-weight:700;" id="tot_pinj_seb">Rp
                                        0</td>
                                    <td style="padding:8px 4px; text-align:right; font-weight:700;" id="tot_pinj_ses">Rp
                                        0</td>
                                </tr>
                                <tr style="border-bottom:2px solid #e5e7eb;">
                                    <td style="padding:10px 4px; font-weight:700;">MODAL <small
                                            style="font-weight:normal; color:#6b7280;"><br>(Otomatis)</small></td>
                                    <td style="padding:4px;"><input type="text" id="modal_sebelum" readonly
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
                                    <li><b>Modal</b> otomatis dihitung agar Total Aktiva & Pasiva selalu seimbang
                                        (Balance).</li>
                                    <li>Sistem otomatis menjumlahkan <b>Plafon Baru</b> pada Neraca <b>Sesudah
                                            Kredit</b> di bagian Pinjaman Bawon.</li>
                                </ul>
                            </div>

                            <!-- Hidden field untuk value modal yg disimpan -->
                            <input type="hidden" name="neraca_modal" id="hidden_neraca_modal">

                            <div style="margin-top:1.5rem; padding:1.2rem; background:#f9fafb; border-radius:8px; border:1px solid #e5e7eb;">
                                <strong style="color:#4b5563; display:block; margin-bottom:0.5rem;">Dokumen Pendukung Neraca</strong>
                                <input type="file" name="file_pendukung_neraca" accept="application/pdf,image/jpeg,image/png,image/webp">
                                <small style="color:#6b7280; display:block; margin-top:0.25rem;">(Opsional) Laporan Neraca, Rekening Koran, dsb.</small>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="btn-save-neraca" class="btn-save-section" onclick="saveSection('neraca')"
                        style="margin-top:2rem; width:100%; padding:14px; font-size:1.1rem; background:#18181b; color:#fff; border:none; border-radius:6px; font-weight:500; cursor:pointer;">
                        <span class="spinner"></span>
                        <span class="btn-text">Simpan Data Neraca</span>
                    </button>
                    <div id="toast-neraca" class="toast-msg"></div>
                </div>

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
                                <p class="card-6c-subtitle">Menilai kemampuan debitur menghasilkan cashflow untuk
                                    membayar kredit</p>
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
                                <p class="card-6c-subtitle">Menilai kekuatan modal sendiri dan komitmen debitur dalam
                                    usaha</p>
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
                                <p class="card-6c-subtitle">Menilai pengaruh faktor eksternal terhadap kelangsungan
                                    usaha</p>
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
                                <p class="card-6c-subtitle">Mengidentifikasi risiko non-keuangan yang dapat mengganggu
                                    kelancaran kredit</p>
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
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                            <div>
                                <div
                                    style="font-size:0.85rem; opacity:0.7; text-transform:uppercase; letter-spacing:1px;">
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
                            <div class="score-value" id="score_summary_rpc">Rp 0</div>
                            <small class="text-muted">(95% dari Net Cashflow)</small>
                        </div>
                    </div>

                    <div
                        style="background:#fff7ed; padding:1.5rem; border-radius:8px; border-left:4px solid #f97316; margin:2rem 0;">
                        <p>Pastikan semua data di semua Tab telah terisi dengan benar sebelum menyimpan.</p>
                    </div>

                    <button type="button" id="btn-save-submit" class="btn-save-section"
                        style="width:100%; padding:1rem; font-size:1.05rem; background-color: var(--primary); border-color: var(--primary);"
                        onclick="saveSection('submit')">
                        <span class="spinner"
                            style="display:none;width:20px;height:20px;border:2px solid #fff;border-top-color:transparent;border-radius:50%;animation:spin .6s linear infinite;margin:0 auto;"></span>
                        <span class="btn-text">Submit Pengajuan Lengkap</span>
                    </button>
                    <div id="toast-submit" class="toast-msg"></div>
                </div>

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
                Object.keys(pg).forEach(function (k) {
                    if (k.indexOf('[') !== -1) return;
                    setField(k, pg[k]);
                });
                var selStat = document.querySelector('select[name="status_perkawinan"]');
                if (selStat && pg.status_perkawinan) selStat.value = pg.status_perkawinan;
                if (typeof togglePasangan === 'function' && selStat) togglePasangan(selStat.value);

                if (P.neraca && P.neraca.aktiva_kas != null) {
                    setField('neraca_kas', P.neraca.aktiva_kas);
                    setField('neraca_bank', P.neraca.aktiva_tabungan);
                    setField('neraca_tanah', P.neraca.aktiva_tanah);
                    setField('neraca_kendaraan', P.neraca.aktiva_kendaraan);
                    setField('neraca_stok', P.neraca.aktiva_stok);
                    setField('neraca_lain', P.neraca.aktiva_lainnya);
                    setField('neraca_pinjaman_bawon', P.neraca.pasiva_hutang_bank);
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
                        setN('luas_tanah_sppt[]', row.luas_tanah_sppt);
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
                if (typeof calcUsaha === 'function') calcUsaha();
                if (typeof calcStruktur === 'function') calcStruktur();
                if (typeof calc6C === 'function') calc6C();
                if (typeof recalcAgunanTotals === 'function') recalcAgunanTotals();
                if (typeof updateScoringSummary === 'function') updateScoringSummary();
            });
        })();
    </script>
</body>

</html>