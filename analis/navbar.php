<?php
if (isset($_SERVER['SCRIPT_FILENAME']) && @realpath((string) $_SERVER['SCRIPT_FILENAME']) === @realpath(__FILE__)) {
    http_response_code(403);
    exit;
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$current_role = $_SESSION['role'] ?? 'guest';
$current_name = $_SESSION['nama'] ?? 'Guest';

$role_labels = [
    'Superadmin' => 'Super Admin',
    'analis' => 'Analis',
    'kasubag_analis' => 'Kasubag Analis',
    'kabag_analis' => 'Kabag Analis',
    'kabag_kredit' => 'Kabag Kredit',
    'kadiv_kredit' => 'Kadiv Kredit',
    'kadiv_bisnis' => 'Kadiv Bisnis',
    'direksi' => 'Direksi',
    'direktur_utama' => 'Direktur Utama',
    'kepatuhan' => 'Kepatuhan',
];
$role_display = $role_labels[$current_role] ?? ucwords(str_replace('_', ' ', $current_role));
$user_initial = mb_strtoupper(mb_substr(trim($current_name), 0, 1, 'UTF-8'));
if ($user_initial === '') {
    $user_initial = '?';
}

/* ── SVG icon helper ── */
function navSvgIcon(string $pathD, string $pathD2 = ''): string
{
    $inner = '<path stroke-linecap="round" stroke-linejoin="round" d="' . $pathD . '"></path>';
    if ($pathD2 !== '') {
        $inner .= '<path stroke-linecap="round" stroke-linejoin="round" d="' . $pathD2 . '"></path>';
    }
    return '<svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">' . $inner . '</svg>';
}

$ico_dashboard   = navSvgIcon('M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6');
$ico_input       = navSvgIcon('M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z');
$ico_history     = navSvgIcon('M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z');
$ico_approve     = navSvgIcon('M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z');
$ico_clipboard   = navSvgIcon('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4');
$ico_users       = navSvgIcon('M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z');
$ico_settings    = navSvgIcon('M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z', 'M15 12a3 3 0 11-6 0 3 3 0 016 0z');
$ico_audit       = navSvgIcon('M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01');
$ico_chart       = navSvgIcon('M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z');
$ico_money       = navSvgIcon('M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z');
$ico_doc         = navSvgIcon('M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z');
$ico_shield      = navSvgIcon('M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z');
$ico_backup      = navSvgIcon('M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4');
$ico_team        = navSvgIcon('M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z');
$ico_building    = navSvgIcon('M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4');
$ico_exclamation = navSvgIcon('M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z');
$ico_star        = navSvgIcon('M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z');
$ico_edit        = navSvgIcon('M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z');
?>
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Mobile Toggle Button (Visible only on mobile via CSS) -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"></path>
    </svg>
</button>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Bank Wonosobo</h2>
        <!-- Badge with Glass effect -->
        <span class="badge badge-glass">
            <?= htmlspecialchars($role_display) ?>
        </span>
    </div>

    <div class="nav-links">
        <?php if ($current_role == 'analis'): ?>
            <!-- ═══════════════════════════════════════════════
                 ANALIS KREDIT
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/analis/dashboard.php">
                <?= $ico_dashboard ?> Dashboard
            </a>

            <!-- Dropdown: Input Pengajuan -->
            <?php
            $is_input_page = basename($_SERVER['PHP_SELF']) == 'input.php';
            $open_class = $is_input_page ? 'open' : '';
            ?>
            <div class="nav-item-dropdown <?= $open_class ?>">
                <a href="#" class="dropdown-toggle <?= $open_class ?>" onclick="toggleSubmenu(event, 'submenu-input')">
                    <?= $ico_input ?> Input Pengajuan
                </a>
            </div>
            <?php
            $analisNavQ = isset($ANALIS_INPUT_NAV_QUERY) ? $ANALIS_INPUT_NAV_QUERY : '';
            $pegawaiInputNav = isset($jenis_pekerjaan) && in_array($jenis_pekerjaan, ['pppk', 'perangkat_desa'], true);
            $analisStep2Target = $pegawaiInputNav ? 'tab-penghasilan' : 'tab-usaha';
            $analisStep2Label = $pegawaiInputNav ? 'Analisa' : 'Analisa Usaha';
            ?>
            <div id="submenu-input" class="submenu <?= $open_class ?>">
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-pemohon" class="nav-link-step" data-target="tab-pemohon">Data Debitur</a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#<?= htmlspecialchars($analisStep2Target, ENT_QUOTES, 'UTF-8') ?>" class="nav-link-step"
                    data-target="<?= htmlspecialchars($analisStep2Target, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($analisStep2Label) ?></a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-struktur" class="nav-link-step"
                    data-target="tab-struktur">Data Kredit</a>
                <?php if ($pegawaiInputNav): ?>
                <?php if (isset($jenis_pekerjaan) && $jenis_pekerjaan === 'pppk'): ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-jaminan" class="nav-link-step"
                    data-target="tab-jaminan">SK / Avalis</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-agunan" class="nav-link-step"
                    data-target="tab-agunan">Jaminan Agunan</a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-agunan" class="nav-link-step"
                    data-target="tab-agunan">Agunan</a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-neraca" class="nav-link-step"
                    data-target="tab-neraca">Analisa Jaminan</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-6c" class="nav-link-step" data-target="tab-6c">Analisa 6C</a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-scoring" class="nav-link-step"
                    data-target="tab-scoring">Kesimpulan</a>
            </div>

            <a href="<?= BASE_URL ?>/analis/riwayat.php">
                <?= $ico_edit ?> Revisi Pengajuan
            </a>

            <a href="<?= BASE_URL ?>/analis/riwayat.php?view=status">
                <?= $ico_history ?> Status Pengajuan
            </a>

            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>

        <?php elseif ($current_role == 'kasubag_analis'): ?>
            <!-- ═══════════════════════════════════════════════
                 KASUBAG ANALIS (same structure as Kabag Analis)
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/kasubag_analis/dashboard.php">
                <?= $ico_dashboard ?> Dashboard Monitoring
            </a>
            <a href="<?= BASE_URL ?>/kasubag_analis/proses.php">
                <?= $ico_clipboard ?> Review Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/kasubag_analis/riwayat.php">
                <?= $ico_history ?> Riwayat Approval
            </a>
            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>

        <?php elseif ($current_role == 'kabag_analis'): ?>
            <!-- ═══════════════════════════════════════════════
                 KABAG ANALIS
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/kabag_analis/dashboard.php">
                <?= $ico_dashboard ?> Dashboard Monitoring
            </a>
            <a href="<?= BASE_URL ?>/kabag_analis/proses.php">
                <?= $ico_clipboard ?> Review Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/kabag_analis/riwayat.php">
                <?= $ico_doc ?> CRUD Review
            </a>
            <a href="<?= BASE_URL ?>/kabag_analis/proses.php?action=approval">
                <?= $ico_approve ?> Approval / Revisi
            </a>
            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>

        <?php elseif ($current_role == 'kabag_kredit'): ?>
            <!-- ═══════════════════════════════════════════════
                 KABAG KREDIT
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/kabag_kredit/dashboard.php">
                <?= $ico_chart ?> Monitoring Kredit
            </a>
            <a href="<?= BASE_URL ?>/kabag_kredit/proses.php">
                <?= $ico_approve ?> Approval Kredit
            </a>
            <a href="<?= BASE_URL ?>/kabag_kredit/riwayat.php">
                <?= $ico_team ?> Monitoring Tim
            </a>
            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>

        <?php elseif ($current_role == 'kadiv_kredit' || $current_role == 'kadiv_bisnis'):
            $kadiv_folder = ($current_role === 'kadiv_bisnis') ? 'kadiv_bisnis' : 'kadiv_kredit';
        ?>
            <!-- ═══════════════════════════════════════════════
                 KADIV (Kredit / Bisnis)
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/dashboard.php">
                <?= $ico_dashboard ?> Dashboard Persetujuan
            </a>
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/proses.php">
                <?= $ico_building ?> Monitoring Cabang
            </a>
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/riwayat.php">
                <?= $ico_doc ?> Laporan Kredit
            </a>
            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>

        <?php elseif ($current_role == 'direksi' || $current_role == 'direktur_utama'): ?>
            <!-- ═══════════════════════════════════════════════
                 DIREKSI / DIREKTUR UTAMA
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/direksi/dashboard.php">
                <?= $ico_star ?> Executive Dashboard
            </a>
            <a href="<?= BASE_URL ?>/direksi/proses.php">
                <?= $ico_approve ?> Approval Final
            </a>
            <a href="<?= BASE_URL ?>/direksi/riwayat.php">
                <?= $ico_exclamation ?> Monitoring Risiko
            </a>
            <?php include __DIR__ . '/navbar_repayment_link.php'; ?>



        <?php elseif ($current_role == 'Superadmin'): ?>
            <!-- ═══════════════════════════════════════════════
                 ADMIN (Superadmin)
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/admin/dashboard.php">
                <?= $ico_dashboard ?> Dashboard
            </a>

            <!-- User Management -->
            <a href="<?= BASE_URL ?>/admin/users.php">
                <?= $ico_users ?> User Management
            </a>

            <!-- Master Data Dropdown -->
            <div class="nav-item-dropdown">
                <a href="#" class="dropdown-toggle" onclick="toggleSubmenu(event, 'submenu-admin-master')">
                    <?= $ico_audit ?> Master Data
                </a>
            </div>
            <div id="submenu-admin-master" class="submenu">
                <a href="<?= BASE_URL ?>/admin/master_pejabat.php" class="nav-link-step">Master Pejabat</a>
                <a href="<?= BASE_URL ?>/admin/master_parameter_repayment.php" class="nav-link-step">Parameter Repayment</a>
                <a href="<?= BASE_URL ?>/admin/repayment_parameter_audit_log.php" class="nav-link-step">Audit Parameter</a>
            </div>

            <!-- Parameter Sistem Dropdown -->
            <div class="nav-item-dropdown">
                <a href="#" class="dropdown-toggle" onclick="toggleSubmenu(event, 'submenu-admin-system')">
                    <?= $ico_settings ?> Parameter Sistem
                </a>
            </div>
            <div id="submenu-admin-system" class="submenu">
                <a href="<?= BASE_URL ?>/admin/logs.php" class="nav-link-step">Audit Log</a>
                <a href="<?= BASE_URL ?>/admin/audit_repayment_analisa.php" class="nav-link-step">Audit Repayment</a>
                <a href="<?= BASE_URL ?>/admin/backup.php" class="nav-link-step">Backup Data</a>
            </div>

        <?php elseif ($current_role == 'kepatuhan'): ?>
            <!-- ═══════════════════════════════════════════════
                 KEPATUHAN
                 ═══════════════════════════════════════════════ -->
            <a href="<?= BASE_URL ?>/kepatuhan/dashboard.php">
                <?= $ico_dashboard ?> Dashboard
            </a>
            <a href="<?= BASE_URL ?>/kepatuhan/assesmen.php">
                <?= $ico_clipboard ?> Antrian Assesmen
            </a>
            <a href="<?= BASE_URL ?>/kepatuhan/riwayat.php">
                <?= $ico_history ?> Riwayat Assesmen
            </a>
            <div class="nav-item-dropdown">
                <a href="#" class="dropdown-toggle" onclick="toggleSubmenu(event, 'submenu-kepatuhan-doc')">
                    <?= $ico_doc ?> Arsip Dokumen
                </a>
            </div>
            <div id="submenu-kepatuhan-doc" class="submenu">
                <a href="<?= BASE_URL ?>/kepatuhan/upload_dokumen.php" class="nav-link-step">Upload Dokumen</a>
                <a href="<?= BASE_URL ?>/kepatuhan/hasil_dokumen.php" class="nav-link-step">Lihat Dokumen</a>
            </div>

        <?php else: ?>
            <a href="<?= BASE_URL ?>/<?= htmlspecialchars($current_role) ?>/dashboard.php"><?= $ico_dashboard ?> Dashboard</a>
            <?php if (file_exists(__DIR__ . "/../$current_role/history.php")): ?>
                <a href="<?= BASE_URL ?>/<?= htmlspecialchars($current_role) ?>/history.php"><?= $ico_history ?> Riwayat Pengajuan</a>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($current_role !== 'kepatuhan'): ?>
        <!-- Menu Global Dokumen Kepatuhan untuk SEMUA ROLE selain Kepatuhan -->
        <a href="<?= BASE_URL ?>/kepatuhan/hasil_dokumen.php" style="margin-top: 1rem; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1rem;">
            <?= $ico_shield ?> Info Kepatuhan
        </a>
        <?php endif; ?>
    </div>

    <div class="user-profile">
        <p>Login as:</p>
        <p class="user-name">
            <?= htmlspecialchars($current_name) ?>
        </p>
        <a href="<?= BASE_URL ?>/auth/logout.php" class="logout-link">
            Logout <span>&rarr;</span>
        </a>
    </div>
</div>

<!-- Topbar Header: notifikasi & info pengguna (semua role) -->
<header class="topbar-header" id="topbarHeader">
    <div class="topbar-left">
        <span class="topbar-brand">Bank Wonosobo</span>
        <span class="topbar-brand-sub">Sistem Approval Kredit</span>
    </div>
    <div class="topbar-right">
        <div class="topbar-actions">
            <?php include __DIR__ . '/notification_bell.php'; ?>
        </div>
        <div class="topbar-divider" aria-hidden="true"></div>
        <div class="topbar-user-chip">
            <span class="topbar-user-avatar" aria-hidden="true"><?= htmlspecialchars($user_initial) ?></span>
            <div class="topbar-user-info">
                <span class="topbar-user-name"><?= htmlspecialchars($current_name) ?></span>
                <span class="topbar-user-role"><?= htmlspecialchars($role_display) ?></span>
            </div>
        </div>
    </div>
</header>

<script>
    function toggleSubmenu(e, id) {
        e.preventDefault();
        const submenu = document.getElementById(id);
        const toggle = e.currentTarget;

        if (submenu.classList.contains('open')) {
            submenu.classList.remove('open');
            toggle.classList.remove('open');
        } else {
            submenu.classList.add('open');
            toggle.classList.add('open');
        }
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }
</script>