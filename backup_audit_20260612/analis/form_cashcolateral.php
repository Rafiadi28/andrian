<?php
$form_banner_title = 'Form Analisa: Cash Collateral (Deposito / Tabungan)';
$jenis_pekerjaan   = $jenis_pekerjaan ?? 'cashcolateral';
$FORM_BANNER       = $form_banner_title;
$CATATAN_REVISI_UI = $catatan_revisi_display ?? '';
$EDIT_ID_PENGAJUAN = isset($edit_id_pengajuan) ? (int) $edit_id_pengajuan : 0;
$PREFILL_JSON_OUT  = $prefill_json ?? 'null';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Input Analisa Kredit – Cash Collateral</title>
<link rel="stylesheet" href="../assets/style.css">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script>window.__CSRF_TOKEN__ = <?= json_encode(generateCsrfToken(), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;</script>
<style>
body { font-family: 'Outfit', sans-serif; }
.tab-content { display:none; }
.tab-content.active { display:block; animation:fadeIn .3s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:none} }
.tab-title { border-bottom:2px solid #e2e8f0; padding-bottom:1rem; margin-bottom:1.5rem; color:#1e40af; }
.section-header { background:linear-gradient(135deg,#1e40af,#2563eb); color:#fff; padding:.65rem 1.1rem; border-radius:8px; font-weight:600; font-size:.95rem; margin:1.5rem 0 1rem; }
.cc-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:1.25rem; margin-bottom:1rem; position:relative; transition:box-shadow .2s; }
.cc-card:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.cc-card-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:.75rem; border-bottom:1px solid #e2e8f0; }
.cc-card-title { font-weight:700; color:#1e293b; font-size:.95rem; }
.btn-rm { background:#fee2e2; color:#dc2626; border:none; padding:.4rem .9rem; border-radius:6px; cursor:pointer; font-size:.85rem; font-weight:600; transition:all .2s; }
.btn-rm:hover { background:#dc2626; color:#fff; }
.cc-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(230px,1fr)); gap:1rem; }
.cc-group { display:flex; flex-direction:column; gap:.4rem; }
.cc-label { font-size:.88rem; font-weight:500; color:#374151; }
.cc-input,.cc-select { padding:.65rem .9rem; border:1px solid #d1d5db; border-radius:7px; font-size:.9rem; font-family:inherit; transition:border-color .2s; }
.cc-input:focus,.cc-select:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.cc-total-box { background:linear-gradient(135deg,#1e293b,#334155); color:#fff; border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; }
.cc-total-label { font-size:.85rem; opacity:.75; text-transform:uppercase; letter-spacing:.5px; }
.cc-total-value { font-size:1.6rem; font-weight:800; }
.cc-taksasi-box { background:linear-gradient(135deg,#065f46,#059669); color:#fff; border-radius:10px; padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; }
.btn-save-section { width:100%; background:linear-gradient(135deg,#2563eb,#1d4ed8); color:#fff; border:none; padding:1rem; border-radius:10px; font-size:1rem; font-weight:700; cursor:pointer; margin-top:1.5rem; transition:all .2s; }
.btn-save-section:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(37,99,235,.35); }
.btn-save-section.loading { opacity:.7; cursor:wait; }
.toast-msg { display:none; padding:.85rem 1.2rem; border-radius:8px; margin-top:1rem; font-weight:500; font-size:.9rem; }
.toast-msg.success { display:block; background:#dcfce7; color:#166534; border-left:4px solid #16a34a; }
.toast-msg.error { display:block; background:#fee2e2; color:#991b1b; border-left:4px solid #dc2626; }
.form-stepper { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1.5rem; }
.form-stepper .nav-link-step { padding:.6rem 1.1rem; border-radius:8px; border:1.5px solid #e2e8f0; color:#64748b; text-decoration:none; font-size:.88rem; font-weight:500; transition:all .2s; }
.form-stepper .nav-link-step.active, .form-stepper .nav-link-step:hover { background:#2563eb; color:#fff; border-color:#2563eb; }
.form-area { background:#fff; border-radius:12px; box-shadow:0 4px 6px -1px rgba(0,0,0,.1); padding:2rem; border:1px solid #e2e8f0; margin-top:1rem; }
.grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:1.25rem; }
.custom-form-group { display:flex; flex-direction:column; gap:.4rem; }
.custom-form-group label { font-size:.88rem; font-weight:500; color:#374151; }
.custom-form-group input,.custom-form-group select,.custom-form-group textarea { padding:.65rem .9rem; border:1px solid #d1d5db; border-radius:7px; font-size:.9rem; font-family:inherit; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container form-content">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h2>Input Analisa Kredit</h2>
    <a href="riwayat.php" class="btn btn-secondary">Lihat Riwayat</a>
  </div>

  <?php if ($FORM_BANNER !== ''): ?>
  <div style="margin-top:1rem;padding:.85rem 1.1rem;background:#eff6ff;border:1px solid #93c5fd;border-radius:10px;color:#1e3a5f;font-size:.95rem;">
    <strong><?= htmlspecialchars($FORM_BANNER) ?></strong>
  </div>
  <?php endif; ?>
  <?php if ($CATATAN_REVISI_UI !== ''): ?>
  <div style="margin-top:1rem;padding:1rem;background:#fffbeb;border:1px solid #fde68a;border-radius:10px;color:#78350f;font-size:.92rem;">
    <strong>Catatan revisi:</strong><br><?= nl2br(htmlspecialchars($CATATAN_REVISI_UI)) ?>
  </div>
  <?php endif; ?>

  <div class="form-stepper" style="margin-top:1rem;">
    <a href="#" class="nav-link-step active" data-target="tab-pemohon">1. Data Pemohon</a>
    <a href="#" class="nav-link-step" data-target="tab-agunan">2. Data Agunan</a>
    <a href="#" class="nav-link-step" data-target="tab-struktur">3. Struktur Kredit</a>
    <a href="#" class="nav-link-step" data-target="tab-6c">4. Analisa 6C</a>
    <a href="#" class="nav-link-step" data-target="tab-scoring">5. Review &amp; Submit</a>
  </div>

  <form method="POST" enctype="multipart/form-data" onsubmit="return false;">
    <input type="hidden" id="id_pengajuan" name="id_pengajuan" value="<?= $EDIT_ID_PENGAJUAN > 0 ? $EDIT_ID_PENGAJUAN : '' ?>">
    <input type="hidden" name="jenis_pekerjaan" value="cashcolateral">

    <div class="form-area">

      <!-- ====== TAB 1: PEMOHON ====== -->
      <div id="tab-pemohon" class="tab-content active">
        <h3 class="tab-title">1. Data Pemohon</h3>
        <?php include __DIR__ . '/partials/tab_pemohon_only.inc.php'; ?>
      </div>

      <!-- ====== TAB 2: AGUNAN CASH COLLATERAL ====== -->
      <div id="tab-agunan" class="tab-content">
        <h3 class="tab-title">2. Data Agunan Cash Collateral</h3>
        <p style="color:#6b7280;margin-bottom:1.5rem;">Tambahkan agunan berupa <strong>Bilyet Deposito</strong> dan/atau <strong>Tabungan</strong>. Nilai taksasi dihitung otomatis <strong>95%</strong> dari nilai nominal.</p>

        <div class="section-header">🏦 Daftar Agunan Cash Collateral</div>

        <div id="cc-agunan-container"></div>

        <div style="text-align:center;margin:1rem 0 1.5rem;">
          <button type="button" onclick="ccAddAgunan('bilyet_deposito')"
            style="background:linear-gradient(135deg,#2563eb,#1d4ed8);color:#fff;border:none;padding:.7rem 1.4rem;border-radius:8px;font-weight:600;cursor:pointer;margin-right:.5rem;">
            ➕ Bilyet Deposito
          </button>
          <button type="button" onclick="ccAddAgunan('tabungan')"
            style="background:linear-gradient(135deg,#059669,#047857);color:#fff;border:none;padding:.7rem 1.4rem;border-radius:8px;font-weight:600;cursor:pointer;">
            ➕ Tabungan
          </button>
        </div>

        <!-- Totals -->
        <div class="cc-total-box">
          <div>
            <div class="cc-total-label">Total Nilai Nominal</div>
            <div class="cc-total-value" id="cc-total-nominal">Rp 0</div>
          </div>
          <div style="text-align:right;">
            <div class="cc-total-label">Jumlah Agunan</div>
            <div style="font-size:1.4rem;font-weight:700;" id="cc-count">0</div>
          </div>
        </div>
        <div class="cc-taksasi-box">
          <div>
            <div style="font-size:.8rem;opacity:.8;margin-bottom:.25rem;">NILAI TAKSASI (95% dari Nominal)</div>
            <div style="font-size:1.6rem;font-weight:800;" id="cc-total-taksasi">Rp 0</div>
          </div>
          <div style="background:rgba(255,255,255,.15);padding:.75rem 1.25rem;border-radius:8px;font-size:.9rem;">
            95% × Total Nominal
          </div>
        </div>

        <!-- Hidden inputs for save -->
        <input type="hidden" id="cc_total_nominal" name="cc_total_nominal" value="0">
        <input type="hidden" id="cc_total_taksasi" name="cc_total_taksasi" value="0">
        <input type="hidden" id="cc_count" name="cc_count" value="0">
        <!-- JSON agunan list -->
        <input type="hidden" id="cc_agunan_json" name="cc_agunan_json" value="[]">

        <button type="button" id="btn-save-cc_agunan" class="btn-save-section" onclick="saveSection('cc_agunan')">
          <span class="btn-text">💾 Simpan Data Agunan Cash Collateral</span>
        </button>
        <div id="toast-cc_agunan" class="toast-msg"></div>
      </div>

      <!-- ====== TAB 3: STRUKTUR KREDIT (reuse) ====== -->
      <?php include __DIR__ . '/partials/tabs_kredit_lanjutan.inc.php'; ?>

    </div><!-- /form-area -->
  </form>
</div>

<script>
window.__ANALIS_PREFILL__ = <?= $PREFILL_JSON_OUT ?>;

// ===== STEPPER =====
document.querySelectorAll('.nav-link-step').forEach(function(a) {
  a.addEventListener('click', function(e) {
    e.preventDefault();
    var target = this.getAttribute('data-target');
    document.querySelectorAll('.tab-content').forEach(function(t) { t.classList.remove('active'); });
    document.querySelectorAll('.nav-link-step').forEach(function(x) { x.classList.remove('active'); });
    var el = document.getElementById(target);
    if (el) el.classList.add('active');
    this.classList.add('active');
  });
});

// ===== CASH COLLATERAL AGUNAN =====
var ccCounter = 0;
var ccItems = [];

function formatRp(v) {
  return 'Rp ' + Math.round(v).toLocaleString('id-ID');
}

function ccAddAgunan(jenis) {
  ccCounter++;
  var idx = ccCounter;
  var label = jenis === 'bilyet_deposito' ? '🏦 Bilyet Deposito' : '💳 Tabungan';
  var icon  = jenis === 'bilyet_deposito' ? '🏦' : '💳';
  var color = jenis === 'bilyet_deposito' ? '#1e40af' : '#065f46';

  var card = document.createElement('div');
  card.className = 'cc-card';
  card.id = 'cc-card-' + idx;
  card.style.borderLeft = '4px solid ' + color;

  var nomorField = jenis === 'bilyet_deposito'
    ? '<div class="cc-group"><label class="cc-label">Nomor Bilyet Deposito <span style="color:red">*</span></label><input type="text" id="cc_nomor_bilyet_'+idx+'" class="cc-input" placeholder="cth: 12345678" style="text-transform:uppercase;"></div>'
    : '<div class="cc-group"><label class="cc-label">Nomor Rekening Tabungan <span style="color:red">*</span></label><input type="text" id="cc_nomor_rek_'+idx+'" class="cc-input" placeholder="cth: 1234-56-789012" style="text-transform:uppercase;"></div>';

  var jatuhTempoField = jenis === 'bilyet_deposito'
    ? '<div class="cc-group"><label class="cc-label">Tanggal Jatuh Tempo</label><input type="date" id="cc_jatuh_tempo_'+idx+'" class="cc-input"></div>'
    : '';

  card.innerHTML = '<div class="cc-card-header">'
    + '<span class="cc-card-title">' + icon + ' ' + label + ' #' + idx + '</span>'
    + '<button type="button" class="btn-rm" onclick="ccRemoveAgunan(' + idx + ')">✕ Hapus</button>'
    + '</div>'
    + '<div class="cc-grid">'
    + nomorField
    + '<div class="cc-group"><label class="cc-label">Atas Nama</label><input type="text" id="cc_atas_nama_'+idx+'" class="cc-input" placeholder="Nama pemilik" style="text-transform:uppercase;"></div>'
    + '<div class="cc-group"><label class="cc-label">Nilai Nominal (Rp) <span style="color:red">*</span></label><input type="number" id="cc_nominal_'+idx+'" class="cc-input" min="0" value="0" oninput="ccRecalc()"></div>'
    + '<div class="cc-group"><label class="cc-label">Nilai Taksasi (95%)</label><div id="cc_taksasi_disp_'+idx+'" style="padding:.65rem .9rem;background:#d1fae5;border:1px solid #6ee7b7;border-radius:7px;font-weight:700;color:#065f46;">Rp 0</div></div>'
    + jatuhTempoField
    + '<div class="cc-group"><label class="cc-label">Keterangan</label><input type="text" id="cc_ket_'+idx+'" class="cc-input" placeholder="Opsional"></div>'
    + '</div>'
    + '<input type="hidden" id="cc_jenis_'+idx+'" value="' + jenis + '">';

  document.getElementById('cc-agunan-container').appendChild(card);
  ccItems.push(idx);

  // animate
  card.style.opacity = '0'; card.style.transform = 'translateY(-10px)';
  setTimeout(function() { card.style.opacity='1'; card.style.transform='translateY(0)'; card.style.transition='all .3s'; }, 20);

  ccRecalc();
}

function ccRemoveAgunan(idx) {
  var card = document.getElementById('cc-card-' + idx);
  if (!card) return;
  card.style.opacity='0'; card.style.transform='translateY(-10px)'; card.style.transition='all .25s';
  setTimeout(function() {
    card.remove();
    ccItems = ccItems.filter(function(i){ return i !== idx; });
    ccRecalc();
  }, 260);
}

function ccRecalc() {
  var total = 0;
  var dataList = [];

  ccItems.forEach(function(idx) {
    var nominalEl = document.getElementById('cc_nominal_' + idx);
    if (!nominalEl) return;
    var nominal = parseFloat(nominalEl.value) || 0;
    var taksasi = nominal * 0.95;
    total += nominal;

    var dispEl = document.getElementById('cc_taksasi_disp_' + idx);
    if (dispEl) dispEl.textContent = formatRp(taksasi);

    var jenis = (document.getElementById('cc_jenis_' + idx) || {}).value || '';
    var nomorBilyet = jenis === 'bilyet_deposito' ? ((document.getElementById('cc_nomor_bilyet_' + idx) || {}).value || '') : '';
    var nomorRek    = jenis === 'tabungan'         ? ((document.getElementById('cc_nomor_rek_' + idx)    || {}).value || '') : '';
    var atasNama    = (document.getElementById('cc_atas_nama_'  + idx) || {}).value || '';
    var jatuhTempo  = jenis === 'bilyet_deposito' ? ((document.getElementById('cc_jatuh_tempo_' + idx) || {}).value || '') : '';
    var ket         = (document.getElementById('cc_ket_' + idx) || {}).value || '';

    dataList.push({ idx: idx, jenis: jenis, nomor_bilyet: nomorBilyet, nomor_rekening: nomorRek,
      atas_nama: atasNama, nilai_nominal: nominal, nilai_taksasi: taksasi,
      jatuh_tempo: jatuhTempo, keterangan: ket });
  });

  var taksasiTotal = total * 0.95;
  var count = ccItems.length;

  document.getElementById('cc-total-nominal').textContent = formatRp(total);
  document.getElementById('cc-total-taksasi').textContent = formatRp(taksasiTotal);
  document.getElementById('cc-count').textContent = count;
  document.getElementById('cc_total_nominal').value = total;
  document.getElementById('cc_total_taksasi').value = taksasiTotal;
  document.getElementById('cc_count').value = count;
  document.getElementById('cc_agunan_json').value = JSON.stringify(dataList);
}

// ===== 6C SCORING =====
function calc6C() {
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
      
      var out = document.querySelector('[name="skor_' + cat + '"]');
      if (out) out.value = avg;
      
      var hid = document.querySelector('input[name="score_' + cat + '"]');
      if (hid) {
          hid.value = cnt > 0 ? Math.min(5, Math.max(1, Math.round(avg))) : '';
      }
      
      var grade = '';
      if (avg > 0 && avg <= 1.5) grade = '1 — Sangat Baik';
      else if (avg <= 2.5) grade = '2 — Baik';
      else if (avg <= 3.5) grade = '3 — Cukup';
      else if (avg <= 4.5) grade = '4 — Kurang';
      else if (avg > 4.5) grade = '5 — Sangat Kurang';
      else grade = '';
      var outGrade = document.querySelector('[name="grade_' + cat + '"]');
      if (outGrade) outGrade.value = grade;
      
      var qualText = '';
      if (avg > 0 && avg <= 1.5) qualText = 'Kategori sangat kuat, risiko sangat rendah.';
      else if (avg <= 2.5) qualText = 'Kategori baik dan masih dalam batas aman.';
      else if (avg <= 3.5) qualText = 'Kategori cukup, perlu perhatian dan monitoring.';
      else if (avg <= 4.5) qualText = 'Kategori kurang, terdapat risiko yang perlu mitigasi.';
      else if (avg > 4.5) qualText = 'Kategori lemah, risiko tinggi.';
      var outQual = document.querySelector('[name="kual_' + cat + '"]');
      if (outQual) outQual.value = qualText;
      
      var outNote = document.querySelector('[name="catatan_' + cat + '"]');
      if (outNote) outNote.value = qualText;
  });

  var sumCat = 0, cntCat = 0;
  categories.forEach(function(cat){
      if (catCounts[cat] > 0) { sumCat += catScores[cat]; cntCat++; }
  });
  var total = cntCat > 0 ? sumCat / cntCat : 0;
  total = Math.round(total * 100) / 100;
  var outtot = document.querySelector('[name="skor_total_6c"]');
  if (outtot) outtot.value = total;
  var grade = '';
  if (total > 0 && total <= 1.5) grade = 'Sangat Baik';
  else if (total <= 2.5) grade = 'Baik';
  else if (total <= 3.5) grade = 'Cukup';
  else if (total <= 4.5) grade = 'Kurang';
  else if (total > 4.5) grade = 'Sangat Kurang';
  else grade = '';
  var outgrade = document.querySelector('[name="grade_total_6c"]');
  if (outgrade) outgrade.value = grade;

  var summ = document.getElementById('total_score_5c');
  if (summ) summ.textContent = total.toFixed(2);
  var msgElem = document.getElementById('msg_score_5c');
  if (msgElem) msgElem.textContent = grade.toUpperCase();

  return { total: total, grade: grade, msg: grade };
}

function updateScoringSummary() {
  let res6c = calc6C();
  var gtxt = res6c.grade || res6c.msg || '';
  let ss = document.getElementById('score_summary_5c');
  if (ss) ss.textContent = res6c.total.toFixed(2) + " / 5.0 (" + gtxt + ")";

  // Cash Collateral does not rely on typical Repayment Capacity calculation
  // We just show NA or 0
  let rpcElem = document.getElementById('score_summary_rpc');
  if (rpcElem) rpcElem.textContent = 'Rp 0 (Cash Collateral)';
}

document.addEventListener('DOMContentLoaded', function() {
  calc6C();
});

// ===== AJAX SAVE =====
function saveSection(section) {
  var idPengajuan = document.getElementById('id_pengajuan').value || '0';
  var btn = document.getElementById('btn-save-' + section);
  if (btn) { btn.classList.add('loading'); btn.disabled = true; }

  var fd = new FormData(document.querySelector('form'));
  fd.set('section', section);
  fd.set('id_pengajuan', idPengajuan);

  fetch('save_section.php', { method:'POST', headers:{'X-CSRF-Token': window.__CSRF_TOKEN__}, body: fd })
    .then(function(r){ return r.json(); })
    .then(function(data) {
      if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
      showToast(section, data.success, data.message);
      if (data.success && data.id_pengajuan) {
        document.getElementById('id_pengajuan').value = data.id_pengajuan;
      }
    })
    .catch(function() {
      if (btn) { btn.classList.remove('loading'); btn.disabled = false; }
      showToast(section, false, 'Terjadi kesalahan koneksi.');
    });
}

function showToast(section, success, message) {
  var toast = document.getElementById('toast-' + section);
  if (!toast) return;
  toast.className = 'toast-msg ' + (success ? 'success' : 'error');
  toast.textContent = (success ? '✔ ' : '⚠ ') + message;
  toast.style.display = 'block';
  if (success) setTimeout(function(){ toast.style.display='none'; }, 4000);
}

// Prefill
document.addEventListener('DOMContentLoaded', function() {
  var P = window.__ANALIS_PREFILL__;
  if (!P) return;
  
  if (P.pengajuan) {
    var pg = P.pengajuan;
    Object.keys(pg).forEach(function(k) {
      if (k.indexOf('[') !== -1) return;
      var el = document.querySelector('form [name="' + k + '"]');
      if (el && el.type !== 'file') el.value = pg[k];
    });
  }

  if (P.jaminan_cashcolateral && P.jaminan_cashcolateral.length > 0) {
    P.jaminan_cashcolateral.forEach(function(item) {
      ccCounter++;
      var idx = ccCounter;
      var jenis = item.jenis_agunan || 'bilyet_deposito';
      var label = jenis === 'bilyet_deposito' ? '🏦 Bilyet Deposito' : '💳 Tabungan';
      var icon  = jenis === 'bilyet_deposito' ? '🏦' : '💳';
      var color = jenis === 'bilyet_deposito' ? '#1e40af' : '#065f46';

      var card = document.createElement('div');
      card.className = 'cc-card';
      card.id = 'cc-card-' + idx;
      card.style.borderLeft = '4px solid ' + color;

      var nomorField = jenis === 'bilyet_deposito'
        ? '<div class="cc-group"><label class="cc-label">Nomor Bilyet Deposito <span style="color:red">*</span></label><input type="text" id="cc_nomor_bilyet_'+idx+'" class="cc-input" placeholder="cth: 12345678" style="text-transform:uppercase;" value="' + (item.nomor_bilyet || '') + '"></div>'
        : '<div class="cc-group"><label class="cc-label">Nomor Rekening Tabungan <span style="color:red">*</span></label><input type="text" id="cc_nomor_rek_'+idx+'" class="cc-input" placeholder="cth: 1234-56-789012" style="text-transform:uppercase;" value="' + (item.nomor_rekening || '') + '"></div>';

      var jatuhTempoField = jenis === 'bilyet_deposito'
        ? '<div class="cc-group"><label class="cc-label">Tanggal Jatuh Tempo</label><input type="date" id="cc_jatuh_tempo_'+idx+'" class="cc-input" value="' + (item.jatuh_tempo || '') + '"></div>'
        : '';

      card.innerHTML = '<div class="cc-card-header">'
        + '<span class="cc-card-title">' + icon + ' ' + label + ' #' + idx + '</span>'
        + '<button type="button" class="btn-rm" onclick="ccRemoveAgunan(' + idx + ')">✕ Hapus</button>'
        + '</div>'
        + '<div class="cc-grid">'
        + nomorField
        + '<div class="cc-group"><label class="cc-label">Atas Nama</label><input type="text" id="cc_atas_nama_'+idx+'" class="cc-input" placeholder="Nama pemilik" style="text-transform:uppercase;" value="' + (item.atas_nama || '') + '"></div>'
        + '<div class="cc-group"><label class="cc-label">Nilai Nominal (Rp) <span style="color:red">*</span></label><input type="number" id="cc_nominal_'+idx+'" class="cc-input" min="0" value="' + (item.nilai_nominal || '0') + '" oninput="ccRecalc()"></div>'
        + '<div class="cc-group"><label class="cc-label">Nilai Taksasi (95%)</label><div id="cc_taksasi_disp_'+idx+'" style="padding:.65rem .9rem;background:#d1fae5;border:1px solid #6ee7b7;border-radius:7px;font-weight:700;color:#065f46;">Rp 0</div></div>'
        + jatuhTempoField
        + '<div class="cc-group"><label class="cc-label">Keterangan</label><input type="text" id="cc_ket_'+idx+'" class="cc-input" placeholder="Opsional" value="' + (item.keterangan || '') + '"></div>'
        + '</div>'
        + '<input type="hidden" id="cc_jenis_'+idx+'" value="' + jenis + '">';

      document.getElementById('cc-agunan-container').appendChild(card);
      ccItems.push(idx);
    });
    ccRecalc();
  }
});
</script>
</body>
</html>
