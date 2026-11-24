<?php
/**
 * SimpleXlsxReader
 * 
 * Standalone, lightweight, zero-dependency OpenXML (.xlsx) Reader for PHP.
 * Works with native PHP ZipArchive & SimpleXML / XMLReader.
 * Designed for PT. BPR Bank Wonosobo (Perseroda).
 */
class SimpleXlsxReader
{
    private $zip;
    private $sharedStrings = [];
    private $sheetMap = []; // [sheetName => sheetXmlPath]

    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new Exception("File tidak ditemukan: $filePath");
        }

        if (!class_exists('ZipArchive')) {
            throw new Exception("Ekstensi PHP ZipArchive tidak aktif.");
        }

        $this->zip = new ZipArchive();
        $res = $this->zip->open($filePath);
        if ($res !== true) {
            throw new Exception("Gagal membuka file Excel (.xlsx). Kode error: $res");
        }

        $this->parseSharedStrings();
        $this->parseWorkbook();
    }

    public function __destruct()
    {
        if ($this->zip) {
            $this->zip->close();
        }
    }

    /**
     * Membaca tabel sharedStrings.xml
     */
    private function parseSharedStrings(): void
    {
        $content = $this->zip->getFromName('xl/sharedStrings.xml');
        if (!$content) {
            return;
        }

        $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOERROR);
        if (!$xml) {
            return;
        }

        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string) $si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                $this->sharedStrings[] = $text;
            } else {
                $this->sharedStrings[] = '';
            }
        }
    }

    /**
     * Memetakan nama sheet ke file XML-nya
     */
    private function parseWorkbook(): void
    {
        $wbXml = $this->zip->getFromName('xl/workbook.xml');
        $wbRelsXml = $this->zip->getFromName('xl/_rels/workbook.xml.rels');

        if (!$wbXml) {
            throw new Exception("File Excel tidak valid (xl/workbook.xml tidak ditemukan).");
        }

        // Parse relations
        $rels = [];
        if ($wbRelsXml) {
            $relsDoc = simplexml_load_string($wbRelsXml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOERROR);
            if ($relsDoc) {
                foreach ($relsDoc->Relationship as $rel) {
                    $rId = (string) $rel['Id'];
                    $target = ltrim((string) $rel['Target'], '/');
                    if (strpos($target, 'xl/') !== 0) {
                        $target = 'xl/' . $target;
                    }
                    $rels[$rId] = $target;
                }
            }
        }

        $wbDoc = simplexml_load_string($wbXml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOERROR);
        if (!$wbDoc || !isset($wbDoc->sheets->sheet)) {
            throw new Exception("Struktur sheet pada file Excel tidak valid.");
        }

        $idx = 1;
        foreach ($wbDoc->sheets->sheet as $s) {
            $name = (string) $s['name'];
            $rId = '';
            $rAttrs = $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            if (isset($rAttrs['id'])) {
                $rId = (string) $rAttrs['id'];
            }
            if ($rId === '') {
                $rAttrs2 = $s->attributes('r', true);
                if (isset($rAttrs2['id'])) {
                    $rId = (string) $rAttrs2['id'];
                }
            }
            
            $targetPath = $rels[$rId] ?? "xl/worksheets/sheet{$idx}.xml";
            $this->sheetMap[trim($name)] = ltrim($targetPath, '/');
            $idx++;
        }
    }

    /**
     * Mengambil daftar nama sheet yang ada di file Excel
     */
    public function getSheetNames(): array
    {
        return array_keys($this->sheetMap);
    }

    /**
     * Memeriksa apakah sheet tertentu ada (case-insensitive check)
     */
    public function hasSheet(string $sheetName): bool
    {
        $search = mb_strtoupper(trim($sheetName));
        foreach (array_keys($this->sheetMap) as $name) {
            if (mb_strtoupper(trim($name)) === $search) {
                return true;
            }
        }
        return false;
    }

    /**
     * Membaca seluruh data dalam sheet dalam bentuk matriks 2D (baris dan kolom index 1-based)
     */
    public function readSheetRaw(string $sheetName): array
    {
        $actualName = null;
        $search = mb_strtoupper(trim($sheetName));
        foreach ($this->sheetMap as $name => $path) {
            if (mb_strtoupper(trim($name)) === $search) {
                $actualName = $name;
                break;
            }
        }

        if (!$actualName || !isset($this->sheetMap[$actualName])) {
            return [];
        }

        $sheetXml = $this->zip->getFromName($this->sheetMap[$actualName]);
        if (!$sheetXml) {
            return [];
        }

        $doc = simplexml_load_string($sheetXml, 'SimpleXMLElement', LIBXML_COMPACT | LIBXML_NOERROR);
        if (!$doc || !isset($doc->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($doc->sheetData->row as $r) {
            $rowNum = (int) $r['r'];
            $rowData = [];

            foreach ($r->c as $c) {
                $cellRef = (string) $c['r'];
                $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                $colNum = $this->colLetterToNumber($colLetters);

                $val = $this->parseCellValue($c);
                $rowData[$colNum] = $val;
            }

            if (!empty($rowData)) {
                $rows[$rowNum] = $rowData;
            }
        }

        return $rows;
    }

    /**
     * Membaca sheet berformat tabel dengan baris header.
     * Mengembalikan array of associative arrays [ [ 'header_key' => 'val', ... ], ... ]
     * 
     * @param string $sheetName
     * @param int $headerRow Baris letak nama header (default 1)
     * @param int $dataStartRow Baris mulai data (default 3 untuk template dengan 2 baris header)
     */
    public function readSheetTable(string $sheetName, int $headerRow = 1, int $dataStartRow = 3): array
    {
        $rawRows = $this->readSheetRaw($sheetName);
        if (empty($rawRows) || !isset($rawRows[$headerRow])) {
            return [];
        }

        // Build header mapping
        $headers = [];
        foreach ($rawRows[$headerRow] as $colNum => $hVal) {
            $hKey = self::normalizeHeaderKey((string) $hVal);
            if ($hKey !== '') {
                $headers[$colNum] = $hKey;
            }
        }

        if (empty($headers)) {
            return [];
        }

        $result = [];
        foreach ($rawRows as $rNum => $cells) {
            if ($rNum < $dataStartRow) {
                continue;
            }

            $rowObj = [];
            $hasAnyValue = false;

            foreach ($headers as $colNum => $hKey) {
                $val = $cells[$colNum] ?? null;
                if ($val !== null && trim((string) $val) !== '') {
                    $hasAnyValue = true;
                }
                $rowObj[$hKey] = $val !== null ? trim((string) $val) : '';
            }

            // Simpan baris hanya jika ada data yang diisi
            if ($hasAnyValue) {
                $rowObj['_row_num'] = $rNum;
                $result[] = $rowObj;
            }
        }

        return $result;
    }

    /**
     * Membaca nilai dari elemen sel OpenXML
     */
    private function parseCellValue($c)
    {
        $type = (string) $c['t'];
        $val = isset($c->v) ? (string) $c->v : '';

        // Shared string lookup
        if ($type === 's') {
            $idx = (int) $val;
            return $this->sharedStrings[$idx] ?? '';
        }

        // Inline string
        if ($type === 'inlineStr' && isset($c->is->t)) {
            return (string) $c->is->t;
        }

        // Boolean
        if ($type === 'b') {
            return $val === '1';
        }

        // Date detection (jika format cell menggunakan date style)
        // Jika angka desimal tanggal Excel (contoh: 44889)
        return $val;
    }

    /**
     * Konversi kode kolom huruf (A, B, ..., Z, AA, AB) ke angka (1-based)
     */
    private function colLetterToNumber(string $col): int
    {
        $col = strtoupper($col);
        $len = strlen($col);
        $num = 0;
        for ($i = 0; $i < $len; $i++) {
            $num = $num * 26 + (ord($col[$i]) - 64);
        }
        return $num;
    }

    /**
     * Normalisasi nama header kolom:
     * Menghapus spasi ekstra, tanda baca, huruf kecil, ganti spasi dengan underscore
     */
    public static function normalizeHeaderKey(string $header): string
    {
        $header = trim($header);
        $header = strtolower($header);
        $header = preg_replace('/[^\w\s]/', '', $header);
        $header = preg_replace('/\s+/', '_', $header);
        return trim($header, '_');
    }

    /**
     * Helper untuk konversi serial number tanggal Excel atau teks tanggal ke format YYYY-MM-DD
     */
    public static function parseDateValue($val): ?string
    {
        if ($val === null || trim((string) $val) === '') {
            return null;
        }

        $str = trim((string) $val);

        // Jika format sudah YYYY-MM-DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $str, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // Jika format DD/MM/YYYY atau DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $str, $m)) {
            $d = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mo = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            return "{$m[3]}-{$mo}-{$d}";
        }

        // Jika angka serial Excel (misal 23179 s/d 60000)
        if (is_numeric($str) && (float)$str > 1000 && (float)$str < 100000) {
            $days = (int) floor((float) $str);
            // Excel epoch dimulai 1899-12-30 karena bug tahun kabisat 1900
            $unixTimestamp = ($days - 25569) * 86400;
            return gmdate('Y-m-d', $unixTimestamp);
        }

        // Fallback strtotime
        $ts = strtotime($str);
        if ($ts && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return null;
    }

    /**
     * Helper untuk membersihkan nilai nominal uang menjadi float
     */
    public static function parseCurrencyValue($val): float
    {
        if ($val === null || trim((string) $val) === '') {
            return 0.0;
        }

        $str = trim((string) $val);
        // Buang prefix Rp, simbol uang, dan spasi
        $str = preg_replace('/[Rp\s\$\.]/i', '', $str);
        // Jika ada koma sebagai pemisah desimal
        $str = str_replace(',', '.', $str);
        
        return floatval($str);
    }
}
