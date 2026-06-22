# DigitalMM — More Update Ideas (Vol. 2)

> All ideas are specific to existing code. No generic suggestions.
> Effort scale: S = hours · M = 1–2 days · L = 3–5 days

---

## 💳 Checkout & Payment

### 1. Wishlist Item Count Badges in Header & Mobile Nav `S`
**File:** `includes/header.php` + `includes/footer.php`
- Retrieve wishlist count from `wishlist` table or session cache
- Render a vibrant notification badge (e.g. red indicator dot/number) next to the "Wishlist" link in desktop navigation and mobile bottom bar
- Keeps user engaged and reminds them of products they intended to purchase

### 2. Order Cooldown Countdown Timer `S`
**File:** `modules/shop/checkout.php`
- The 10-minute spam cooldown already works server-side but there's no live feedback
- Add a JS countdown that ticks down from `<?php echo $time_remaining; ?>` seconds
- Show minutes:seconds remaining instead of just blocking the button silently

### 3. Checkout — Show Product Stock Warning `S`
**File:** `modules/shop/checkout.php`
- For `unique` delivery products, query remaining keys before showing the buy button
- If stock = 0: show "Out of Stock — notify me" instead of the payment form
- If stock ≤ 3: show a yellow "Only X left!" badge near the product title

### 4. One-Click Reorder `S`
**File:** `modules/user/orders.php`
- On each completed order in the history, add a "Reorder" button
- Redirects to `checkout.php?id=PRODUCT_ID` pre-filled with the same product
- Saves users from navigating back through the catalog

---

## 👤 User Account

### 5. Avatar / Profile Picture Upload `M`
**File:** `modules/user/profile.php`
- Add an avatar upload field (stores to `uploads/avatars/`)
- Display the avatar in the user dashboard header and order chat bubbles
- Fallback: first letter of username as a colored circle (already using `fas fa-user` icon)

### 6. User Notification Bell — In-App Feed `M`
**File:** new `modules/user/notifications.php`
- A page listing all notifications received (push notifications are sent but there's no read history)
- Add a `user_notifications` table: `(id, user_id, title, body, url, is_read, created_at)`
- Show an unread count badge on the bell icon in the header nav

### 7. Email — Password Reset Flow `M`
**File:** `modules/auth/` + new `reset.php`
- PHPMailer is already installed and used in `verify.php`
- Add a "Forgot Password?" link on login page → sends reset token via email
- Reset page validates token (15-minute expiry) and sets new password

### 8. User Dashboard — Spending Summary Card `S`
**File:** `modules/user/dashboard.php`
- Add a card showing: Total Spent (all time), This Month, Saved via Discounts
- Query: `SELECT SUM(total_price_paid) FROM orders WHERE user_id = ? AND status = 'active'`
- Show "You saved Xks this month with your agent discount" for motivation

### 9. Profile — Preferred Payment Method `S`
**File:** `modules/user/profile.php`
- Let users save a preferred payment method (KBZPay / WavePay)
- Pre-selects it on checkout automatically
- Store as `preferred_payment_id` on the `users` table

---

## 🤖 AI Chatbot (MatrixLocalAI)

### 10. Admin — AI Training UI `M`
**File:** `admin/console.php` + `api/ai_training.json`
- The AI runs on `ai_training.json` but editing JSON manually is error-prone
- Build a simple CRUD UI in admin to add/edit/delete training pairs (question → answer)
- Form: `<input name="pattern">` + `<textarea name="response">` → writes to the JSON file

### 11. AI — Myanmar Language Synonym Expansion `S`
**File:** `includes/MatrixLocalAI.php`
- The `$synonyms` array already maps Myanmar words (e.g., `'ဈေး' => 'price'`)
- Add more common Myanmar customer phrases: payment problems, delivery questions, refund requests
- Also add English typo corrections: `'netflex' => 'netflix'`, `'spotfy' => 'spotify'`

### 12. AI — Escalation to Admin Alert `M`
**File:** `includes/MatrixLocalAI.php` + `MatrixActionHub.php`
- When AI confidence score falls below a threshold (low cosine similarity), flag the message
- Trigger a Telegram notification to admin: "Customer [X] asked an unanswered question: [text]"
- Helps identify gaps in training data from real customer questions

---

## 🏪 Shop Experience

### 13. Category Page — Sort Bar `S`
**File:** `modules/shop/category.php`
- Already has pagination and region filtering
- Add sort: Newest / Price Low-High / Price High-Low / Most Popular
- URL param: `&sort=price_asc` — append to existing query

### 14. Product Page — Image Lightbox `S`
**File:** `modules/shop/product.php`
- When user taps the product image, open a full-screen overlay/lightbox
- Pure CSS + JS: no library needed — just a `<dialog>` element with the image inside
- Add pinch-zoom support on mobile with `touch-action: pinch-zoom`

### 15. Search — Recent & Popular Searches `S`
**File:** `modules/shop/search.php`
- Store last 5 searches in `localStorage` and show them as chips below the search bar
- Also fetch the top 5 most-searched terms from a `search_log` table (new, lightweight)
- Display "Trending Searches" when the search box is focused but empty

### 16. Wishlist — Share List Link `S`
**File:** `modules/user/wishlist.php`
- Generate a public shareable link: `?module=shop&page=wishlist&share=TOKEN`
- Token stored in `users.wishlist_token` (nullable) — generated on demand
- Public view is read-only, shows product names and prices only

---

## 👑 Agent / Pass System

### 17. Pass — Expiry Warning Banner `S`
**File:** `modules/user/agent.php`
- Check `pass_expires_at` from the `user_passes` table
- If expiry < 7 days away: show a yellow banner "Your agent pass expires in X days — renew now"
- Links directly to the pass purchase section

### 18. Agent Leaderboard `M`
**File:** new `modules/user/leaderboard.php`
- Show top 10 agents ranked by total orders placed this month (anonymized — first letter + ***)
- Motivates agents to sell more to climb the board
- Query: `SELECT referred_by, COUNT(*) as sales FROM orders WHERE ... GROUP BY referred_by ORDER BY sales DESC LIMIT 10`

### 19. Referral Conversion Detail Log `M`
**File:** `modules/user/referrals.php`
- Show list of purchased items from referred users (anonymized first character + ***)
- Displays what products they successfully purchased to help referrers track which products convert best
- Query: `SELECT u.username, o.created_at, p.name FROM users u JOIN orders o ON o.user_id = u.id JOIN products p ON o.product_id = p.id WHERE u.referred_by = ? AND o.status = 'active'`

---

## 🛠️ Admin Panel

### 20. Admin Orders — Bulk Status Update `M`
**File:** `admin/orders.php`
- Add checkboxes on each order row + a "Mark Selected as Active" bulk action
- Useful when approving multiple manual transfers at once
- POST array of IDs → `UPDATE orders SET status='active' WHERE id IN (?)`

### 21. Admin Users — Ban / Suspend Account `S`
**File:** `admin/users.php` + `admin/user_detail.php`
- Add `is_banned TINYINT(1)` to `users` table
- Toggle button in `user_detail.php` — banned users see a "Your account is suspended" page
- Check `is_banned` in the login flow and redirect to an error page

### 22. Admin P&L — Export to CSV `S`
**File:** `admin/pandl.php`
- Already has temporal scoping (7d / 30d / lifetime)
- Add an "Export CSV" button that triggers a download of the current filtered data
- PHP `header('Content-Type: text/csv')` + loop through the same query results

### 23. Admin Dashboard — Revenue vs Expenses Chart `M`
**File:** `admin/dashboard.php`
- Chart.js is already loaded on the dashboard
- Add a second dataset to the existing 7-day chart: expenses per day alongside revenue
- Shows profit margin visually — green when revenue > expenses, red when not

### 24. Admin — Product Duplicate Button `S`
**File:** `admin/products.php`
- Add a "Duplicate" action next to Edit/Delete on each product row
- Copies all fields (name gets " (Copy)" suffix, slug gets "-copy") → INSERT
- Saves time when creating variations of the same product

### 25. Admin Banners — Preview Before Save `S`
**File:** `admin/banner_edit.php`
- Show a live preview of the banner image after file selection (`<input type="file">` onchange)
- Set `<img id="preview">` src via `FileReader.readAsDataURL()`
- Prevents uploading wrong images accidentally

---

## 📱 Mobile / PWA

### 26. Pull-to-Refresh on Home Page `S`
**File:** `modules/home/index.php`
- Detect a downward drag past the top of the page on mobile
- Show a spinner, then reload the page
- Pure JS: track `touchstart` Y → `touchend` Y delta > 80px

### 27. Bottom Nav — Cart / Wishlist Count Badges `S`
**File:** `includes/footer.php` (bottom nav)
- Show a numeric badge on the Wishlist icon with the count of saved items
- Query count from `wishlist` table if logged in, store in session to avoid per-request queries

### 28. App-Like Page Transitions `S`
**File:** `assets/css/style.css`
- The `@view-transition { navigation: auto; }` is already in the CSS
- Add `view-transition-name: main-content` to the main content area
- Gives smooth slide/fade between pages in Chrome without any JS framework

### 29. Haptic Feedback on Buy Button (Mobile) `S`
**File:** `modules/shop/product.php`
- `navigator.vibrate(50)` on the Buy Now button click
- Very short pulse (50ms) — feels native on Android
- Wrap in `if ('vibrate' in navigator)` to skip on iOS gracefully

---

## 🔐 Security & Performance

### 30. Rate Limit Failed Logins `M`
**File:** `modules/auth/login.php`
- After 5 failed attempts from one IP in 10 minutes: lock for 15 minutes
- Store attempts in a `login_attempts` table: `(ip, attempts, last_attempt_at)`
- Show "Too many attempts. Try again in X minutes." message

### 31. Admin 2FA via Telegram OTP `M`
**File:** `admin/login.php` + `includes/telegram_webhook.php`
- After correct password, send a 6-digit OTP to the admin's Telegram
- Admin enters OTP on a second screen within 2 minutes to complete login
- Uses existing Telegram bot — just `sendMessage` to a configured `ADMIN_TELEGRAM_ID`

### 32. Image Lazy Load with Blur Placeholder `S`
**File:** `assets/css/style.css` + product card/home templates
- Add a tiny 10px blurred base64 placeholder as the `src`, swap to real image on load
- Uses `IntersectionObserver` — images only load when entering the viewport
- Cuts initial page load significantly for the 18-product New Arrivals grid

---

*Generated: 2026-06-22 · Vol. 2 · BunPremiumStore / DigitalMM*
