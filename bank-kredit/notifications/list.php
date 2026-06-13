<?php
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    // Get all notifications for current user with pagination
    $stmt = $pdo->prepare("
        SELECT 
            n.*,
            pk.nama_debitur,
            pk.jumlah_kredit,
            pk.status_pengajuan
        FROM notifications n
        LEFT JOIN pengajuan_kredit pk ON n.id_pengajuan = pk.id_pengajuan
        WHERE n.id_user = ?
        ORDER BY n.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$_SESSION['user_id'], $limit, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE id_user = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = (int)$stmt->fetchColumn();
    
    $total_pages = ceil($total / $limit);
} catch (Exception $e) {
    $notifications = [];
    $total = 0;
    $total_pages = 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi - Approval Kredit</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
    <style>
        .notifikasi-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .notifikasi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }

        .notifikasi-header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }

        .notifikasi-actions {
            display: flex;
            gap: 10px;
        }

        .notifikasi-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            background: #f0f0f0;
            color: #333;
            transition: background 0.2s;
        }

        .notifikasi-actions button:hover {
            background: #e0e0e0;
        }

        .notifikasi-item {
            padding: 15px;
            margin-bottom: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            background: white;
            transition: all 0.2s;
            cursor: pointer;
        }

        .notifikasi-item:hover {
            background: #f9f9f9;
            border-color: #0066cc;
            box-shadow: 0 2px 6px rgba(0,102,204,0.1);
        }

        .notifikasi-item.unread {
            background: #f0f7ff;
            border-left: 4px solid #0066cc;
            border-width: 4px;
        }

        .notifikasi-item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 8px;
        }

        .notifikasi-item-type {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .notifikasi-item-type.submitted {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .notifikasi-item-type.auto_routed {
            background: #bbdefb;
            color: #1565c0;
        }

        .notifikasi-item-type.approved {
            background: #a5d6a7;
            color: #1b5e20;
        }

        .notifikasi-item-type.revised {
            background: #ffe0b2;
            color: #e65100;
        }

        .notifikasi-item-type.rejected {
            background: #ffcdd2;
            color: #c62828;
        }

        .notifikasi-item-type.completed {
            background: #c8e6c9;
            color: #1b5e20;
        }

        .notifikasi-item-time {
            font-size: 0.85rem;
            color: #999;
        }

        .notifikasi-item-title {
            font-weight: 600;
            font-size: 1rem;
            color: #333;
            margin-bottom: 6px;
        }

        .notifikasi-item-message {
            font-size: 0.9rem;
            color: #666;
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .notifikasi-item-debitur {
            font-size: 0.85rem;
            color: #0066cc;
            font-weight: 500;
        }

        .notifikasi-empty {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .notifikasi-pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .notifikasi-pagination a,
        .notifikasi-pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #0066cc;
            cursor: pointer;
        }

        .notifikasi-pagination a:hover {
            background: #f0f0f0;
        }

        .notifikasi-pagination span.current {
            background: #0066cc;
            color: white;
            border-color: #0066cc;
        }

        .notifikasi-pagination span.disabled {
            color: #999;
            border-color: #ddd;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container">
<div class="notifikasi-container">
    <div class="notifikasi-header">
        <h1>📬 Pusat Notifikasi</h1>
        <div class="notifikasi-actions">
            <button onclick="location.href='?'">Refresh</button>
            <button onclick="markAllRead()">Tandai Semua Sudah Dibaca</button>
        </div>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="notifikasi-empty">
            <p style="font-size: 3rem; margin-bottom: 10px;">✓</p>
            <p style="font-size: 1.1rem; margin-bottom: 5px;">Tidak ada notifikasi</p>
            <p style="font-size: 0.9rem; color: #ccc;">Anda sudah menangani semua notifikasi</p>
        </div>
    <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
            <div class="notifikasi-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="goToDetail(<?= $notif['id_pengajuan'] ?>, <?= $notif['id_notification'] ?>)">
                <div class="notifikasi-item-header">
                    <div>
                        <span class="notifikasi-item-type <?= $notif['tipe_notifikasi'] ?>">
                            <?php
                                $type_label = [
                                    'submitted' => '✓ Diajukan',
                                    'auto_routed' => '→ Diteruskan',
                                    'approved' => '✓ Disetujui',
                                    'revised' => '✏ Revisi',
                                    'rejected' => '✗ Ditolak',
                                    'completed' => '✓ Selesai'
                                ];
                                echo $type_label[$notif['tipe_notifikasi']] ?? ucfirst($notif['tipe_notifikasi']);
                            ?>
                        </span>
                    </div>
                    <div class="notifikasi-item-time">
                        <?php
                            $time = strtotime($notif['created_at']);
                            $diff = time() - $time;
                            if ($diff < 60) {
                                echo "Baru saja";
                            } elseif ($diff < 3600) {
                                echo intval($diff / 60) . " menit lalu";
                            } elseif ($diff < 86400) {
                                echo intval($diff / 3600) . " jam lalu";
                            } else {
                                echo date('d M Y H:i', $time);
                            }
                        ?>
                    </div>
                </div>

                <div class="notifikasi-item-title"><?= htmlspecialchars($notif['judul_notifikasi']) ?></div>
                <div class="notifikasi-item-message"><?= htmlspecialchars($notif['pesan_notifikasi']) ?></div>
                
                <?php if ($notif['nama_debitur']): ?>
                    <div class="notifikasi-item-debitur">
                        👤 <?= htmlspecialchars($notif['nama_debitur']) ?> 
                        · Rp <?= number_format($notif['jumlah_kredit'], 0, ',', '.') ?>
                        · Status: <strong><?= htmlspecialchars($notif['status_pengajuan']) ?></strong>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php if ($total_pages > 1): ?>
            <div class="notifikasi-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1">« Awal</a>
                    <a href="?page=<?= $page - 1 ?>">‹ Sebelumnya</a>
                <?php else: ?>
                    <span class="disabled">« Awal</span>
                    <span class="disabled">‹ Sebelumnya</span>
                <?php endif; ?>

                <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    if ($start > 1) echo '<span class="disabled">...</span>';
                    
                    for ($i = $start; $i <= $end; $i++):
                ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end < $total_pages) echo '<span class="disabled">...</span>'; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Berikutnya ›</a>
                    <a href="?page=<?= $total_pages ?>">Akhir »</a>
                <?php else: ?>
                    <span class="disabled">Berikutnya ›</span>
                    <span class="disabled">Akhir »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    function goToDetail(id_pengajuan, id_notification) {
        // Mark as read if not already
        fetch('<?= BASE_URL ?>/api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id_notification=' + id_notification + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
        .then(() => {
            window.location.href = '<?= BASE_URL ?>/detail.php?id=' + id_pengajuan;
        });
    }

    function markAllRead() {
        if (!confirm('Tandai semua notifikasi sebagai sudah dibaca?')) return;
        
        fetch('<?= BASE_URL ?>/api/mark_all_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
</script>
</body>
</html>
