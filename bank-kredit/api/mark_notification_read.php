<?php
/**
 * API: Mark notification as read
 * POST /api/mark_notification_read.php
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

try {
    $id_notification = (int)($_POST['id_notification'] ?? 0);
    
    if ($id_notification <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
        exit;
    }

    // Verify notification belongs to current user
    $stmt = $pdo->prepare("SELECT id_user FROM notifications WHERE id_notification = ?");
    $stmt->execute([$id_notification]);
    $notif = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$notif || $notif['id_user'] != $_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Notification not found or access denied']);
        exit;
    }

    // Mark as read
    $result = markNotificationAsRead($id_notification);

    if ($result) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to mark notification as read']);
    }

} catch (Exception $e) {
    error_log("Error in mark_notification_read.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
?>
