<?php
// api/notifications.php
// PRODUCTION v2.6 - Hardened Matrix Lite Speed Cache

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(['count' => 0, 'notifications' => []]);
    exit;
}

$cache_key = "user_notif_data_{$user_id}";

// ⚡️ STAGE 1: Check Matrix Cache (Fastest Path)
try {
    $cached_data = matrix_cache_get($cache_key);
    if ($cached_data && is_array($cached_data)) {
        $etag = '"' . md5(json_encode($cached_data)) . '"';
        header("ETag: $etag");
        header("Cache-Control: private, max-age=10");

        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
            http_response_code(304);
            exit;
        }
        
        echo json_encode($cached_data);
        exit;
    }
} catch (Exception $e) {
    // Fail silently on cache error
}

try {
    // ⚡️ STAGE 2: Database Fetch (Optimized)
    
    // 1. Count unread messages from Admin (48h window)
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

    // 2. Fetch latest 5 notification snippets
    // Using created_at as primary sort and fallback for time display
    $stmt = $pdo->prepare("
        SELECT o.id, o.status, o.created_at,
               COALESCE(p.name, ps.name, 'Digital Asset') as name
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        LEFT JOIN passes ps ON o.pass_id = ps.id
        WHERE o.user_id = ?
        ORDER BY o.id DESC
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
            'time' => date('H:i', strtotime($order['created_at']))
        ];
    }

    $final_data = [
        'count' => $unread_count,
        'notifications' => $notifications,
        'timestamp' => time()
    ];

    // ⚡️ STAGE 3: Commit to Cache
    matrix_cache_set($cache_key, $final_data, 30);

    echo json_encode($final_data);

} catch (Exception $e) {
    // Log the actual error for the admin
    error_log("Notification API Error: " . $e->getMessage());
    
    // Return graceful empty state to the user instead of 500
    echo json_encode([
        'count' => 0,
        'notifications' => [],
        'error' => 'Syncing...'
    ]);
}
?>