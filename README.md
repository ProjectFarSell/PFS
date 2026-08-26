# FarSell

FarSell is a Doorzo/Shopee-inspired marketplace starter. It is a Laravel, Blade, Alpine.js, Tailwind CSS, and MySQL application with a guest cart, role-based accounts, rider registration, catalogue browsing, checkout, and a small versioned catalogue API for the future mobile app.

## Included now

- Responsive Doorzo-style storefront with product, shop, search, cart, and checkout views.
- Guest browsing and guest checkout. The guest cart and temporary order access are stored in the browser session.
- Buyer, seller, rider, and admin roles.
- Rider application and rider-profile starter, with pending/approved workflow states.
- MySQL migrations and development seed data.
- Sail-compatible Docker Compose setup.
- Public mobile-ready endpoints: `GET /api/v1/health` and `GET /api/v1/catalog`.

## Local setup with Docker (recommended on Windows)

1. Install and start Docker Desktop, then confirm it is running.
2. In PowerShell, open this project folder and create the local environment file:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Build and start FarSell:

   ```powershell
   docker compose up --build
   ```

   The first run downloads PHP, MySQL, Composer packages, and Node packages. Leave this terminal running.

4. Open `http://localhost` in a browser. Demo credentials use the password `password`:

   - buyer@farsell.test
   - seller@farsell.test
   - rider@farsell.test
   - admin@farsell.test

5. In a second terminal, run the test suite:

   ```powershell
   docker compose exec app php artisan test
   ```

6. To reset development data:

   ```powershell
   docker compose exec app php artisan migrate:fresh --seed
   ```

## Important scope notes

- Payment is intentionally a COD / gateway placeholder. Do not add real payment keys until the payment provider, legal requirements, webhooks, retries, and reconciliation flow are selected.
- Rider licence numbers should be treated as sensitive data. Before beta, move any identity-document handling to protected storage and add staff authorization, retention, and audit rules.
- The initial mobile path is a separate Capacitor client that consumes the versioned Laravel API. Follow [MOBILE_SETUP.md](MOBILE_SETUP.md) after the web flow is stable.

## Project map

- `app/Models` - commerce and rider domain models.
- `app/Http/Controllers` - web and versioned API controllers.
- `database/migrations` - MySQL schema.
- `resources/views` - Blade storefront and rider UI.
- `routes/web.php` - browser routes.
- `routes/api.php` - mobile API contract.
- `WORKFLOW.md` - 12-week, three-person delivery plan.
