<?php
// includes/PushService.php
// PRODUCTION v2.1 - Updated VAPID Cryptographic Keys & Bulk Broadcast Matrix

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require_once __DIR__ . '/../vendor/autoload.php';

class PushService {
    
    private $webPush;
    private $pdo;

    // YOUR LIVE CRYPTOGRAPHIC KEYS
    const VAPID_SUBJECT = 'mailto:noreply-otpsender@digitalmarketplacemm.com';
    const PUBLIC_KEY    = 'BH9ppIX9b68N76wmcNsrevG4Jl6RRCMuOFptWOgE9C0-0hLTTLhnEB2orPy_POTaM1PJxvH1pW0jyG1x8gnqWh0'; 
    const PRIVATE_KEY   = 'JBhrzriKcczPYpx8vgC-D-ObrXksntdJBbkut-Xmrwc';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        $auth = [
            'VAPID' => [
                'subject' => self::VAPID_SUBJECT,
                'publicKey' => self::PUBLIC_KEY,
                'privateKey' => self::PRIVATE_KEY,
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
            'icon'  => 'https://digitalmarketplacemm.com/assets/images/logo.png',
            'url'   => $url ?? 'https://digitalmarketplacemm.com/index.php?module=user&page=dashboard'
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