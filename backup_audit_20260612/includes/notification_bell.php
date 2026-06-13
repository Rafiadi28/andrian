<?php
/**
 * Notification Bell Component
 * Include this in navbar to show notification dropdown
 * 
 * Usage: <?php include __DIR__ . '/notification_bell.php'; ?>
 */

if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    return;
}

$unread_count = getUnreadNotificationCount($_SESSION['user_id']);
$recent_notifications = getUnreadNotifications($_SESSION['user_id'], 5);
?>

<style>
    .notification-bell-container {
        position: relative;
        display: inline-block;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 1.5rem;
        padding: 8px 12px;
        color: #333;
        border: none;
        background: none;
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #ff4444;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .notification-dropdown {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        z-index: 1000;
        margin-top: 5px;
    }

    .notification-dropdown.show {
        display: block;
    }

    .notification-dropdown-header {
        padding: 12px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f9f9f9;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .notification-dropdown-header a {
        color: #0066cc;
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: normal;
        cursor: pointer;
    }

    .notification-dropdown-header a:hover {
        text-decoration: underline;
    }

    .notification-item {
        padding: 10px 12px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
    }

    .notification-item:hover {
        background: #f5f5f5;
    }

    .notification-item.unread {
        background: #f0f7ff;
        border-left: 3px solid #0066cc;
    }

    .notification-item-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #333;
        margin-bottom: 4px;
    }

    .notification-item-message {
        font-size: 0.85rem;
        color: #666;
        line-height: 1.3;
        max-height: 40px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notification-item-time {
        font-size: 0.75rem;
        color: #999;
        margin-top: 4px;
    }

    .notification-item-type {
        display: inline-block;
        background: #e3f2fd;
        color: #1976d2;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 0.75rem;
        margin-bottom: 4px;
    }

    .notification-empty {
        padding: 20px;
        text-align: center;
        color: #999;
        font-size: 0.9rem;
    }

    .notification-footer {
        padding: 10px 12px;
        border-top: 1px solid #eee;
        text-align: center;
        background: #f9f9f9;
    }

    .notification-footer a {
        color: #0066cc;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .notification-footer a:hover {
        text-decoration: underline;
    }
</style>

<div class="notification-bell-container">
    <button class="notification-bell" onclick="toggleNotificationDropdown(event)" title="Notifikasi">
        🔔
        <?php if ($unread_count > 0): ?>
            <span class="notification-badge"><?= min($unread_count, 99) ?></span>
        <?php endif; ?>
    </button>

    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <span>Notifikasi <?php if ($unread_count > 0): ?>(<?= $unread_count ?>)<?php endif; ?></span>
            <?php if ($unread_count > 0): ?>
                <a onclick="markAllNotificationsAsRead()">Tandai semua</a>
            <?php endif; ?>
        </div>

        <div id="notificationContent">
            <?php if (empty($recent_notifications)): ?>
                <div class="notification-empty">
                    ✓ Tidak ada notifikasi baru
                </div>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notif): ?>
                    <div class="notification-item unread" onclick="clickNotification(<?= $notif['id_notification'] ?>, <?= $notif['id_pengajuan'] ?>)">
                        <div>
                            <span class="notification-item-type"><?= ucfirst($notif['tipe_notifikasi']) ?></span>
                        </div>
                        <div class="notification-item-title"><?= htmlspecialchars($notif['judul_notifikasi']) ?></div>
                        <div class="notification-item-message"><?= htmlspecialchars($notif['pesan_notifikasi']) ?></div>
                        <div class="notification-item-time">
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
                                    echo intval($diff / 86400) . " hari lalu";
                                }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="notification-footer">
            <a href="<?= BASE_URL ?>/notifications/list.php">Lihat semua notifikasi →</a>
        </div>
    </div>
</div>

<script>
    function toggleNotificationDropdown(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.classList.toggle('show');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const container = document.querySelector('.notification-bell-container');
        if (!container.contains(event.target)) {
            document.getElementById('notificationDropdown').classList.remove('show');
        }
    });

    function markAllNotificationsAsRead() {
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
                // Refresh page to update badge
                location.reload();
            }
        });
    }

    function clickNotification(id_notification, id_pengajuan) {
        // Mark as read
        fetch('<?= BASE_URL ?>/api/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id_notification=' + id_notification + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
        .then(() => {
            // Redirect to detail page
            window.location.href = '<?= BASE_URL ?>/detail.php?id=' + id_pengajuan;
        });
    }
</script>
