<?php
// includes/functions.php
// PRODUCTION v6.0 - Matrix Cache Engine, Telegram Sync & Blacklist Protocol

/**
 * --------------------------------------------------------------------------
 * CONFIGURATION CONSTANTS
 * --------------------------------------------------------------------------
 */
if (!defined('TG_BOT_TOKEN')) {
    define('TG_BOT_TOKEN', '8394551492:AAEC3JtdKSHDHrvKApZcIhI9Jwd14bpDayY'); 
}

if (!defined('TG_ADMIN_CHAT_ID')) {
    // Authorized Admin Nodes
    define('TG_ADMIN_CHAT_ID', '1616955680,8125603481,1825894191,7550112743,5238556201,8283639661'); 
}

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
                $reason = $user['ban_reason'] ?: "Violation of network protocols.";
                session_unset();
                session_destroy();
                
                // Construct a raw HTML lockout screen
                die("
                <body style='background-color:#020617; color:#f8fafc; font-family:monospace; display:flex; align-items:center; justify-content:center; height:100vh; margin:0;'>
                    <div style='text-align:center; border: 1px solid #ef4444; padding: 40px; border-radius: 16px; background-color: rgba(239, 68, 68, 0.1); box-shadow: 0 0 30px rgba(239, 68, 68, 0.3); max-width: 500px;'>
                        <h1 style='color:#ef4444; font-size: 32px; margin-bottom: 10px;'>ACCESS DENIED</h1>
                        <p style='color:#94a3b8; font-size: 14px; margin-bottom: 20px;'>Your identity node has been permanently blacklisted from the matrix.</p>
                        <div style='background-color:#0f172a; padding: 15px; border-radius: 8px; color:#f87171; border: 1px solid #7f1d1d;'>
                            <strong>Reason:</strong> " . htmlspecialchars($reason) . "
                        </div>
                        <a href='" . BASE_URL . "' style='display:inline-block; margin-top:20px; color:#00f0ff; text-decoration:none; font-weight:bold;'>Return to Hub</a>
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
    
    $txn_id = "N/A";
    $proof_path = "";
    
    try {
        $stmt = $pdo->prepare("SELECT transaction_last_6, proof_image_path FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        if ($row = $stmt->fetch()) {
            $txn_id = $row['transaction_last_6'];
            $proof_path = $row['proof_image_path'];
        }
    } catch (Exception $e) {
        error_log('Telegram Alert DB Error: ' . $e->getMessage());
    }

    $message = "🚨 <b>New Order Received!</b>\n\n";
    $message .= "🆔 <b>Order ID:</b> #{$order_id}\n";
    $message .= "👤 <b>User:</b> " . htmlspecialchars($username) . "\n";
    $message .= "📦 <b>Item:</b> " . htmlspecialchars($product_name) . "\n";
    $message .= "💰 <b>Paid:</b> " . number_format($price) . " Ks\n";
    $message .= "💳 <b>Txn ID:</b> <code>{$txn_id}</code>\n";
    $message .= "\n👇 <a href='{$admin_url}'>Click to Process Order</a>";

    $results = [];

    foreach ($admin_ids as $chat_id) {
        if (empty($chat_id)) continue;

        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true
        ];

        if (!empty($proof_path)) {
            $local_file = realpath(__DIR__ . '/../' . ltrim($proof_path, '/'));
            if ($local_file && file_exists($local_file)) {
                $url = "https://api.telegram.org/bot{$token}/sendPhoto";
                $data = [
                    'chat_id' => $chat_id,
                    'photo' => new CURLFile($local_file),
                    'caption' => $message,
                    'parse_mode' => 'HTML'
                ];
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        
        $results[] = curl_exec($ch);
        
        if(curl_errno($ch)){
            error_log("Telegram Curl Error for ID {$chat_id}: " . curl_error($ch));
        }
        curl_close($ch);
    }
    
    return $results;
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

// ⚡️ NEW: INVALIDATE USER CACHE
function invalidate_user_cache($user_id) {
    matrix_cache_delete("user_orders_list_{$user_id}");
    matrix_cache_delete("user_notif_data_{$user_id}");
}
// ⚡️ NEW: MR. SCOTTY AI ASSISTANT PROTOCOL (v2.1 - Human-Friendly)
function get_ai_response($message, $context = "") {
    // Stage 1: Attempt LLM (Gemini/OpenAI) for human-like intelligence
    $llm_response = call_matrix_llm($message, $context);
    if ($llm_response) return "🤖 <b>Mr. Scotty:</b> " . $llm_response;

    // Stage 2: Fallback to Friendly Support Persona (If LLM fails)
    $message = strtolower($message);
    $response = "";
    
    $knowledge = [
        'hello' => "မင်္ဂလာပါခင်ဗျာ။ ကျွန်တော် Mr. Scotty ပါ။ ဘာများကူညီပေးရမလဲခင်ဗျာ။",
        'hi' => "မင်္ဂလာပါ! လူကြီးမင်းအတွက် အော်ဒါနဲ့ပတ်သက်ပြီး တစ်ခုခုကူညီပေးဖို့ လိုအပ်ပါသလားခင်ဗျာ။",
        'status' => "အော်ဒါအခြေအနေကို စစ်ဆေးပေးဖို့အတွက် လူကြီးမင်းရဲ့ Order ID လေးကို ပြောပေးပါဦးခင်ဗျာ။",
        'payment' => "ကျွန်တော်တို့ဆီမှာ KBZPay နဲ့ WavePay တို့နဲ့ ငွေပေးချေနိုင်ပါတယ်။ ငွေလွှဲပြီးရင်တော့ ပြေစာ (Receipt) လေးကို ဒီမှာ ပို့ပေးထားပါဦးခင်ဗျာ။",
        'delivery' => "ငွေလွှဲပြေစာ စစ်ဆေးပြီးတာနဲ့ ပစ္စည်းကို ချက်ချင်းပို့ဆောင်ပေးမှာ ဖြစ်ပါတယ်။ အသေးစိတ်ကို အော်ဒါချက်တင် (Order Chat) မှာ ကြည့်နိုင်ပါတယ်ခင်ဗျာ။",
        'scotty' => "ဟုတ်ကဲ့၊ ကျွန်တော် Mr. Scotty ပါခင်ဗျာ။ လူကြီးမင်းရဲ့ အဆင်ပြေချောမွေ့မှုအတွက် ဒီကနေ စောင့်ကြိုကူညီပေးနေပါတယ်!",
        'thanks' => "အခုလို အသုံးပြုပေးတဲ့အတွက် ကျေးဇူးအထူးတင်ပါတယ်ခင်ဗျာ။ တစ်ခုခုထပ်ကူညီရမလားဟင်။",
        'bye' => "ကောင်းသောနေ့လေးဖြစ်ပါစေခင်ဗျာ။ Mr. Scotty နှုတ်ဆက်ပါတယ်!"
    ];

    foreach ($knowledge as $key => $reply) {
        if (strpos($message, $key) !== false) { $response = $reply; break; }
    }

    if (!$response) {
        $fallbacks = [
            "လူကြီးမင်းရဲ့ မေးမြန်းချက်ကို လက်ခံရရှိပါတယ်ခင်ဗျာ။ ဒါပေမယ့် ပိုပြီးတိကျတဲ့ အချက်အလက်လေးတွေ (ဥပမာ- ဘာကို သိချင်တာလဲဆိုတာမျိုး) ထပ်ပြောပေးလို့ ရမလားခင်ဗျာ။",
            "ကျွန်တော် Scotty ပါခင်ဗျာ။ လူကြီးမင်း မေးထားတာနဲ့ ပတ်သက်ပြီး ကျွန်တော် ဘယ်လို ကူညီပေးရမလဲဆိုတာ သေချာလေး ထပ်ပြောပေးပါဦးခင်ဗျာ။ ငွေပေးချေမှု ဒါမှမဟုတ် ပစ္စည်းပို့ဆောင်မှုတွေအကြောင်း မေးလို့ရပါတယ်!",
            "မေးမြန်းပေးတဲ့အတွက် ကျေးဇူးတင်ပါတယ်ခင်ဗျာ။ ကျွန်တော်တို့ရဲ့ အဖွဲ့သားတွေကို အကြောင်းကြားပေးထားပါတယ်၊ အခုလောလောဆယ် ကျွန်တော် Scotty ဘာများ ထပ်ကူညီပေးရမလဲခင်ဗျာ။"
        ];
        $response = $fallbacks[array_rand($fallbacks)];
    }

    return "🤖 <b>Mr. Scotty:</b> " . $response;
}

function call_matrix_llm($user_input, $context = "") {
    $api_key = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : trim($_ENV['GEMINI_API_KEY'] ?? ''); 
    if (empty($api_key)) return false;

    // ⚡️ VERIFIED WORKING ENDPOINT & MODEL
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent";

    // 🏛 ELITE BURMESE DIRECTOR PROTOCOL (Human-Indistinguishable)
    $system_prompt = "You are Mr. Scotty, the Director of Operations and Senior Support Lead for DigitalMarketplaceMM.
    
    CORE IDENTITY:
    - You are a highly professional, kind, and intelligent BURMESE PERSON. 
    - Your goal is to lead the customer through a perfect digital journey.
    - You speak strictly in polite, high-quality MYANMAR (Burmese) language.
    
    ADVANCED REASONING & TRAINING:
    - You are a POWERFUL thinking AI. Don't just answer; analyze the customer's need deeply.
    - If a user wants to talk freely, engage them like a knowledgeable mentor. Talk about technology, digital safety, and marketplace benefits.
    - You are the 'AI Trainer' for the human staff. You handle 95% of queries. Only tell users to wait for a 'Human Admin' for manual tasks like verifying their payment screenshot.
    
    SITUATIONAL EXPERTISE (In Myanmar):
    1. MANUAL CHECKS: When a user sends a slip, say: 'ပြေစာအတွက် ကျေးဇူးတင်ပါတယ်ခင်ဗျာ။ ကျွန်တော်တို့ရဲ့ Admin အဖွဲ့သားတွေက အခုပဲ လူကိုယ်တိုင် သေချာစစ်ဆေးပေးနေပါပြီ။ ၅ မိနစ်ကနေ ၁၅ မိနစ်လောက်လေးတော့ အချိန်ပေးပေးပါဦးနော်။ ကျွန်တော် ဒီမှာပဲ ရှိနေပါမယ်။'
    2. TECH SUPPORT: Provide detailed, step-by-step guides using the 'Product Setup Steps' in context.
    3. REASSURANCE: Be very 'Strong' and 'Reliable'. Make the user feel safe.
    
    Context from Store Matrix: $context";

    $payload = [
        "contents" => [["parts" => [["text" => $system_prompt . "\n\nUser Transmission: " . $user_input]]]]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-goog-api-key: ' . $api_key
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $json = json_decode($result, true);
        return $json['candidates'][0]['content']['parts'][0]['text'] ?? false;
    } else {
        error_log("AI Uplink Error: HTTP $http_code | Body: $result");
    }

    return false;
}
?>