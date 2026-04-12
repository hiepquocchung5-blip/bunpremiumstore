DigitalMarketplaceMM - Telegram Bot Integration Guide

This guide explains how to set up the Telegram Webhook so your bot can instantly receive and process commands (like /approve, /stats) directly on your api.digitalmarketplacemm.com subdomain.

Step 1: Create Your Bot & Get the Token

Open Telegram and search for @BotFather.

Send the command /newbot and follow the prompts to name your bot (e.g., DMMM_AdminBot).

BotFather will give you an HTTP API Token (e.g., 123456789:ABCDefghIJKLmnopQRSTuvwxYZ).

Copy this token.

Step 2: Configure Your Matrix

Open your includes/functions.php file.

Paste your bot token into the TG_BOT_TOKEN constant:

define('TG_BOT_TOKEN', '123456789:ABCDefghIJKLmnopQRSTuvwxYZ'); 


Step 3: Register the Webhook

Telegram needs to know where to send incoming messages. You must manually register your API endpoint with Telegram.

Open your web browser and paste the following URL, replacing YOUR_BOT_TOKEN with your actual token:

https://api.telegram.org/botYOUR_BOT_TOKEN/setWebhook?url=https://api.digitalmarketplacemm.com/telegram_webhook.php

Expected Output in Browser:

{
  "ok": true,
  "result": true,
  "description": "Webhook was set"
}


Note: Telegram requires HTTPS for webhooks. Ensure api.digitalmarketplacemm.com has a valid SSL certificate.

Step 4: Get Your Admin Chat ID

Open Telegram and search for the bot you just created.

Click Start or send the command /start.

The bot will reply with your Node ID (Chat ID).

Copy this ID and paste it into includes/functions.php:

define('TG_ADMIN_CHAT_ID', 'YOUR_CHAT_ID_HERE'); 


Available Admin Commands

Once configured, you can control the store directly from Telegram:

/stats - View live revenue, pending order count, and dynamic active user nodes.

/pending - Retrieve a formatted list of the latest 10 orders awaiting manual verification.

/approve [ID] - Instantly authorize an order (e.g., /approve 1045). This shifts the order status to Active.

/reject [ID] - Instantly terminate an order request (e.g., /reject 1045).