<?php
// api/push_subscribe.php
// PRODUCTION v2.2 - CORS Enabled for API Subdomain

// ⚡️ CORS SECURITY HEADERS (Cross-Origin Matrix Authorization)
header("Access-Control-Allow-Origin: https://digitalmarketplacemm.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

// Handle Browser Preflight OPTIONS request instantly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'invalid_method']);
    exit;
}

if (!is_logged_in()) {
    echo json_encode(['status' => 'guest_skipped', 'message' => 'Subscription successful locally.']);
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