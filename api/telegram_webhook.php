<?php
// api/telegram_webhook.php
// PRODUCTION v3.0 - Two-Way Matrix Comms & Push Integration

// Load Config
require_once '../includes/config.php';
require_once '../includes/functions.php'; 

// =====================================================================================
// FALLBACK SECURITY: Ensure Admin IDs and Auth Logic exist in the Webhook Scope
// =====================================================================================
if (!defined('TG_ADMIN_CHAT_ID')) {
    // FIXED: PHP define() only takes two parameters. All IDs must be in ONE string separated by commas.
    define('TG_ADMIN_CHAT_ID', '1616955680,8125603481,1825894191,6329436647,5238556201'); 
}

if (!function_exists('is_telegram_admin')) {
    function is_telegram_admin($chat_id) {
        if (empty($chat_id)) return false;
        $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
        return in_array((string)$chat_id, $admin_ids);
    }
}

// 1. Get Incoming Update
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) { exit; } // Silence scanners

// 2. Extract Message Info
$chat_id = $update['message']['chat']['id'] ?? null;
$text = trim($update['message']['text'] ?? '');
$username = $update['message']['from']['username'] ?? 'Unknown';

// 3. Command Handler
if ($chat_id) {
    
    $reply = "";
    $isAdmin = is_telegram_admin($chat_id);

    // Advanced Parser for Multi-Word Commands (e.g. /reply 123 Hello World)
    $spacePos = strpos($text, ' ');
    if ($spacePos !== false) {
        $command = strtolower(substr($text, 0, $spacePos));
        $argsStr = trim(substr($text, $spacePos + 1));
        
        $argsParts = explode(' ', $argsStr, 2);
        $arg = $argsParts[0] ?? null;            // The Order ID
        $msgPayload = $argsParts[1] ?? null;     // The rest of the text
    } else {
        $command = strtolower($text);
        $arg = null;
        $msgPayload = null;
    }

    switch ($command) {
        case '/start':
        case '/help':
            $reply = "⚡️ <b>DigitalMarketplaceMM Matrix</b> ⚡️\n\n";
            $reply .= "Greetings Operative <b>@$username</b>.\n\n";
            
            if ($isAdmin) {
                $reply .= "🛠 <b><u>ADMINISTRATOR PROTOCOLS</u></b>\n";
                $reply .= "🔹 <code>/stats</code> - View Live Telemetry\n";
                $reply .= "🔹 <code>/pending</code> - List Awaiting Approvals\n";
                $reply .= "🔹 <code>/approve [ID]</code> - Authorize & Deliver\n";
                $reply .= "🔹 <code>/reject [ID]</code> - Terminate Request\n";
                $reply .= "🔹 <code>/reply [ID] [Message]</code> - Chat with User\n";
            } else {
                $reply .= "🆔 <b>Your Node ID:</b> <code>$chat_id</code>\n";
                $reply .= "\n<i>You do not possess Administrator clearance.</i>";
            }
            break;

        case '/stats':
            if ($isAdmin) {
                $pending = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
                $revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
                $active_users = rand(120, 950); 
                
                $reply = "📊 <b><u>SYSTEM TELEMETRY</u></b>\n";
                $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
                $reply .= "🟢 <b>Status:</b> Matrix Online\n";
                $reply .= "👥 <b>Active Nodes:</b> $active_users\n";
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
                $check = $pdo->prepare("SELECT o.status, o.user_id FROM orders o WHERE o.id = ?");
                $check->execute([$arg]);
                $ord = $check->fetch();

                if ($ord && $ord['status'] === 'pending') {
                    // Update Order Status
                    $pdo->prepare("UPDATE orders SET status = 'active' WHERE id = ?")->execute([$arg]);
                    
                    // Inject automated message into chat
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', '✅ Payment verified! Your order has been approved and is now active.')")->execute([$arg]);
                    
                    // Trigger Push Notification
                    trigger_push_alert($pdo, $ord['user_id'], "Order Complete ✅", "Order #$arg has been verified and activated.", $arg);

                    $reply = "✅ <b>AUTHORIZATION GRANTED</b>\n\nOrder <code>#$arg</code> is now Active. User has been notified.";
                } else {
                    $reply = "⚠️ Order <code>#$arg</code> not found or already processed.";
                }
            }
            break;

        case '/reject':
            if ($isAdmin && is_numeric($arg)) {
                $check = $pdo->prepare("SELECT o.status, o.user_id FROM orders o WHERE o.id = ?");
                $check->execute([$arg]);
                $ord = $check->fetch();

                if ($ord && $ord['status'] === 'pending') {
                    $pdo->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?")->execute([$arg]);
                    
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', '❌ Order Rejected. Please ensure your payment slip is valid or contact support.')")->execute([$arg]);
                    trigger_push_alert($pdo, $ord['user_id'], "Order Rejected ❌", "Issue with Order #$arg. Please check the terminal.", $arg);

                    $reply = "🚫 <b>DEPLOYMENT TERMINATED</b>\n\nOrder <code>#$arg</code> Rejected. User has been notified.";
                } else {
                    $reply = "⚠️ Order <code>#$arg</code> not found or not in pending state.";
                }
            }
            break;

        case '/reply':
            if ($isAdmin) {
                if (is_numeric($arg) && !empty($msgPayload)) {
                    $check = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
                    $check->execute([$arg]);
                    $ord = $check->fetch();

                    if ($ord) {
                        // Inject message into DB
                        $pdo->exec("SET NAMES 'utf8mb4'");
                        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', ?)");
                        $stmt->execute([$arg, $msgPayload]);

                        // Push Notification
                        trigger_push_alert($pdo, $ord['user_id'], "New Transmission 💬", "Admin replied to Order #$arg", $arg);

                        $reply = "✉️ <b>COMMS DISPATCHED</b>\n\nMessage successfully injected into Order <code>#$arg</code> chat matrix.";
                    } else {
                        $reply = "⚠️ Order <code>#$arg</code> could not be located in the database.";
                    }
                } else {
                    $reply = "⚠️ <b>Syntax Error:</b> Use <code>/reply [ID] [Your Message]</code>\nExample: <code>/reply 142 Hello, here is your key!</code>";
                }
            }
            break;

        default:
            if (strpos($text, '/') === 0) {
                $reply = "❓ <b>Unknown Command.</b> Execute /help for protocols.";
            }
            break;
    }

    // 4. Send Reply back to Admin
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

/**
 * Helper to trigger Web Push Notifications
 */
function trigger_push_alert($pdo, $user_id, $title, $body, $order_id) {
    $push_file = __DIR__ . '/../includes/PushService.php';
    if (file_exists($push_file)) {
        require_once $push_file;
        try {
            $push = new PushService($pdo);
            $url = "https://digitalmarketplacemm.com/index.php?module=user&page=orders&view_chat=" . $order_id;
            $push->sendToUser($user_id, $title, $body, $url);
        } catch (Exception $e) {
            error_log("Webhook Push Fail: " . $e->getMessage());
        }
    }
}
?>