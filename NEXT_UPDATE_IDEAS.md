# DigitalMM — Next Update Ideas

> Organized by priority and effort. Each item is specific to the existing codebase.

---

## 🔥 High Priority (Quick Wins) [ALL COMPLETED]

### 1. Search Page — Autocomplete + Filters [COMPLETED]
**File:** `modules/shop/search.php`
- Live search suggestions as user types (fetch from `/api/` endpoint) [DONE]
- Filter bar: by category, price range, delivery type (universal / unique / form) [DONE]
- Sort options: Newest, Price Low→High, Best Selling, On Sale [DONE]
- "No results" state with suggested categories [DONE]

### 2. Product Page — Related Products Section [COMPLETED]
**File:** `modules/shop/product.php`
- Show 4 products from the same category below the main product [DONE]
- "You may also like" section using `ORDER BY RAND() LIMIT 4 WHERE category_id = ?` [DONE]
- Pulls from existing product card template (`product_card.php`) [DONE]

### 3. Category Page — Visual Header Card [COMPLETED]
**File:** `modules/shop/category.php`
- Add a hero banner at top showing category `image_url` + name + description [DONE]
- Currently jumps straight into the product grid with no visual context [DONE]

### 4. Admin Dashboard — Low Stock Alert [COMPLETED]
**File:** `admin/dashboard.php`
- Add a warning widget for `unique` delivery products where `stock_count < 3` [DONE]
- Query: `SELECT p.name, COUNT(pk.id) as stock FROM products p LEFT JOIN product_keys pk ON pk.product_id = p.id AND pk.is_sold = 0 WHERE p.delivery_type = 'unique' GROUP BY p.id HAVING stock < 3` [DONE]
- Show as a red badge on the dashboard stat cards [DONE]

### 5. Admin Products — Slug Column in Table [COMPLETED]
**File:** `admin/products.php`
- Show the `slug` column in the Active Catalog table (was the original bug context) [DONE]
- Add a copy-to-clipboard button on the slug cell [DONE]
- Useful for sharing/testing product URLs [DONE]

---

## 🧩 Medium Priority (Feature Additions) [ALL COMPLETED]

### 6. Wishlist → Cart Flow [COMPLETED]
**File:** `modules/user/wishlist.php`
- Add "Move to Cart / Buy Now" button directly on wishlist items [DONE]
- Currently wishlist just links to the product page — one extra click wasted [DONE]

### 7. Order Page — Download Invoice Button [COMPLETED]
**File:** `modules/user/invoice.php`
- A "Download PDF" button that triggers browser print/save on the invoice page [DONE]
- Simple: `window.print()` with a print-specific CSS (`@media print`) to hide nav/footer [DONE]

### 8. Coupon — User-Facing Input at Checkout [COMPLETED]
**File:** `modules/shop/checkout.php`
- The coupon system exists in admin (`admin/coupons.php`) and `includes/coupon.php` [DONE]
- But there's no visible coupon input on the checkout form for the customer [DONE]
- Add a collapsible "Have a coupon code?" field that calls the coupon validation API [DONE]

### 9. Reviews — Display on Product Page [COMPLETED]
**File:** `modules/shop/product.php`
- Admin `reviews.php` shows reviews exist in the DB [DONE]
- Add a reviews section at the bottom of the product page with star display + comment [DONE]
- Show average rating as a star bar near the price [DONE]

### 10. Admin Reports — Date Range Filter [COMPLETED]
**File:** `admin/reports.php`
- Currently shows all-time totals [DONE]
- Add a date range picker (start date / end date) to filter revenue & expenses [DONE]
- Export filtered data to CSV [DONE]

---

## ✨ Polish & UX [ALL COMPLETED]

### 11. Category Page — Skeleton Loading [COMPLETED]
**File:** `modules/shop/category.php` + CSS
- Show skeleton placeholder cards while products load on page transition [DONE]
- CSS-only: pulsing `bg-slate-800/50` divs with `animate-pulse` that disappear on load [DONE]

### 12. Home Page — Countdown Timer on Flash Sales [COMPLETED]
**File:** `modules/home/index.php`
- Add a live countdown clock (HH:MM:SS) next to the "Flash Sales" heading [DONE]
- Resets every 24 hours at midnight to create urgency [DONE]
- Pure JS: `setInterval` updating the display [DONE]

### 13. Product Card — Stock Badge [COMPLETED]
**File:** `modules/home/product_card.php`
- For `unique` delivery products, show a "X left" badge when stock is low (< 5) [DONE]
- Requires passing `stock_count` into the card data — add to home queries [DONE]

### 14. Mobile Bottom Nav — Active State Highlight [COMPLETED]
**File:** `includes/footer.php` (or wherever bottom nav lives)
- Highlight the active tab based on current `module` + `page` URL param [DONE]
- Currently likely static with no active indicator [DONE]

### 15. Admin Sidebar — Collapsed Mode on Mobile [COMPLETED]
**File:** `admin/includes/` layout files
- Sidebar auto-hides on screens < 1024px with a hamburger toggle [DONE]
- Saves screen space when managing orders on a phone [DONE]

---

## 🚀 Bigger Features (Next Major Version)

### 16. Bundle Products
- Let admin group multiple products into a "Bundle" at a discounted price
- New `product_bundles` table: `(bundle_id, product_id)`
- Checkout handles bundle delivery by fulfilling each child product

### 17. Referral Link Tracking
**File:** `modules/user/referrals.php`
- Generate a unique `?ref=CODE` link per user
- Track clicks and conversions in a `referral_clicks` table
- Show conversion rate on the user referral dashboard

### 18. Telegram Bot — Order Status Push
**File:** `includes/telegram_webhook.php` / `MatrixActionHub.php`
- When an order status changes to `active`, send a Telegram message to the buyer
- Bot already set up — just add a send-message call in the order fulfillment flow

### 19. Admin — Bulk Key Import (CSV Upload)
**File:** `admin/keys.php`
- Currently keys are pasted one-per-line in a textarea
- Add a CSV file upload that parses rows and batch-inserts into `product_keys`
- Useful for large key batches (100+)

### 20. PWA Offline Page
**File:** `sw.js` + new `offline.html`
- The service worker (`sw.js`) exists — add a proper branded offline fallback page
- Currently likely shows a browser default error when offline
- Cache the home page shell and show a friendly "You're offline" message

---

## 🗄️ Database / Backend

### 21. `products` Table — `is_active` Toggle
- Add `is_active TINYINT(1) DEFAULT 1` column
- Admin toggle in `admin/products.php` to hide a product without deleting it
- Filter out `is_active = 0` in all shop queries

### 22. Slug Uniqueness Enforcement [COMPLETED]
**File:** `admin/products.php` + `admin/product_edit.php`
- Current slug generation doesn't check for duplicates [DONE]
- Add a suffix (`-2`, `-3`) if slug already exists: `SELECT COUNT(*) FROM products WHERE slug LIKE ?` [DONE]

### 23. Image Optimization on Upload [COMPLETED]
**File:** `admin/products.php` image upload block
- After `move_uploaded_file`, run `imagecreatefromjpeg` + `imagejpeg($img, $path, 80)` to compress [DONE]
- Prevents admins uploading 5MB product images that slow the store [DONE]

---

*Generated: 2026-06-22 · BunPremiumStore / DigitalMM*
