<?php
function captureAllDbState($pdo, $id_pengajuan) {
    if (!$id_pengajuan) return [];
    $state = [];
    
    // pengajuan_kredit
    $stmt = $pdo->prepare("SELECT * FROM pengajuan_kredit WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $state['pengajuan_kredit'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // analisa_5c
    $stmt = $pdo->prepare("SELECT * FROM analisa_5c WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $state['analisa_5c'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // analisa_neraca
    $stmt = $pdo->prepare("SELECT * FROM analisa_neraca WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $state['analisa_neraca'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // jaminan_tanah_bangunan (multiple)
    $stmt = $pdo->prepare("SELECT * FROM jaminan_tanah_bangunan WHERE id_pengajuan = ?");
    $stmt->execute([$id_pengajuan]);
    $state['jaminan_tanah_bangunan'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    
    return $state;
}

function processAuditDiffAndCheckpoint($pdo, $id_pengajuan, $section, $before_snapshot, $user_id) {
    if (!$id_pengajuan || empty($before_snapshot)) return;
    
    $after_snapshot = captureAllDbState($pdo, $id_pengajuan);
    
    // Bandingkan dan catat diff
    $diffs = [];
    foreach (['pengajuan_kredit', 'analisa_5c', 'analisa_neraca'] as $table) {
        $before = $before_snapshot[$table] ?? [];
        $after = $after_snapshot[$table] ?? [];
        foreach ($after as $col => $new_val) {
            $old_val = $before[$col] ?? null;
            if ((string)$new_val !== (string)$old_val) {
                // Ignore tracking timestamp/auto updates
                if (in_array($col, ['updated_at', 'tanggal_analisa', 'last_revision_at'])) continue;
                $diffs[] = [
                    'table' => $table,
                    'field' => $col,
                    'old' => $old_val,
                    'new' => $new_val
                ];
            }
        }
    }
    
    // Insert audit logs
    if (!empty($diffs)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO analysis_audit_logs (id_pengajuan, tab_name, field_name, old_value, new_value, changed_by) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($diffs as $d) {
                $stmt->execute([$id_pengajuan, $section, $d['field'], $d['old'], $d['new'], $user_id]);
            }
        } catch (Exception $e) {}
    }

    // Map backend section names to UI tab IDs
    $sectionMap = [
        'pemohon' => 'pemohon',
        'usaha' => 'usaha',
        'penghasilan_pegawai' => 'penghasilan',
        'struktur' => 'struktur',
        'neraca' => 'neraca',
        'agunan' => 'agunan',
        'jaminan_pegawai' => 'agunan',
        '6c' => '6c',
        'kesimpulan' => 'scoring',
        'scoring' => 'scoring'
    ];

    if (isset($sectionMap[$section])) {
        $tabName = $sectionMap[$section];
        try {
            $stmt = $pdo->prepare("SELECT status FROM analysis_checkpoints WHERE id_pengajuan = ? AND tab_name = ?");
            $stmt->execute([$id_pengajuan, $tabName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                if ($row['status'] === 'REVISION') {
                    $pdo->prepare("UPDATE analysis_checkpoints SET status = 'FIXED' WHERE id_pengajuan = ? AND tab_name = ?")->execute([$id_pengajuan, $tabName]);
                    // Update analysis_revisions status too!
                    $pdo->prepare("UPDATE analysis_revisions SET status = 'FIXED', fixed_by = ?, fixed_at = NOW() WHERE id_pengajuan = ? AND tab_name = ? AND status = 'PENDING'")->execute([$user_id, $id_pengajuan, $tabName]);
                }
            } else {
                $pdo->prepare("INSERT INTO analysis_checkpoints (id_pengajuan, tab_name, status) VALUES (?, ?, 'FILLED') ON DUPLICATE KEY UPDATE status = 'FILLED'")->execute([$id_pengajuan, $tabName]);
            }
        } catch (Exception $e) {}
    }
}
