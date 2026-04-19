<?php
// api/push_subscribe.php
// PRODUCTION v2.1 - Silent fail for guests to prevent JS errors during local push

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'invalid_method']);
    exit;
}

// If user is not logged in, we still allow them to accept push notifications
// so the frontend can trigger the local Welcome message, but we do NOT save it to the DB yet.
if (!is_logged_in()) {
    echo json_encode(['status' => 'guest_skipped', 'message' => 'Subscription successful locally, waiting for authentication to sync.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['endpoint'])) {
    $endpoint = $input['endpoint'];
    $keys = $input['keys'];
    
    // Check if subscription exists
    $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->execute([$endpoint]);
    
    if ($stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $endpoint, $keys['p256dh'], $keys['auth']]);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'exists']);
    }
} else {
    echo json_encode(['status' => 'invalid_payload']);
}
?>