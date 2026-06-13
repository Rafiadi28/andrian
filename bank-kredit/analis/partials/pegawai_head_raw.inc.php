<?php
/** @var string $pegawai_tipe_save pppk|perangkat_desa */
if (!isset($pegawai_tipe_save)) {
    $pegawai_tipe_save = 'pppk';
}
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

        /* Save Button per Tab */
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
        }

        .form-stepper .nav-link-step.active {
            color: #ffffff;
            background: var(--primary);
            border-color: var(--primary);
        }
    </style>
    <script>
        /**
         * Calculate Repayment Capacity with standard multiplier
         * @param {number} penghasilanBersih Net income (after all expenses)
         * @returns {number} Repayment capacity (max monthly payment)
         */
        function hitungRepayment(penghasilanBersih) {
            return penghasilanBersih * 0.75;
        }

        function normalizeDigits(raw) {
            return String(raw || '').replace(/\D/g, '');
        }

        function attachInputMasks() {
            var nik = document.querySelector('[name="nik"]');
            var hp = document.querySelector('[name="no_hp"]');

            if (nik) {
                nik.addEventListener('input', function () {
                    nik.value = normalizeDigits(nik.value).slice(0, 16);
                    if (nik.value.length > 0 && nik.value.length !== 16) {
                        nik.setCustomValidity('NIK harus tepat 16 digit.');
                    } else {
                        nik.setCustomValidity('');
                    }
                });
            }

            if (hp) {
                hp.addEventListener('input', function () {
                    hp.value = normalizeDigits(hp.value).slice(0, 15);
                    if (hp.value.length > 0 && (hp.value.length < 10 || hp.value.length > 15)) {
                        hp.setCustomValidity('Nomor HP harus 10 sampai 15 digit.');
                    } else {
                        hp.setCustomValidity('');
                    }
                });
            }
        }

        function validateSectionBeforeSave(section) {
            var validators = {
                pemohon: ['nama_debitur', 'nik', 'no_hp', 'alamat_ktp'],
                penghasilan_pegawai: ['angsuran_diajukan'],
                struktur: ['jumlah_kredit', 'jangka_waktu'],
            };
            var fields = validators[section] || [];
            for (var i = 0; i < fields.length; i++) {
                var input = document.querySelector('[name="' + fields[i] + '"]');
                if (!input) continue;
                if (!input.checkValidity()) {
                    input.reportValidity();
                    return false;
                }
            }

            if (section === 'agunan') {
                var cards = document.querySelectorAll('#agunan-container .agunan-card');
                if (!cards.length) {
                    showToast(section, false, 'Tambahkan minimal 1 data agunan.');
                    return false;
                }
            }
            return true;
        }

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
        window.addEventListener('DOMContentLoaded', function () {
            showTabFromHash();
            attachInputMasks();
        });

        // Legacy toggleJaminan — now handled per-card by toggleAgunanForm(idx)
        function toggleJaminan() {
            // No-op: multi-agunan uses toggleAgunanForm per card
        }

        function formatRupiah(num) {
            return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(num);
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
            div.innerHTML = '<input type="text" name="tanah_lokasi[]" placeholder="Lokasi" style="flex:2; padding:6px;">'
                + '<input type="number" name="tanah_luas[]" placeholder="Luas (m2)" style="width:120px; padding:6px;">'
                + '<input type="number" name="tanah_nilai[]" placeholder="Nilai" style="width:160px; padding:6px;" oninput="calcNeraca()">'
                + '<button type="button" onclick="removeRow(this)" style="padding:6px 8px;">🗑</button>';
            return div;
        }

        function createKendRow() {
            var div = document.createElement('div');
            div.className = 'neraca-row';
            div.style = 'display:flex; gap:8px; margin-bottom:6px; align-items:center;';
            div.innerHTML = '<input type="text" name="kendaraan_jenis[]" placeholder="Merk/Tipe" style="flex:2; padding:6px;">'
                + '<input type="text" name="kendaraan_tahun[]" placeholder="Tahun" style="width:120px; padding:6px;">'
                + '<input type="number" name="kendaraan_nilai[]" placeholder="Nilai" style="width:160px; padding:6px;" oninput="calcNeraca()">'
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
            let kas = parseFloat(document.querySelector('[name=neraca_kas]').value) || 0;
            let bank = parseFloat(document.querySelector('[name=neraca_bank]').value) || 0;

            // Sum tanah values from dynamic inputs if present
            let tanah = 0;
            var tanahInputs = document.querySelectorAll('input[name="tanah_nilai[]"]');
            if (tanahInputs.length > 0) {
                tanahInputs.forEach(function (el) { tanah += parseFloat(el.value) || 0; });
            } else {
                tanah = parseFloat(document.querySelector('[name=neraca_tanah]').value) || 0;
            }
            // Keep legacy field in sync
            var fTanah = document.querySelector('[name=neraca_tanah]'); if (fTanah) fTanah.value = tanah;

            // Sum kendaraan values from dynamic inputs if present
            let kend = 0;
            var kendInputs = document.querySelectorAll('input[name="kendaraan_nilai[]"]');
            if (kendInputs.length > 0) {
                kendInputs.forEach(function (el) { kend += parseFloat(el.value) || 0; });
            } else {
                kend = parseFloat(document.querySelector('[name=neraca_kendaraan]').value) || 0;
            }
            var fKend = document.querySelector('[name=neraca_kendaraan]'); if (fKend) fKend.value = kend;

            let stok = parseFloat(document.querySelector('[name=neraca_stok]').value) || 0;
            let lain = parseFloat(document.querySelector('[name=neraca_lain]').value) || 0;

            let totalAktiva = kas + bank + tanah + kend + stok + lain;
            document.getElementById('lbl_total_aktiva').textContent = new Intl.NumberFormat('id-ID').format(totalAktiva);

            let hutangBank = parseFloat(document.querySelector('[name=neraca_hutang_bank]').value) || 0;
            let hutangLain = parseFloat(document.querySelector('[name=neraca_hutang_lain]').value) || 0;
            let modal = parseFloat(document.querySelector('[name=neraca_modal]').value) || 0;

            let totalPasiva = hutangBank + hutangLain + modal;
            document.getElementById('lbl_total_pasiva').textContent = new Intl.NumberFormat('id-ID').format(totalPasiva);
        }

        function calc6C() {
            // Compute average per category based on indicator dropdowns
            var categories = ['character','capacity','capital','collateral','condition','constraint'];
            var catScores = {};
            var catCounts = {};
            
            categories.forEach(function(cat){
                var elems = document.querySelectorAll('.' + cat + '-6c');
                var sum = 0, cnt = 0;
                elems.forEach(function(el){
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
                
                // Auto-compute grade for all categories (5 is best, 1 is worst)
                var grade = '';
                if (avg > 0 && avg <= 1.5) grade = '1 — Sangat Kurang';
                else if (avg <= 2.5) grade = '2 — Kurang';
                else if (avg <= 3.5) grade = '3 — Cukup';
                else if (avg <= 4.5) grade = '4 — Baik';
                else if (avg > 4.5) grade = '5 — Sangat Baik';
                else grade = '';
                var outGrade = document.querySelector('[name="grade_' + cat + '"]');
                if (outGrade) outGrade.value = grade;
                
                // Auto-compute qualitative text for all categories
                var qualText = '';
                if (avg > 0 && avg <= 1.5) qualText = 'Kategori lemah, risiko tinggi.';
                else if (avg <= 2.5) qualText = 'Kategori kurang, terdapat risiko yang perlu mitigasi.';
                else if (avg <= 3.5) qualText = 'Kategori cukup, perlu perhatian dan monitoring.';
                else if (avg <= 4.5) qualText = 'Kategori baik dan masih dalam batas aman.';
                else if (avg > 4.5) qualText = 'Kategori sangat kuat, risiko sangat rendah.';
                var outQual = document.querySelector('[name="kual_' + cat + '"]');
                if (outQual) outQual.value = qualText;
                
                // Auto-populate catatan (notes) with same as qualitative
                var outNote = document.querySelector('[name="catatan_' + cat + '"]');
                if (outNote) outNote.value = qualText;
            });

            // Total average across populated categories
            var sumCat = 0, cntCat = 0;
            categories.forEach(function(cat){
                if (catCounts[cat] > 0) { sumCat += catScores[cat]; cntCat++; }
            });
            var total = cntCat > 0 ? sumCat / cntCat : 0;
            total = Math.round(total * 100) / 100;
            var outtot = document.querySelector('[name="skor_total_6c"]');
            if (outtot) outtot.value = total;
            var grade = '';
            if (total > 0 && total <= 1.5) grade = 'Sangat Kurang';
            else if (total <= 2.5) grade = 'Kurang';
            else if (total <= 3.5) grade = 'Cukup';
            else if (total <= 4.5) grade = 'Baik';
            else if (total > 4.5) grade = 'Sangat Baik';
            else grade = '';
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
            let res6c = calc6C();
            var gtxt = res6c.grade || res6c.msg || '';
            document.getElementById('score_summary_5c').textContent = res6c.total.toFixed(2) + " / 5.0 (" + gtxt + ")";

            let rpc = 0;
            if (document.getElementById('pppk_gaji')) {
                let gaji = parseFloat(document.getElementById('pppk_gaji').value) || 0;
                let biayaHidup = parseFloat(document.getElementById('pppk_biaya_hidup').value) || 0;
                let cicTotalElem = document.getElementById('pppk_total_angsuran');
                let cicLainElem = document.getElementById('pppk_angsuran_lain');
                let cic = 0;
                if (cicTotalElem) cic = parseFloat(cicTotalElem.value) || 0;
                else if (cicLainElem) cic = parseFloat(cicLainElem.value) || 0;
                let net = gaji - biayaHidup - cic;
                rpc = hitungRepayment(net);
            } else if (document.getElementById('desk_penghasilan_tetap')) {
                let a = parseFloat(document.getElementById('desk_penghasilan_tetap').value) || 0;
                let b = parseFloat(document.getElementById('desk_tambahan_penghasilan').value) || 0;
                let biayaHidup = parseFloat(document.getElementById('desk_biaya_hidup').value) || 0;
                let cicDesaElem = document.getElementById('desk_total_angsuran');
                let cicLainDesaElem = document.getElementById('desk_angsuran_lain');
                let cic = 0;
                if (cicDesaElem) cic = parseFloat(cicDesaElem.value) || 0;
                else if (cicLainDesaElem) cic = parseFloat(cicLainDesaElem.value) || 0;
                let net = (a + b) - biayaHidup - cic;
                rpc = hitungRepayment(net);
            } else {
                var omEl = document.querySelector('[name=omset_per_bulan]');
                let omzet = omEl ? (parseFloat(omEl.value) || 0) : 0;
                let bBaku = parseFloat(document.querySelector('[name=biaya_bahan_baku]')?.value) || 0;
                let bGaji = parseFloat(document.querySelector('[name=biaya_gaji]')?.value) || 0;
                let bListrik = parseFloat(document.querySelector('[name=biaya_listrik]')?.value) || 0;
                let bAir = parseFloat(document.querySelector('[name=biaya_air]')?.value) || 0;
                let bSewa = parseFloat(document.querySelector('[name=biaya_sewa]')?.value) || 0;
                let bTransport = parseFloat(document.querySelector('[name=biaya_transportasi]')?.value) || 0;
                let bLain = parseFloat(document.querySelector('[name=biaya_lainnya]')?.value) || 0;
                let totalBiaya = bBaku + bGaji + bListrik + bAir + bSewa + bTransport + bLain;
                let laba = omzet - totalBiaya;
                let biayaHidup = parseFloat(document.querySelector('[name=biaya_hidup]')?.value) || 0;
                let cicilanLain = parseFloat(document.querySelector('[name=cicilan_lain]')?.value) || 0;
                let netCashflow = laba - biayaHidup - cicilanLain;
                rpc = hitungRepayment(netCashflow);
            }

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
            if (!validateSectionBeforeSave(section)) {
                return;
            }

            let formData = new FormData();
            formData.append('section', section);
            formData.append('id_pengajuan', idPengajuan);
            formData.append('csrf_token', window.__CSRF_TOKEN__ || '');
            let jh = document.getElementById('jenis_pekerjaan_hidden');
            if (jh && jh.value) formData.append('jenis_pekerjaan', jh.value);
            formData.append('pegawai_tipe', '<?= htmlspecialchars($pegawai_tipe_save, ENT_QUOTES, "UTF-8") ?>');

            // Collect fields based on section
            let tabMap = {
                'pemohon': '#tab-pemohon',
                'penghasilan_pegawai': '#tab-penghasilan',
                'struktur': '#tab-struktur',
                'agunan': '#tab-agunan',
                'neraca': '#tab-neraca',
                '6c': '#tab-6c',
            };

            if (section === 'submit') {
                // Submit — no extra fields needed
            } else if (tabMap[section]) {
                let tab = document.querySelector(tabMap[section]);

                let inputs = tab.querySelectorAll('input, select, textarea');
                inputs.forEach(function (el) {
                    if (!el.name) return;
                    if (el.type === 'file') {
                        if (el.files && el.files.length > 0) {
                            Array.from(el.files).forEach(function (file) {
                                formData.append(el.name, file);
                            });
                        }
                    } else {
                        formData.append(el.name, el.value);
                    }
                });
            }

            // Button UX
            let btn = document.getElementById('btn-save-' + section);
            if (btn) { btn.classList.add('loading'); btn.disabled = true; }

            if (section === 'penghasilan_pegawai') {
                var angH = document.querySelector('[name=angsuran_diajukan]');
                if (angH) {
                    formData.set('angsuran_diajukan', angH.value);
                }
            }

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
