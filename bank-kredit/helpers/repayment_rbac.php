<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

/**
 * Role yang boleh membuka halaman master parameter repayment.
 *
 * @return string[]
 */
function repaymentParameterAllowedRoles()
{
    return [
        'analis',
        'kasubag_analis',
        'kabag_analis',
        'kabag_kredit',
        'kadiv_kredit',
        'kadiv_bisnis',
        'direksi',
        'direktur_utama',
        'Superadmin',
    ];
}

function getRepaymentRbacRole()
{
    return (string) ($_SESSION['role'] ?? '');
}

function canAccessRepaymentParameterPage()
{
    return in_array(getRepaymentRbacRole(), repaymentParameterAllowedRoles(), true);
}

function requireRepaymentParameterAccess()
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
    if (!canAccessRepaymentParameterPage()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Akses Ditolak</title><link rel="stylesheet" href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/assets/style.css"></head><body style="padding:2rem;font-family:sans-serif"><h1>Akses Ditolak</h1><p>Anda tidak memiliki hak akses ke master parameter repayment.</p><p><a href="' . htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') . '/auth/login.php">Kembali</a></p></body></html>';
        exit;
    }
}

/**
 * Analis, Admin Kredit (kasubag/kabag analis), Superadmin/IT: lihat saja.
 */
function isRepaymentParameterViewOnly()
{
    return in_array(getRepaymentRbacRole(), ['analis', 'kasubag_analis', 'kabag_analis'], true);
}

function canViewRepaymentParameters()
{
    return canAccessRepaymentParameterPage();
}

function canCreateRepaymentDraft()
{
    return in_array(getRepaymentRbacRole(), ['kabag_kredit', 'Superadmin'], true);
}

function canEditRepaymentDraft(array $row)
{
    if (!in_array(getRepaymentRbacRole(), ['kabag_kredit', 'Superadmin'], true)) {
        return false;
    }
    return in_array($row['status_approval'] ?? '', ['draft', 'ditolak'], true) || getRepaymentRbacRole() === 'Superadmin';
}

function canSubmitRepaymentProposal(array $row)
{
    return getRepaymentRbacRole() === 'kabag_kredit'
        && in_array($row['status_approval'] ?? '', ['draft', 'ditolak'], true);
}

function canDeleteRepaymentDraft(array $row)
{
    if (getRepaymentRbacRole() === 'Superadmin') {
        return true;
    }
    return getRepaymentRbacRole() === 'kabag_kredit'
        && ($row['status_approval'] ?? '') === 'draft'
        && empty($row['catatan_kadiv']);
}

function canApproveRepaymentKadiv(array $row)
{
    return in_array(getRepaymentRbacRole(), ['kadiv_kredit', 'kadiv_bisnis'], true)
        && ($row['status_approval'] ?? '') === 'menunggu';
}

function canApproveRepaymentFinal(array $row)
{
    return in_array(getRepaymentRbacRole(), ['direksi', 'direktur_utama'], true)
        && in_array($row['status_approval'] ?? '', ['disetujui_kadiv', 'menunggu', 'draft'], true);
}

function canRejectRepaymentKadiv(array $row)
{
    return in_array(getRepaymentRbacRole(), ['kadiv_kredit', 'kadiv_bisnis'], true)
        && ($row['status_approval'] ?? '') === 'menunggu';
}

function canRejectRepaymentDireksi(array $row)
{
    return in_array(getRepaymentRbacRole(), ['direksi', 'direktur_utama'], true)
        && in_array($row['status_approval'] ?? '', ['disetujui_kadiv', 'menunggu'], true);
}

/** @deprecated Gunakan canRejectRepaymentKadiv / canRejectRepaymentDireksi */
function canRejectRepaymentParameter(array $row)
{
    return canRejectRepaymentKadiv($row) || canRejectRepaymentDireksi($row);
}

/**
 * @return array<string, string>
 */
function getRepaymentApprovalStatusLabels()
{
    return [
        'draft' => 'Draft — Kabag Kredit',
        'menunggu' => 'Menunggu Review Kadiv',
        'disetujui_kadiv' => 'Disetujui Kadiv — Menunggu Direksi',
        'disetujui' => 'Disetujui & Aktif',
        'ditolak' => 'Ditolak Direksi',
    ];
}

/**
 * @return string
 */
function getRepaymentRbacRoleDescription()
{
    $role = getRepaymentRbacRole();
    $map = [
        'analis' => 'Analis Kredit — hanya melihat parameter.',
        'kasubag_analis' => 'Admin Kredit — hanya melihat parameter.',
        'kabag_analis' => 'Admin Kredit — hanya melihat parameter.',
        'kabag_kredit' => 'Kabag Kredit — dapat membuat draft dan mengusulkan perubahan.',
        'kadiv_kredit' => 'Kadiv Kredit — review usulan dan persetujuan tahap pertama.',
        'kadiv_bisnis' => 'Kadiv Bisnis — review usulan dan persetujuan tahap pertama.',
        'direksi' => 'Direksi — persetujuan akhir, aktivasi, atau penolakan parameter.',
        'direktur_utama' => 'Direksi — persetujuan akhir, aktivasi, atau penolakan parameter.',
        'Superadmin' => 'Administrator Sistem — kelola user; tidak dapat mengubah kebijakan kredit.',
    ];

    return $map[$role] ?? 'Akses terbatas.';
}

function fetchRepaymentParameterById(PDO $pdo, int $id)
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT * FROM master_parameter_repayment WHERE id_parameter = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function denyRepaymentAction(string $message)
{
    global $error;
    $error = $message;
}
