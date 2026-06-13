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
    <style>
        .login-wrapper {
            display: flex;
            min-height: 100vh;
            background: #fff;
        }
        .login-left {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: white;
            padding: 3rem;
            flex-direction: column;
            text-align: center;
        }
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
            background: #f8fafc;
        }
        .login-form-container {
            width: 100%;
            max-width: 400px;
        }
        .brand-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }
        .brand-desc {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 80%;
            line-height: 1.6;
        }
        /* Override generic input styles for login */
        .login-input {
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid #cbd5e1;
            background: white;
            font-size: 1rem;
        }
        .login-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        @media (max-width: 768px) {
            .login-left { display: none; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side: Branding -->
        <div class="login-left">
            <div class="brand-logo">PT. BPR Bank Wonosobo (Perseroda)</div>
            <p class="brand-desc">Sistem Informasi Manajemen Persetujuan Kredit Terintegrasi.</p>
            <div style="margin-top: 3rem; opacity: 0.8; font-size: 0.9rem;">
                &copy; <?= date('Y') ?> Bank Wonosobo. All rights reserved.
            </div>
        </div>

        <!-- Right Side: Form -->
        <div class="login-right">
            <div class="login-form-container">
                <div style="margin-bottom: 2rem;">
                    <h2 style="font-size: 1.8rem; color: #0f172a; margin-bottom: 0.5rem;">Selamat Datang Kembali</h2>
                    <p style="color: #64748b;">Silakan login untuk mengakses akun Anda.</p>
                </div>

                <?php if($error): ?>
                    <div class="alert alert-danger" style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:0.5rem; margin-bottom:1.5rem; border:1px solid #fecaca;">
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form method="POST" onsubmit="var b=this.querySelector('button[type=submit]'); if(b) b.disabled=true;">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <div class="form-group">
                        <label style="color: #475569; font-weight: 600;">Username</label>
                        <input type="text" name="username" class="login-input" placeholder="Masukkan username Anda" required>
                    </div>
                    <div class="form-group">
                        <label style="color: #475569; font-weight: 600;">Password</label>
                        <input type="password" name="password" class="login-input" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1rem; margin-top: 1rem;">Masuk ke Dashboard</button>
                    
                    <div style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                        <p style="font-size: 0.85rem; color: #94a3b8; text-align: center;">
                            Hubungi administrator jika mengalami kesulitan login.
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
