<?php
// api/telegram_webhook.php

// Load Config
require_once '../includes/config.php';
require_once '../includes/functions.php'; // For DB connection $pdo

// 1. Get Incoming Update
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    // Silence is golden
    exit;
}

// 2. Extract Message Info
$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');
$username = $update['message']['from']['username'] ?? 'Unknown';

// 3. Command Handler
if ($chat_id) {
    
    // Default Response
    $reply = "";
    
    // Check if user is Admin
    $isAdmin = ($chat_id == TG_ADMIN_CHAT_ID);

    // Split command and arguments (e.g., "/approve 123" -> ["/approve", "123"])
    $parts = explode(' ', $text);
    $command = strtolower($parts[0]);
    $arg = $parts[1] ?? null;

    switch ($command) {
        case '/start':
            $reply = "👋 <b>Hello $username!</b>\n\nI am the ScottSub Notification Bot.\n\nType /myid to get your Chat ID for the config file.";
            if ($isAdmin) {
                $reply .= "\n\n<b>Admin Commands:</b>\n/stats - View Revenue\n/pending - List Pending Orders\n/approve [id] - Approve Order\n/reject [id] - Reject Order";
            }
            break;

        case '/myid':
            $reply = "🆔 <b>Your Chat ID is:</b> <code>$chat_id</code>\n\nCopy this ID and paste it into <code>includes/functions.php</code> under <code>TG_ADMIN_CHAT_ID</code> to receive order alerts.";
            break;

        case '/stats':
            if ($isAdmin) {
                // Fetch live stats
                $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                $revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
                
                $reply = "📊 <b>Live Store Stats:</b>\n\n";
                $reply .= "🕒 Pending Orders: <b>$pending</b>\n";
                $reply .= "💰 Today's Revenue: <b>" . number_format($revenue) . " Ks</b>\n";
                $reply .= "✅ System: <b>Online</b>";
            } else {
                $reply = "⛔ Access Denied.";
            }
            break;

        case '/pending':
            if ($isAdmin) {
                $stmt = $pdo->query("SELECT id, total_price_paid, created_at FROM orders WHERE status = 'pending' ORDER BY id DESC LIMIT 10");
                $orders = $stmt->fetchAll();
                
                if ($orders) {
                    $reply = "🕒 <b>Pending Orders:</b>\n\n";
                    foreach ($orders as $o) {
                        $reply .= "🆔 #{$o['id']} - " . number_format($o['total_price_paid']) . " Ks\n";
                    }
                    $reply .= "\n<i>Use /approve [id] to process.</i>";
                } else {
                    $reply = "✅ No pending orders.";
                }
            } else {
                $reply = "⛔ Access Denied.";
            }
            break;

        case '/approve':
            if ($isAdmin) {
                if (is_numeric($arg)) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'active' WHERE id = ?");
                    $stmt->execute([$arg]);
                    if ($stmt->rowCount() > 0) {
                        $reply = "✅ Order #$arg has been <b>APPROVED</b>.";
                    } else {
                        $reply = "⚠️ Order #$arg not found or already active.";
                    }
                } else {
                    $reply = "⚠️ Usage: <code>/approve 123</code>";
                }
            } else {
                $reply = "⛔ Access Denied.";
            }
            break;

        case '/reject':
            if ($isAdmin) {
                if (is_numeric($arg)) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$arg]);
                    if ($stmt->rowCount() > 0) {
                        $reply = "🚫 Order #$arg has been <b>REJECTED</b>.";
                    } else {
                        $reply = "⚠️ Order #$arg not found.";
                    }
                } else {
                    $reply = "⚠️ Usage: <code>/reject 123</code>";
                }
            } else {
                $reply = "⛔ Access Denied.";
            }
            break;

        default:
            $reply = "Unknown command. Try /start";
            break;
    }

    // 4. Send Reply
    if ($reply) {
        send_reply($chat_id, $reply);
    }
}

/**
 * Helper to send message back to Telegram
 */
function send_reply($chat_id, $text) {
    $url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    curl_close($ch);
}
?>