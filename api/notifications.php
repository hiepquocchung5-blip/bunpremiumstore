<?php
// api/notifications.php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // 1. Count unread messages from Admin
    // Using a simpler logic: messages in last 24h that are from admin
    // In a full app, you'd have a 'read_at' column
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM order_messages om
        JOIN orders o ON om.order_id = o.id
        WHERE o.user_id = ? 
        AND om.sender_type = 'admin'
        AND om.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();

    // 2. Fetch latest 3 notification snippets (e.g. order updates)
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, p.name 
        FROM orders o
        JOIN products p ON o.product_id = p.id
        WHERE o.user_id = ?
        ORDER BY o.updated_at DESC, o.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();

    $notifications = [];
    foreach($recent_orders as $order) {
        $status_msg = match($order['status']) {
            'active' => 'Order completed',
            'rejected' => 'Order rejected',
            default => 'Order pending'
        };
        
        $notifications[] = [
            'text' => "#{$order['id']}: $status_msg",
            'link' => "index.php?module=user&page=orders&view_chat={$order['id']}"
        ];
    }

    echo json_encode([
        'count' => (int)$unread_count,
        'notifications' => $notifications
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>