<?php
// api/push_subscribe.php
// PRODUCTION v2.5 - Hardened Matrix Uplink & CORS Optimization

// ⚡️ CORS SECURITY HEADERS
header("Access-Control-Allow-Origin: https://digitalmarketplacemm.com");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'invalid_method']);
    exit;
}

try {
    if (!is_logged_in()) {
        echo json_encode(['status' => 'guest_skipped', 'message' => 'Local uplink established.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['endpoint'])) {
        $endpoint = $input['endpoint'];
        $keys = $input['keys'];
        $user_id = $_SESSION['user_id'];
        
        // ⚡️ Check existing node
        $stmt = $pdo->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
        
        if ($stmt->rowCount() == 0) {
            $stmt = $pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $endpoint, $keys['p256dh'], $keys['auth']]);
            echo json_encode(['status' => 'success', 'message' => 'Uplink secured.']);
        } else {
            // Update user_id for existing endpoint if it changed
            $stmt = $pdo->prepare("UPDATE push_subscriptions SET user_id = ? WHERE endpoint = ?");
            $stmt->execute([$user_id, $endpoint]);
            echo json_encode(['status' => 'exists', 'message' => 'Node synchronized.']);
        }
    } else {
        echo json_encode(['status' => 'invalid_payload']);
    }

} catch (Exception $e) {
    error_log("Push Subscribe Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Matrix sync failed.']);
}
?>