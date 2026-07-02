# Repository Guidelines

## Project Structure & Module Organization

This is a PHP 8.1+ digital marketplace backed by MySQL. `index.php` is the storefront entry point and routes into `modules/`, with feature areas such as `modules/home`, `modules/shop`, `modules/auth`, and `modules/user`. Shared configuration, session setup, services, and layout live in `includes/`. Admin workflows are under `admin/`, JSON/webhook endpoints are under `api/`, and static CSS, JavaScript, and images are in `assets/`. Uploaded runtime files are stored in `uploads/`; avoid committing generated user content. Database schema and incremental changes are tracked in `db.sql` and `update*.sql`.

## Build, Test, and Development Commands

- `composer install`: install PHP dependencies into `vendor/`.
- `composer dump-autoload -o`: refresh optimized classmap autoloading after adding classes under `includes/`.
- `php -S 127.0.0.1:8000`: run a local PHP server from the repository root for quick manual checks.
- `php -l path/to/file.php`: lint a changed PHP file before committing.

Configure `.env` values for database access, app URL, Telegram, OAuth, mail, and push notification settings before running locally.

## Coding Style & Naming Conventions

Follow the existing procedural PHP style for pages and small endpoint scripts. Use four-space indentation in PHP, concise guard clauses, and clear snake_case variables where surrounding files use them. Keep service-style classes in `includes/` named with PascalCase, for example `MailService.php` or `PushService.php`. Preserve existing CSS class naming in `assets/css/style.css`, including shared UI tokens such as `liquid-glass-btn`.

## Testing Guidelines

There is no dedicated automated test suite in this repository. At minimum, lint every changed PHP file with `php -l`. For behavior changes, manually verify the affected storefront, admin, or API path in a browser or with a focused request. For database changes, apply the relevant `update*.sql` migration to a local copy and confirm pages using the changed tables still load.

## Commit & Pull Request Guidelines

Recent history uses Conventional Commit prefixes such as `feat:`, `fix:`, and `chore:`. Keep commit subjects imperative and specific, for example `fix: block checkout for out-of-stock products`. Pull requests should include a short summary, affected routes or modules, database migration notes, configuration changes, and screenshots for visible UI changes. Link related issues when available and call out manual verification performed.

## Security & Configuration Tips

Never commit `.env`, credentials, private keys, or uploaded customer files. Treat webhook, OAuth, mail, payment, and push notification settings as deployment secrets. Validate admin and API changes against authentication and authorization expectations before release.
