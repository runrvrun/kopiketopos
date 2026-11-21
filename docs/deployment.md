# Deploying Kopiketo POS v2 to pos.kopiketo.com

Shared cPanel hosting (MultiPHP Manager, MariaDB, no Node). Built CSS/JS is
committed in `public/build`, so the server only needs PHP 8.3+ and Composer.

The hosting allows only two databases (WordPress + POS), so v2 shares the POS
database: every v1 table is renamed to `v1_*` (nothing is deleted), v2 creates
its own tables, and `legacy:import` copies the v1 data across and verifies it.
Rollback = drop the v2 tables, rename `v1_*` back, restore the old folder.
(Rehearsed end-to-end on a copy of the production dump, including rollback.)

## 0. Before you start

- After closing time (no transactions while you deploy).
- cPanel › Domains: check the document root of `pos.kopiketo.com`.
  These steps assume `~/pos.kopiketo.com/public`; adjust paths if different.
- In cPanel › Terminal, find the PHP 8.3 binary (the terminal's plain `php` is the account default, 7.2):

  ```bash
  ls /opt/alt/php83/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php 2>/dev/null
  PHP=/opt/alt/php83/usr/bin/php        # use whichever path exists
  $PHP -v                               # must say 8.3
  $PHP -m | grep -iE '^(gd|zip|mbstring|pdo_mysql|intl|fileinfo|xml|dom|iconv)$'
  ```

  Needed: ctype dom fileinfo filter gd hash iconv json libxml mbstring openssl pcre
  pdo_mysql session simplexml tokenizer xml xmlreader xmlwriter zip zlib.
  Missing ones can be enabled in cPanel › Select PHP Version › Extensions (for 8.3).

## 1. Back up today's data

phpMyAdmin › select the v1 database (`k3346760_kopiketopos`) › Export › Quick › SQL › Go.
Keep the file. (v1's DB stays untouched anyway, but take it.)

Note the v1 DB user/password — they're in the v1 `.env`:

```bash
grep -E '^DB_(HOST|DATABASE|USERNAME|PASSWORD)=' ~/pos.kopiketo.com/.env
```

## 2. Rename the v1 tables

phpMyAdmin › `k3346760_kopiketopos` › **SQL** tab → paste the contents of
[`docs/deploy/1-rename-v1-tables.sql`](deploy/1-rename-v1-tables.sql) → Go.
The table list should now show only `v1_…` tables.

## 3. Swap the code

```bash
cd ~
mv pos.kopiketo.com pos.kopiketo.com-v1
git clone https://github.com/runrvrun/kopiketopos.git pos.kopiketo.com
# no git? curl -L -o v2.zip https://github.com/runrvrun/kopiketopos/archive/refs/heads/master.zip && unzip -q v2.zip && mv kopiketopos-master pos.kopiketo.com
cd ~/pos.kopiketo.com

curl -sS https://getcomposer.org/installer | $PHP -- --quiet
$PHP composer.phar install --no-dev --optimize-autoloader --no-interaction
```

## 4. Configure

```bash
cp .env.production.example .env
nano .env          # fill DB_USERNAME, DB_PASSWORD (from step 1); DB_DATABASE and LEGACY_DB_DATABASE = the v1 DB name, LEGACY_DB_PREFIX=v1_
$PHP artisan key:generate --force
```

## 5. Product photos uploaded since the repo snapshot

```bash
cp -n ~/pos.kopiketo.com-v1/public/uploads/product/* ~/pos.kopiketo.com/public/uploads/product/
```

## 6. Database: create tables and import all v1 data

```bash
$PHP artisan migrate --force
$PHP artisan legacy:import --force
```

Every line must show ✓ and end with "Import verified: data matches v1."

## 7. Optimise

```bash
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```

## 8. Switch the domain to PHP 8.3 (only this domain)

cPanel › MultiPHP Manager: tick **only** `pos.kopiketo.com` → PHP 8.3 → Apply.
Leave `kopiketo.com` (WordPress) and the system default on 7.2.
cPanel writes the PHP handler into `~/pos.kopiketo.com/public/.htaccess`.

## 9. Check

- https://pos.kopiketo.com → sign in with your usual account.
- Transaksi › Lunas shows today's transactions; Home shows today's sales.
- A receipt opens and Kirim WA shows the share sheet on the phone.
- Admin › Rekening: add the payment account. Admin › Toko: add Senopati's address/phone.
- https://kopiketo.com (WordPress) still works.

## Rollback

1. phpMyAdmin › `k3346760_kopiketopos` › SQL → run [`docs/deploy/rollback-to-v1.sql`](deploy/rollback-to-v1.sql)
   (drops the v2 tables, renames `v1_*` back).
2. Restore the folder:
   ```bash
   cd ~ && mv pos.kopiketo.com pos.kopiketo.com-v2 && mv pos.kopiketo.com-v1 pos.kopiketo.com
   ```
3. MultiPHP Manager: `pos.kopiketo.com` → PHP 7.2. v1 is exactly as before.

## Afterwards

Once v2 has run fine for a while, the `v1_*` tables can be dropped (keep the step 1 backup).

## Later updates

```bash
cd ~/pos.kopiketo.com
git pull                                   # if .htaccess conflicts: git checkout public/.htaccess, pull, re-apply step 8
$PHP composer.phar install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```
Locally: run `npm run build` and commit `public/build` before pushing changes to CSS/JS/Blade.
