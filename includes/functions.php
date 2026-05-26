<?php
// includes/functions.php
// PRODUCTION v6.0 - Matrix Cache Engine, Telegram Sync & Blacklist Protocol

/**
 * --------------------------------------------------------------------------
 * SECURITY, AUTHENTICATION & BLACKLIST ENGINE
 * --------------------------------------------------------------------------
 */

// Helper to securely check if a Telegram Chat ID has Admin Clearance
function is_telegram_admin($chat_id) {
    if (empty($chat_id)) return false;
    $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
    return in_array((string)$chat_id, $admin_ids);
}

// ⚡️ INVALIDATE USER CACHE
function invalidate_user_cache($user_id) {
    if (function_exists('matrix_cache_delete')) {
        matrix_cache_delete("user_orders_list_{$user_id}");
        matrix_cache_delete("user_notif_data_{$user_id}");
    }
}

// Generate CSRF Token for Forms
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Check if User is Logged In
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Safe Redirect Helper
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . BASE_URL . ltrim($url, '/'));
    } else {
        echo "<script>window.location.href='" . BASE_URL . ltrim($url, '/') . "';</script>";
    }
    exit;
}

// ⚡️ NEW: BLACKLIST PROTOCOL (Ban Console)
function enforce_ban_protocol() {
    global $pdo;
    if (is_logged_in()) {
        try {
            $stmt = $pdo->prepare("SELECT is_banned, ban_reason FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if ($user && $user['is_banned'] == 1) {
                // Terminate Session
                $reason = $user['ban_reason'] ?: "Violation of terms of service.";
                session_unset();
                session_destroy();
                
                // Construct a raw HTML lockout screen
                die("
                <body style='background-color:#020617; color:#f8fafc; font-family:sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;'>
                    <div style='text-align:center; border: 1px solid #ef4444; padding: 40px; border-radius: 16px; background-color: rgba(239, 68, 68, 0.1); box-shadow: 0 0 30px rgba(239, 68, 68, 0.3); max-width: 500px;'>
                        <h1 style='color:#ef4444; font-size: 32px; margin-bottom: 10px;'>ACCESS DENIED</h1>
                        <p style='color:#94a3b8; font-size: 14px; margin-bottom: 20px;'>Your account has been permanently suspended.</p>
                        <div style='background-color:#0f172a; padding: 15px; border-radius: 8px; color:#f87171; border: 1px solid #7f1d1d;'>
                            <strong>Reason:</strong> " . htmlspecialchars($reason) . "
                        </div>
                        <a href='" . BASE_URL . "' style='display:inline-block; margin-top:20px; color:#3b82f6; text-decoration:none; font-weight:bold;'>Return Home</a>
                    </div>
                </body>");
            }
        } catch (Exception $e) {
            // Fails gracefully if 'is_banned' column doesn't exist yet
        }
    }
}

/**
 * --------------------------------------------------------------------------
 * PRICING & DISCOUNTS
 * --------------------------------------------------------------------------
 */

// Get Active Agent Discount % for a User
function get_user_discount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT p.discount_percent FROM user_passes up
            JOIN passes p ON up.pass_id = p.id
            WHERE up.user_id = ? AND up.status = 'active' AND up.expires_at > NOW()
            ORDER BY p.discount_percent DESC LIMIT 1
        ");
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        return 0;
    }
}

// Format Price based on Session Currency (MMK or USD)
function format_price($amount_mmk) {
    if (isset($_SESSION['currency']) && $_SESSION['currency'] === 'USD') {
        $usd = $amount_mmk / (defined('EXCHANGE_RATE') ? EXCHANGE_RATE : 4200);
        return '$' . number_format($usd, 2);
    }
    return number_format($amount_mmk, 0) . ' Ks';
}

/**
 * --------------------------------------------------------------------------
 * TELEGRAM NOTIFICATIONS (WEBHOOK / API)
 * --------------------------------------------------------------------------
 */

function send_telegram_alert($order_id, $product_name, $price, $username) {
    global $pdo;

    $token = TG_BOT_TOKEN;
    $admin_ids = array_map('trim', explode(',', TG_ADMIN_CHAT_ID));
    $admin_url = defined('ADMIN_URL') ? ADMIN_URL : BASE_URL . 'admin/';
    $admin_url .= "index.php?page=order_detail&id=" . $order_id;
    $base_url = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://digitalmarketplacemm.com';

    $txn_id = "N/A";
    $proof_path = "";
    $product_image_path = "";
    $category_image_path = "";

    try {
        $stmt = $pdo->prepare("
            SELECT o.transaction_last_6, o.proof_image_path, p.image_path as product_image_path, c.image_url as category_image_path
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        if ($row = $stmt->fetch()) {
            $txn_id = $row['transaction_last_6'];
            $proof_path = $row['proof_image_path'];
            $product_image_path = $row['product_image_path'] ?? '';
            $category_image_path = $row['category_image_path'] ?? '';
        }
    } catch (Exception $e) {
        error_log('Telegram Alert DB Error: ' . $e->getMessage());
    }

    $full_msg = "🆕 <b>New Order Node</b>\n";
    $full_msg .= "━━━━━━━━━━━━━━━━\n";
    $full_msg .= "🆔 <b>ID:</b> #{$order_id}\n";
    $full_msg .= "👤 <b>User:</b> @{$username}\n";
    $full_msg .= "📦 <b>Item:</b> {$product_name}\n";
    $full_msg .= "💰 <b>Price:</b> " . number_format($price) . " Ks\n";
    $full_msg .= "💳 <b>Txn:</b> <code>{$txn_id}</code>\n";
    if (!empty($proof_path)) {
        $full_msg .= "🧾 <b>Proof Path:</b> <code>" . htmlspecialchars($proof_path) . "</code>\n";
    }
    $full_msg .= "━━━━━━━━━━━━━━━━\n";
    $full_msg .= "🔗 <a href='{$admin_url}'>View Order Terminal</a>";

    $proof_url = '';
    if (!empty($proof_path)) {
        $proof_url = $base_url . '/' . ltrim($proof_path, '/');
    }

    $order_image_url = '';
    if (!empty($product_image_path)) {
        $order_image_url = $base_url . '/' . ltrim($product_image_path, '/');
    } elseif (!empty($category_image_path)) {
        $order_image_url = $base_url . '/' . ltrim($category_image_path, '/');
    }

    foreach ($admin_ids as $chat_id) {
        if (empty($chat_id)) continue;
        if (!empty($order_image_url)) {
            $product_caption = "🖼 <b>Product Mini Info</b>\n";
            $product_caption .= "Order #{$order_id} • @{$username}\n";
            $product_caption .= "📦 {$product_name}\n";
            $product_caption .= "💰 " . number_format($price) . " Ks";

            $product_photo_url = "https://api.telegram.org/bot{$token}/sendPhoto";
            $product_photo_data = [
                'chat_id' => $chat_id,
                'photo' => $order_image_url,
                'caption' => $product_caption,
                'parse_mode' => 'HTML'
            ];

            $ch = curl_init($product_photo_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($product_photo_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }

        if (!empty($proof_url)) {
            $photo_url = "https://api.telegram.org/bot{$token}/sendPhoto";
            $photo_data = [
                'chat_id' => $chat_id,
                'photo' => $proof_url,
                'caption' => "🖼 <b>Payment Screenshot</b>\nOrder #{$order_id} • @{$username}\n" . (!empty($proof_path) ? "🧾 <code>" . htmlspecialchars($proof_path) . "</code>" : ""),
                'parse_mode' => 'HTML'
            ];

            $ch = curl_init($photo_url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($photo_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_exec($ch);
            curl_close($ch);
        }

        $text_url = "https://api.telegram.org/bot{$token}/sendMessage";
        $text_data = [
            'chat_id' => $chat_id,
            'text' => $full_msg,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init($text_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($text_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * --------------------------------------------------------------------------
 * UTILITIES
 * --------------------------------------------------------------------------
 */

// Get Unread Admin Messages Count
function get_unread_notifications($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM order_messages om
            JOIN orders o ON om.order_id = o.id
            WHERE o.user_id = ? AND om.sender_type = 'admin'
            AND om.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Build a full URL from relative path (with fallback base)
function base_url($path = '') {
    $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://digitalmarketplacemm.com';
    if (empty($path) || $path === '/') {
        return $base . '/';
    }
    return $base . '/' . ltrim($path, '/');
}

// Normalize legacy dashboard query to standard page=dashboard route
function normalize_dashboard_route() {
    $isUserModule = isset($_GET['module']) && $_GET['module'] === 'user';
    $hasLegacyDashboard = $isUserModule && (isset($_GET['dashboard']) || preg_match('/(?:^|&)dashboard(?:=|&|$)/', $_SERVER['QUERY_STRING'] ?? ''));
    $needsRedirect = $hasLegacyDashboard && !isset($_GET['page']);

    if ($needsRedirect) {
        $target = base_url('index.php?module=user&page=dashboard');
        redirect($target);
    }
}
normalize_dashboard_route();

// Sanitize Output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * --------------------------------------------------------------------------
 * MATRIX CACHE ENGINE (FILE-BASED)
 * --------------------------------------------------------------------------
 * Stores heavy database queries as flat JSON files to reduce MySQL load.
 */

function matrix_cache_get($key) {
    $cache_file = __DIR__ . '/../uploads/cache/sys_' . md5($key) . '.cache';
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && isset($data['expires']) && $data['expires'] > time()) {
            return $data['content'];
        }
        @unlink($cache_file); // Purge expired
    }
    return false;
}

function matrix_cache_set($key, $content, $ttl_seconds = 300) {
    $cache_dir = __DIR__ . '/../uploads/cache/';

    // Auto-provision secure directory if missing
    if (!is_dir($cache_dir)) {
        @mkdir($cache_dir, 0755, true);
        @file_put_contents($cache_dir . '.htaccess', "Order Deny,Allow\nDeny from all");
    }

    $cache_file = $cache_dir . 'sys_' . md5($key) . '.cache';
    $data = [
        'expires' => time() + $ttl_seconds,
        'content' => $content
    ];
    @file_put_contents($cache_file, json_encode($data));
}

function matrix_cache_delete($key) {
    $cache_file = __DIR__ . '/../uploads/cache/sys_' . md5($key) . '.cache';
    if (file_exists($cache_file)) {
        @unlink($cache_file);
    }
}

// ⚡️ HUMAN SUPPORT PIPELINE

function detect_intent($message) {
    $message = strtolower($message);

    $intents = [
        'payment' => ['pay', 'payment', 'kbz', 'wave', 'slip', 'ငွေ', 'ဘဏ်', 'လွှဲ', 'kpay'],
        'delivery' => ['delivery', 'receive', 'arrive', 'ပို့', 'ရပြီ', 'ဘယ်တော့'],
        'refund' => ['refund', 'money back', 'ပြန်အမ်း'],
        'angry' => ['bad', 'slow', 'scam', 'hate', 'လိမ်', 'ကြာ', 'စောက်'],
        'human' => ['admin', 'human', 'staff', 'real person', 'လူ', 'အက်ဒမင်']
    ];

    foreach ($intents as $intent => $words) {
        foreach ($words as $word) {
            if (strpos($message, $word) !== false) {
                return $intent;
            }
        }
    }

    return 'general';
}

function normalize_ai_reply($reply) {
    $reply = trim((string)$reply);
    if ($reply === '') return '';
    $reply = preg_replace("/[ \t]+/u", ' ', $reply);
    $reply = preg_replace("/\n{3,}/u", "\n\n", $reply);
    return trim($reply);
}

function train_local_ai_example($tag, $pattern, $response = null, $reward = 1.0) {
    $local_ai_path = __DIR__ . '/MatrixLocalAI.php';
    $training_path = __DIR__ . '/ai_training.json';

    if (!file_exists($local_ai_path) || !file_exists($training_path)) {
        return false;
    }

    require_once $local_ai_path;
    $localAI = new MatrixLocalAI($training_path);
    return $localAI->teach($tag, $pattern, $response, $reward);
}

function get_ai_response($message, $context = "") {
    $intent = detect_intent($message);

    // Stage 1: Matrix Local Algorithmic AI (Reinforcement Engine)
    $local_ai_path = __DIR__ . '/MatrixLocalAI.php';
    $training_path = __DIR__ . '/ai_training.json';
    
    if (file_exists($local_ai_path) && file_exists($training_path)) {
        require_once $local_ai_path;
        $localAI = new MatrixLocalAI($training_path);
        
        // Predict with Reinforcement Logic (Threshold 0.25)
        $local_prediction = $localAI->predict($message, 0.25);
        if ($local_prediction && !empty($local_prediction['response'])) {
            // We can log the successful hit for future analytics here
            return normalize_ai_reply($local_prediction['response']);
        }
    }
    
    // Stage 2: External LLM Fallback (Matrix Core Inference)
    $llm_response = matrix_core_inference($message, $context, $intent);
    
    // 🛡️ AI CONFIDENCE SCORING (Optimized for Burmese Human Realism)
    if ($llm_response) {
        $reply = normalize_ai_reply($llm_response);
        $len = mb_strlen($reply, 'UTF-8');
        if (
            $len >= 2 && 
            $len <= 1000 && 
            substr_count($reply, "\n") <= 20 &&
            !preg_match('/^(ဟုတ်ကဲ့|ဟုတ်ကဲ့ခင်ဗျာ|ok)$/ui', $reply)
        ) {
            return $reply;
        }
    }

    // Stage 3: Absolute Safety Fallback
    $fallbacks = [
        "ဟုတ်ကဲ့၊ သိချင်တာလေးကို သေချာလေး ထပ်ပြောပေးလို့ရမလားခင်ဗျာ။",
        "ဟုတ်ကဲ့၊ စစ်ဆေးပေးနေပါတယ် ခဏလေးစောင့်ပေးပါနော်။",
        "မေးမြန်းပေးတဲ့အတွက် ကျေးဇူးပါ။ ဘာများထပ်ကူညီပေးရမလဲခင်ဗျာ။"
    ];
    return normalize_ai_reply($fallbacks[array_rand($fallbacks)]);
}

/**
 * 🧠 MATRIX CORE INFERENCE ENGINE
 * Advanced LLM abstraction layer for DigitalMarketplaceMM.
 * Custom implementation optimized for Burmese human-like support.
 */
function matrix_core_inference($user_input, $context = "", $intent = "general") {
    global $pdo;
    $raw_keys = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : ($_ENV['GEMINI_API_KEY'] ?? ''); 
    if (empty($raw_keys)) return false;

    // ⚡️ ADAPTIVE LOAD BALANCING
    $keys = array_filter(array_map('trim', explode(',', $raw_keys)));
    if (empty($keys)) return false;
    $api_key = $keys[array_rand($keys)];

    // 🛡️ CIRCUIT BREAKER (Rate Limit Protection)
    if (function_exists('matrix_cache_get') && matrix_cache_get('ai_quota_cooldown')) {
        return false; 
    }

    // 🛠 INITIALIZE ACTION HUB (25+ Tools)
    $hub_path = __DIR__ . '/MatrixActionHub.php';
    $hub = null;
    $manifest_text = "";
    if (file_exists($hub_path)) {
        require_once $hub_path;
        $is_admin = (strpos($context, 'ADMIN_SESSION') !== false);
        $hub = new MatrixActionHub($pdo, $_SESSION['user_id'] ?? null, $is_admin);
        $manifest = $hub->get_manifest();
        foreach ($manifest as $cmd => $desc) { $manifest_text .= "- $cmd: $desc\n"; }
    }

    $url = "https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent";

    $system_prompt = "
CORE IDENTITY: MATRIX CORE v7.0 (Neural Persona: Ko Scotty)
VIBE: Senior Digital Consultant & Warm Burmese Staff

⚡️ TOOL USE PROTOCOL:
You have access to the following real-time tools:
{$manifest_text}

If you need data, output ONLY: [ACTION: action_name, PARAMS: value]
Wait for the tool result, then provide your final answer in polite Burmese.

If the user asks you to learn, remember, train, or teach a phrase, prefer the teach_local_ai tool with the shortest useful tag and pattern.

CONTEXT & HISTORY:
{$context}
";

    $payload = [
        "contents" => [["parts" => [["text" => $system_prompt . "\n\nUser Question: " . $user_input]]]],
        "generationConfig" => ["temperature" => 0.85, "maxOutputTokens" => 1200]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-goog-api-key: ' . $api_key],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20
    ]);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $json = json_decode($result, true);
        $ai_output = $json['candidates'][0]['content']['parts'][0]['text'] ?? false;

        // 🚀 ACTION DISPATCHER (The 'Agent' Brain)
        if ($ai_output && preg_match('/\[ACTION:\s*(\w+),\s*PARAMS:\s*(.*)\]/', $ai_output, $matches)) {
            $action = $matches[1];
            $params = array_map('trim', explode(',', $matches[2]));
            
            if ($hub) {
                $tool_result = $hub->execute($action, $params);
                
                // Re-inject tool result into conversation
                $final_payload = $payload;
                $final_payload['contents'][] = ["role" => "model", "parts" => [["text" => $ai_output]]];
                $final_payload['contents'][] = [
                    "role" => "user", 
                    "parts" => [["text" => "SYSTEM (Verified Data): " . $tool_result . "\n\nAction complete. Now provide your final, warm, and data-rich response to the user in polite Burmese."]]
                ];
                
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-goog-api-key: ' . $api_key],
                    CURLOPT_POSTFIELDS => json_encode($final_payload),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 20
                ]);
                $final_res = curl_exec($ch);
                curl_close($ch);
                
                if (curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                    $final_json = json_decode($final_res, true);
                    return $final_json['candidates'][0]['content']['parts'][0]['text'] ?? $ai_output;
                }
            }
        }
        return $ai_output;
    } else {
        if ($http_code === 429) {
            if (function_exists('matrix_cache_set')) { matrix_cache_set('ai_quota_cooldown', true, 60); }
            // Admin notification logic simplified for brevity
            error_log("Gemini Quota Exceeded (429).");
        }
        return false;
    }
}

/**
 * ⚡️ TELEGRAM COMMUNICATION PROTOCOL
 * Sends a message to a specific Telegram chat/admin.
 */
function send_reply($chat_id, $text) {
    if (!defined('TG_BOT_TOKEN') || empty(TG_BOT_TOKEN)) return false;
    
    $ch = curl_init("https://api.telegram.org/bot" . TG_BOT_TOKEN . "/sendMessage");
    curl_setopt_array($ch, [
        CURLOPT_POST => true, 
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chat_id, 
            'text' => $text, 
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ]
    ]);
    $res = curl_exec($ch); 
    curl_close($ch);
    return $res;
}

/**
 * ⚡️ PUSH NOTIFICATION DISPATCHER
 * Sends a real-time web push alert to a user.
 */
function trigger_push_alert($pdo, $user_id, $title, $body, $order_id) {
    $push_file = __DIR__ . '/PushService.php';
    if (file_exists($push_file)) {
        require_once $push_file;
        try {
            $push = new PushService($pdo);
            $url = (defined('BASE_URL') ? BASE_URL : 'https://digitalmarketplacemm.com/') . "index.php?module=user&page=orders&view_chat=" . $order_id;
            $push->sendToUser($user_id, $title, $body, $url);
        } catch (Exception $e) {
            error_log("Push Alert Error: " . $e->getMessage());
        } 
    }
}
?>
