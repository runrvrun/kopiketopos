# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

"Kopiketo POS" — a mobile-first point-of-sale web app for a multi-store coffee business, built on **Laravel 7** (PHP ^7.2.5), MySQL, server-rendered Blade views with Bootstrap 4 / jQuery loaded from CDNs. The README is the stock Laravel README and contains no project-specific info.

## Commands

```bash
composer install
cp .env.example .env && php artisan key:generate   # then set DB_* (MySQL)
php artisan serve                                   # dev server

vendor/bin/phpunit                                  # all tests
vendor/bin/phpunit --filter ExampleTest             # single test
vendor/bin/phpunit tests/Feature/ExampleTest.php    # single file

npm install && npm run dev                          # Laravel Mix (resources/js, resources/sass -> public/)
```

Only the default example tests exist. Style follows StyleCI's `laravel` preset (`.styleci.yml`); there is no local linter configured.

## Architecture

- **Two route surfaces**:
  - `routes/web.php` — session-auth (`Auth::routes()`, registration disabled) wrapping everything in the `auth` middleware. This is the real app: dashboard, products, transactions.
  - `routes/api.php` — Laravel Passport (`auth:api`) endpoints for login/signup and `product/list`, `product/add`. Note `UserController` is referenced but does not exist.
- **Models live directly in `app/`** (Laravel 7 style, no `app/Models`): `Product`, `Transaction`, `Product_transaction` (pivot-like model on table `product_transaction`), `Store`, `User`. Models use `$guarded = ['id']`, so controllers pass `$request->all()` straight into `create`/`update`.
- **Schema is not fully in migrations.** Only `users` and `failed_jobs` migrations exist; the `products`, `transactions`, `product_transaction`, `stores` tables and the `users.role` / `users.store_id` columns were created outside the repo. Infer columns from controllers/views (e.g. transactions: `key`, `user_id`, `store_id`, `customer`, `total_item`, `total_price`, `discount`, `downpayment`, `status`).
- **Multi-store scoping by role**: `Auth::user()->role == 'admin'` sees all stores; other users are filtered by `Auth::user()->store_id`. This pattern is repeated inline in `TransactionController` and `DashboardController` — keep it consistent when adding queries.
- **Transaction flow** (`TransactionController`):
  - `status` is `NULL` while ongoing and `'Paid'` once paid; dashboards sum only `Paid` rows.
  - `key` is generated as `Ymd` + 5-digit daily sequence.
  - Line items arrive as `quant[product_id] => amount`; on update, all `product_transaction` rows for the transaction are deleted and re-inserted (only amounts > 0).
  - Submitting with a `payment` field redirects to `transaction/payment/{id}`; otherwise to `transaction/ongoing`.
- **Excel export**: `app/Exports/TransactionExport.php` (maatwebsite/excel) takes a `YYYYMM` int, used by `transaction/generatedownloadhistory`. `stubs/` holds the package's export/import stubs.
- **Product images**: uploaded via intervention/image, resized/cropped to 300×300 and written to `public/uploads/product/` (committed to git); the DB stores the relative path.
- **Views**: `resources/views/layouts/app.blade.php` is the single layout (CDN assets + `public/css/style.css`, cache-busted via `filemtime`). UI is composed from class-based Blade components in `app/View/Components` (`<x-backnav>`, `<x-bnav>` bottom nav, `<x-product>`, `<x-transaction>`, …) with templates in `resources/views/components/`. Feedback messages use `Session::flash('message'|'alert-class')`.
- `EmployeeController` is a resource route with an empty stub controller (and references a nonexistent `App\Employee`).
