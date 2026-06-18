# Audit Logika Repayment Capacity Existing

Sesuai dengan instruksi *STEP 1 – Audit Logika Existing*, berikut adalah hasil identifikasi sistem untuk perhitungan dan klasifikasi **Repayment Capacity (RPC)** yang saat ini beroperasi pada aplikasi analisa kredit.

## 1. Lokasi File yang Mengatur Perhitungan Repayment
Fungsi-fungsi perhitungan dan validasi kapasitas bayar tersebar di sisi Server (PHP) dan Client (Javascript) untuk sinkronisasi nilai secara *realtime* di antarmuka (UI).

**Backend (Server-side):**
- `helpers/credit_helper.php`: Menyimpan core function / helper logika perbankan untuk kalkulasi repayment dan status kelayakan.
- `analis/save_section.php`: Controller API yang menangani logic `INSERT/UPDATE` nilai `repayment_capacity`, `net_cashflow`, dan `status_kelayakan` ke dalam database (`pengajuan_kredit`).

**Frontend (Client-side):**
- `analis/form_umum.php`: Mengatur tampilan (UI), hitungan _real-time_ JavaScript (fungsi `calcUsaha()`), pengujian kelayakan angsuran, serta penyusunan teks otomatis untuk tab "Kesimpulan Analisa Usaha".
- `analis/partials/pegawai_head_raw.inc.php`: Berisi konfigurasi javascript, termasuk JS function `hitungRepayment()` yang digunakan juga oleh form selain Umum (misal PPPK, Perangkat Desa).

## 2. Fungsi yang Digunakan Saat Ini

Mekanisme saat ini menggunakan kalkulasi *cashflow-based* dengan multiplier *fixed margin* sebesar **75%**.

**a. Fungsi di `helpers/credit_helper.php`:**
- `hitungRepayment($penghasilanBersih)`: Fungsi ini dieksekusi di server untuk menghitung Repayment Capacity. Formulanya di-hardcode *safety margin* 75%.
  ```php
  function hitungRepayment($penghasilanBersih) {
      $penghasilanBersih = (float)($penghasilanBersih ?? 0);
      return $penghasilanBersih * 0.75;
  }
  ```
- Terdapat fungsi lain: `hitung_repayment($gaji, $pengeluaran, $angsuran=0)` dan `klasifikasi_repayment($nilai, $gaji=null)`.
- `fetch_data_analis_untuk_kepatuhan()`: Fungsi query untuk menyiapkan kalkulasi kelayakan agunan bagi tab Compliance/Kepatuhan untuk mencocokkan `repayment_capacity` melawan `angsuran_diajukan`.

**b. Fungsi di Frontend Javascript (`form_umum.php` & `pegawai_head_raw.inc.php`):**
- `hitungRepayment(penghasilanBersih)`: Menduplikasi logika server-side untuk update DOM.
  ```javascript
  function hitungRepayment(penghasilanBersih) {
      return penghasilanBersih * 0.75; // Fix 75%
  }
  ```
- Status kelayakan ditentukan dari:
  ```php
  $status_kelayakan = ($rpc >= $angsuran_diajukan) ? 'LAYAK' : 'TIDAK LAYAK';
  ```

## 3. Jenis Kredit yang Menggunakan Logika Tersebut
Dari audit pada controller utama penyimpan data (`analis/save_section.php`), algoritma Repayment Capacity 75% ini bersifat **sentral** dan saat ini berdampak merata pada seluruh klaster pekerjaan, yakni:
1. **Pemohon Umum/Wiraswasta / Usaha / Kretamas** (Segmen *Cashflow* usaha dan omzet).
2. **PPPK (Pegawai Pemerintah dengan Perjanjian Kerja)** (Segmen gaji bersih/ *take home pay*).
3. **Perangkat Desa (Kepala Desa, Sekretaris, dll)** (Segmen penghasilan tetap/tambahan desa).
4. *(Note: Model Cash Collateral (Deposito bersaldo) menggunakan uji kelayakan rasio 'Taksasi Jaminan >= Jumlah Kredit' bukan RPC cashflow).* 

## 4. Pengaruh Perubahan Terhadap Modul Lain
Untuk membuat logika RPC ini "Dapat Di-Custom" (dinamis) dan mendukung "Model Approval Berjenjang", ini akan mempengaruhi titik-titik krusial berikut:

1. **Database Schema (`pengajuan_kredit`)**:
   Detail mengenai field tambahan jika disetujui custom value RPC, contoh mungkin `rpc_custom_persen` atau sejenisnya.
2. **Skrip Javascript Kalkulasi Real-time (`form_umum.php`, `pegawai_head_raw.inc.php`)**:
   Kalkulasi JS harus tahu berapa % RPC yang sedang diterapkan (tidak lagi *hardcoded* 0.75). Harus dirancang ulang agar form bisa mengirim persen kustom dari Analis atau UI approval.
3. **Teks DOM Automatis JS `form_umum.php`**: 
   Kesimpulan otomatis UI di Frontend saat ini menyatakan "Dengan rasio perhitungan maksimal 75%, Repayment Capacity debitur adalah...". Redaksinya perlu dibuat mendinamiskan string agar merefleksikan persentase terbaru.
4. **Validasi Server-Side (`save_section.php`)**:
   Backend harus dapat menerima _submitted parameter_ `% custom RPC` dan memverifikasi batas wewenangnya.
5. **Approval Workflow**: 
   Akan ada flow di mana penggunaan *custom rate RPC* dengan batas limit di luar standar memerlukan approval jabatan tertentu.

---
**Status Audit**: Selesai. Seluruh logik teridentifikasi. Tidak ada file atau logika yang diubah dalam langkah audit ini, sesuai dengan perintah (Stage Audit-Only).
