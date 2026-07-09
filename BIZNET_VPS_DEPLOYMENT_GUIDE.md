# Panduan Deploy Website ke VPS Windows (Khusus Biznet Gio)

Panduan ini disesuaikan secara khusus untuk Anda yang menggunakan layanan VPS dari Biznet (seperti NEO Virtual Compute atau layanan Biznet Cloud lainnya).

> [!CAUTION]
> **HAL PALING PENTING:** Pada layanan cloud seperti Biznet, terdapat "Dua Lapis Firewall" yaitu Firewall dari sisi panel Biznet (disebut Security Group) dan Firewall dari sisi OS Windows itu sendiri. Ini adalah hal yang paling sering membuat pemula kebingungan karena website tidak bisa diakses padahal web server sudah menyala.

---

## 1. Persiapan dari Panel Biznet Gio (Wajib Dilakukan)

Sebelum mulai melakukan remote ke dalam VPS, Anda harus mengatur akses jaringan di panel Biznet:

1. Login ke portal manajemen Biznet Gio (misalnya portal.biznetgio.com).
2. Masuk ke menu layanan VPS Anda (contoh: NEO Virtual Compute / Instances).
3. Catat **Public IP Address**, **Username** (biasanya `Administrator`), dan **Password** VPS Anda. 
4. **Konfigurasi Security Group / Firewall Biznet (SANGAT PENTING):**
   - Cari menu **Security Groups** atau pengaturan jaringan pada instance VPS Anda.
   - Anda **wajib** menambahkan *Rules* untuk mengizinkan akses web masuk ke VPS.
   - Tambahkan *Inbound Rules* berikut: 
     - **HTTP** (Port 80) - Source: `0.0.0.0/0` (Agar bisa diakses semua orang dari internet)
     - **HTTPS** (Port 443) - Source: `0.0.0.0/0`
     - **RDP** (Port 3389) - (Biasanya sudah ada secara otomatis agar Anda bisa meremote VPS).
   - *Tanpa langkah ini, website Anda tidak akan bisa diakses dari luar meskipun Windows Firewall sudah dimatikan.*

---

## 2. Login ke VPS via Remote Desktop Connection (RDP)

Setelah jalur terbuka di panel Biznet, mari masuk ke dalam VPS:

1. Buka Start Menu di komputer lokal Anda, ketik **Remote Desktop Connection**, lalu buka aplikasinya.
2. Masukkan **Public IP Address** VPS Biznet Anda, lalu klik **Connect**.
3. Masukkan **Username** (misal `Administrator`) dan **Password** dari panel Biznet.
4. Jika ada peringatan sertifikat koneksi, centang kotak *Don't ask me again...* lalu klik **Yes**.

---

## 3. Instalasi Web Server (Menggunakan Laragon)

Kita akan menggunakan Laragon seperti yang Anda gunakan di komputer lokal agar lingkungan kerjanya sama persis.

1. Buka browser (misal Microsoft Edge) **di dalam VPS** Anda.
2. Download **Laragon Full** dari situs resminya: [https://laragon.org/download/](https://laragon.org/download/).
3. Install Laragon seperti biasa (Next -> Next -> Install).
4. Buka aplikasi Laragon dan klik **Start All**. 
5. Pastikan Apache dan MySQL berjalan (biasanya ditandai dengan angka port yang muncul, misal Apache: 80, MySQL: 3306).

---

## 4. Memindahkan File Project Website

Sekarang pindahkan kode sumber website Bank Kredit Anda ke VPS.

1. Agar lebih cepat dan tidak korup, jadikan folder project Anda di lokal menjadi file **.zip** terlebih dahulu.
2. Copy (CTRL+C) file `.zip` tersebut dari komputer lokal Anda.
3. Paste (CTRL+V) di dalam Desktop VPS Anda. (RDP Windows mendukung copy-paste file secara langsung antar komputer).
4. Ekstrak file zip tersebut di VPS.
5. Pindahkan folder hasil ekstrak ke dalam folder khusus web server, yaitu: `C:\laragon\www\`.
   > Pastikan strukturnya benar dan tidak ada folder ganda. Contoh yang benar: `C:\laragon\www\bank-kredit\index.php`.

---

## 5. Konfigurasi Database (MySQL)

1. Klik tombol **Database** di aplikasi Laragon pada VPS (ini biasanya akan membuka aplikasi HeidiSQL).
2. Buat database baru dengan nama yang **sama persis** dengan database di komputer lokal Anda.
3. Lakukan **Import** (atau Load SQL file) dan pilih file `.sql` yang Anda bawa dari komputer lokal.
4. Jalankan eksekusi import hingga semua tabel selesai dibuat.
5. Periksa file koneksi database pada kode project Anda (misal `koneksi.php` atau `database.php`). Pastikan menggunakan kredensial bawaan Laragon:
   - Host: `localhost`
   - User: `root`
   - Password: `(kosongkan)`
   - Nama Database: *(Sesuai yang dibuat)*

---

## 6. Mengatur Windows Firewall di Dalam VPS

Selain Firewall di panel Biznet (Langkah 1), Anda juga harus membuka gerbang dari dalam OS Windows itu sendiri.

1. Di Start Menu VPS, ketik **Windows Defender Firewall with Advanced Security** lalu tekan Enter.
2. Klik menu **Inbound Rules** di panel sebelah kiri.
3. Klik **New Rule...** di panel sebelah kanan.
4. Pilih tipe **Port**, lalu klik Next.
5. Pilih **TCP**, lalu pada kolom *Specific local ports* ketikkan: `80, 443`. Klik Next.
6. Pilih **Allow the connection**. Klik Next.
7. Centang ketiga opsi yang ada (Domain, Private, Public). Klik Next.
8. Beri nama rule ini (misalnya `Akses Web Publik`), lalu klik Finish.

---

## 7. Mengakses Website Anda

Selamat! Kini website Anda telah mengudara.
Untuk mencobanya:
1. Buka browser dari HP atau komputer mana saja (jangan dari dalam VPS).
2. Ketikkan di address bar: `http://<IP-Public-Biznet-Anda>/bank-kredit/`
3. Jika konfigurasi sudah benar semua, website aplikasi Bank Kredit Anda akan langsung terbuka!

> [!TIP]
> Jika sewaktu-waktu website tidak bisa diakses, periksa kembali 2 lapis firewall yang disebutkan di atas: **Security Group di panel Biznet** dan **Windows Firewall di dalam VPS**. Hampir 90% masalah pemula ada di bagian ini.
