<?php
/**
 * Muat data untuk prefill form analis (edit mode).
 * @return array{pengajuan:array,neraca:?array,analisa_5c:?array,jaminan_tanah:array,jaminan_kendaraan:array}
 */
function analisLoadPrefillBundle(PDO $pdo, int $id_pengajuan): array
{
    $stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ? LIMIT 1");
    $stmt->execute([$id_pengajuan]);
    $pengajuan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pengajuan) {
        return [
            'pengajuan' => [],
            'neraca' => null,
            'analisa_6c' => null,
            'jaminan_tanah' => [],
            'jaminan_kendaraan' => [],
            'angsuran_bank_lain' => [],
        ];
    }

    $stmt = $pdo->prepare("SELECT * FROM analisa_neraca WHERE id_pengajuan = ? LIMIT 1");
    $stmt->execute([$id_pengajuan]);
    $neraca = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("SELECT * FROM analisa_5c WHERE id_pengajuan = ? LIMIT 1");
    $stmt->execute([$id_pengajuan]);
    $c5 = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $stmt = $pdo->prepare("SELECT * FROM jaminan_tanah_bangunan WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
    $stmt->execute([$id_pengajuan]);
    $jt = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM jaminan_kendaraan WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
    $stmt->execute([$id_pengajuan]);
    $jk = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM angsuran_bank_lain WHERE id_pengajuan = ? ORDER BY id ASC");
    $stmt->execute([$id_pengajuan]);
    $abl = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("SELECT * FROM jaminan_cashcolateral WHERE id_pengajuan = ? ORDER BY id_jaminan ASC");
    $stmt->execute([$id_pengajuan]);
    $jc = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'pengajuan' => $pengajuan,
        'neraca' => $neraca,
        'analisa_5c' => $c5,
        'jaminan_tanah' => $jt,
        'jaminan_kendaraan' => $jk,
        'angsuran_bank_lain' => $abl,
        'jaminan_cashcolateral' => $jc,
    ];
}
