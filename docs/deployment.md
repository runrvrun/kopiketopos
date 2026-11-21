# Deploying Kopiketo POS v2 to pos.kopiketo.com

Shared cPanel hosting (MultiPHP Manager, MariaDB, no Node). Built CSS/JS is
committed in `public/build`, so the server only needs PHP 8.3+ and Composer.

The v1 database is **never modified**: v2 gets a new database and
`legacy:import` copies everything into it. Rollback = put the old folder back.

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

## 2. Create the v2 database

cPanel › MySQL Databases:
1. Create database `posv2` (becomes `k3346760_posv2`).
2. "Add User To Database": the same user as v1 → `k3346760_posv2` → ALL PRIVILEGES.

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
nano .env          # fill DB_USERNAME, DB_PASSWORD (from step 1); check DB_DATABASE / LEGACY_DB_DATABASE
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

```bash
cd ~ && mv pos.kopiketo.com pos.kopiketo.com-v2 && mv pos.kopiketo.com-v1 pos.kopiketo.com
```
Then MultiPHP Manager: `pos.kopiketo.com` → PHP 7.2. v1 and its database are as before.

## Later updates

```bash
cd ~/pos.kopiketo.com
git pull                                   # if .htaccess conflicts: git checkout public/.htaccess, pull, re-apply step 8
$PHP composer.phar install --no-dev --optimize-autoloader --no-interaction
$PHP artisan migrate --force
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```
Locally: run `npm run build` and commit `public/build` before pushing changes to CSS/JS/Blade.
