<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../helpers/credit_helper.php';
require_once __DIR__ . '/../helpers/repayment_rbac.php';
require_once __DIR__ . '/../helpers/repayment_workflow.php';
require_once __DIR__ . '/../helpers/repayment_parameter_audit.php';

requireRepaymentParameterAccess();

$jenisOptions = getJenisKreditRepaymentOptions();
$dasarOptions = getDasarPerhitunganRepaymentOptions();
$approvalOptions = getRepaymentApprovalStatusLabels();
$viewOnly = isRepaymentParameterViewOnly();
$roleDesc = getRepaymentRbacRoleDescription();

$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || verifyCsrfToken($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
}

function parseRepaymentDate(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    return ($dt && $dt->format('Y-m-d') === $value) ? $value : null;
}

if ($csrf_ok && isset($_POST['save_parameter'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak untuk mengubah kebijakan kredit.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $jenis = trim((string) ($_POST['jenis_kredit'] ?? 'default'));
        $dasar = trim((string) ($_POST['dasar_perhitungan'] ?? 'net_cashflow'));
        $persen = (float) ($_POST['persen_maks_angsuran'] ?? 0);
        $status = ($_POST['status'] ?? 'nonaktif') === 'aktif' ? 'aktif' : 'nonaktif';
        $tglMulai = parseRepaymentDate($_POST['tgl_berlaku_mulai'] ?? '');
        $tglSampai = parseRepaymentDate($_POST['tgl_berlaku_sampai'] ?? '');
        $keterangan = trim((string) ($_POST['keterangan_kebijakan'] ?? ''));

        if (!isset($error) && !canCreateRepaymentDraft() && $id === 0) {
            denyRepaymentAction('Hanya Kabag Kredit yang dapat membuat draft parameter.');
        } elseif ($id > 0) {
            $existing = fetchRepaymentParameterById($pdo, $id);
            if (!$existing || !canEditRepaymentDraft($existing)) {
                denyRepaymentAction('Parameter hanya dapat diedit pada status draft atau ditolak oleh Kabag Kredit.');
            }
        }

        if (!isset($error)) {
            if (!isset($jenisOptions[$jenis])) {
                $error = 'Jenis kredit tidak valid.';
            } elseif (!isset($dasarOptions[$dasar])) {
                $error = 'Dasar perhitungan tidak valid.';
            } elseif ($persen < 1 || $persen > 100) {
                $error = 'Persentase maksimal angsuran harus antara 1 dan 100.';
            } elseif ($tglMulai === null) {
                $error = 'Tanggal efektif wajib diisi (format YYYY-MM-DD).';
            } elseif ($tglSampai !== null && $tglSampai < $tglMulai) {
                $error = 'Tanggal berakhir tidak boleh lebih awal dari tanggal efektif.';
            } else {
                $checkOverlap = $pdo->prepare("SELECT COUNT(*) FROM master_parameter_repayment WHERE jenis_kredit = ? AND status = 'aktif' AND status_approval IN ('disetujui', 'draft', 'menunggu', 'disetujui_kadiv') AND id_parameter != ? AND (tgl_berlaku_sampai IS NULL OR tgl_berlaku_sampai >= ?)");
                $checkOverlap->execute([$jenis, $id, $tglMulai]);
                if ($status === 'aktif' && $checkOverlap->fetchColumn() > 0) {
                    $error = 'Tidak boleh ada parameter aktif ganda untuk jenis kredit yang sama pada rentang waktu yang beririsan.';
                } else {
                try {
                    if ($id > 0) {
                        $existing = fetchRepaymentParameterById($pdo, $id);
                        $wasDitolak = ($existing['status_approval'] ?? '') === 'ditolak';
                        $sql = "
                            UPDATE master_parameter_repayment
                            SET jenis_kredit = ?, dasar_perhitungan = ?, persen_maks_angsuran = ?,
                                status = ?, tgl_berlaku_mulai = ?, tgl_berlaku_sampai = ?,
                                keterangan_kebijakan = ?, status_approval = 'draft',
                                submitted_by = NULL, submitted_at = NULL,
                                approved_kadiv_by = NULL, approved_kadiv_at = NULL,
                                approved_by = NULL, approved_at = NULL";
                        if (!$wasDitolak) {
                            $sql .= ", catatan_kadiv = NULL, catatan_direksi = NULL";
                        }
                        $sql .= " WHERE id_parameter = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $jenis, $dasar, $persen, $status, $tglMulai, $tglSampai,
                            $keterangan !== '' ? $keterangan : null, $id,
                        ]);
                        $afterSnap = repaymentParameterAuditSnapshotFromForm(
                            $jenis, $dasar, $persen, $status, $tglMulai, $tglSampai,
                            $keterangan, 'draft', $id
                        );
                        logRepaymentParameterAudit($pdo, [
                            'id_parameter' => $id,
                            'aksi' => 'ubah_draft',
                            'id_user' => (int) $_SESSION['user_id'],
                            'nilai_sebelum' => repaymentParameterAuditSnapshot($existing),
                            'nilai_sesudah' => $afterSnap,
                            'alasan_perubahan' => $keterangan !== '' ? $keterangan : null,
                            'status_approval' => 'draft',
                        ]);
                        repaymentWorkflowLog($pdo, $id, 'simpan_draft', $existing['status_approval'] ?? null, 'draft', (int) $_SESSION['user_id'], null);
                        auditLog($pdo, $_SESSION['user_id'], 'Kabag Kredit mengubah draft parameter repayment ID ' . $id);
                        $success = 'Draft parameter berhasil disimpan.';
                    } else {
                        $stmt = $pdo->prepare("
                            INSERT INTO master_parameter_repayment
                                (jenis_kredit, dasar_perhitungan, persen_maks_angsuran, status,
                                 tgl_berlaku_mulai, tgl_berlaku_sampai, keterangan_kebijakan,
                                 status_approval, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'draft', ?)
                        ");
                        $stmt->execute([
                            $jenis, $dasar, $persen, $status, $tglMulai, $tglSampai,
                            $keterangan !== '' ? $keterangan : null, $_SESSION['user_id'],
                        ]);
                        $newId = (int) $pdo->lastInsertId();
                        $afterSnap = repaymentParameterAuditSnapshotFromForm(
                            $jenis, $dasar, $persen, $status, $tglMulai, $tglSampai,
                            $keterangan, 'draft', $newId
                        );
                        logRepaymentParameterAudit($pdo, [
                            'id_parameter' => $newId,
                            'aksi' => 'buat_draft',
                            'id_user' => (int) $_SESSION['user_id'],
                            'nilai_sebelum' => null,
                            'nilai_sesudah' => $afterSnap,
                            'alasan_perubahan' => $keterangan !== '' ? $keterangan : null,
                            'status_approval' => 'draft',
                        ]);
                        auditLog($pdo, $_SESSION['user_id'], 'Kabag Kredit membuat draft parameter repayment: ' . $jenis);
                        $success = 'Draft parameter baru berhasil dibuat.';
                    }
                } catch (Exception $e) {
                    logError('master_parameter_repayment save', ['err' => $e->getMessage()]);
                    $error = 'Gagal menyimpan parameter. Silakan coba lagi.';
                }
                } // ends the new else block
            }
        }
    }
}

if ($csrf_ok && isset($_POST['submit_parameter'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak untuk mengajukan perubahan kebijakan.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        [$ok, $msg] = repaymentWorkflowSubmit($pdo, $id, (int) $_SESSION['user_id']);
        if ($ok) {
            auditLog($pdo, $_SESSION['user_id'], 'Kabag Kredit mengajukan parameter repayment ID ' . $id . ' ke Kadiv');
            $success = $msg;
        } else {
            $error = $msg;
        }
    }
}

if ($csrf_ok && isset($_POST['approve_kadiv'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak persetujuan Kadiv.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_kadiv'] ?? ''));
        [$ok, $msg] = repaymentWorkflowApproveKadiv($pdo, $id, (int) $_SESSION['user_id'], $catatan);
        if ($ok) {
            auditLog($pdo, $_SESSION['user_id'], 'Kadiv menyetujui tahap 1 parameter repayment ID ' . $id);
            $success = $msg;
        } else {
            $error = $msg;
        }
    }
}

if ($csrf_ok && isset($_POST['approve_final'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak persetujuan akhir.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_direksi'] ?? ''));
        [$ok, $msg] = repaymentWorkflowApproveDireksi($pdo, $id, (int) $_SESSION['user_id'], $catatan);
        if ($ok) {
            auditLog($pdo, $_SESSION['user_id'], 'Direksi mengaktifkan parameter repayment ID ' . $id);
            $success = $msg;
        } else {
            $error = $msg;
        }
    }
}

if ($csrf_ok && isset($_POST['reject_kadiv'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak menolak usulan.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_penolakan'] ?? ''));
        [$ok, $msg] = repaymentWorkflowRejectKadiv($pdo, $id, (int) $_SESSION['user_id'], $catatan);
        if ($ok) {
            auditLog($pdo, $_SESSION['user_id'], 'Kadiv mengembalikan parameter repayment ID ' . $id . ' ke Kabag');
            $success = $msg;
        } else {
            $error = $msg;
        }
    }
}

if ($csrf_ok && isset($_POST['reject_direksi'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak menolak parameter.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $catatan = trim((string) ($_POST['catatan_penolakan'] ?? ''));
        [$ok, $msg] = repaymentWorkflowRejectDireksi($pdo, $id, (int) $_SESSION['user_id'], $catatan);
        if ($ok) {
            auditLog($pdo, $_SESSION['user_id'], 'Direksi menolak parameter repayment ID ' . $id);
            $success = $msg;
        } else {
            $error = $msg;
        }
    }
}

if ($csrf_ok && isset($_POST['delete_parameter'])) {
    if ($viewOnly) {
        denyRepaymentAction('Anda tidak memiliki hak menghapus parameter.');
    } else {
        $id = (int) ($_POST['id_parameter'] ?? 0);
        $row = fetchRepaymentParameterById($pdo, $id);
        if (!$row || !canDeleteRepaymentDraft($row)) {
            denyRepaymentAction('Hanya draft oleh Kabag Kredit yang dapat dihapus.');
        } else {
            try {
                logRepaymentParameterAudit($pdo, [
                    'id_parameter' => $id,
                    'aksi' => 'hapus_draft',
                    'id_user' => (int) $_SESSION['user_id'],
                    'nilai_sebelum' => repaymentParameterAuditSnapshot($row),
                    'nilai_sesudah' => null,
                    'alasan_perubahan' => 'Penghapusan draft parameter oleh Kabag Kredit.',
                    'status_approval' => $row['status_approval'] ?? 'draft',
                ]);
                $stmt = $pdo->prepare('DELETE FROM master_parameter_repayment WHERE id_parameter = ?');
                $stmt->execute([$id]);
                auditLog($pdo, $_SESSION['user_id'], 'Menghapus draft parameter repayment ID ' . $id);
                $success = 'Draft parameter berhasil dihapus.';
            } catch (Exception $e) {
                logError('master_parameter_repayment delete', ['err' => $e->getMessage()]);
                $error = 'Gagal menghapus parameter.';
            }
        }
    }
}

$stmtList = $pdo->query("
    SELECT p.*,
           u1.nama AS created_by_nama,
           u2.nama AS approved_by_nama,
           u3.nama AS submitted_by_nama,
           u4.nama AS approved_kadiv_by_nama
    FROM master_parameter_repayment p
    LEFT JOIN users u1 ON u1.id_user = p.created_by
    LEFT JOIN users u2 ON u2.id_user = p.approved_by
    LEFT JOIN users u3 ON u3.id_user = p.submitted_by
    LEFT JOIN users u4 ON u4.id_user = p.approved_kadiv_by
    ORDER BY p.jenis_kredit ASC, p.tgl_berlaku_mulai DESC, p.id_parameter DESC
");
$parameters = $stmtList ? $stmtList->fetchAll(PDO::FETCH_ASSOC) : [];
$auditFilterId = isset($_GET['audit_id']) ? (int) $_GET['audit_id'] : 0;
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Master Parameter Repayment - Pengaturan</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="section-header" style="margin-bottom: 2rem; border-bottom: none;">
            <div>
                <h1>Master Parameter Repayment Capacity</h1>
                <p style="color:#64748b; margin-top:0.5rem; font-size:0.95rem;">
                    <?= htmlspecialchars($roleDesc, ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p style="color:#94a3b8; margin-top:0.35rem; font-size:0.88rem;">
                    Workflow terpisah dari pengajuan kredit. Penolakan Kadiv → kembali ke Kabag. Penolakan Direksi → status ditolak, Kabag dapat revisi.
                </p>
            </div>
            <?php if (canCreateRepaymentDraft()): ?>
                <button type="button" class="btn btn-primary" onclick="openModal()" style="display:flex; align-items:center; gap:0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Buat Draft Parameter
                </button>
            <?php endif; ?>
            <a href="repayment_parameter_audit_log.php<?= $auditFilterId > 0 ? '?id_parameter=' . $auditFilterId : '' ?>" class="btn btn-secondary" style="margin-left:0.5rem;">
                Audit Log Parameter
            </a>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <strong>Berhasil!</strong>
                <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <strong>Error!</strong>
                <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endif; ?>

        <?php
        $wfStages = getRepaymentWorkflowStages();
        $activeStep = 0;
        if (!empty($parameters)) {
            foreach ($parameters as $pp) {
                if (($pp['status_approval'] ?? '') === 'menunggu' && in_array(getRepaymentRbacRole(), ['kadiv_kredit', 'kadiv_bisnis'], true)) {
                    $activeStep = 1;
                    break;
                }
                if (($pp['status_approval'] ?? '') === 'disetujui_kadiv' && in_array(getRepaymentRbacRole(), ['direksi', 'direktur_utama'], true)) {
                    $activeStep = 2;
                    break;
                }
            }
        }
        if (canCreateRepaymentDraft()) {
            $activeStep = 0;
        }
        ?>
        <div class="card" style="margin-bottom:1.5rem; padding:1.25rem 1.5rem;">
            <h3 style="margin:0 0 1rem; font-size:1rem; color:#334155;">Alur Workflow Approval Parameter</h3>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center;">
                <?php foreach ($wfStages as $i => $stage): ?>
                    <?php if ($i > 0): ?>
                        <span style="color:#94a3b8; font-size:1.25rem;">↓</span>
                    <?php endif; ?>
                    <div style="flex:1; min-width:140px; padding:0.75rem 1rem; border-radius:8px; text-align:center; font-size:0.85rem;
                        <?= $i === $activeStep ? 'background:#dbeafe;border:2px solid #2563eb;color:#1e40af;font-weight:600;' : 'background:#f8fafc;border:1px solid #e2e8f0;color:#64748b;' ?>">
                        <?= htmlspecialchars($stage['label'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p style="margin:1rem 0 0; font-size:0.82rem; color:#64748b;">
                <strong>Penolakan Kadiv:</strong> usulan kembali ke Kabag Kredit (draft revisi).
                <strong>Penolakan Direksi:</strong> status ditolak → Kabag Kredit dapat perbaiki & ajukan ulang.
            </p>
        </div>

        <div class="card" style="margin-bottom:1.5rem; display:grid; grid-template-columns: minmax(200px, 2fr) 1fr 1fr; gap:1rem; padding:1.25rem 1.5rem; align-items:center; background-color:#ffffff;">
            <input type="text" id="searchInput" placeholder="Cari parameter..." style="width:100%; padding:0.6rem 1rem; border:1px solid #e2e8f0; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s; outline:none;" onkeyup="filterParameterTable()" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
            <select id="filterProduk" style="width:100%; padding:0.6rem 2.5rem 0.6rem 1rem; border:1px solid #e2e8f0; border-radius:8px; font-size:0.95rem; cursor:pointer; outline:none; transition:border-color 0.2s;" onchange="filterParameterTable()" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                <option value="">Semua Produk</option>
                <?php foreach ($jenisOptions as $val => $label): ?>
                    <?php if ($val === 'default') continue; ?>
                    <option value="<?= htmlspecialchars($label) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filterStatus" style="width:100%; padding:0.6rem 2.5rem 0.6rem 1rem; border:1px solid #e2e8f0; border-radius:8px; font-size:0.95rem; cursor:pointer; outline:none; transition:border-color 0.2s;" onchange="filterParameterTable()" onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
                <option value="">Semua Status</option>
                <option value="Berlaku">Berlaku</option>
                <option value="Menunggu Efektif">Menunggu Efektif</option>
                <option value="Nonaktif">Nonaktif</option>
                <option value="Draft">Draft</option>
                <option value="Menunggu">Menunggu Review</option>
                <option value="Disetujui">Disetujui</option>
                <option value="Ditolak">Ditolak</option>
            </select>
        </div>

        <div class="card table-responsive">
            <table id="parameterTable">
                <thead>
                    <tr>
                        <th style="width:20%; padding:1rem 1.25rem; font-weight:600; color:#334155;">Jenis Kredit</th>
                        <th style="width:20%; padding:1rem 1.25rem; font-weight:600; color:#334155; text-align:center;">Dasar</th>
                        <th style="width:10%; padding:1rem 1.25rem; font-weight:600; color:#334155; text-align:center;">Maks %</th>
                        <th style="width:15%; padding:1rem 1.25rem; font-weight:600; color:#334155; text-align:center;">Status</th>
                        <th style="width:15%; padding:1rem 1.25rem; font-weight:600; color:#334155; text-align:center;">Masa Berlaku</th>
                        <th style="width:20%; padding:1rem 1.25rem; font-weight:600; color:#334155; text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parameters)): ?>
                        <tr>
                            <td colspan="<?= $viewOnly ? 8 : 9 ?>" style="text-align:center; color:#94a3b8; padding:2rem;">
                                Belum ada parameter.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parameters as $p): ?>
                            <?php
                            $isActiveNow = ($p['status'] === 'aktif'
                                && $p['status_approval'] === 'disetujui'
                                && $p['tgl_berlaku_mulai'] <= date('Y-m-d')
                                && (empty($p['tgl_berlaku_sampai']) || $p['tgl_berlaku_sampai'] >= date('Y-m-d')));
                            $isFutureEffective = ($p['status'] === 'aktif'
                                && $p['status_approval'] === 'disetujui'
                                && $p['tgl_berlaku_mulai'] > date('Y-m-d'));
                            $badgeClass = 'badge-secondary';
                            if ($p['status_approval'] === 'disetujui') {
                                $badgeClass = 'badge-process';
                            } elseif ($p['status_approval'] === 'ditolak') {
                                $badgeClass = 'badge-danger';
                            } elseif ($p['status_approval'] === 'disetujui_kadiv') {
                                $badgeClass = 'badge-process';
                            }
                            ?>
                            <tr class="item-row" style="border-bottom:1px solid #f1f5f9; transition:background-color 0.2s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='transparent'">
                                <td class="col-jenis" style="padding:1rem 1.25rem;">
                                    <div style="display:flex; flex-direction:column; gap:0.4rem; align-items:flex-start;">
                                        <strong style="color:#0f172a; font-size:0.95rem;"><?= htmlspecialchars($jenisOptions[$p['jenis_kredit']] ?? $p['jenis_kredit']) ?></strong>
                                        <?php if ($isActiveNow): ?>
                                            <span class="badge badge-status" style="background:#dcfce7;color:#166534; font-size:0.75rem; padding:0.25rem 0.6rem; border-radius:999px; font-weight:500;">Berlaku</span>
                                        <?php elseif ($isFutureEffective): ?>
                                            <span class="badge badge-status" style="background:#fef3c7;color:#92400e; font-size:0.75rem; padding:0.25rem 0.6rem; border-radius:999px; font-weight:500;">Menunggu Efektif</span>
                                        <?php elseif ($p['status'] === 'nonaktif'): ?>
                                            <span class="badge badge-status" style="background:#f1f5f9;color:#64748b; font-size:0.75rem; padding:0.25rem 0.6rem; border-radius:999px; font-weight:500;">Nonaktif</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding:1rem 1.25rem; text-align:center; color:#475569; font-size:0.9rem;"><?= htmlspecialchars($dasarOptions[$p['dasar_perhitungan']] ?? $p['dasar_perhitungan']) ?></td>
                                <td style="padding:1rem 1.25rem; text-align:center;">
                                    <strong style="color:#0f172a; font-size:0.95rem; background:#f8fafc; padding:0.35rem 0.7rem; border-radius:8px; border:1px solid #e2e8f0;"><?= number_format((float) $p['persen_maks_angsuran'], 2, ',', '.') ?>%</strong>
                                </td>
                                <td class="col-status-approval" style="padding:1rem 1.25rem; text-align:center;">
                                    <div style="display:flex; flex-direction:column; align-items:center; gap:0.3rem;">
                                        <span class="badge <?= $badgeClass ?>" style="padding:0.3rem 0.7rem; font-size:0.8rem; border-radius:6px; font-weight:500;">
                                            <?= htmlspecialchars(getRepaymentWorkflowDisplayStatus($p), ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                        <div style="font-size:0.75rem; color:#64748b;">
                                            Pembuat: <span style="font-weight:500; color:#475569;"><?= htmlspecialchars($p['created_by_nama'] ?? '-') ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding:1rem 1.25rem; text-align:center; font-size:0.88rem; color:#475569; line-height:1.5;">
                                    <?= htmlspecialchars($p['tgl_berlaku_mulai']) ?>
                                    <?php if (!empty($p['tgl_berlaku_sampai'])): ?>
                                        <br><span style="color:#94a3b8; font-size:0.8rem;">s/d</span> <?= htmlspecialchars($p['tgl_berlaku_sampai']) ?>
                                    <?php else: ?>
                                        <br><span style="color:#94a3b8; font-size:0.8rem; font-style:italic;">(tanpa batas)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:1rem 1.25rem;">
                                    <div style="display:flex; flex-wrap:wrap; gap:0.4rem; justify-content:center;">
                                        <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                            onclick="showDetailParameter(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($jenisOptions[$p['jenis_kredit']] ?? $p['jenis_kredit']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($dasarOptions[$p['dasar_perhitungan']] ?? $p['dasar_perhitungan']), ENT_QUOTES, 'UTF-8') ?>)">Detail</button>
                                        <?php if (!$viewOnly || $_SESSION['role'] === 'Superadmin'): ?>
                                            <?php if (canEditRepaymentDraft($p) || $_SESSION['role'] === 'Superadmin'): ?>
                                                <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                    onclick='editParameter(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit</button>
                                            <?php endif; ?>
                                            <?php if (canSubmitRepaymentProposal($p)): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengajukan usulan ini?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id_parameter" value="<?= (int) $p['id_parameter'] ?>">
                                                    <button type="submit" name="submit_parameter" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem;">
                                                        <?= ($p['status_approval'] ?? '') === 'ditolak' ? 'Ajukan Ulang' : 'Ajukan Usulan' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if (canApproveRepaymentKadiv($p)): ?>
                                                <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                    onclick="openKadivModal(<?= (int) $p['id_parameter'] ?>)">Setujui</button>
                                                <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem; color:#dc2626;"
                                                    onclick="openRejectKadivModal(<?= (int) $p['id_parameter'] ?>)">Kembalikan</button>
                                            <?php endif; ?>
                                            <?php if (canApproveRepaymentFinal($p)): ?>
                                                <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                    onclick="openDireksiModal(<?= (int) $p['id_parameter'] ?>)">Aktifkan</button>
                                                <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem; color:#dc2626;"
                                                    onclick="openRejectDireksiModal(<?= (int) $p['id_parameter'] ?>)">Tolak</button>
                                            <?php endif; ?>
                                            <?php if (canDeleteRepaymentDraft($p) || $_SESSION['role'] === 'Superadmin'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data ini secara permanen?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id_parameter" value="<?= (int) $p['id_parameter'] ?>">
                                                    <button type="submit" name="delete_parameter" class="btn btn-danger" style="padding:0.35rem 0.65rem; font-size:0.8rem;">Hapus</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (canCreateRepaymentDraft()): ?>
    <div id="modal-parameter" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header">
                <h3 id="modal-title">Buat Draft Parameter</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-parameter')">&times;</button>
            </div>
            <form method="POST" id="form-parameter" style="display:flex; flex-direction:column; max-height:80vh;">
                <div class="modal-body" style="overflow-y:auto; padding-right:1rem;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_parameter" id="id_parameter" value="0">

                    <div class="custom-form-group">
                        <label>Jenis Kredit</label>
                        <select name="jenis_kredit" id="jenis_kredit" required>
                            <?php foreach ($jenisOptions as $val => $label): ?>
                                <?php if ($val === 'default') continue; ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="custom-form-group">
                        <label>Dasar Perhitungan</label>
                        <select name="dasar_perhitungan" id="dasar_perhitungan" required>
                            <?php foreach ($dasarOptions as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid-2">
                        <div class="custom-form-group">
                            <label>Persentase Maksimal Angsuran (%)</label>
                            <input type="number" name="persen_maks_angsuran" id="persen_maks_angsuran" min="1" max="100" step="0.01" value="75" required oninput="if(this.value < 1) this.value=1; if(this.value > 100) this.value=100;">
                        </div>
                        <div class="custom-form-group">
                            <label>Status (saat diaktifkan Direksi)</label>
                            <select name="status" id="status">
                                <option value="aktif">Aktif</option>
                                <option value="nonaktif">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div class="custom-form-group">
                            <label>Tanggal Efektif <span class="required">*</span></label>
                            <input type="date" name="tgl_berlaku_mulai" id="tgl_berlaku_mulai" required>
                            <small class="form-hint">Kebijakan berlaku untuk pengajuan dengan tanggal analisa pada atau setelah tanggal ini.</small>
                        </div>
                        <div class="custom-form-group">
                            <label>Tanggal Berakhir <span class="optional">(opsional)</span></label>
                            <input type="date" name="tgl_berlaku_sampai" id="tgl_berlaku_sampai">
                        </div>
                    </div>

                    <div class="custom-form-group">
                        <label>Keterangan Kebijakan</label>
                        <textarea name="keterangan_kebijakan" id="keterangan_kebijakan" rows="3" placeholder="Alasan dan rujukan kebijakan perubahan parameter"></textarea>
                    </div>
                </div>

                <div class="modal-footer" style="display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;">
                    <p style="font-size:0.85rem; color:#64748b; margin-right:auto; margin-bottom:0;">Draft disimpan dengan status <strong>Draft</strong>. Ajukan ke Kadiv setelah selesai.</p>
                    <button type="submit" name="save_parameter" class="btn btn-primary" style="padding: 0.5rem 1rem; flex: 0 0 auto; height: auto;">Simpan Draft</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-parameter')" style="padding: 0.5rem 1rem; flex: 0 0 auto; height: auto;">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$viewOnly): ?>
    <div id="modal-kadiv" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h3>Persetujuan Tahap Pertama (Kadiv)</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-kadiv')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_parameter" id="kadiv_id_parameter" value="0">
                    <div class="custom-form-group">
                        <label>Catatan Review</label>
                        <textarea name="catatan_kadiv" rows="3" placeholder="Opsional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="approve_kadiv" class="btn btn-primary">Setujui & Teruskan</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-kadiv')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-direksi" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h3>Persetujuan Akhir (Direksi)</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-direksi')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_parameter" id="direksi_id_parameter" value="0">
                    <div class="custom-form-group">
                        <label>Catatan Direksi</label>
                        <textarea name="catatan_direksi" rows="3" placeholder="Opsional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="approve_final" class="btn btn-primary">Setujui & Aktifkan Parameter</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-direksi')">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-reject-kadiv" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h3>Kembalikan ke Kabag</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-reject-kadiv')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="modal-info">
                        <p>Usulan akan dikembalikan ke Kabag Kredit untuk revisi (status draft).</p>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_parameter" id="reject_kadiv_id" value="0">
                    <div class="custom-form-group">
                        <label>Catatan / Alasan</label>
                        <textarea name="catatan_penolakan" rows="3" required placeholder="Jelaskan perbaikan yang diperlukan"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reject_kadiv" class="btn btn-secondary">Kembalikan ke Kabag</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-reject-direksi" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <div class="modal-header">
                <h3>Tolak Parameter</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-reject-direksi')">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="modal-info">
                        <p>Status menjadi <strong>ditolak</strong>. Kabag Kredit dapat memperbaiki dan mengajukan ulang.</p>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id_parameter" id="reject_direksi_id" value="0">
                    <div class="custom-form-group">
                        <label>Alasan Penolakan</label>
                        <textarea name="catatan_penolakan" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reject_direksi" class="btn btn-secondary">Tolak Usulan</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="modal-detail" class="modal-overlay" style="display:none;">
        <div class="modal-content" style="max-width:640px;">
            <div class="modal-header">
                <h3>Detail Parameter</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-detail')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="grid-2">
                    <div class="custom-form-group">
                        <label>Jenis Kredit</label>
                        <input type="text" id="detail_jenis_kredit" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Dasar Perhitungan</label>
                        <input type="text" id="detail_dasar_perhitungan" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Persentase</label>
                        <input type="text" id="detail_persen" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Status</label>
                        <input type="text" id="detail_status" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Berlaku Mulai</label>
                        <input type="text" id="detail_tgl_mulai" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Berlaku Sampai</label>
                        <input type="text" id="detail_tgl_sampai" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Pembuat</label>
                        <input type="text" id="detail_dibuat_oleh" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Dibuat Pada</label>
                        <input type="text" id="detail_dibuat_tanggal" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Diubah Oleh</label>
                        <input type="text" id="detail_diubah_oleh" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                    <div class="custom-form-group">
                        <label>Diubah Pada</label>
                        <input type="text" id="detail_diubah_tanggal" readonly style="background:#f1f5f9; cursor:not-allowed;">
                    </div>
                </div>
                <div class="custom-form-group" style="padding-bottom:1rem;">
                    <label>Keterangan</label>
                    <textarea id="detail_keterangan" rows="3" readonly style="background:#f1f5f9; cursor:not-allowed;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-detail')">Tutup</button>
            </div>
        </div>
    </div>

    <script>
        function closeModal(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = 'none';
        }

        <?php if (canCreateRepaymentDraft()): ?>
        function openModal() {
            document.getElementById('modal-title').textContent = 'Buat Draft Parameter';
            document.getElementById('id_parameter').value = '0';
            document.getElementById('jenis_kredit').value = 'umum';
            document.getElementById('dasar_perhitungan').value = 'net_cashflow';
            document.getElementById('persen_maks_angsuran').value = '75';
            document.getElementById('status').value = 'aktif';
            document.getElementById('tgl_berlaku_mulai').value = new Date().toISOString().slice(0, 10);
            document.getElementById('tgl_berlaku_sampai').value = '';
            document.getElementById('keterangan_kebijakan').value = '';
            document.getElementById('modal-parameter').style.display = 'flex';
        }

        function editParameter(row) {
            document.getElementById('modal-title').textContent = 'Edit Draft Parameter';
            document.getElementById('id_parameter').value = row.id_parameter;
            document.getElementById('jenis_kredit').value = row.jenis_kredit;
            document.getElementById('dasar_perhitungan').value = row.dasar_perhitungan;
            document.getElementById('persen_maks_angsuran').value = row.persen_maks_angsuran;
            document.getElementById('status').value = row.status;
            document.getElementById('tgl_berlaku_mulai').value = row.tgl_berlaku_mulai;
            document.getElementById('tgl_berlaku_sampai').value = row.tgl_berlaku_sampai || '';
            document.getElementById('keterangan_kebijakan').value = row.keterangan_kebijakan || '';
            document.getElementById('modal-parameter').style.display = 'flex';
        }
        <?php endif; ?>

        <?php if (!$viewOnly): ?>
        function openKadivModal(id) {
            document.getElementById('kadiv_id_parameter').value = id;
            document.getElementById('modal-kadiv').style.display = 'flex';
        }

        function openDireksiModal(id) {
            document.getElementById('direksi_id_parameter').value = id;
            document.getElementById('modal-direksi').style.display = 'flex';
        }

        function openRejectKadivModal(id) {
            document.getElementById('reject_kadiv_id').value = id;
            document.getElementById('modal-reject-kadiv').style.display = 'flex';
        }

        function openRejectDireksiModal(id) {
            document.getElementById('reject_direksi_id').value = id;
            document.getElementById('modal-reject-direksi').style.display = 'flex';
        }
        <?php endif; ?>


        function showDetailParameter(row, jenisLabel, dasarLabel) {
            document.getElementById('detail_jenis_kredit').value = jenisLabel;
            document.getElementById('detail_dasar_perhitungan').value = dasarLabel;
            document.getElementById('detail_persen').value = Number(row.persen_maks_angsuran).toFixed(2) + ' %';
            document.getElementById('detail_status').value = row.status === 'aktif' ? 'Aktif' : 'Nonaktif';
            document.getElementById('detail_tgl_mulai').value = row.tgl_berlaku_mulai || '-';
            document.getElementById('detail_tgl_sampai').value = row.tgl_berlaku_sampai || '(Tanpa Batas)';
            document.getElementById('detail_dibuat_oleh').value = row.created_by_nama || '-';
            document.getElementById('detail_dibuat_tanggal').value = row.created_at || '-';
            document.getElementById('detail_diubah_oleh').value = row.submitted_by_nama || row.approved_kadiv_by_nama || '-';
            document.getElementById('detail_diubah_tanggal').value = row.updated_at || '-';
            document.getElementById('detail_keterangan').value = row.keterangan_kebijakan || '-';
            document.getElementById('modal-detail').style.display = 'flex';
        }

        function filterParameterTable() {
            var input, filterProduk, filterStatus, table, tr, i;
            input = document.getElementById("searchInput").value.toUpperCase();
            filterProduk = document.getElementById("filterProduk").value.toUpperCase();
            filterStatus = document.getElementById("filterStatus").value.toUpperCase();
            table = document.getElementById("parameterTable");
            tr = table.getElementsByClassName("item-row");

            for (i = 0; i < tr.length; i++) {
                var tdJenis = tr[i].getElementsByClassName("col-jenis")[0];
                var tdStatus = tr[i].getElementsByClassName("col-status-approval")[0];
                var tdAll = tr[i].innerText.toUpperCase();
                
                if (tdJenis && tdStatus) {
                    var txtJenis = tdJenis.innerText.toUpperCase();
                    var txtStatus = tdStatus.innerText.toUpperCase();
                    
                    var matchSearch = tdAll.indexOf(input) > -1;
                    var matchProduk = filterProduk === "" || txtJenis.indexOf(filterProduk) > -1;
                    var matchStatus = filterStatus === "" || txtStatus.indexOf(filterStatus) > -1 || tdJenis.innerText.toUpperCase().indexOf(filterStatus) > -1;

                    if (matchSearch && matchProduk && matchStatus) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }
            }
        }

        window.onclick = function (event) {
            ['modal-parameter', 'modal-kadiv', 'modal-direksi', 'modal-reject-kadiv', 'modal-reject-direksi', 'modal-detail'].forEach(function (id) {
                var modal = document.getElementById(id);
                if (modal && event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
    </script>
</body>

</html>
