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
            <?= strtoupper(str_replace('_', ' ', $current_role)) ?>
        </span>
    </div>

    <div class="nav-links">
        <?php if ($current_role == 'Superadmin'): ?>
            <a href="<?= BASE_URL ?>/admin/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/admin/users.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                    </path>
                </svg>
                Manajemen User
            </a>
            <a href="<?= BASE_URL ?>/admin/logs.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                    </path>
                </svg>
                Audit Log
            </a>
            <a href="<?= BASE_URL ?>/admin/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                    </path>
                </svg>
                Riwayat Approval
            </a>


            <!-- Admin: Input & Assesmen Dropdown -->
            <div class="nav-item-dropdown">
                <a href="#" class="dropdown-toggle" onclick="toggleSubmenu(event, 'submenu-admin-input')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Input & Assesmen
                </a>
            </div>
            <div id="submenu-admin-input" class="submenu">
                <a href="<?= BASE_URL ?>/analis/input.php" class="nav-link-step">+ Form Input Kredit</a>
            </div>

            <!-- Admin: Approval Dropdown -->
            <div class="nav-item-dropdown">
                <a href="#" class="dropdown-toggle" onclick="toggleSubmenu(event, 'submenu-admin-approval')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Menu Approval
                </a>
            </div>
            <div id="submenu-admin-approval" class="submenu">
                <a href="<?= BASE_URL ?>/analis/dashboard.php?approval_view=true" class="nav-link-step">Analis</a>
                <a href="<?= BASE_URL ?>/kepatuhan/proses.php" class="nav-link-step">Kepatuhan Assessment</a>
                <a href="<?= BASE_URL ?>/kasubag_analis/proses.php" class="nav-link-step">Kasubag Analis</a>
                <a href="<?= BASE_URL ?>/kabag_kredit/proses.php" class="nav-link-step">Kabag Kredit</a>
                <a href="<?= BASE_URL ?>/kadiv_bisnis/proses.php" class="nav-link-step">Kadiv Bisnis</a>
                <a href="<?= BASE_URL ?>/direksi/proses.php" class="nav-link-step">Direktur Utama</a>
            </div>

            <a href="<?= BASE_URL ?>/admin/backup.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4">
                    </path>
                </svg>
                Backup Data
            </a>
        <?php elseif ($current_role == 'analis'): ?>
            <a href="<?= BASE_URL ?>/analis/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>

            <!-- Dropdown for Form Input Kredit -->
            <?php
            $is_input_page = basename($_SERVER['PHP_SELF']) == 'input.php';
            $open_class = $is_input_page ? 'open' : '';
            ?>
            <div class="nav-item-dropdown <?= $open_class ?>">
                <a href="#" class="dropdown-toggle <?= $open_class ?>" onclick="toggleSubmenu(event, 'submenu-input')">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                    Form Input Kredit
                </a>
            </div>
            <?php
            $analisNavQ = isset($ANALIS_INPUT_NAV_QUERY) ? $ANALIS_INPUT_NAV_QUERY : '';
            $pegawaiInputNav = isset($jenis_pekerjaan) && in_array($jenis_pekerjaan, ['pppk', 'perangkat_desa'], true);
            $analisStep2Target = $pegawaiInputNav ? 'tab-penghasilan' : 'tab-usaha';
            $analisStep2Label = $pegawaiInputNav ? 'Penghasilan' : 'Data Usaha';
            ?>
            <div id="submenu-input" class="submenu <?= $open_class ?>">
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-pemohon" class="nav-link-step" data-target="tab-pemohon">Data
                    Pemohon</a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#<?= htmlspecialchars($analisStep2Target, ENT_QUOTES, 'UTF-8') ?>" class="nav-link-step"
                    data-target="<?= htmlspecialchars($analisStep2Target, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($analisStep2Label) ?></a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-struktur" class="nav-link-step"
                    data-target="tab-struktur">Struktur</a>

                <?php if (!$pegawaiInputNav): // Hide agunan for PPPK/Desa forms ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-agunan" class="nav-link-step"
                    data-target="tab-agunan">Data Agunan</a>
                <?php endif; ?>


                <?php if (!$pegawaiInputNav): // Hide neraca for PPPK/Desa forms ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-neraca" class="nav-link-step"
                    data-target="tab-neraca">Neraca</a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-6c" class="nav-link-step" data-target="tab-6c">Analisa 6C</a>
                <a href="<?= BASE_URL ?>/analis/input.php<?= htmlspecialchars($analisNavQ, ENT_QUOTES, 'UTF-8') ?>#tab-scoring" class="nav-link-step"
                    data-target="tab-scoring">Scoring</a>
            </div>

            <a href="<?= BASE_URL ?>/analis/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Pengajuan
            </a>
        <?php elseif ($current_role == 'kasubag_analis'): ?>
            <a href="<?= BASE_URL ?>/kasubag_analis/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/kasubag_analis/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Proses Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/kasubag_analis/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Approval
            </a>
        <?php elseif ($current_role == 'kabag_analis'): ?>
            <a href="<?= BASE_URL ?>/kabag_analis/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/kabag_analis/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Proses Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/kabag_analis/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Approval
            </a>
        <?php elseif ($current_role == 'kabag_kredit'): ?>
            <a href="<?= BASE_URL ?>/kabag_kredit/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/kabag_kredit/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Proses Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/kabag_kredit/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Approval
            </a>
        <?php elseif ($current_role == 'kadiv_kredit' || $current_role == 'kadiv_bisnis'): 
            $kadiv_folder = ($current_role === 'kadiv_bisnis') ? 'kadiv_bisnis' : 'kadiv_kredit';
        ?>
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Proses Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/<?= $kadiv_folder ?>/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Approval
            </a>
        <?php elseif ($current_role == 'direksi' || $current_role == 'direktur_utama'): ?>
            <a href="<?= BASE_URL ?>/direksi/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/direksi/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Proses Pengajuan
            </a>
            <a href="<?= BASE_URL ?>/direksi/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Approval
            </a>
        <?php elseif ($current_role == 'kepatuhan'): ?>
            <a href="<?= BASE_URL ?>/kepatuhan/dashboard.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6">
                    </path>
                </svg>
                Dashboard
            </a>
            <a href="<?= BASE_URL ?>/kepatuhan/proses.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Antrian Assessment
            </a>
            <a href="<?= BASE_URL ?>/kepatuhan/assesmen.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4">
                    </path>
                </svg>
                Assesmen Kepatuhan
            </a>
            <a href="<?= BASE_URL ?>/kepatuhan/riwayat.php">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z">
                    </path>
                </svg>
                Riwayat Assesmen
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/<?= $current_role ?>/dashboard.php">Dashboard</a>
            <?php if (file_exists(__DIR__ . "/../$current_role/history.php")): ?>
                <a href="<?= BASE_URL ?>/<?= $current_role ?>/history.php">Riwayat Pengajuan</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Notification Bell -->
    <div style="padding: 12px; border-top: 1px solid #f0f0f0;">
        <?php include __DIR__ . '/notification_bell.php'; ?>
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