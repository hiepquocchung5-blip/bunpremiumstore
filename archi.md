# Architecture

## Overview

DigitalMM uses a classic PHP module architecture with a single router and shared layout shell.

Requests enter through `index.php`, which validates the route, enforces auth guards, sets page metadata, and then includes the target module.

## Layer Breakdown

### 1. Bootstrap Layer

- `includes/config.php` loads environment config, opens the database connection, and starts the session.
- `includes/functions.php` provides auth helpers, price formatting, cache utilities, and notification helpers.

### 2. Presentation Shell

- `includes/header.php` renders `<head>`, global navigation, theme state, search modal, and mobile navigation.
- `includes/footer.php` renders the shared footer, scroll-to-top control, and core JS bootstrapping.

### 3. Feature Modules

- `modules/home/*` handles landing-page product discovery.
- `modules/shop/*` handles category browsing, product detail, checkout, and search.
- `modules/auth/*` handles login, registration, verification, password reset, and logout.
- `modules/user/*` handles dashboard, orders, wishlist, referrals, profile, and invoices.
- `modules/info/*` serves static support/legal pages.

### 4. Data and Caching

- MySQL stores product, category, order, review, wishlist, and user data.
- Short-lived file cache entries are used for selected homepage collections to reduce query pressure.

### 5. Client State

- Theme preference is stored in the `site_theme` cookie and mirrored into the session.
- User session state stores identity, currency, and navigation resume URL.
- `assets/js/app.js` owns shared interactions like notifications, image fallbacks, and global sharing support.

## SEO Strategy

- Page-level metadata is generated in `index.php` before the shared header renders.
- Product pages emit `schema.org/Product` JSON-LD for better search and social preview support.

## Performance Strategy

- Keep route-specific queries in modules, but cache expensive global collections briefly.
- Prefer shared layout tokens and CSS classes over repeated inline styles.
- Use compact grid densities on mobile so users can scan more products without extra scrolling.

## Liquid Glass UI Reference

- Reuse `liquid-glass-btn` for product CTAs instead of one-off inline glass code.
- Apply `liquid-glass-buy`, `liquid-glass-like`, and `liquid-glass-share` as the semantic variants.
- In light mode, keep the primary CTA visually different from the like button by using the purple-pink gradient on Buy Now and a white surface for Like.
- Keep hover motion restrained: small lift, no large scale jumps, and preserve legibility in all themes.

## Security Strategy

- Route allow-list in `index.php`.
- Auth guard for protected pages.
- CSRF token generation for write actions.
- Session regeneration after successful login.
- Secure cookie flags and same-site defaults in `includes/config.php`.

## Deployment Notes

- Deploy behind HTTPS so the session cookie can stay secure.
- Ensure `.env` is populated with database, app URL, Google OAuth, Telegram, and push settings.
- Clear any generated cache files only if the catalog changes heavily or cache invalidation is required.
