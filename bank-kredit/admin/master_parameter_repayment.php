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
            } elseif ($persen <= 0 || $persen > 100) {
                $error = 'Persentase maksimal angsuran harus antara 0.01 dan 100.';
            } elseif ($tglMulai === null) {
                $error = 'Tanggal efektif wajib diisi (format YYYY-MM-DD).';
            } elseif ($tglSampai !== null && $tglSampai < $tglMulai) {
                $error = 'Tanggal berakhir tidak boleh lebih awal dari tanggal efektif.';
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

        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Jenis Kredit</th>
                        <th>Dasar Perhitungan</th>
                        <th>Persentase</th>
                        <th>Tanggal Berlaku</th>
                        <th>Status Approval</th>
                        <th>Pembuat Draft</th>
                        <th>Reviewer</th>
                        <th>Approver</th>
                        <?php if (!$viewOnly): ?><th>Aksi</th><?php endif; ?>
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
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($jenisOptions[$p['jenis_kredit']] ?? $p['jenis_kredit']) ?></strong>
                                    <?php if ($isActiveNow): ?>
                                        <span class="badge" style="background:#dcfce7;color:#166534;margin-left:0.35rem;">Berlaku</span>
                                    <?php elseif ($isFutureEffective): ?>
                                        <span class="badge" style="background:#fef3c7;color:#92400e;margin-left:0.35rem;">Menunggu Efektif</span>
                                    <?php elseif ($p['status'] === 'nonaktif'): ?>
                                        <span class="badge" style="background:#f1f5f9;color:#64748b;margin-left:0.35rem;">Nonaktif</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($dasarOptions[$p['dasar_perhitungan']] ?? $p['dasar_perhitungan']) ?></td>
                                <td><strong><?= number_format((float) $p['persen_maks_angsuran'], 2, ',', '.') ?>%</strong></td>
                                <td style="font-size:0.88rem;">
                                    <?= htmlspecialchars($p['tgl_berlaku_mulai']) ?>
                                    <?php if (!empty($p['tgl_berlaku_sampai'])): ?>
                                        <br>s/d <?= htmlspecialchars($p['tgl_berlaku_sampai']) ?>
                                    <?php else: ?>
                                        <br><span style="color:#64748b;">(tanpa batas)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeClass ?>">
                                        <?= htmlspecialchars(getRepaymentWorkflowDisplayStatus($p), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php if (!empty($p['catatan_kadiv']) && ($p['status_approval'] ?? '') === 'draft'): ?>
                                        <div style="font-size:0.75rem; color:#b45309; margin-top:0.35rem;">Catatan Kadiv: <?= htmlspecialchars($p['catatan_kadiv']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($p['catatan_direksi']) && ($p['status_approval'] ?? '') === 'ditolak'): ?>
                                        <div style="font-size:0.75rem; color:#dc2626; margin-top:0.35rem;">Catatan Direksi: <?= htmlspecialchars($p['catatan_direksi']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size:0.88rem; color:#475569;">
                                        <?= htmlspecialchars($p['created_by_nama'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.88rem; color:#475569;">
                                        <?= htmlspecialchars($p['approved_kadiv_by_nama'] ?? '-') ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size:0.88rem; color:#475569;">
                                        <?= htmlspecialchars($p['approved_by_nama'] ?? '-') ?>
                                    </span>
                                </td>
                                <?php if (!$viewOnly): ?>
                                <td>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.35rem;">
                                        <?php if (canEditRepaymentDraft($p)): ?>
                                            <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                onclick='editParameter(<?= json_encode($p, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>Edit Draft</button>
                                        <?php endif; ?>
                                        <?php if (canSubmitRepaymentProposal($p)): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Ajukan usulan ini ke Kadiv?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id_parameter" value="<?= (int) $p['id_parameter'] ?>">
                                                <button type="submit" name="submit_parameter" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;">
                                                    <?= ($p['status_approval'] ?? '') === 'ditolak' ? 'Ajukan Ulang' : 'Ajukan Usulan' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (canApproveRepaymentKadiv($p)): ?>
                                            <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                onclick="openKadivModal(<?= (int) $p['id_parameter'] ?>)">Setujui Kadiv</button>
                                        <?php endif; ?>
                                        <?php if (canApproveRepaymentFinal($p)): ?>
                                            <button type="button" class="btn btn-primary" style="padding:0.35rem 0.65rem; font-size:0.8rem;"
                                                onclick="openDireksiModal(<?= (int) $p['id_parameter'] ?>)">Aktifkan</button>
                                        <?php endif; ?>
                                        <?php if (canRejectRepaymentKadiv($p)): ?>
                                            <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem; color:#dc2626;"
                                                onclick="openRejectKadivModal(<?= (int) $p['id_parameter'] ?>)">Kembalikan ke Kabag</button>
                                        <?php endif; ?>
                                        <?php if (canRejectRepaymentDireksi($p)): ?>
                                            <button type="button" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem; color:#dc2626;"
                                                onclick="openRejectDireksiModal(<?= (int) $p['id_parameter'] ?>)">Tolak</button>
                                        <?php endif; ?>
                                        <?php if (canDeleteRepaymentDraft($p)): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus draft ini?');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id_parameter" value="<?= (int) $p['id_parameter'] ?>">
                                                <button type="submit" name="delete_parameter" class="btn btn-secondary" style="padding:0.35rem 0.65rem; font-size:0.8rem; color:#dc2626;">Hapus</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if (canCreateRepaymentDraft()): ?>
    <div id="modal-parameter" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:640px;">
            <span class="close" onclick="closeModal('modal-parameter')">&times;</span>
            <h2 id="modal-title">Buat Draft Parameter</h2>
            <form method="POST" id="form-parameter">
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
                        <input type="number" name="persen_maks_angsuran" id="persen_maks_angsuran" min="0.01" max="100" step="0.01" value="75" required>
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
                        <label>Tanggal Efektif <span style="color:#dc2626;">*</span></label>
                        <input type="date" name="tgl_berlaku_mulai" id="tgl_berlaku_mulai" required>
                        <small style="color:#64748b;">Kebijakan berlaku untuk pengajuan dengan tanggal analisa pada atau setelah tanggal ini.</small>
                    </div>
                    <div class="custom-form-group">
                        <label>Tanggal Berakhir <span style="color:#94a3b8;">(opsional)</span></label>
                        <input type="date" name="tgl_berlaku_sampai" id="tgl_berlaku_sampai">
                    </div>
                </div>

                <div class="custom-form-group">
                    <label>Keterangan Kebijakan</label>
                    <textarea name="keterangan_kebijakan" id="keterangan_kebijakan" rows="3" placeholder="Alasan dan rujukan kebijakan perubahan parameter"></textarea>
                </div>

                <p style="font-size:0.85rem; color:#64748b;">Draft disimpan dengan status <strong>Draft</strong>. Ajukan ke Kadiv setelah selesai.</p>

                <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
                    <button type="submit" name="save_parameter" class="btn btn-primary">Simpan Draft</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-parameter')">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$viewOnly): ?>
    <div id="modal-kadiv" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <span class="close" onclick="closeModal('modal-kadiv')">&times;</span>
            <h2>Persetujuan Tahap Pertama (Kadiv)</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_parameter" id="kadiv_id_parameter" value="0">
                <div class="custom-form-group">
                    <label>Catatan Review</label>
                    <textarea name="catatan_kadiv" rows="3" placeholder="Opsional"></textarea>
                </div>
                <button type="submit" name="approve_kadiv" class="btn btn-primary">Setujui & Teruskan ke Direksi</button>
            </form>
        </div>
    </div>

    <div id="modal-direksi" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <span class="close" onclick="closeModal('modal-direksi')">&times;</span>
            <h2>Persetujuan Akhir (Direksi)</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_parameter" id="direksi_id_parameter" value="0">
                <div class="custom-form-group">
                    <label>Catatan Direksi</label>
                    <textarea name="catatan_direksi" rows="3" placeholder="Opsional"></textarea>
                </div>
                <button type="submit" name="approve_final" class="btn btn-primary">Setujui & Aktifkan Parameter</button>
            </form>
        </div>
    </div>

    <div id="modal-reject-kadiv" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <span class="close" onclick="closeModal('modal-reject-kadiv')">&times;</span>
            <h2>Kembalikan ke Kabag Kredit</h2>
            <p style="font-size:0.9rem; color:#64748b;">Usulan akan dikembalikan ke Kabag Kredit untuk revisi (status draft).</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_parameter" id="reject_kadiv_id" value="0">
                <div class="custom-form-group">
                    <label>Catatan / Alasan</label>
                    <textarea name="catatan_penolakan" rows="3" required placeholder="Jelaskan perbaikan yang diperlukan"></textarea>
                </div>
                <button type="submit" name="reject_kadiv" class="btn btn-secondary" style="color:#dc2626;">Kembalikan ke Kabag</button>
            </form>
        </div>
    </div>

    <div id="modal-reject-direksi" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:480px;">
            <span class="close" onclick="closeModal('modal-reject-direksi')">&times;</span>
            <h2>Tolak Parameter (Direksi)</h2>
            <p style="font-size:0.9rem; color:#64748b;">Status menjadi <strong>ditolak</strong>. Kabag Kredit dapat memperbaiki dan mengajukan ulang.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_parameter" id="reject_direksi_id" value="0">
                <div class="custom-form-group">
                    <label>Alasan Penolakan</label>
                    <textarea name="catatan_penolakan" rows="3" required></textarea>
                </div>
                <button type="submit" name="reject_direksi" class="btn btn-secondary" style="color:#dc2626;">Tolak Usulan</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

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
            document.getElementById('modal-parameter').style.display = 'block';
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
            document.getElementById('modal-parameter').style.display = 'block';
        }
        <?php endif; ?>

        <?php if (!$viewOnly): ?>
        function openKadivModal(id) {
            document.getElementById('kadiv_id_parameter').value = id;
            document.getElementById('modal-kadiv').style.display = 'block';
        }

        function openDireksiModal(id) {
            document.getElementById('direksi_id_parameter').value = id;
            document.getElementById('modal-direksi').style.display = 'block';
        }

        function openRejectKadivModal(id) {
            document.getElementById('reject_kadiv_id').value = id;
            document.getElementById('modal-reject-kadiv').style.display = 'block';
        }

        function openRejectDireksiModal(id) {
            document.getElementById('reject_direksi_id').value = id;
            document.getElementById('modal-reject-direksi').style.display = 'block';
        }
        <?php endif; ?>

        window.onclick = function (event) {
            ['modal-parameter', 'modal-kadiv', 'modal-direksi', 'modal-reject-kadiv', 'modal-reject-direksi'].forEach(function (id) {
                var modal = document.getElementById(id);
                if (modal && event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
    </script>
</body>

</html>
