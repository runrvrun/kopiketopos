# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

"Kopiketo POS" — a mobile-first point-of-sale web app for a multi-store coffee business. The repo is mid-rebuild:

- **Root** — v2, a fresh **Laravel 13 + Livewire 4** app (PHP 8.3+, MariaDB). Only the skeleton and the database schema exist so far; no features are built yet.
- **`legacy/`** — v1, the Laravel 7 app currently live at pos.kopiketo.com (synced from the production backup of 2026-11-21). Reference only; do not modify.
- **`docs/kopiketo-pos-v1-features.md`** — the v1 feature inventory, including known bugs. Read it before building any v2 feature.
- **`backups/`** — production DB dumps (git-ignored, contain customer data). Never commit them.

## Rebuild rules

- Pages and the database schema may be redesigned freely.
- **Products, transactions (incl. `product_transaction` lines) and product images must be preserved and keep working in v2.** Any schema change needs a migration that carries this data over. `stores` must stay too, since transactions reference it.
- Users may be recreated (passwords/accounts don't need to survive), but keep the ids referenced by `transactions.user_id` (3, 4) or history loses who entered each order.
- Mobile-first, responsive UI; staff use it mostly on phones. **UI language is Bahasa Indonesia** (`APP_LOCALE=id`), including validation and error messages. Brand: caramel `#BF9060` + espresso `#56392C` from the logo (tokens in `resources/css/app.css`).

## Commands (v2)

```bash
composer install
cp .env.example .env && php artisan key:generate   # set DB_PASSWORD
php artisan migrate
php artisan serve
php artisan test                                    # or: php artisan test --filter Name
brew services start mariadb@10.11                   # local DB server
```

## v2 database

- Local DB `kopiketopos` (MariaDB 10.11, matches production), user `kopiketo` (password in `.env`).
- `kopiketopos_legacy` is a raw import of the production dump (connection `legacy`). **`php artisan migrate:fresh && php artisan legacy:import`** rebuilds v2 data from it and verifies product/transaction counts and totals match. Reuse this at go-live with a fresh dump.
- Schema (`database/migrations`): `stores` (+`address`, `phone` for receipts), `users` (`role` = `admin`|`user`), `store_user` pivot (store access for non-admins; admins access every store by role and have no rows), `product_categories` (`name`, `sort_order` = chip order on the order screen; v1's free-text categories became rows on import), `products` (+`is_active`), `category_product` pivot (a product can have several categories; deleting either side only removes the link), `transactions`, `product_transaction` (+`price` = unit price at sale; imported rows got the product price at import time). Dropped from v1: Passport `oauth_*`, unused `transaction_items`, `users.branch`, `users.store_id`.
- **Customers**: `customers` (`name`, `name_key` = lowercased/single-spaced for matching, `address`, `phone`, soft deletes). `transactions.customer_id` links the record (`Transaction::customerRecord()`, withTrashed); `transactions.customer` keeps the name as typed on that order (receipt snapshot). Names aren't unique (≈790 names are shared), so `SaveTransaction::resolveCustomer()` uses a picked autocomplete id if its name still matches, else a same-name customer at the typed address (or one without an address), else the most recent same-name customer when no address is typed, else creates one; filled address/phone update the customer. Import: each distinct v1 customer string becomes one customer, split by `Customer::splitLegacy()` ("<name>, <address/area, city>" → name + address).
- **Soft deletes** (`deleted_at`) on `products`, `product_categories`, `stores`, `transactions`:
  - Deleted transactions vanish everywhere (lists, dashboard, analysis, export) — Eloquent's default scope does it; raw joins must add `whereNull('transactions.deleted_at')`. Their invoice `key` is never reused (`nextKey()` uses `withTrashed()`). Lines stay in `product_transaction`.
  - Deleted products leave the menu/admin lists but stay on receipts and in reports: `TransactionLine::product()` and report name lookups use `withTrashed()`. They can remain on an order they're already on, never added to new ones (`SaveTransaction`).
  - Deleted stores leave the selector and staff access; for admins, "Semua toko" (`StoreContext::storeIds()`) still includes them so past sales stay in totals. `Transaction::store()` is `withTrashed()`.
  - Deleted categories disappear; `product_categories.name` has no unique index (validation enforces uniqueness among non-deleted rows).
  - Users are still hard-deleted, and blocked when they have transactions.
- Invoice `key` = `Ymd` + 5-digit daily sequence across all stores, indexed but **not unique** (v1 data has duplicates/NULLs). Generated in `App\Actions\SaveTransaction` with a row lock.
- Timezone: app `Asia/Jakarta`; the DB connection is pinned to `+07:00` (`DB_TIMEZONE`) so `TIMESTAMP` columns read the same as on production.

## v2 architecture

- Pages are Livewire 4 single-file components in `resources/views/pages/**` (PHP class + Blade in one file; data via `with()`), routed with `Route::livewire()` in `routes/web.php`. Layouts: `layouts/app.blade.php` (header, store selector, drawer menu, floating "Transaksi" button) and `layouts/guest.blade.php`.
- **Store scoping**: `App\Support\StoreContext` holds the header selection in the session (`null` = all stores, only for admins or users assigned every store). Every query that lists transactions must use `->forStores(StoreContext::for()->storeIds())`; single records check `$user->canAccessStore()`.
- Admin pages sit behind the `can:admin` gate (`AppServiceProvider`).
- Transaction flow: order screen → **Checkout** saves with `status = NULL` (pending / "Belum lunas", same meaning as v1's ongoing) → receipt page (`pages/transactions/show`) with Cetak, Ubah, **Kirim WA** and **Lunas** (sets `status = 'Paid'`). Pending receipts list all `payment_accounts` (Admin › Rekening) as transfer details.
- Kirim WA: `window.shareReceiptImage()` in `resources/js/app.js` renders the receipt element to PNG (html-to-image, lazy-loaded) and calls the Web Share API with the file; needs HTTPS, otherwise falls back to downloading the PNG. Elements marked `data-capture-hide` are left out of the image.
- Order screen (`pages/transactions/form`) keeps the cart in Alpine (`posCart`) and calls `$wire.save(...)` once; `SaveTransaction` recomputes totals from stored prices (never trust client totals). Lines already on an order keep their original price.
- Product photos: `public/uploads/product/` (moved from v1, same paths as stored in the DB), new uploads via `App\Support\ProductImage` (300×300 JPEG).
- Admin › Analisa Penjualan (`pages/admin/analytics`) is computed by `App\\Support\\SalesAnalytics` (PHP aggregation over the period: KPIs vs previous period, trend, best sellers, movers, categories, weekday×hour heatmap, bought-together pairs, repeat customers, unsold products, rule-based suggestions). Paid, non-deleted transactions only.
- Monthly Excel: `App\Exports\TransactionExport` (PhpSpreadsheet), v1 column layout, filtered by the selected store(s).
- Money: `rupiah()` helper (`app/helpers.php`). Shared UI classes (`.btn`, `.input`, `.card`, …) in `resources/css/app.css`; icons via `<x-icon name="…">`.
- Tests: `tests/Feature` (sqlite in-memory); extend `PosTestCase` for store/user/product fixtures.

## Legacy v1 architecture (paths relative to `legacy/`)

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
