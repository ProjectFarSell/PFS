# Metro Surplus development catalog

The repository includes 238 unique products and their 640 x 640 images under `public/metro-catalog/`. These listings belong to **Metro Surplus Co.**, owned by `seller@farsell.test`, and use the normal seller dashboard and product editing forms. Names, prices, and stock are demo values.

For a new local checkout, install dependencies, configure your own `.env`, then run:

```bash
php artisan migrate
php artisan db:seed
```

The normal development seeder creates the demo seller/shop before importing this catalog. For an existing development shop with the same owner:

```bash
php artisan migrate
php artisan db:seed --class=MetroSurplusProductsSeeder
```

For Docker, prefix Artisan commands with `docker compose exec app`. Append `--env=local` only when intentionally using a standalone `.env.local` installation.

The catalog adds missing listings and leaves matching listings, seller edits, stock, publication status, and existing products unchanged. It refuses staging and production, never moves products between shops, and never resets a database. It needs no source-machine folder or R2 credentials. Existing cloud images continue using their configured bucket URL.

Primary categories remain available to seller forms. Additional category memberships let keyboards appear under Gadgets, Keyboards, Custom Keyboards, and Gaming & Audio, and controllers/other gadgets appear under Gadgets and Gaming & Audio. Figurines use Figures and Models; desk items use Desk Accessories. Photos retain their proportions with neutral padding.

No database dump, account records, provider secrets, or local test snapshots are included.

This package integrates with the current curated category slugs `figures-models`, `custom-keyboards`, `gaming-audio`, and `desk-accessories`. Additional primary categories preserve the imported clothing, beauty, sports, and gadget groups.
