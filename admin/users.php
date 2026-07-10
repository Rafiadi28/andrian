<?php
require_once __DIR__ . '/../includes/functions.php';
/** @var PDO $pdo */
requireSameRole('Superadmin');

$csrf_ok = ($_SERVER['REQUEST_METHOD'] !== 'POST') || verifyCsrfToken($_POST['csrf_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$csrf_ok) {
    $error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
}

// Handle Status Update
if ($csrf_ok && isset($_POST['update_status'])) {
    $id = $_POST['user_id'];
    $status = $_POST['status_jabatan'];

    $stmt = $pdo->prepare("UPDATE users SET status_jabatan = ? WHERE id_user = ?");
    $stmt->execute([$status, $id]);

    // Log
    auditLog($pdo, $_SESSION['user_id'], "Admin mengubah status user ID $id menjadi $status");
    $success = "Status berhasil diperbarui.";
}

// Handle Edit User
if ($csrf_ok && isset($_POST['edit_user'])) {
    $id = $_POST['edit_id'];
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $role = $_POST['role'];

    // validate role before attempting update; prevents enum truncation warnings
    if (!isValidRole($role)) {
        $error = "Peran tidak valid: $role";
    } else {
        $stmtPrev = $pdo->prepare("SELECT nama, username, role FROM users WHERE id_user = ?");
        $stmtPrev->execute([$id]);
        $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

        $sql = "UPDATE users SET nama = ?, username = ?, role = ?";
        $params = [$nama, $username, $role];

        // Conditional password update
        if (!empty($_POST['password'])) {
            $sql .= ", password = ?";
            $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id_user = ?";
        $params[] = $id;

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = "Data user berhasil diperbarui.";
            if ($prev) {
                $pwdNote = !empty($_POST['password']) ? ', password diubah' : '';
                auditLog($pdo, $_SESSION['user_id'], 'Mengubah user ID ' . $id . ': nama "' . $prev['nama'] . '"→"' . $nama . '", username "' . $prev['username'] . '"→"' . $username . '", role ' . $prev['role'] . '→' . $role . $pwdNote);
            }
        } catch (Exception $e) {
            logError('admin users edit_user', ['err' => $e->getMessage()]);
            $error = 'Update gagal. Silakan coba lagi atau hubungi administrator.';
        }
    }
}

// Handle Add User (Simplified)
if ($csrf_ok && isset($_POST['add_user'])) {
    $nama = $_POST['nama'];
    $username = $_POST['username'];
    $role = $_POST['role'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (!isValidRole($role)) {
        $error = "Peran tidak valid: $role";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (nama, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $username, $password, $role]);
            $success = "User baru berhasil ditambahkan.";
            auditLog($pdo, $_SESSION['user_id'], 'Menambah user: ' . $username . ' (role ' . $role . ')');
        } catch (Exception $e) {
            logError('admin users add_user', ['err' => $e->getMessage()]);
            $error = 'Gagal menambah user. Silakan coba lagi atau hubungi administrator.';
        }
    }
}


// Handle Delete User
if ($csrf_ok && isset($_POST['delete_user'])) {
    $id = $_POST['delete_id'];
    // Prevent deleting self or defaults if needed, but for now just simple delete
    try {
        $stmtPrev = $pdo->prepare("SELECT nama, username, role FROM users WHERE id_user = ?");
        $stmtPrev->execute([$id]);
        $prev = $stmtPrev->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM users WHERE id_user = ?");
        $stmt->execute([$id]);
        $success = "User berhasil dihapus.";
        if ($prev) {
            auditLog($pdo, $_SESSION['user_id'], 'Menghapus user ID ' . $id . ' (' . $prev['username'] . ', ' . $prev['nama'] . ', role ' . $prev['role'] . ')');
        }
    } catch (Exception $e) {
        logError('admin users delete_user', ['err' => $e->getMessage()]);
        $error = 'Gagal menghapus user. Silakan coba lagi atau hubungi administrator.';
    }
}
// Handle role labels update (Manage Roles)
if ($csrf_ok && isset($_POST['update_roles'])) {
    $labels = $_POST['labels'] ?? [];
    try {
        $stmt = $pdo->prepare("INSERT INTO roles (role_key, label) VALUES (?, ?) ON DUPLICATE KEY UPDATE label = VALUES(label)");
        foreach (getHierarchy() as $rk) {
            $lab = trim($labels[$rk] ?? '');
            if ($lab === '') {
                $lab = ucfirst(str_replace('_', ' ', $rk));
            }
            $stmt->execute([$rk, $lab]);
        }
        $success = 'Label role berhasil diperbarui.';
    } catch (Exception $e) {
        logError('admin users update_roles', ['err' => $e->getMessage()]);
        $error = 'Gagal memperbarui label role. Silakan coba lagi atau hubungi administrator.';
    }
}

$usersPerPage = 25;
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalUserPages = max(1, (int) ceil($totalUsers / $usersPerPage));
if ($pageNum > $totalUserPages) {
    $pageNum = $totalUserPages;
}
$offsetUsers = ($pageNum - 1) * $usersPerPage;
$stmtUsers = $pdo->prepare("SELECT id_user, nama, username, role, status_jabatan FROM users ORDER BY role ASC LIMIT :lim OFFSET :off");
$stmtUsers->bindValue(':lim', $usersPerPage, PDO::PARAM_INT);
$stmtUsers->bindValue(':off', $offsetUsers, PDO::PARAM_INT);
$stmtUsers->execute();
$users = $stmtUsers->fetchAll();
$roles = getHierarchy(); // ['analis', 'kabag_analis', ...]
array_unshift($roles, 'Superadmin', 'kepatuhan');
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Users - Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>

<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <div class="container">
        <div class="section-header" style="margin-bottom: 2rem; border-bottom: none;">
            <h1>Manajemen Users & Status</h1>
            <div class="button-group">
                <button onclick="document.getElementById('modal-add').style.display='flex'" class="btn btn-primary"
                    style="display:flex; align-items:center; gap:0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Tambah User Baru
                </button>
                <button onclick="document.getElementById('modal-roles').style.display='flex'" class="btn btn-secondary">
                    Kelola Role
                </button>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm3.5-8.5l-5 5-2-2"/>
                </svg>
                <div>
                    <strong>Berhasil!</strong>
                    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zM8 4v4m0 3v.5"/>
                </svg>
                <div>
                    <strong>Error!</strong>
                    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status Jabatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['nama']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><span class="badge badge-process"><?php echo htmlspecialchars(getRoleLabel($u['role'])); ?></span></td>
                            <td>
                                <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="user_id" value="<?= $u['id_user'] ?>">
                                    <select name="status_jabatan">
                                        <?php
                                        $statuses = ['aktif', 'sakit', 'izin', 'cuti', 'berhalangan'];
                                        foreach ($statuses as $st):
                                            ?>
                                            <option value="<?= $st ?>" <?= $u['status_jabatan'] == $st ? 'selected' : '' ?>>
                                                <?= ucfirst($st) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_status" class="btn btn-secondary"
                                        style="padding: 0.5rem 1rem; white-space: nowrap;">Update</button>
                                </form>
                            </td>
                            <td style="display:flex; gap:0.5rem; align-items:center;">
                                <button onclick="openEditModal('<?= $u['id_user'] ?>', '<?= htmlspecialchars($u['nama'], ENT_QUOTES) ?>', '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>', '<?= $u['role'] ?>')" class="btn btn-secondary"
                                    style="font-size: 0.8rem; padding: 0.4rem 0.8rem; display:flex; align-items:center; gap:0.25rem;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z">
                                        </path>
                                    </svg>
                                    Edit
                                </button>

                                <?php if ($u['role'] != 'Superadmin'): ?>
                                    <form method="POST"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?');"
                                        style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="delete_id" value="<?= $u['id_user'] ?>">
                                        <button type="submit" name="delete_user" class="btn"
                                            style="background:#fee2e2; color:#ef4444; border:1px solid #fca5a5; font-size: 0.8rem; padding: 0.4rem 0.8rem; display:flex; align-items:center; gap:0.25rem; cursor:pointer; border-radius:4px;">
                                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                            Hapus
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($totalUserPages > 1): ?>
                <p style="margin-top: 1.25rem; text-align: center; color: #64748b; font-size: 0.95rem;">
                    Halaman <?= (int) $pageNum ?> dari <?= (int) $totalUserPages ?>
                    (<?= (int) $totalUsers ?> user)
                    <?php if ($pageNum > 1): ?>
                        <a href="?page=<?= (int) ($pageNum - 1) ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">Sebelumnya</a>
                    <?php endif; ?>
                    <?php if ($pageNum < $totalUserPages): ?>
                        <a href="?page=<?= (int) ($pageNum + 1) ?>" class="btn btn-secondary" style="margin-left: 0.5rem;">Berikutnya</a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page-specific Modal Overrides -->
    <style>
        /* Improved Modal Container */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100vw; height: 100vh;
            z-index: 9999 !important;
            background: rgba(15, 23, 42, 0.6) !important;
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .modal-content {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 580px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            border: 1px solid #f1f5f9;
            overflow: hidden;
            margin: auto;
            max-height: 90vh; /* Prevent cut-offs */
            display: flex;
            flex-direction: column;
        }

        .modal-content form {
            display: flex;
            flex-direction: column;
            overflow: hidden;
            max-height: 90vh; /* Form takes full modal height */
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95) translateY(10px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            padding: 1.25rem 1.75rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            flex-shrink: 0;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
            color: #0f172a;
            font-weight: 600;
        }

        .modal-close {
            background: #f8fafc;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            cursor: pointer;
            color: #64748b;
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .modal-body {
            padding: 1.75rem;
            overflow-y: auto;
            flex-grow: 1;
        }

        .modal-footer {
            padding: 1.25rem 1.75rem;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            align-items: center;
            flex-shrink: 0;
        }

        .modal-footer .btn {
            padding: 0.625rem 1.5rem;
            min-height: 40px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Two-column responsive grid for modal form fields */
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        
        .modal-grid .form-group {
            margin-bottom: 0;
            display: flex;
            flex-direction: column;
        }
        
        .modal-grid label {
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
        }

        .modal-grid .form-hint {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        
        .modal-grid input, .modal-grid select {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.65rem 1rem;
            font-size: 0.95rem;
            background: #ffffff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .modal-grid input:focus, .modal-grid select:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        
        @media (max-width: 640px) {
            .modal-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            .full-width {
                grid-column: span 1;
            }
            .modal-footer {
                flex-direction: column-reverse;
                gap: 0.5rem;
                padding: 1.25rem;
            }
            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }
            .modal-content {
                max-width: 100%;
                border-radius: 12px;
                max-height: 95vh;
            }
            .modal-content form {
                max-height: 95vh;
            }
        }
    </style>

    <!-- Modal Manage Roles -->
    <div id="modal-roles" class="modal-overlay" style="display:none; z-index: 104;">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3>Kelola Role (Label)</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('modal-roles').style.display='none'">×</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="modal-grid">
                        <?php foreach (getHierarchy() as $rk): ?>
                            <div class="form-group">
                                <label for="label_<?= htmlspecialchars($rk) ?>"><?= htmlspecialchars($rk) ?> <span class="required">*</span></label>
                                <input type="text" id="label_<?= htmlspecialchars($rk) ?>" name="labels[<?= $rk ?>]" value="<?= htmlspecialchars(getRoleLabel($rk)) ?>" placeholder="Masukkan label" required>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('modal-roles').style.display='none'" class="btn btn-secondary">Batal</button>
                    <button type="submit" name="update_roles" class="btn btn-primary">Simpan Label</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Add User -->
    <div id="modal-add" class="modal-overlay" style="display:none; z-index: 100;">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3>Tambah User Baru</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('modal-add').style.display='none'">×</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    
                    <div class="modal-grid">
                        <div class="form-group full-width">
                            <label for="add_nama">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="add_nama" name="nama" placeholder="Contoh: Budi Santoso" required>
                        </div>
                        <div class="form-group">
                            <label for="add_username">Username <span class="required">*</span></label>
                            <input type="text" id="add_username" name="username" placeholder="Contoh: budi123" required>
                        </div>
                        <div class="form-group">
                            <label for="add_role">Role <span class="required">*</span></label>
                            <select id="add_role" name="role" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r ?>"><?= htmlspecialchars(getRoleLabel($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group full-width">
                            <label for="add_password">Password <span class="required">*</span></label>
                            <input type="password" id="add_password" name="password" placeholder="Masukkan password minimum 6 karakter" required>
                            <small class="form-hint text-muted" style="display:block; margin-top:5px;"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Gunakan kombinasi huruf dan angka untuk keamanan.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('modal-add').style.display='none'" class="btn btn-secondary">Batal</button>
                    <button type="submit" name="add_user" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="modal-edit" class="modal-overlay" style="display:none; z-index: 105;">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h3>Edit User</h3>
                    <button type="button" class="modal-close" onclick="document.getElementById('modal-edit').style.display='none'">×</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="edit_id" id="edit_id">
                    
                    <div class="modal-grid">
                        <div class="form-group full-width">
                            <label for="edit_nama">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="edit_nama" name="nama" placeholder="Contoh: Budi Santoso" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_username">Username <span class="required">*</span></label>
                            <input type="text" id="edit_username" name="username" placeholder="Contoh: budi123" required>
                        </div>

                        <div class="form-group">
                            <label for="edit_role">Role <span class="required">*</span></label>
                            <select id="edit_role" name="role" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r ?>"><?= htmlspecialchars(getRoleLabel($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group full-width">
                            <label for="edit_password">Password Baru <span class="optional" style="font-weight:normal; color:#64748b;">(opsional)</span></label>
                            <input type="password" id="edit_password" name="password" placeholder="Ketik password baru untuk mereset">
                            <small class="form-hint" style="color: #d97706; display:block; margin-top:5px;"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Biarkan kosong jika tidak ingin mengubah password saat ini.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="document.getElementById('modal-edit').style.display='none'" class="btn btn-secondary">Batal</button>
                    <button type="submit" name="edit_user" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(id, nama, username, role) {
            document.getElementById('modal-edit').style.display = 'flex';
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_role').value = role;
        }
    </script>
</body>

</html>