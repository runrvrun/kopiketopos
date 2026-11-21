# Kopiketo POS v1 — Feature Inventory

Reference for the rebuild. Describes what the legacy app (`/legacy`, Laravel 7, live at pos.kopiketo.com since Aug 2020) actually does, including its quirks and bugs, so v2 can decide what to keep, fix, or drop.

Source of truth: production code backup and database dump taken 2026-11-21.

## 1. Overview

- Mobile-first web POS for Kopi Keto, a coffee business with multiple stores.
- Server-rendered Blade + Bootstrap 4 + jQuery (all from CDNs), Font Awesome icons.
- UI language is mixed: navigation in English, dashboard labels and receipt text in Indonesian.
- Currency is Indonesian Rupiah, whole numbers, formatted `Rp12.345` (dot thousands separator).
- Fixed top bar (logo or back button + title) and fixed bottom navigation, sized for phones.

## 2. Users, roles and stores

| Concept | v1 behaviour |
|---|---|
| Stores | `stores` table, managed directly in the DB (no UI). Current: **1 Pamulang**, **2 Senopati**. |
| Roles | `users.role`: `admin` or `user`. No UI to manage users or roles. |
| Store assignment | `users.store_id`. Admins are assigned store 1 but see all stores. |
| Scoping | `admin` sees all stores' transactions and totals; `user` sees only their own store. |
| Admin-only screens | Product management, Download History (both reached from the "More" menu). Enforced only by hiding menu links — the routes themselves are open to any logged-in user. |
| `users.branch` column | Exists in DB, unused by code. |

Current accounts: 3 (2 admins, 1 store user for Senopati).

## 3. Authentication

- Laravel UI session login (email + password), "remember me", password reset routes (email sending not configured on the server).
- Public registration **disabled** (`Auth::routes(['register' => false])`).
- Every page requires login. Logout from the "More" screen.
- A Passport token API exists (`/api/auth/login`, `signup`, `logout`, `user`, `/api/product/list`, `/api/product/add`) but nothing uses it; `UserController` it references does not exist. Treat as dead code.

## 4. Navigation

Bottom nav (all users): **Home** · **Ongoing** · **(+) New transaction** · **History** · **More (…)**

"More" screen: **Product** (admin) · **Download History** (admin) · **Logout**.

## 5. Dashboard (Home)

Sales cards showing net sales (`total_price − discount`) of **Paid** transactions:

- **Penjualan hari ini** (today)
- **Penjualan bulan ini** (this calendar month)
- **Penjualan 3 bulan terakhir** (since the 1st of the month three months ago)

Admins see an **All Stores** block followed by one block per store; users see only their store. A store with no data shows "Belum ada penjualan".

Known issues:
- The 3-month *discount* figure uses `whereMonth(created_at) > month-3`, which compares month numbers only (wrong around year boundaries and across years), so the 3-month net figure is unreliable.
- Each figure is a separate SUM query (6 per store + 6 for all).

## 6. Transactions

### 6.1 Lifecycle

`status = NULL` → **ongoing** (open tab / saved order) → `status = 'Paid'` → **paid**. There is no cancelled state; cancelling means deleting.

### 6.2 New / edit transaction screen

Single screen used for both create and edit:

- Sticky header showing item count and amount due (`total − discount − DP`).
- Fields: **Name** (customer, optional, free text), **Total** (read-only, computed), **DP** (down payment), **Disc** (discount, Rupiah amount, not %).
- Full product list (image, name, price) with a quantity box (0–99) and **−/+** buttons; tapping a product row also adds 1. Totals update live in the browser (jQuery).
- In edit mode, products already in the order are listed first, then the rest.
- Product list order: unsorted (DB order) on production.
- Actions:
  - **Save** (disk icon): saves and goes to Ongoing. Status stays ongoing.
  - **Payment**: saves; on edit it also sets `status = 'Paid'`. Goes to the "Transaction Complete" screen.
  - **Delete** (trash icon, edit only, with confirm): deletes the transaction and its lines.
- No product search or category filter; no category grouping on this screen.

Known issues:
- Pressing **Payment on a brand-new transaction does not mark it Paid** (only `update` sets the status). It shows "Transaction Complete" but the order stays in Ongoing. Staff work around it by paying from the Ongoing list.
- Totals, count and prices are computed client-side and trusted by the server (no server-side recalculation; line items store no unit price, so receipts and reports use the *current* product price).
- No validation on discount/DP; negative or empty values are accepted.
- Editing a paid transaction is possible (from History) and keeps it paid unless "Payment" is pressed again.

### 6.3 Invoice number

`key` = `YYYYMMDD` + 5-digit sequence, e.g. `2025121400007`. The sequence counts transactions created today **across all stores** (not per store) and is not unique-constrained (gaps/duplicates possible after deletes).

### 6.4 Ongoing list

Ongoing (unpaid) transactions, newest first: item count, amount (`total − discount`), date `dd/mm`, store name (admins). Tap edit to reopen.

### 6.5 History list

All transactions (paid and ongoing), newest first, same card layout plus status. Loads every row with no pagination (18,259 rows on production — slow).

### 6.6 Transaction Complete + receipt

- Complete screen: check animation, "Transaction Complete", **View Receipt** link; tapping anywhere else returns to Home.
- Receipt (`/transaction/receipt/{id}`), small-print layout meant for phone screenshot / print:
  - Logo, store address hard-coded for Pamulang: "Jl. Alam Asri 1 Blok H 35 No. 5, Pamulang, Tangerang Selatan, 081213327373" (same address shown for Senopati orders).
  - Date/time, `INV-{key}`, customer name.
  - Lines: product, qty, `@ unit price`, line total.
  - TOTAL, DISCOUNT, DP, PAYMENT (`total − discount − DP`).
  - Footer: "Terima kasih sudah berbelanja di Kopi Keto / Salam Kopi Nusantara".
- No print stylesheet / thermal printer support.

## 7. Products (admin)

- List of all products (image, name, price).
- Add / edit: image upload, name, price (Rupiah, integer), category; delete with confirm.
- Categories (fixed list in the form): Coffee-Based Drinks, Non-Coffee Drinks, Boba Series, Frappucino Series, Instant Series, Snacks.
- Image is resized/cropped to 300×300 and saved to `public/uploads/product/{timestamp}{rand}.{ext}`; a default placeholder is shown when missing.
- No stock/inventory, no per-store pricing or availability, no active/archived flag. Deleting a product **cascades and deletes its lines from past transactions** (FK `ON DELETE CASCADE`), altering history.

## 8. Download History (admin)

- Pick a month (from the current month back to August 2020), downloads `kopiketo-transaction-YYYY-MM.xlsx`.
- Columns: Date, Time, User, Customer, Total_Item, Total_Price, Discount, Status.
- All stores, no store column, no line items.

Known issue: the row mapping skips discount, so the **Discount column actually contains the status and the Status column is empty**.

## 9. Data model (production)

| Table | Purpose | Notes |
|---|---|---|
| `users` | Login accounts | `role` (`admin`/`user`), `store_id`, unused `branch` |
| `stores` | Store list | `id`, `name` only; id not auto-increment |
| `products` | Menu items | `name`, `price`, `image` path, `category` (free string) |
| `transactions` | Orders | `store_id`, `user_id`, `key`, `customer`, `total_item`, `total_price`, `discount`, `downpayment`, `status`; no FKs |
| `product_transaction` | Order lines | `transaction_id`, `product_id`, `amount` (qty); FKs cascade |
| `transaction_items` | — | Empty, unused |
| `oauth_*`, `password_resets`, `failed_jobs`, `migrations` | Framework | Passport tables unused |

### Data snapshot (dump of 2026-11-21)

| Item | Value |
|---|---|
| Transactions | 18,259, all `Paid` (no open orders at backup time) |
| — Pamulang (store 1) | 14,990, 2020-08-21 → 2026-11-19, all entered by one admin account |
| — Senopati (store 2) | 3,269, 2021-03-22 → 2025-05-31 (no sales since) |
| Order lines | 38,189 (some with quantity 0) |
| Products | 192; 78 have no category; one uses a non-standard category "Coffee Based" |
| Gross sales / discounts, all time | Rp4,371,726,700 / Rp73,774,050 |
| Invoice keys | 705 NULL, 731 duplicates |
| Down payment | Used on 1 transaction only |
| Timestamps | Stored correctly; read in Asia/Jakarta (WIB), peak hour 19:00 |

## 10. Not in v1

User/store management UI · stock · per-store menus · payment method tracking (cash/QRIS/transfer) · change calculation · refunds/void with audit trail · shifts/cash drawer · reports beyond the dashboard and monthly export · charts · best-seller analytics · receipt sharing/printing · offline mode · tests.
