# 🛡️ DigitalMarketplaceMM | API Infrastructure Guide

This guide explains how the platform's high-performance API ecosystem works, including caching, security, and the **Mr. Scotty AI** integration.

## ⚡️ Core Architecture: "Hardened Matrix Lite Speed"

The API follows a 3-stage execution pipeline to ensure 0-1ms latency for users:

1.  **Stage 1: Matrix Cache (Memory-First)**
    - Intercepts requests before hitting the database.
    - Uses file-based JSON caching (`uploads/cache/`) with unique md5 signatures.
    - Handles **ETags** to return `304 Not Modified`, saving user bandwidth and battery.

2.  **Stage 2: Optimized DB Fetch**
    - Only executes if cache is missing or expired.
    - Uses 100% **PDO Prepared Statements** to prevent SQL Injection.
    - Includes automatic cache invalidation (`invalidate_user_cache`) whenever data changes.

3.  **Stage 3: Secure Delivery**
    - Strict JSON output with CORS protection forauthorized nodes.
    - Hardened error handling (Raw errors are logged to server, users see a nice "Syncing..." message).

---

## 🤖 AI Operative: Mr. Scotty

Mr. Scotty is a high-level LLM (Large Language Model) assistant integrated directly into the Matrix.

### How he works:
- **Engine:** Powered by `Google Gemini 1.5 Flash`.
- **Personality:** Helpful, high-tech, and "nice". Uses Matrix terminology.
- **Auto-Reply Trigger:** Intercepts normal transmissions on Telegram and Web Chat.
- **Context Injection:** He is aware of the user's `@username` and the specific **Product Node** (item name) being discussed.

### Implementation:
- Located in `includes/functions.php` -> `get_ai_response()`.
- Uses a fallback rule-engine if the LLM node is under heavy load.

---

## 🛰️ API Endpoints

| Endpoint | Purpose | Tech Stack |
| :--- | :--- | :--- |
| `/api/notifications.php` | Real-time system alerts & counts | Matrix Cache + ETag |
| `/api/coupon.php` | Node signature (coupon) validation | MD5 Signature Cache |
| `/api/push_subscribe.php` | Browser-to-Matrix uplink sync | CORS Matrix Auth |
| `/api/telegram_webhook.php` | Admin command center & AI Bot | Webhook Sync |

---

## 🛠 Troubleshooting for Operatives

- **500 Error:** Check `error_log` on the server. Likely a missing DB column or invalid API Key.
- **AI Not Responding:** Ensure `GEMINI_API_KEY` is correctly defined in `includes/config.php`.
- **SW Response Error:** Ensure you are using **Matrix SW v2.7+** which fixes the response cloning conflict.

---
*Document Version: 1.0 - Matrix Intelligence Sector*