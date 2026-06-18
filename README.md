# DigitalMM Marketplace

DigitalMM is a PHP-based digital marketplace for products such as games, software, passes, and other instant-delivery items.

## What Changed

- Product listings now render as a denser 2-up mobile and 4-up desktop grid in the main storefront areas.
- The app now supports a persisted dark/light theme using a cookie plus session sync.
- Session handling is hardened with safer cookie settings and login session regeneration.
- Home-page data queries use short-lived cache entries to reduce repeated database work.
- Product pages now emit SEO-friendly structured data and better share metadata.
- Shared UI tokens were added for cards, buttons, dividers, and theme-aware surfaces.

## Main Entry Points

- `index.php` routes requests and injects page metadata.
- `includes/config.php` loads environment values, database access, and session state.
- `includes/header.php` renders the shared shell, theme controls, and SEO tags.
- `includes/footer.php` renders the shared footer and loads client-side behavior.

## Core Modules

- `modules/home/index.php` home dashboard and featured product sections.
- `modules/shop/category.php` category browsing, filters, and pagination.
- `modules/shop/search.php` keyword search results.
- `modules/shop/product.php` product detail, wishlist, reviews, share flow, and structured data.
- `modules/auth/login.php` login flow and Google OAuth handling.
- `modules/user/dashboard.php` account overview.

## Theme and State

- Theme preference is stored in the `site_theme` cookie.
- Session state mirrors the cookie value so server-rendered pages stay consistent.
- Login regenerates the PHP session ID after success.

## Performance Notes

- Several expensive homepage queries are cached briefly with the existing matrix cache helper.
- Product grids were simplified to reduce layout noise and improve scan speed on mobile.

## Deployment

This project is intended to run on the `digitalmarketplacemm.com` deployment target with:

- PHP 8+
- MySQL
- Composer dependencies installed
- `.env` values configured for database, app URL, Telegram, OAuth, and push notifications

## Verification

Recommended checks after deployment:

1. Home page loads with fresh product cards.
2. Theme toggle persists after refresh.
3. Product share button copies or opens native share.
4. Product page metadata renders correctly for social previews.
5. Login sessions survive refresh and logout clears the user state.

