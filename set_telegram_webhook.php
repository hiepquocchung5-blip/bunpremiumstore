<?php
// set_telegram_webhook.php
// Matrix Sector Tool: Run this once to register your bot with Telegram

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Only allow super admins to run this
if (!is_logged_in() || ($_SESSION['user_role'] ?? '') !== 'super_admin') {
    die("Access Denied: Administrative Clearance Required.");
}

$webhook_url = BASE_URL . "api/telegram_webhook.php";
$api_url = "https://api.telegram.org/bot" . TG_BOT_TOKEN . "/setWebhook?url=" . urlencode($webhook_url);

echo "<body style='background:#0f172a; color:#00f0ff; font-family:monospace; padding:40px;'>";
echo "<h1>🛰 Telegram Webhook Protocol</h1>";
echo "<hr style='border:1px solid #1e293b; margin:20px 0;'>";

echo "<p>Initializing Uplink for:<br><code style='color:#fff;'>$webhook_url</code></p>";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err) {
    echo "<p style='color:#ef4444;'>❌ Uplink Failed: $err</p>";
} else {
    $res = json_decode($response, true);
    if ($res['ok']) {
        echo "<p style='color:#22c55e;'>✅ Uplink Established Successfully!</p>";
        echo "<pre style='background:#000; padding:20px; border-radius:10px; color:#fff;'>" . print_r($res, true) . "</pre>";
    } else {
        echo "<p style='color:#ef4444;'>❌ Matrix Rejected Request:</p>";
        echo "<pre style='background:#000; padding:20px; border-radius:10px; color:#fff;'>" . print_r($res, true) . "</pre>";
    }
}

echo "<hr style='border:1px solid #1e293b; margin:20px 0;'>";
echo "<a href='index.php' style='color:#fff; text-decoration:underline;'>Return to Hub</a>";
echo "</body>";
?>