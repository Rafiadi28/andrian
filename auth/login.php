<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    if ($role == 'Superadmin') {
        header("Location: " . BASE_URL . "/admin/dashboard.php");
    } else {
        $folder = ($role === 'direktur_utama') ? 'direksi' : $role;
        header("Location: " . BASE_URL . "/$folder/dashboard.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.';
    } elseif (!checkLoginRateLimit()) {
        $error = 'Terlalu banyak upaya login gagal. Coba lagi dalam 1 menit.';
    } else {
        $username = sanitizeUsername($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi.';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // legacy conversion: treat old 'admin' users as Superadmin and update DB
                if ($user['role'] === 'admin') {
                    $user['role'] = 'Superadmin';
                    try {
                        $pdo->prepare("UPDATE users SET role='Superadmin' WHERE id_user = ?")->execute([$user['id_user']]);
                    } catch (Exception $e) {
                        // ignore if update fails
                    }
                }
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status_jabatan'] = $user['status_jabatan'];
                $_SESSION['last_activity'] = time();

                // Login Audit
                $stmt_log = $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (?, 'Login ke sistem')");
                $stmt_log->execute([$user['id_user']]);

                if ($user['role'] == 'Superadmin') {
                    header("Location: " . BASE_URL . "/admin/dashboard.php");
                } else {
                    $folder = ($user['role'] === 'direktur_utama') ? 'direksi' : $user['role'];
                    header("Location: " . BASE_URL . "/{$folder}/dashboard.php");
                }
                exit;
            } else {
                $error = "Username atau Password salah!";
                $safeUser = substr(preg_replace('/[^a-zA-Z0-9._@-]/', '', $username), 0, 64);
                if ($safeUser !== '') {
                    try {
                        $pdo->prepare("INSERT INTO audit_log (id_user, aktivitas) VALUES (NULL, ?)")
                            ->execute(['Login gagal (username: ' . $safeUser . ')']);
                    } catch (Exception $e) {
                        error_log('audit login gagal: ' . $e->getMessage());
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PT. BPR Bank Wonosobo (Perseroda)</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-left">
            <div class="brand-logo">PT. BPR Bank Wonosobo (Perseroda)</div>
            <p class="brand-desc">Sistem Informasi Manajemen Persetujuan Kredit Terintegrasi.</p>
            <div class="login-copyright">
                &copy; <?= date('Y') ?> Bank Wonosobo. All rights reserved.
            </div>
        </div>

        <div class="login-right">
            <div class="login-form-container">
                <div style="margin-bottom: 1.75rem;">
                    <h2 class="login-form-title">Selamat Datang Kembali</h2>
                    <p class="login-form-subtitle">Silakan login untuk mengakses akun Anda.</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="var b=this.querySelector('button[type=submit]'); if(b) b.disabled=true;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" class="login-input" placeholder="Masukkan username Anda" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" class="login-input" placeholder="Masukkan password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top: 0.5rem;">Masuk ke Dashboard</button>

                    <div class="login-footer-note">
                        Hubungi administrator jika mengalami kesulitan login.
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
