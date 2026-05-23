<?php
// api/telegram_webhook.php
// PRODUCTION v3.5 - Hardened Command Matrix & Real-time Synchronization

require_once '../includes/config.php';
require_once '../includes/functions.php'; 

// 1. Get Incoming Update (Strict JSON parsing)
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) { exit; } 

// 2. Extract Message Info
$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');
$username = $update['message']['from']['username'] ?? 'Unknown';

if (!$chat_id) { exit; }

try {
    $reply = "";
    $isAdmin = is_telegram_admin($chat_id);

    // Advanced Parser for Multi-Word Commands
    $argsStr = '';
    $spacePos = strpos($text, ' ');
    if ($spacePos !== false) {
        $command = strtolower(substr($text, 0, $spacePos));
        $argsStr = trim(substr($text, $spacePos + 1));
        
        $argsParts = explode(' ', $argsStr, 2);
        $arg = $argsParts[0] ?? null;            // Usually ID
        $msgPayload = $argsParts[1] ?? null;     // Content
    } else {
        $command = strtolower($text);
        $arg = null;
        $msgPayload = null;
    }

    switch ($command) {
        case '/stats':
            if ($isAdmin) {
                $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                $revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
                
                $reply = "📊 <b><u>SYSTEM TELEMETRY</u></b>\n";
                $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
                $reply .= "🟢 <b>Status:</b> Matrix Online\n";
                $reply .= "🕒 <b>Pending Orders:</b> $pending\n";
                $reply .= "💰 <b>Daily Volume:</b> " . number_format($revenue) . " Ks\n";
                $reply .= "━━━━━━━━━━━━━━━━━━━━";
            }
            break;

        case '/pending':
            if ($isAdmin) {
                $stmt = $pdo->query("
                    SELECT o.id, o.total_price_paid, u.username, COALESCE(p.name, ps.name) as item_name
                    FROM orders o JOIN users u ON o.user_id = u.id
                    LEFT JOIN products p ON o.product_id = p.id LEFT JOIN passes ps ON o.pass_id = ps.id
                    WHERE o.status = 'pending' ORDER BY o.id DESC LIMIT 10
                ");
                $orders = $stmt->fetchAll();
                
                if ($orders) {
                    $reply = "🕒 <b><u>AWAITING AUTHORIZATION</u></b>\n\n";
                    foreach ($orders as $o) {
                        $reply .= "🔹 <b>Order:</b> <code>{$o['id']}</code> | 👤 @{$o['username']}\n";
                        $reply .= "📦 {$o['item_name']}\n";
                        $reply .= "💰 " . number_format($o['total_price_paid']) . " Ks\n";
                        $reply .= "━━━━━━━━━━━━━━━━\n";
                    }
                    $reply .= "\n<i>Execute: /approve [ID], /reject [ID], or /reply [ID] [Msg]</i>";
                } else {
                    $reply = "✅ <b>Matrix Clear:</b> Zero pending orders.";
                }
            }
            break;

        case '/approve':
            if ($isAdmin && is_numeric($arg)) {
                $check = $pdo->prepare("SELECT status, user_id, COALESCE(p.name, ps.name) as item_name FROM orders o LEFT JOIN products p ON o.product_id=p.id LEFT JOIN passes ps ON o.pass_id=ps.id WHERE o.id = ?");
                $check->execute([$arg]);
                $ord = $check->fetch();

                if ($ord && $ord['status'] === 'pending') {
                    $pdo->prepare("UPDATE orders SET status = 'active' WHERE id = ?")->execute([$arg]);
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', '✅ Payment verified! Your order has been approved.')")->execute([$arg]);
                    
                    // 🤖 MR. SCOTTY AUTOMATIC DELIVERY MSG (Extra effort)
                    $delivery_msg = "Operative! Your deployment for <b>{$ord['item_name']}</b> is now active in the matrix. Check your orders tab!";
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', ?)")->execute([$arg, $delivery_msg]);

                    invalidate_user_cache($ord['user_id']);
                    trigger_push_alert($pdo, $ord['user_id'], "Order Complete ✅", "Order #$arg has been verified and activated.", $arg);
                    
                    $reply = "✅ <b>AUTHORIZATION GRANTED</b>\n\nOrder <code>#$arg</code> Active. Cache cleared. Delivery protocols initialized.";
                } else {
                    $reply = "⚠️ Order <code>#$arg</code> not found or processed.";
                }
            }
            break;

        case '/reject':
            if ($isAdmin && is_numeric($arg)) {
                $check = $pdo->prepare("SELECT status, user_id FROM orders WHERE id = ?");
                $check->execute([$arg]);
                $ord = $check->fetch();

                if ($ord && $ord['status'] === 'pending') {
                    $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?")->execute([$arg]);
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', '❌ Order Rejected. Please contact support.')")->execute([$arg]);
                    
                    invalidate_user_cache($ord['user_id']);
                    
                    trigger_push_alert($pdo, $ord['user_id'], "Order Rejected ❌", "Issue with Order #$arg.", $arg);
                    $reply = "🚫 <b>DEPLOYMENT TERMINATED</b>\n\nOrder <code>#$arg</code> Rejected.";
                }
            }
            break;

        case '/reply':
            if ($isAdmin && is_numeric($arg) && !empty($msgPayload)) {
                $check = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                $check->execute([$arg]);
                $ord = $check->fetch();

                if ($ord) {
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', ?)")->execute([$arg, $msgPayload]);
                    
                    invalidate_user_cache($ord['user_id']);
                    
                    trigger_push_alert($pdo, $ord['user_id'], "New Transmission 💬", "Admin replied to Order #$arg", $arg);
                    $reply = "✉️ <b>COMMS DISPATCHED</b>\n\nMessage injected into Order <code>#$arg</code> matrix.";
                }
            }
            break;

        case '/start':
        case '/help':
        default:
            if ($isAdmin) {
                // If it's a command starting with /, show help
                if (strpos($text, '/') === 0) {
                    $reply = "⚡️ <b>DigitalMarketplaceMM Matrix</b> ⚡️\n\n Operative <b>@$username</b> identified.\n\n";
                    $reply .= "🛠 <b><u>ADMIN PROTOCOLS</u></b>\n";
                    $reply .= "🔹 <code>/stats</code> | <code>/pending</code>\n";
                    $reply .= "🔹 <code>/approve [ID]</code> | <code>/reject [ID]</code>\n";
                    $reply .= "🔹 <code>/reply [ID] [Msg]</code>\n";
                } else {
                    // It's a normal message from Admin, Mr. Scotty can act as a co-pilot if requested
                    if (strpos(strtolower($text), 'scotty') !== false) {
                        $reply = get_ai_response($text);
                    }
                }
            } else {
                // IT IS A USER (Non-Admin) - Mr. Scotty takes over for normal messages!
                if (strpos($text, '/') === 0) {
                    $reply = "⚡️ <b>DigitalMarketplaceMM Matrix</b> ⚡️\n\n Operative <b>@$username</b> identified.\n\n";
                    $reply .= "🆔 <b>Node:</b> <code>$chat_id</code>\nAccess Restricted.";
                } else {
                    // Normal message from user -> AUTO REPLY BY MR. SCOTTY
                    $reply = get_ai_response($text, "User: @{$username}");
                    
                    // Also notify admins that a user is talking to Scotty
                    $admin_notify = "🤖 <b>Mr. Scotty</b> is handling 👤 @{$username}\n💬 <i>{$text}</i>";
                    $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
                    foreach ($admin_ids as $adid) {
                        if ($adid == $chat_id) continue;
                        send_reply($adid, $admin_notify);
                    }
                }
            }
            break;
    }

    if ($reply) {
        send_reply($chat_id, $reply);
    }

} catch (Exception $e) {
    error_log("Telegram Webhook Error: " . $e->getMessage());
}

/**
 * TELEGRAM COMMUNICATION PROTOCOLS
 */
function send_reply($chat_id, $text) {
    $ch = curl_init("https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => ['chat_id' => $chat_id, 'text' => $text, 'parse_mode' => 'HTML']
    ]);
    curl_exec($ch); curl_close($ch);
}

function trigger_push_alert($pdo, $user_id, $title, $body, $order_id) {
    $push_file = __DIR__ . '/../includes/PushService.php';
    if (file_exists($push_file)) {
        require_once $push_file;
        try {
            $push = new PushService($pdo);
            $url = "https://digitalmarketplacemm.com/index.php?module=user&page=orders&view_chat=" . $order_id;
            $push->sendToUser($user_id, $title, $body, $url);
        } catch (Exception $e) {}
    }
}
?>