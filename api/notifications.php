<?php
// api/notifications.php
// PRODUCTION v2.5 - Matrix Lite Speed Cache & iOS Payload Optimization

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user_id'];
$cache_key = "user_notif_data_{$user_id}";

// ⚡️ STAGE 1: Check Matrix Cache for ultra-fast response (0-1ms latency)
$cached_data = matrix_cache_get($cache_key);

// ⚡️ STAGE 2: If cache exists, handle ETag comparison to save bandwidth
if ($cached_data) {
    $etag = '"' . md5(json_encode($cached_data)) . '"';
    header("ETag: $etag");
    header("Cache-Control: private, max-age=10"); // Allow browser to cache for 10s

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304); // Not Modified
        exit;
    }
    
    echo json_encode($cached_data);
    exit;
}

try {
    // ⚡️ STAGE 3: Cache Miss - Run Optimized MySQL Queries
    
    // 1. Count unread messages from Admin (Optimized for performance)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM order_messages om
        JOIN orders o ON om.order_id = o.id
        WHERE o.user_id = ? 
        AND om.sender_type = 'admin'
        AND om.created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
    ");
    $stmt->execute([$user_id]);
    $unread_count = (int)$stmt->fetchColumn();

    // 2. Fetch latest 5 notification snippets (Improved breadth)
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, COALESCE(p.name, ps.name) as name, o.updated_at
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.user_id = ?
        ORDER BY o.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_orders = $stmt->fetchAll();

    $notifications = [];
    foreach($recent_orders as $order) {
        $status_msg = match($order['status']) {
            'completed', 'active' => 'Order fulfilled',
            'rejected' => 'Verification failed',
            'pending' => 'Processing...',
            default => 'Status updated'
        };
        
        $notifications[] = [
            'id' => $order['id'],
            'text' => "#{$order['id']}: $status_msg",
            'subtext' => $order['name'],
            'link' => "index.php?module=user&page=orders&view_chat={$order['id']}",
            'time' => date('H:i', strtotime($order['updated_at']))
        ];
    }

    $final_data = [
        'count' => $unread_count,
        'notifications' => $notifications,
        'timestamp' => time()
    ];

    // Commit to Matrix Cache (30 seconds TTL for lite speed)
    matrix_cache_set($cache_key, $final_data, 30);

    echo json_encode($final_data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Matrix connectivity error']);
}
?>