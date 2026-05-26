<?php
// includes/PushService.php
// PRODUCTION v2.1 - Updated VAPID Cryptographic Keys & Bulk Broadcast Matrix

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require_once __DIR__ . '/../vendor/autoload.php';

class PushService {
    
    private $webPush;
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // ⚡️ MATRIX AUTO-HEALING: Load environment if missing (Critical for Admin Subdomains)
        if (!defined('VAPID_PUBLIC_KEY') && !isset($_ENV['VAPID_PUBLIC_KEY'])) {
            try {
                if (file_exists(__DIR__ . '/../.env')) {
                    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
                    $dotenv->safeLoad();
                }
            } catch (\Exception $e) {
                // Ignore silent failures
            }
        }

        $publicKey = defined('VAPID_PUBLIC_KEY') ? VAPID_PUBLIC_KEY : ($_ENV['VAPID_PUBLIC_KEY'] ?? '');
        $privateKey = defined('VAPID_PRIVATE_KEY') ? VAPID_PRIVATE_KEY : ($_ENV['VAPID_PRIVATE_KEY'] ?? '');
        $subject = defined('VAPID_SUBJECT') ? VAPID_SUBJECT : ($_ENV['VAPID_SUBJECT'] ?? '');

        // 🛡️ CIRCUIT BREAKER: Prevent Cryptic 65-byte Errors
        if (empty($publicKey) || empty($privateKey)) {
            throw new \Exception("[VAPID] Uplink failed: Cryptographic keys are missing from environment.");
        }

        // Standardize Base64Url to Base64 before validation
        $decodedKey = base64_decode(str_replace(['-', '_'], ['+', '/'], $publicKey));
        if (strlen($decodedKey) !== 65) {
             throw new \Exception("[VAPID] Integrity violation: Public key must be exactly 65 bytes when decoded.");
        }

        $auth = [
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $this->webPush = new WebPush($auth);
    }

    /**
     * Transmit to a single specific user node
     */
    public function sendToUser($userId, $title, $body, $url = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $this->dispatchPayload($stmt->fetchAll(), $title, $body, $url);
    }

    /**
     * Transmit to ALL subscribed nodes in the matrix
     */
    public function sendToAll($title, $body, $url = null) {
        $stmt = $this->pdo->query("SELECT * FROM push_subscriptions");
        return $this->dispatchPayload($stmt->fetchAll(), $title, $body, $url);
    }

    /**
     * Transmit ONLY to Active Resellers (Agent Tiers)
     */
    public function sendToAgents($title, $body, $url = null) {
        $stmt = $this->pdo->query("
            SELECT ps.* FROM push_subscriptions ps
            JOIN user_passes up ON ps.user_id = up.user_id
            WHERE up.status = 'active' AND up.expires_at > NOW()
            GROUP BY ps.id
        ");
        return $this->dispatchPayload($stmt->fetchAll(), $title, $body, $url);
    }

    /**
     * Core Dispatch Engine
     */
    private function dispatchPayload($subscriptions, $title, $body, $url = null) {
        if (!$subscriptions) return 0; // 0 sent

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => (defined('BASE_URL') ? BASE_URL : 'https://digitalmarketplacemm.com/') . 'assets/images/logo.png',
            'url'   => $url ?? (defined('BASE_URL') ? BASE_URL : 'https://digitalmarketplacemm.com/') . 'index.php?module=user&page=dashboard',
            'tag'   => 'order-update-' . (strpos($url, 'view_chat=') !== false ? explode('view_chat=', $url)[1] : 'general'),
            'renotify' => true
        ]);

        $queued = 0;
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh'],
                    'auth' => $sub['auth']
                ],
            ]);
            $this->webPush->queueNotification($subscription, $payload);
            $queued++;
        }

        $report = $this->webPush->flush();
        $success_count = 0;

        foreach ($report as $result) {
            if (!$result->isSuccess() && $result->isSubscriptionExpired()) {
                $this->deleteSubscription($result->getRequest()->getUri()->__toString());
            } else if ($result->isSuccess()) {
                $success_count++;
            }
        }

        return $success_count;
    }

    private function deleteSubscription($endpoint) {
        $stmt = $this->pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
    }
}
?>