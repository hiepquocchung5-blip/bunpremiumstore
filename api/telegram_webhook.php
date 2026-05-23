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
                $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                $revenue = $pdo->query("SELECT SUM(total_price_paid) FROM orders WHERE status = 'active' AND DATE(created_at) = CURDATE()")->fetchColumn() ?: 0;
                
                $reply = "📊 <b><u>STORE STATUS</u></b>\n";
                $reply .= "━━━━━━━━━━━━━━━━━━━━\n";
                $reply .= "🟢 <b>System:</b> Online\n";
                $reply .= "👤 <b>Total Users:</b> $total_users\n";
                $reply .= "🕒 <b>Pending Orders:</b> $pending\n";
                $reply .= "💰 <b>Today's Sales:</b> " . number_format($revenue) . " Ks\n";
                $reply .= "━━━━━━━━━━━━━━━━━━━━";
            }
            break;

        case '/find':
            if ($isAdmin && !empty($arg)) {
                $stmt = $pdo->prepare("
                    SELECT o.id, o.status, u.username, u.email, COALESCE(p.name, ps.name) as item_name
                    FROM orders o JOIN users u ON o.user_id = u.id
                    LEFT JOIN products p ON o.product_id = p.id LEFT JOIN passes ps ON o.pass_id = ps.id
                    WHERE o.id = ? OR u.email LIKE ? OR u.username LIKE ?
                    LIMIT 1
                ");
                $search = "%$arg%";
                $stmt->execute([$arg, $search, $search]);
                $res = $stmt->fetch();

                if ($res) {
                    $reply = "🔍 <b><u>USER LOCATED</u></b>\n\n";
                    $reply .= "🆔 <b>ID:</b> <code>#{$res['id']}</code>\n";
                    $reply .= "👤 <b>User:</b> @{$res['username']}\n";
                    $reply .= "📧 <b>Email:</b> {$res['email']}\n";
                    $reply .= "📦 <b>Item:</b> {$res['item_name']}\n";
                    $reply .= "🚥 <b>Status:</b> " . strtoupper($res['status']);
                } else {
                    $reply = "❌ <b>Not Found:</b> No record matching <code>$arg</code>.";
                }
            }
            break;

        case '/broadcast':
            if ($isAdmin && !empty($argsStr)) {
                $reply = "📡 <b>ANNOUNCEMENT SENT</b>\n\nMessage sent to all active admins.";
                $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
                foreach ($admin_ids as $adid) {
                    if ($adid == $chat_id) continue;
                    send_reply($adid, "📣 <b><u>STORE ANNOUNCEMENT</u></b>\n\n" . $argsStr);
                }
            }
            break;

        case '/ping':
            $start = microtime(true);
            $reply = "🏓 <b>PONG</b>\n\n";
            $reply .= "🛰 <b>Speed:</b> " . round((microtime(true) - $start) * 1000, 2) . "ms\n";
            $reply .= "⏲ <b>Server Time:</b> " . date('H:i:s');
            break;

        case '/aistatus':
            if ($isAdmin) {
                $reply = "🤖 <b><u>AI SYSTEM HEALTH</u></b>\n\n";
                
                $is_cooling_down = function_exists('matrix_cache_get') ? matrix_cache_get('ai_quota_cooldown') : false;
                
                if ($is_cooling_down) {
                    $reply .= "⏳ <b>Status:</b> RATE LIMITED (429)\n";
                    $reply .= "⚠️ <b>Mode:</b> Manual Human Rules Active\n";
                    $reply .= "📝 <b>Details:</b> Google free tier quota reached. System is resting for 60 seconds to reset uplink.";
                } else {
                    $test_reply = matrix_core_inference("Are you online?", "Health Check");
                    if ($test_reply) {
                        $reply .= "🟢 <b>Status:</b> Online & Optimal\n";
                        $reply .= "🧠 <b>Model:</b> Gemini Flash Latest\n";
                        $reply .= "💬 <b>Test Result:</b> <i>" . h($test_reply) . "</i>";
                    } else {
                        $reply .= "🔴 <b>Status:</b> Connection Error\n";
                        $reply .= "⚠️ <b>Action:</b> Check Gemini API Key and network uplink.";
                    }
                }
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
                    $reply = "🕒 <b><u>PENDING ORDERS</u></b>\n\n";
                    foreach ($orders as $o) {
                        $reply .= "🔹 <b>Order:</b> <code>{$o['id']}</code> | 👤 @{$o['username']}\n";
                        $reply .= "📦 {$o['item_name']}\n";
                        $reply .= "💰 " . number_format($o['total_price_paid']) . " Ks\n";
                        $reply .= "━━━━━━━━━━━━━━━━\n";
                    }
                    $reply .= "\n<i>Type: /approve [ID], /reject [ID], or /reply [ID] [Msg]</i>";
                } else {
                    $reply = "✅ <b>All Clear:</b> No pending orders.";
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
                    $delivery_msg = "Hello! Your order for <b>{$ord['item_name']}</b> is now active. You can check your items in the orders tab!";
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', ?)")->execute([$arg, $delivery_msg]);

                    invalidate_user_cache($ord['user_id']);
                    trigger_push_alert($pdo, $ord['user_id'], "Order Complete ✅", "Order #$arg has been checked and activated.", $arg);
                    
                    $reply = "✅ <b>ORDER APPROVED</b>\n\nOrder <code>#$arg</code> is now Active.";
                } else {
                    $reply = "⚠️ Order <code>#$arg</code> not found.";
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
                    $pdo->prepare("INSERT INTO order_messages (order_id, sender_type, message) VALUES (?, 'admin', '❌ Order Rejected. Please contact support for more information.')")->execute([$arg]);
                    
                    invalidate_user_cache($ord['user_id']);
                    
                    trigger_push_alert($pdo, $ord['user_id'], "Order Rejected ❌", "There was an issue with Order #$arg.", $arg);
                    $reply = "🚫 <b>ORDER REJECTED</b>\n\nOrder <code>#$arg</code> Rejected.";
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
                    
                    trigger_push_alert($pdo, $ord['user_id'], "New Message 💬", "The admin replied to your Order #$arg", $arg);
                    $reply = "✉️ <b>MESSAGE SENT</b>\n\nYour message was sent to Order <code>#$arg</code>.";
                }
            }
            break;

        case '/train':
            if ($isAdmin && !empty($argsStr)) {
                $parts = explode(' ', $argsStr, 2);
                if (count($parts) < 2) {
                    $reply = "❌ <b>Usage:</b> <code>/train [Tag] [New Pattern]</code>\nExample: <code>/train greeting hello there</code>";
                } else {
                    $tag = trim($parts[0]);
                    $pattern = trim($parts[1]);
                    
                    require_once __DIR__ . '/../includes/MatrixLocalAI.php';
                    $localAI = new MatrixLocalAI(__DIR__ . '/../includes/ai_training.json');
                    
                    if ($localAI->learn($pattern, $tag)) {
                        $reply = "🧠 <b>REINFORCEMENT SUCCESS</b>\n\nAI has learned to associate <i>\"$pattern\"</i> with 🏷 <b>$tag</b>.";
                    } else {
                        $reply = "❌ <b>TRAINING FAILED</b>\n\nTag <code>$tag</code> not found in training data.";
                    }
                }
            }
            break;

        case '/start':
        case '/help':
        default:
            if ($isAdmin) {
                // If it's a command starting with /, show help
                if (strpos($text, '/') === 0) {
                    $reply = "👋 <b>Hello @$username!</b>\nWelcome to the <b>DigitalMarketplaceMM Store Manager</b>.\n\n";
                    $reply .= "🛠 <b><u>ADMIN COMMANDS</u></b>\n";
                    $reply .= "🔹 <code>/stats</code> - Store status\n";
                    $reply .= "🔹 <code>/pending</code> - See new orders\n";
                    $reply .= "🔹 <code>/train [Tag] [Msg]</code> - Teach AI\n";
                    $reply .= "🔹 <code>/approve [ID]</code> - Confirm an order\n";
                    $reply .= "🔹 <code>/reply [ID] [Msg]</code> - Message a user\n";
                    $reply .= "🔹 <code>/aistatus</code> - Check AI health\n";
                    $reply .= "🔹 <code>/ping</code> - Check server speed\n";
                } else {
                    // It's a normal message from Admin, Mr. Scotty can act as a co-pilot if requested
                    if (strpos(strtolower($text), 'scotty') !== false) {
                        $reply = get_ai_response($text);
                    }
                }
            } else {
                // IT IS A USER (Non-Admin) - Mr. Scotty takes over for normal messages!
                if (strpos($text, '/') === 0) {
                    $reply = "👋 <b>Hello @$username!</b>\nWelcome to our store.\n\n";
                    $reply .= "🆔 <b>Your ID:</b> <code>$chat_id</code>\nSupport is restricted to our website chat.";
                } else {
                    // Normal message from user -> AUTO REPLY BY MR. SCOTTY
                    $reply = get_ai_response($text, "User: @{$username}");
                    
                    // Also notify admins that a user is talking to AI
                    $admin_notify = "🤖 <b>AI Support</b> is helping 👤 @{$username}\n💬 <i>{$text}</i>";
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
?>