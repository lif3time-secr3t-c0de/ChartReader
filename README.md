# ChartReader.io

AI-powered trading chart analysis platform with complete user panel, SQLite auth, favorites, profile settings, Stripe billing, and admin analytics.

## Core Features
- Secure user authentication (register/login/logout/session)
- Chart upload and Gemini-powered analysis workflow
- User dashboard panels: Analyze, History, Favorites, Settings
- Profile update + password change flows
- Stripe checkout + billing portal + webhook subscription sync
- Admin panel with stats, recent analyses, users, and payments
- PWA service worker + manifest support

## Tech
- Backend: PHP 8.2+
- Database: SQLite
- Frontend: HTML + Tailwind CDN + Vanilla JS
- Integrations: Gemini API, Stripe

## Local Setup
1. Install dependencies
   ```bash
   composer install
   ```
2. Configure env
   ```bash
   copy .env.example .env
   ```
3. Initialize database
   ```bash
   php init_db.php
   ```
4. Start local server
   ```bash
   composer serve
   ```

## Main API Endpoints
- `GET /api/csrf.php`
- `POST /api/auth.php?action=register`
- `POST /api/auth.php?action=login`
- `POST /api/auth.php?action=logout`
- `GET /api/auth.php?action=me`
- `POST /api/analyze.php` (multipart `chart`)
- `GET /api/analyze.php?limit=200`
- `GET /api/favorites.php`
- `POST /api/favorites.php` (`toggle`/`add`/`remove`)
- `GET /api/profile.php`
- `POST /api/profile.php` (`update-profile`, `change-password`)
- `POST /api/stripe.php?action=create-checkout`
- `POST /api/stripe.php?action=portal`
- `POST /api/stripe.php?action=webhook`
- `GET /api/admin.php?action=stats|users|payments`

## Commands
- Run tests:
  ```bash
  composer test
  ```
- Lint all app PHP files:
  ```bash
  rg --files -g "*.php" -g "!vendor/**" | % { php -l $_ }
  ```

## Environment Variables
See `.env.example` for full list.
Required for production-grade behavior:
- `GEMINI_API_KEY`
- `STRIPE_SECRET_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `PREMIUM_PRICE_ID`
- `JWT_SECRET`

## Notes
- Uploaded images are stored under `public/uploads/`.
- SQLite database is `database.sqlite` by default.
- Local routing to `api/` is handled by `router.php`.
