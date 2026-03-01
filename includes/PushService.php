<?php
// includes/PushService.php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

require '/../vendor/autoload.php';

class PushService {
    
    private $webPush;
    private $pdo;

    // REPLACE THESE WITH YOUR GENERATED KEYS
    const VAPID_SUBJECT = 'mailto:admin@digitalmarketplacemm.com';
    const PUBLIC_KEY    = 'BN8UEB3CSeOF0iWGN1FiBBR88hwxSzOf0i1zQQJlflF4GASn3LlndfgrZz7Z6akSPORPAfmwLO2GKn33aiSINHU'; 
    const PRIVATE_KEY   = 'dngL_yeyREkxn2ohyd3Odgy4z_L5P0oYTRxwMd5cTLo';

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
     * Send a notification to a specific user
     */
    public function sendToUser($userId, $title, $body, $url = null) {
        // 1. Fetch user's subscriptions (a user might be logged in on multiple devices)
        $stmt = $this->pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $subscriptions = $stmt->fetchAll();

        if (!$subscriptions) return false;

        // 2. Prepare Payload
        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => BASE_URL . 'assets/images/logo.png',
            'url'   => $url ?? BASE_URL . 'index.php?module=user&page=orders'
        ]);

        // 3. Queue notifications
        foreach ($subscriptions as $sub) {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => [
                    'p256dh' => $sub['p256dh'],
                    'auth' => $sub['auth']
                ],
            ]);

            $this->webPush->queueNotification($subscription, $payload);
        }

        // 4. Send all
        $report = $this->webPush->flush();

        // 5. Clean up expired subscriptions
        foreach ($report as $result) {
            if (!$result->isSuccess() && $result->isSubscriptionExpired()) {
                $endpoint = $result->getRequest()->getUri()->__toString();
                $this->deleteSubscription($endpoint);
            }
        }

        return true;
    }

    private function deleteSubscription($endpoint) {
        $stmt = $this->pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?");
        $stmt->execute([$endpoint]);
    }
}
?>