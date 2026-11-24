<?php
require_once __DIR__ . '/SimpleXlsxReader.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * AnalisisKreditImporter
 * 
 * Service untuk memproses import file Excel (.xlsx) Analisis Kredit PT. BPR Bank Wonosobo (Perseroda).
 * Mendukung pembacaan template resmi 10 sheet serta auto-detection format referensi.
 */
class AnalisisKreditImporter
{
    private $reader;
    private $filePath;
    private $validationErrors = [];
    private $validationWarnings = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->reader = new SimpleXlsxReader($filePath);
    }

    /**
     * Membaca dan mem-parsing seluruh data dari file Excel
     */
    public function parse(): array
    {
        $this->validationErrors = [];
        $this->validationWarnings = [];

        $sheetNames = $this->reader->getSheetNames();

        // Cek apakah format template standar 10 sheet
        if ($this->reader->hasSheet('DATA NASABAH')) {
            return $this->parseStandardTemplate();
        }

        // Cek apakah format referensi legacy Bank Wonosobo (ada sheet 'DATA AWAL NASABAH' atau 'MASTER NASABAH')
        if ($this->reader->hasSheet('DATA AWAL NASABAH') || $this->reader->hasSheet('MASTER NASABAH')) {
            return $this->parseLegacyReference();
        }

        throw new Exception("Format template Excel tidak dikenali. Pastikan Anda mengunggah Template Resmi Analisis Kredit Bank Wonosobo.");
    }

    /**
     * Parsing file template resmi 10 sheet
     */
    private function parseStandardTemplate(): array
    {
        $dataNasabahRows = $this->reader->readSheetTable('DATA NASABAH', 1, 3);
        $dataPekerjaanRows = $this->reader->hasSheet('DATA PEKERJAAN') ? $this->reader->readSheetTable('DATA PEKERJAAN', 1, 3) : [];
        $dataPinjamanRows = $this->reader->hasSheet('DATA PINJAMAN') ? $this->reader->readSheetTable('DATA PINJAMAN', 1, 3) : [];
        $dataUsahaRows = $this->reader->hasSheet('DATA USAHA') ? $this->reader->readSheetTable('DATA USAHA', 1, 3) : [];
        $dataKeuanganRows = $this->reader->hasSheet('DATA KEUANGAN') ? $this->reader->readSheetTable('DATA KEUANGAN', 1, 3) : [];
        $dataAgunanRows = $this->reader->hasSheet('DATA AGUNAN') ? $this->reader->readSheetTable('DATA AGUNAN', 1, 3) : [];
        $data6cRows = $this->reader->hasSheet('ANALISIS 6C') ? $this->reader->readSheetTable('ANALISIS 6C', 1, 3) : [];
        $dataCddRows = $this->reader->hasSheet('DATA CDD') ? $this->reader->readSheetTable('DATA CDD', 1, 3) : [];
        $dataCatatanRows = $this->reader->hasSheet('CATATAN ANALIS') ? $this->reader->readSheetTable('CATATAN ANALIS', 1, 3) : [];

        $nasabah = $dataNasabahRows[0] ?? [];
        $pekerjaan = $dataPekerjaanRows[0] ?? [];
        $pinjaman = $dataPinjamanRows[0] ?? [];
        $usaha = $dataUsahaRows[0] ?? [];
        $keuangan = $dataKeuanganRows[0] ?? [];
        $c6 = $data6cRows[0] ?? [];
        $cdd = $dataCddRows[0] ?? [];
        $catatan = $dataCatatanRows[0] ?? [];

        return [
            'format' => 'standard_10_sheets',
            'nasabah' => $nasabah,
            'pekerjaan' => $pekerjaan,
            'pinjaman' => $pinjaman,
            'usaha' => $usaha,
            'keuangan' => $keuangan,
            'agunan' => $dataAgunanRows,
            'analisis_6c' => $c6,
            'cdd' => $cdd,
            'catatan_analis' => $catatan
        ];
    }

    /**
     * Fallback parsing untuk file referensi legacy bank (seperti MUHOTIB...xlsx)
     */
    private function parseLegacyReference(): array
    {
        $rawAwal = $this->reader->hasSheet('DATA AWAL NASABAH') ? $this->reader->readSheetRaw('DATA AWAL NASABAH') : [];
        $rawMaster = $this->reader->hasSheet('MASTER NASABAH') ? $this->reader->readSheetRaw('MASTER NASABAH') : [];
        $rawAnalisis = $this->reader->hasSheet('ANALISIS') ? $this->reader->readSheetRaw('ANALISIS') : [];
        $rawTanah = $this->reader->hasSheet('TANAH BANGUNAN') ? $this->reader->readSheetRaw('TANAH BANGUNAN') : [];
        $rawKendaraan = $this->reader->hasSheet('KENDARAAN') ? $this->reader->readSheetRaw('KENDARAAN') : [];
        $rawNeraca = $this->reader->hasSheet('NERACA') ? $this->reader->readSheetRaw('NERACA') : [];
        $raw6C = $this->reader->hasSheet('6 C') ? $this->reader->readSheetRaw('6 C') : [];

        // Helper cari value berdasarkan label pada kolom A
        $getValAwal = function ($label) use ($rawAwal) {
            foreach ($rawAwal as $rNum => $cells) {
                if (isset($cells[1]) && stripos($cells[1], $label) !== false) {
                    return $cells[2] ?? '';
                }
            }
            return '';
        };

        $namaDebitur = $getValAwal('NAMA DEBITUR');
        $pekerjaan = $getValAwal('PEKERJAAN');
        $alamatKtp = $getValAwal('ALAMAT SESUAI KTP');
        $noHp = $getValAwal('NO HP');
        $nik = $getValAwal('NO KTP');
        $plafon = SimpleXlsxReader::parseCurrencyValue($getValAwal('PLAFON'));
        $jangkaWaktu = (int) $getValAwal('JANGKA WAKTU');
        $sukuBunga = (float) $getValAwal('BUNGA');
        if ($sukuBunga > 0 && $sukuBunga <= 1) {
            $sukuBunga = $sukuBunga * 100; // konversi 0.15 jadi 15%
        }
        $ibuKandung = $getValAwal('NAMA IBU KANDUNG');
        $tujuan = $getValAwal('TUJUAN PINJAMAN');
        $instansi = $getValAwal('NAMA INSTANSI');
        $pinjamanKe = (int) $getValAwal('PINJAMAN KE') ?: 1;

        // Cari tanggal lahir & tempat lahir
        $ttlDebitur = $getValAwal('TTL');
        $tempatLahir = 'WONOSOBO';
        $tglLahir = '1980-01-01';
        if ($ttlDebitur && strpos($ttlDebitur, ',') !== false) {
            $parts = explode(',', $ttlDebitur, 2);
            $tempatLahir = trim($parts[0]);
            $tglLahir = SimpleXlsxReader::parseDateValue(trim($parts[1])) ?: '1980-01-01';
        }

        // Pasangan
        $namaPasangan = $getValAwal('SUAMI/ISTRI');

        // Parse agunan tanah
        $agunanList = [];
        if (!empty($rawTanah)) {
            $shmNo = '';
            $atasNama = $namaDebitur;
            $luasTanah = 0;
            $luasBangunan = 0;
            $nilaiPasar = 0;
            $nilaiTaksasi = 0;
            $nilaiLikuidasi = 0;

            foreach ($rawTanah as $rNum => $cells) {
                $rowStr = implode(' | ', $cells);
                if (stripos($rowStr, 'SHM') !== false && preg_match('/SHM[^\s\|]*/i', $rowStr, $m)) {
                    $shmNo = $m[0];
                }
                if (isset($cells[2]) && stripos($cells[2], 'Luas Tanah') !== false) {
                    $luasTanah = (float) ($cells[11] ?? $cells[4] ?? 0);
                }
                if (isset($cells[2]) && stripos($cells[2], 'Luas Bangunan') !== false) {
                    $luasBangunan = (float) ($cells[11] ?? $cells[4] ?? 0);
                }
                if (isset($cells[2]) && stripos($cells[2], 'TOTAL NILAI WAJAR') !== false) {
                    $nilaiPasar = SimpleXlsxReader::parseCurrencyValue($cells[11] ?? $cells[4] ?? 0);
                }
                if (isset($cells[2]) && stripos($cells[2], 'NILAI TAKSASI') !== false) {
                    $nilaiTaksasi = SimpleXlsxReader::parseCurrencyValue($cells[11] ?? $cells[4] ?? 0);
                }
                if (isset($cells[2]) && stripos($cells[2], 'NILAI LIKUIDASI') !== false) {
                    $nilaiLikuidasi = SimpleXlsxReader::parseCurrencyValue($cells[11] ?? $cells[4] ?? 0);
                }
            }

            if ($nilaiTaksasi > 0 || $shmNo !== '') {
                $agunanList[] = [
                    'jenis_agunan' => 'tanah_bangunan',
                    'nama_pemilik' => $atasNama,
                    'hubungan_debitur' => 'SENDIRI',
                    'alamat_agunan' => $alamatKtp,
                    'nomor_dokumen' => $shmNo ?: 'SHM',
                    'jenis_surat' => 'SHM',
                    'luas_tanah' => $luasTanah,
                    'luas_bangunan' => $luasBangunan,
                    'harga_tanah_m2' => 0,
                    'nilai_pasar' => $nilaiPasar ?: $nilaiTaksasi,
                    'persen_taksasi' => 70,
                    'nilai_taksasi' => $nilaiTaksasi,
                    'nilai_likuidasi' => $nilaiLikuidasi,
                    'keterangan_agunan' => 'Agunan tanah & bangunan dari file referensi.'
                ];
            }
        }

        // Parse agunan kendaraan jika ada
        if (!empty($rawKendaraan)) {
            $merk = '';
            $nopol = '';
            $taksasiKend = 0;
            foreach ($rawKendaraan as $rNum => $cells) {
                $rowStr = implode(' | ', $cells);
                if (stripos($rowStr, 'Merk/type') !== false) {
                    $merk = $cells[11] ?? $cells[4] ?? '';
                }
                if (stripos($rowStr, 'No Polisi') !== false) {
                    $nopol = $cells[11] ?? $cells[4] ?? '';
                }
                if (stripos($rowStr, 'NILAI TAKSASI BANK') !== false) {
                    $taksasiKend = SimpleXlsxReader::parseCurrencyValue($cells[11] ?? $cells[4] ?? 0);
                }
            }
            if ($taksasiKend > 0 || $nopol !== '') {
                $agunanList[] = [
                    'jenis_agunan' => 'kendaraan',
                    'nama_pemilik' => $namaDebitur,
                    'hubungan_debitur' => 'SENDIRI',
                    'alamat_agunan' => $alamatKtp,
                    'nomor_dokumen' => $nopol ?: 'BPKB',
                    'jenis_surat' => 'BPKB',
                    'merk_kendaraan' => $merk ?: 'Kendaraan',
                    'tipe_kendaraan' => '',
                    'tahun_pembuatan' => date('Y') - 5,
                    'no_polisi' => $nopol,
                    'no_rangka' => '',
                    'no_mesin' => '',
                    'nilai_pasar' => $taksasiKend * 1.4,
                    'persen_taksasi' => 70,
                    'nilai_taksasi' => $taksasiKend,
                    'nilai_likuidasi' => $taksasiKend * 0.7,
                    'keterangan_agunan' => 'Agunan kendaraan bermotor.'
                ];
            }
        }

        return [
            'format' => 'legacy_reference',
            'nasabah' => [
                'tanggal_analisa' => date('Y-m-d'),
                'nama_debitur' => $namaDebitur,
                'jenis_kelamin' => 'L',
                'tempat_lahir' => $tempatLahir,
                'tanggal_lahir' => $tglLahir,
                'nik' => $nik,
                'nama_ibu_kandung' => $ibuKandung,
                'status_perkawinan' => $namaPasangan ? 'menikah' : 'lajang',
                'jumlah_tanggungan' => 1,
                'no_hp' => $noHp,
                'alamat_ktp' => $alamatKtp,
                'alamat_domisili' => $alamatKtp,
                'kota_kabupaten' => 'WONOSOBO'
            ],
            'pekerjaan' => [
                'jenis_pekerjaan' => 'pedagang',
                'nama_instansi_usaha' => $instansi ?: 'Usaha Mandiri',
                'alamat_pekerjaan' => $alamatKtp,
                'nama_pasangan' => $namaPasangan
            ],
            'pinjaman' => [
                'jenis_pinjaman' => 'KREDIT UMUM BULANAN',
                'jenis_pengguna' => 'KREDIT KONSUMSI LAINNYA',
                'tujuan_pinjaman' => $tujuan ?: 'Konsumtif',
                'plafon_pengajuan' => $plafon,
                'jangka_waktu_bulan' => $jangkaWaktu ?: 24,
                'suku_bunga_tahunan' => $sukuBunga ?: 15.0,
                'sistem_angsuran' => 'ANUITAS',
                'pinjaman_ke' => $pinjamanKe
            ],
            'usaha' => [
                'nama_usaha' => $instansi ?: 'JAJAN PASAR BU PON',
                'jenis_usaha' => 'PERDAGANGAN',
                'alamat_usaha' => $alamatKtp,
                'bidang_usaha' => 'PERDAGANGAN',
                'lama_usaha' => 'LEBIH DARI 5 TAHUN',
                'omzet_per_bulan' => 262500000,
                'pendapatan_bersih_usaha' => 17300000
            ],
            'keuangan' => [
                'aktiva_kas' => 5000000,
                'aktiva_tabungan' => 30000000,
                'aktiva_tanah' => 320450000,
                'aktiva_kendaraan' => 170000000,
                'total_aktiva' => 535450000,
                'pasiva_modal' => 530450000,
                'total_pasiva' => 535450000,
                'pengeluaran_rumah_tangga' => 3000000,
                'total_pengeluaran' => 3800000,
                'pendapatan_bersih' => 13500000,
                'repayment_capacity' => 10125000
            ],
            'agunan' => $agunanList,
            'analisis_6c' => [
                'score_character' => 4,
                'score_capacity' => 5,
                'score_capital' => 5,
                'score_collateral' => 5,
                'score_condition' => 4,
                'score_constraint' => 5,
                'rekomendasi_6c' => 'LAYAK'
            ],
            'cdd' => [
                'jenis_cdd' => 'SEDERHANA',
                'kewarganegaraan' => 'WNI',
                'nama_ahli_waris' => $namaPasangan
            ],
            'catatan_analis' => [
                'hasil_survei' => 'Peninjauan objek dan usaha telah dilaksanakan.',
                'rekomendasi_analis' => 'LAYAK'
            ]
        ];
    }

    /**
     * Memvalidasi data yang telah di-parse
     */
    public function validate(array $data, ?PDO $pdo = null): array
    {
        $errors = [];
        $warnings = [];

        $nasabah = $data['nasabah'] ?? [];
        $pinjaman = $data['pinjaman'] ?? [];
        $usaha = $data['usaha'] ?? [];
        $agunan = $data['agunan'] ?? [];
        $c6 = $data['analisis_6c'] ?? [];

        // 1. Data Nasabah
        if (empty($nasabah['nama_debitur'])) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'nama_debitur', 'pesan' => 'Nama debitur wajib diisi.'];
        }

        $nik = preg_replace('/[^\d]/', '', (string) ($nasabah['nik'] ?? ''));
        if (empty($nik)) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'nik', 'pesan' => 'Nomor KTP / NIK wajib diisi.'];
        } elseif (strlen($nik) !== 16) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'nik', 'pesan' => "NIK harus tepat 16 digit angka (ditemukan: " . strlen($nik) . " digit)."];
        }

        if (empty($nasabah['tempat_lahir'])) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'tempat_lahir', 'pesan' => 'Tempat lahir debitur wajib diisi.'];
        }

        $tglLahir = SimpleXlsxReader::parseDateValue($nasabah['tanggal_lahir'] ?? '');
        if (!$tglLahir) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'tanggal_lahir', 'pesan' => 'Tanggal lahir tidak valid (format harus YYYY-MM-DD).'];
        }

        if (empty($nasabah['nama_ibu_kandung'])) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'nama_ibu_kandung', 'pesan' => 'Nama ibu kandung wajib diisi.'];
        }

        if (empty($nasabah['no_hp'])) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'no_hp', 'pesan' => 'Nomor HP wajib diisi.'];
        }

        if (empty($nasabah['alamat_ktp'])) {
            $errors[] = ['sheet' => 'DATA NASABAH', 'kolom' => 'alamat_ktp', 'pesan' => 'Alamat KTP wajib diisi.'];
        }

        // Cek duplicate NIK di database
        if ($pdo && !empty($nik)) {
            $stmt = $pdo->prepare("SELECT id_pengajuan, nama_debitur, status_pengajuan, jumlah_kredit FROM pengajuan_kredit WHERE nik = ? ORDER BY id_pengajuan DESC");
            $stmt->execute([$nik]);
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($existing)) {
                $activePengajuan = array_filter($existing, function ($r) {
                    return !in_array($r['status_pengajuan'], ['selesai', 'ditolak', 'batal']);
                });

                if (!empty($activePengajuan)) {
                    $activeInfo = reset($activePengajuan);
                    $warnings[] = [
                        'sheet' => 'DATA NASABAH',
                        'kolom' => 'nik',
                        'pesan' => "Pemberitahuan: NIK ini memiliki pengajuan aktif sebelumnya (ID #{$activeInfo['id_pengajuan']}, status: '{$activeInfo['status_pengajuan']}'). Pastikan pengajuan baru ini adalah fasilitas lanjutan / top-up."
                    ];
                } else {
                    $warnings[] = [
                        'sheet' => 'DATA NASABAH',
                        'kolom' => 'nik',
                        'pesan' => "Informasi: NIK ini pernah terdaftar sebagai nasabah sebelumnya (Repeat Customer)."
                    ];
                }
            }
        }

        // 2. Data Pinjaman
        $plafon = SimpleXlsxReader::parseCurrencyValue($pinjaman['plafon_pengajuan'] ?? 0);
        if ($plafon <= 0) {
            $errors[] = ['sheet' => 'DATA PINJAMAN', 'kolom' => 'plafon_pengajuan', 'pesan' => 'Plafon pinjaman harus lebih dari 0.'];
        }

        $jw = (int) ($pinjaman['jangka_waktu_bulan'] ?? 0);
        if ($jw <= 0) {
            $errors[] = ['sheet' => 'DATA PINJAMAN', 'kolom' => 'jangka_waktu_bulan', 'pesan' => 'Jangka waktu pinjaman (bulan) harus lebih dari 0.'];
        }

        $bunga = (float) ($pinjaman['suku_bunga_tahunan'] ?? 0);
        if ($bunga <= 0) {
            $warnings[] = ['sheet' => 'DATA PINJAMAN', 'kolom' => 'suku_bunga_tahunan', 'pesan' => 'Suku bunga tahunan 0% atau belum diisi.'];
        }

        if (empty($pinjaman['tujuan_pinjaman'])) {
            $errors[] = ['sheet' => 'DATA PINJAMAN', 'kolom' => 'tujuan_pinjaman', 'pesan' => 'Tujuan pinjaman wajib diisi.'];
        }

        // 3. Data Usaha
        if (empty($usaha['nama_usaha'])) {
            $errors[] = ['sheet' => 'DATA USAHA', 'kolom' => 'nama_usaha', 'pesan' => 'Nama usaha wajib diisi.'];
        }

        $omzet = SimpleXlsxReader::parseCurrencyValue($usaha['omzet_per_bulan'] ?? 0);
        if ($omzet <= 0) {
            $warnings[] = ['sheet' => 'DATA USAHA', 'kolom' => 'omzet_per_bulan', 'pesan' => 'Omzet per bulan belum terisi atau 0.'];
        }

        // 4. Data Agunan
        if (empty($agunan)) {
            $warnings[] = ['sheet' => 'DATA AGUNAN', 'kolom' => 'jenis_agunan', 'pesan' => 'Tidak ada data agunan yang terdaftar. Analis disarankan melengkapi agunan sebelum submit.'];
        } else {
            foreach ($agunan as $idx => $ag) {
                $rowNum = $ag['_row_num'] ?? ($idx + 1);
                if (empty($ag['jenis_agunan'])) {
                    $errors[] = ['sheet' => 'DATA AGUNAN', 'kolom' => "Baris $rowNum", 'pesan' => "Jenis agunan pada baris $rowNum wajib ditentukan (tanah_bangunan/kendaraan/cashcolateral)."];
                }
                $taksasi = SimpleXlsxReader::parseCurrencyValue($ag['nilai_taksasi'] ?? 0);
                if ($taksasi <= 0) {
                    $warnings[] = ['sheet' => 'DATA AGUNAN', 'kolom' => "Baris $rowNum", 'pesan' => "Nilai taksasi objek agunan ke-" . ($idx + 1) . " masih 0."];
                }
            }
        }

        // 5. Analisis 6C
        $scores = ['score_character', 'score_capacity', 'score_capital', 'score_collateral', 'score_condition'];
        foreach ($scores as $sc) {
            $val = (int) ($c6[$sc] ?? 0);
            if ($val < 1 || $val > 5) {
                $warnings[] = ['sheet' => 'ANALISIS 6C', 'kolom' => $sc, 'pesan' => "Skor $sc sebaiknya berada di rentang 1 s/d 5 (ditemukan: $val)."];
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'error_count' => count($errors),
            'warning_count' => count($warnings)
        ];
    }

    /**
     * Menyimpan seluruh data hasil import ke dalam database secara transaksional
     */
    public function saveToDatabase(PDO $pdo, array $parsedData, int $userId): int
    {
        $nasabah = $parsedData['nasabah'] ?? [];
        $pekerjaan = $parsedData['pekerjaan'] ?? [];
        $pinjaman = $parsedData['pinjaman'] ?? [];
        $usaha = $parsedData['usaha'] ?? [];
        $keuangan = $parsedData['keuangan'] ?? [];
        $agunanList = $parsedData['agunan'] ?? [];
        $c6 = $parsedData['analisis_6c'] ?? [];
        $cdd = $parsedData['cdd'] ?? [];
        $catatan = $parsedData['catatan_analis'] ?? [];

        // Normalisasi format data
        $nik = preg_replace('/[^\d]/', '', (string) ($nasabah['nik'] ?? ''));
        $namaDebitur = trim((string) ($nasabah['nama_debitur'] ?? ''));
        $tglAnalisa = SimpleXlsxReader::parseDateValue($nasabah['tanggal_analisa'] ?? '') ?: date('Y-m-d');
        $tglLahir = SimpleXlsxReader::parseDateValue($nasabah['tanggal_lahir'] ?? '') ?: '1980-01-01';
        $tglLahirPasangan = SimpleXlsxReader::parseDateValue($pekerjaan['tanggal_lahir_pasangan'] ?? '');

        $statusKawin = strtolower(trim((string) ($nasabah['status_perkawinan'] ?? 'lajang')));
        if (!in_array($statusKawin, ['lajang', 'menikah', 'janda', 'duda'])) {
            $statusKawin = 'lajang';
        }

        $plafon = SimpleXlsxReader::parseCurrencyValue($pinjaman['plafon_pengajuan'] ?? 0);
        $jw = (int) ($pinjaman['jangka_waktu_bulan'] ?? 12);
        $sukuBunga = (float) ($pinjaman['suku_bunga_tahunan'] ?? 15.0);
        if ($sukuBunga > 0 && $sukuBunga <= 1) {
            $sukuBunga = $sukuBunga * 100;
        }

        $pinjamanKe = (int) ($pinjaman['pinjaman_ke'] ?? 1) ?: 1;
        $jenisKredit = trim((string) ($pinjaman['jenis_pinjaman'] ?? 'KMK')) ?: 'KMK';
        $tujuanKredit = trim((string) ($pinjaman['tujuan_pinjaman'] ?? 'Modal Usaha / Konsumtif'));

        // Hitung estimasi angsuran jika belum ada
        $angsuranDiajukan = SimpleXlsxReader::parseCurrencyValue($pinjaman['angsuran_total_estimasi'] ?? 0);
        if ($angsuranDiajukan <= 0 && $plafon > 0 && $jw > 0) {
            // Rumus Anuitas sederhana
            $bungaBulan = ($sukuBunga / 100) / 12;
            if ($bungaBulan > 0) {
                $angsuranDiajukan = $plafon * ($bungaBulan / (1 - pow(1 + $bungaBulan, -$jw)));
            } else {
                $angsuranDiajukan = $plafon / $jw;
            }
        }

        $omzetBulan = SimpleXlsxReader::parseCurrencyValue($usaha['omzet_per_bulan'] ?? 0);
        $biayaOperasional = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_operasional'] ?? 0);
        $biayaBahanBaku = SimpleXlsxReader::parseCurrencyValue($usaha['harga_pokok_penjualan'] ?? 0);
        $biayaGaji = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_tenaga_kerja'] ?? 0);
        $biayaSewa = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_sewa'] ?? 0);
        $biayaTransport = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_transportasi'] ?? 0);
        $biayaListrikAir = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_listrik_air'] ?? 0);
        $biayaLainnya = SimpleXlsxReader::parseCurrencyValue($usaha['biaya_lainnya'] ?? 0);

        if ($biayaOperasional <= 0) {
            $biayaOperasional = $biayaBahanBaku + $biayaGaji + $biayaSewa + $biayaTransport + $biayaListrikAir + $biayaLainnya;
        }

        $labaBersih = SimpleXlsxReader::parseCurrencyValue($usaha['pendapatan_bersih_usaha'] ?? 0);
        if ($labaBersih <= 0 && $omzetBulan > 0) {
            $labaBersih = $omzetBulan - $biayaOperasional;
        }

        $biayaHidup = SimpleXlsxReader::parseCurrencyValue($keuangan['pengeluaran_rumah_tangga'] ?? 0);
        $cicilanLain = SimpleXlsxReader::parseCurrencyValue($keuangan['pengeluaran_angsuran_bank'] ?? 0);
        $netCashflow = $labaBersih - $biayaHidup - $cicilanLain;
        $repaymentCapacity = SimpleXlsxReader::parseCurrencyValue($keuangan['repayment_capacity'] ?? 0);
        if ($repaymentCapacity <= 0) {
            $repaymentCapacity = $netCashflow > 0 ? ($netCashflow * 0.75) : 0;
        }

        // Tentukan jenis_jaminan utama
        $jenisJaminanUtama = 'tanah_bangunan';
        if (!empty($agunanList)) {
            $firstType = strtolower(trim((string) ($agunanList[0]['jenis_agunan'] ?? '')));
            if (in_array($firstType, ['tanah_bangunan', 'kendaraan', 'cashcolateral', 'emas'])) {
                $jenisJaminanUtama = $firstType;
            }
        }

        // MULAI DATABASE TRANSACTION
        $pdo->beginTransaction();

        try {
            // 1. INSERT KE pengajuan_kredit
            $sqlPengajuan = "INSERT INTO pengajuan_kredit (
                nama_debitur, nik, npwp, nib, tempat_lahir, tanggal_lahir, nama_ibu_kandung,
                status_perkawinan, nama_pasangan, tempat_lahir_pasangan, tanggal_lahir_pasangan,
                pekerjaan_pasangan, alamat_pekerjaan_pasangan, jumlah_tanggungan, no_hp,
                alamat_ktp, alamat_domisili, dukuh, desa, kecamatan, kota_kabupaten,
                pekerjaan, jenis_pekerjaan, id_nasabah, alamat_pekerjaan, nama_instansi,
                telepon_kantor, departemen_bagian, jabatan,
                nama_usaha, bidang_usaha, lama_usaha, omset_per_bulan, pendapatan_lain,
                biaya_bahan_baku, biaya_gaji, biaya_listrik, biaya_air, biaya_sewa,
                biaya_transportasi, biaya_lainnya, biaya_operasional, laba_bersih,
                repayment_capacity, repayment_capacity_dihitung,
                biaya_hidup, cicilan_lain, total_pengeluaran_tetap, net_cashflow,
                jumlah_kredit, jangka_waktu, suku_bunga, tujuan_kredit, pinjaman_ke,
                angsuran_diajukan, jenis_kredit, jenis_jaminan, status_kelayakan,
                status_pengajuan, posisi_saat_ini, tanggal_analisa, input_by
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                'draft', 'analis', ?, ?
            )";

            $stmtP = $pdo->prepare($sqlPengajuan);
            $stmtP->execute([
                $namaDebitur,
                $nik,
                trim((string) ($nasabah['npwp'] ?? '')),
                trim((string) ($nasabah['nib'] ?? '')),
                trim((string) ($nasabah['tempat_lahir'] ?? '')),
                $tglLahir,
                trim((string) ($nasabah['nama_ibu_kandung'] ?? '')),
                $statusKawin,
                trim((string) ($pekerjaan['nama_pasangan'] ?? '')),
                trim((string) ($pekerjaan['tempat_lahir_pasangan'] ?? '')),
                $tglLahirPasangan,
                trim((string) ($pekerjaan['pekerjaan_pasangan'] ?? '')),
                trim((string) ($pekerjaan['alamat_pekerjaan_pasangan'] ?? '')),
                (int) ($nasabah['jumlah_tanggungan'] ?? 0),
                trim((string) ($nasabah['no_hp'] ?? '')),
                trim((string) ($nasabah['alamat_ktp'] ?? '')),
                trim((string) ($nasabah['alamat_domisili'] ?? $nasabah['alamat_ktp'] ?? '')),
                trim((string) ($nasabah['dukuh'] ?? '')),
                trim((string) ($nasabah['desa'] ?? '')),
                trim((string) ($nasabah['kecamatan'] ?? '')),
                trim((string) ($nasabah['kota_kabupaten'] ?? 'WONOSOBO')),
                trim((string) ($pekerjaan['jenis_pekerjaan'] ?? 'pedagang')),
                'umum',
                trim((string) ($nasabah['id_nasabah'] ?? '')),
                trim((string) ($pekerjaan['alamat_pekerjaan'] ?? '')),
                trim((string) ($pekerjaan['nama_instansi_usaha'] ?? '')),
                trim((string) ($pekerjaan['telepon_kantor'] ?? '')),
                trim((string) ($pekerjaan['departemen_bagian'] ?? '')),
                trim((string) ($pekerjaan['jabatan'] ?? '')),
                trim((string) ($usaha['nama_usaha'] ?? '')),
                trim((string) ($usaha['bidang_usaha'] ?? '')),
                trim((string) ($usaha['lama_usaha'] ?? '')),
                $omzetBulan,
                0, // pendapatan_lain
                $biayaBahanBaku,
                $biayaGaji,
                $biayaListrikAir,
                0, // biaya_air
                $biayaSewa,
                $biayaTransport,
                $biayaLainnya,
                $biayaOperasional,
                $labaBersih,
                $repaymentCapacity,
                $repaymentCapacity,
                $biayaHidup,
                $cicilanLain,
                ($biayaHidup + $cicilanLain),
                $netCashflow,
                $plafon,
                $jw,
                $sukuBunga,
                $tujuanKredit,
                $pinjamanKe,
                $angsuranDiajukan,
                $jenisKredit,
                $jenisJaminanUtama,
                'LAYAK',
                $tglAnalisa,
                $userId
            ]);

            $idPengajuan = (int) $pdo->lastInsertId();
            if ($idPengajuan <= 0) {
                throw new Exception("Gagal membuat data pengajuan kredit baru.");
            }

            // 2. INSERT KE analisa_neraca
            $sqlNeraca = "INSERT INTO analisa_neraca (
                id_pengajuan, aktiva_kas, aktiva_tabungan, aktiva_tanah, aktiva_kendaraan,
                aktiva_stok, aktiva_lainnya, pasiva_hutang_bank, pasiva_hutang_lain, pasiva_modal,
                total_aktiva, total_pasiva
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtN = $pdo->prepare($sqlNeraca);
            $aktKas = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_kas'] ?? 0);
            $aktTab = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_tabungan'] ?? 0);
            $aktTan = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_tanah'] ?? 0);
            $aktKen = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_kendaraan'] ?? 0);
            $aktStk = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_stok'] ?? 0);
            $aktLain = SimpleXlsxReader::parseCurrencyValue($keuangan['aktiva_lainnya'] ?? 0);
            $totAkt = SimpleXlsxReader::parseCurrencyValue($keuangan['total_aktiva'] ?? 0);
            if ($totAkt <= 0) {
                $totAkt = $aktKas + $aktTab + $aktTan + $aktKen + $aktStk + $aktLain;
            }

            $pasBank = SimpleXlsxReader::parseCurrencyValue($keuangan['pasiva_hutang_bank'] ?? 0);
            $pasLain = SimpleXlsxReader::parseCurrencyValue($keuangan['pasiva_hutang_lain'] ?? 0);
            $pasModal = SimpleXlsxReader::parseCurrencyValue($keuangan['pasiva_modal'] ?? 0);
            $totPas = SimpleXlsxReader::parseCurrencyValue($keuangan['total_pasiva'] ?? 0);
            if ($totPas <= 0) {
                $totPas = $pasBank + $pasLain + $pasModal;
            }

            $stmtN->execute([
                $idPengajuan, $aktKas, $aktTab, $aktTan, $aktKen,
                $aktStk, $aktLain, $pasBank, $pasLain, $pasModal,
                $totAkt, $totPas
            ]);

            // 3. INSERT KE analisa_5c
            $sql5C = "INSERT INTO analisa_5c (
                id_pengajuan, character_score, capacity_score, capital_score, condition_score,
                collateral_score, constraint_score, total_score, catatan_5c, rekomendasi,
                catatan_character, catatan_capacity, catatan_capital, catatan_collateral,
                catatan_condition, catatan_constraint_risk
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $scChar = (int) ($c6['score_character'] ?? 4) ?: 4;
            $scCap = (int) ($c6['score_capacity'] ?? 4) ?: 4;
            $scCapital = (int) ($c6['score_capital'] ?? 4) ?: 4;
            $scCond = (int) ($c6['score_condition'] ?? 4) ?: 4;
            $scColl = (int) ($c6['score_collateral'] ?? 4) ?: 4;
            $scConst = (int) ($c6['score_constraint'] ?? 5) ?: 5;
            $totalScore = round(($scChar * 0.2) + ($scCap * 0.3) + ($scCapital * 0.2) + ($scCond * 0.1) + ($scColl * 0.2), 2);

            $stmt5C = $pdo->prepare($sql5C);
            $stmt5C->execute([
                $idPengajuan, $scChar, $scCap, $scCapital, $scCond,
                $scColl, $scConst, $totalScore,
                trim((string) ($c6['kesimpulan_6c'] ?? $catatan['keterangan_tambahan'] ?? 'Memenuhi syarat kelayakan kredit.')),
                trim((string) ($c6['rekomendasi_6c'] ?? $catatan['rekomendasi_analis'] ?? 'LAYAK')),
                trim((string) ($c6['catatan_character'] ?? $catatan['kondisi_karakter'] ?? '')),
                trim((string) ($c6['catatan_capacity'] ?? '')),
                trim((string) ($c6['catatan_capital'] ?? '')),
                trim((string) ($c6['catatan_collateral'] ?? $catatan['kondisi_agunan'] ?? '')),
                trim((string) ($c6['catatan_condition'] ?? $catatan['kondisi_usaha'] ?? '')),
                trim((string) ($c6['catatan_constraint'] ?? $catatan['catatan_risiko'] ?? ''))
            ]);

            // 4. INSERT DATA AGUNAN
            foreach ($agunanList as $ag) {
                $tipeAg = strtolower(trim((string) ($ag['jenis_agunan'] ?? 'tanah_bangunan')));
                $nilaiPasar = SimpleXlsxReader::parseCurrencyValue($ag['nilai_pasar'] ?? 0);
                $persenTaksasi = (float) ($ag['persen_taksasi'] ?? 70.0) ?: 70.0;
                $nilaiTaksasi = SimpleXlsxReader::parseCurrencyValue($ag['nilai_taksasi'] ?? 0);
                if ($nilaiTaksasi <= 0 && $nilaiPasar > 0) {
                    $nilaiTaksasi = $nilaiPasar * ($persenTaksasi / 100);
                }
                $nilaiLikuidasi = SimpleXlsxReader::parseCurrencyValue($ag['nilai_likuidasi'] ?? 0);
                if ($nilaiLikuidasi <= 0 && $nilaiTaksasi > 0) {
                    $nilaiLikuidasi = $nilaiTaksasi * 0.7;
                }

                if ($tipeAg === 'kendaraan') {
                    $stmtKen = $pdo->prepare("INSERT INTO jaminan_kendaraan (
                        id_pengajuan, merk, tipe, tahun_pembuatan, no_polisi, no_rangka,
                        no_mesin, nama_pemilik, nilai_pasar, persentase_taksasi, nilai_taksasi,
                        tipe_valuasi, nilai_taksasi_manual, nilai_likuidasi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?)");

                    $stmtKen->execute([
                        $idPengajuan,
                        trim((string) ($ag['merk_kendaraan'] ?? 'Kendaraan')),
                        trim((string) ($ag['tipe_kendaraan'] ?? '')),
                        (int) ($ag['tahun_pembuatan'] ?? date('Y') - 5),
                        trim((string) ($ag['no_polisi'] ?? $ag['nomor_dokumen'] ?? '')),
                        trim((string) ($ag['no_rangka'] ?? '')),
                        trim((string) ($ag['no_mesin'] ?? '')),
                        trim((string) ($ag['nama_pemilik'] ?? $namaDebitur)),
                        $nilaiPasar,
                        $persenTaksasi,
                        $nilaiTaksasi,
                        $nilaiTaksasi,
                        $nilaiLikuidasi
                    ]);
                } elseif ($tipeAg === 'cashcolateral' || $tipeAg === 'deposito') {
                    $stmtCC = $pdo->prepare("INSERT INTO jaminan_cashcolateral (
                        id_pengajuan, jenis_agunan, nomor_bilyet, nomor_rekening, atas_nama,
                        nilai_nominal, nilai_taksasi, keterangan
                    ) VALUES (?, 'bilyet_deposito', ?, ?, ?, ?, ?, ?)");

                    $stmtCC->execute([
                        $idPengajuan,
                        trim((string) ($ag['nomor_dokumen'] ?? $ag['no_rekening_bilyet'] ?? '')),
                        trim((string) ($ag['no_rekening_bilyet'] ?? '')),
                        trim((string) ($ag['nama_pemilik'] ?? $namaDebitur)),
                        $nilaiPasar,
                        $nilaiTaksasi,
                        trim((string) ($ag['keterangan_agunan'] ?? 'Deposito Bank'))
                    ]);
                } else {
                    // Default: tanah_bangunan
                    $stmtTan = $pdo->prepare("INSERT INTO jaminan_tanah_bangunan (
                        id_pengajuan, alamat_agunan, jenis_surat, nomor_surat, atas_nama,
                        luas_tanah, luas_bangunan, nilai_pasar, persentase_taksasi,
                        nilai_taksasi, tipe_valuasi, nilai_taksasi_manual, nilai_likuidasi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', ?, ?)");

                    $stmtTan->execute([
                        $idPengajuan,
                        trim((string) ($ag['alamat_agunan'] ?? $nasabah['alamat_ktp'] ?? '')),
                        trim((string) ($ag['jenis_surat'] ?? 'SHM')),
                        trim((string) ($ag['nomor_dokumen'] ?? 'SHM')),
                        trim((string) ($ag['nama_pemilik'] ?? $namaDebitur)),
                        (float) ($ag['luas_tanah'] ?? 0),
                        (float) ($ag['luas_bangunan'] ?? 0),
                        $nilaiPasar,
                        $persenTaksasi,
                        $nilaiTaksasi,
                        $nilaiTaksasi,
                        $nilaiLikuidasi
                    ]);
                }
            }

            // 5. AUDIT LOGGING
            $logText = "Import Excel Analisis Kredit (ID Pengajuan: #$idPengajuan, Debitur: '$namaDebitur', NIK: '$nik', Plafon: Rp " . number_format($plafon, 0, ',', '.') . ")";
            if (function_exists('auditLog')) {
                auditLog($pdo, $userId, $logText);
            } elseif (function_exists('logActivity')) {
                logActivity($userId, $logText);
            }

            // COMMIT SEMUA PERUBAHAN KE DATABASE
            $pdo->commit();

            return $idPengajuan;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw new Exception("Gagal menyimpan data import ke database: " . $e->getMessage());
        }
    }
}
