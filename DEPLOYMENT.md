# Deploying the Think Ocean CIC website

This guide is for whoever installs the site on hosting (intended: IONOS). It assumes command-line (SSH) access. **Check every requirement against the actual IONOS plan before starting.** Some shared plans lack SSH, Composer, Node.js or the ability to change the document root. If the plan can't meet a requirement, see "If the hosting plan is limited" below.

Do not point the live domain or change DNS until the site has been tested on a staging address and signed off.

## 1. Server requirements

| Requirement | Detail |
|---|---|
| PHP | **8.3 or newer** (the project requires `^8.3`; tested on 8.3.33) |
| PHP extensions | `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd` (with WebP support), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, `zip`; `exif` recommended (corrects photo orientation) |
| Database | MySQL 8.0+ or MariaDB 10.6+, with a dedicated database and user |
| Composer | 2.x (on the server, or build locally and upload `vendor/`) |
| Node.js | 20+ with npm, **only where assets are built** (can be your computer) |
| Web server | Apache with `mod_rewrite` (the included `public/.htaccess` is used) or nginx |
| HTTPS | Required. The site forces HTTPS links in production and sets secure cookies |
| Cron | One cron entry (optional, see section 8) |

Check PHP on the server with `php -v` and `php -m`. The **command-line PHP version on IONOS can differ from the web PHP version**, so check both. You may need to call a specific binary such as `/usr/bin/php8.3`.

## 2. Get the code onto the server

Recommended: build a release on your own computer and upload it.

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build            # creates public/build (fonts are downloaded and bundled here)
```

Upload the project **except** `.env`, `node_modules/`, `storage/app/*` contents from development, and `database/*.sqlite`.

If the server has Composer, you can instead run `composer install --no-dev --optimize-autoloader` there. Do not use `--ignore-platform-reqs` or disable security checks. If Composer reports a PHP version or extension problem, fix the server configuration rather than forcing the install.

## 3. Document root

The web server's document root **must be the `public/` directory**, not the project folder. Otherwise `.env`, logs and source code may be downloadable.

- In the IONOS control panel, set the domain's target directory to `<project>/public`.
- If the document root can't be changed, don't deploy this way. See "If the hosting plan is limited".

## 4. Database

1. In IONOS, create a MySQL/MariaDB database and a user with a strong password, limited to that database.
2. Note the host name (IONOS often uses a host other than `localhost`), port, database name and user.

## 5. Environment file

Copy `.env.example` to `.env` on the server and set at least:

```dotenv
APP_NAME="Think Ocean CIC"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_TIMEZONE=Europe/London
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=...           # IONOS SMTP host for the organisation's mailbox
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="no-reply@your-domain.example"
```

Then generate the application key **once** (keep a secure copy; changing it logs everyone out and makes encrypted data unreadable):

```bash
php artisan key:generate
```

Restrict the file: `chmod 600 .env`. Never commit `.env` to Git.

## 6. First installation

```bash
php artisan migrate --force          # creates the tables
php artisan db:seed --force          # core pages (as unpublished drafts) and menus; safe to re-run
php artisan storage:link             # makes public uploads available at /storage
php artisan cms:create-user          # create the first administrator (prompts for a password)
php artisan filament:optimize
php artisan optimize                 # caches config, routes, views and events
```

`db:seed` never creates user accounts or overwrites existing pages.

If `storage:link` can't create symbolic links on the plan, ask IONOS support. Public images won't display without it.

## 7. File permissions

The web server user needs write access to:

- `storage/` (logs, cache, sessions, uploaded files)
- `bootstrap/cache/`

Typically `chmod -R 775 storage bootstrap/cache` with the correct owner or group. Nothing else should be writable by the web server.

Private documents are stored in `storage/app/private` and served only to signed-in staff. Public images are in `storage/app/public`.

## 8. Scheduled tasks

The site doesn't need a scheduler to work: scheduled news posts appear automatically when their publication time passes. If you later add scheduled jobs, add this cron entry:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## 9. HTTPS and security checklist

- [ ] SSL certificate active (IONOS includes certificates on most plans); HTTP redirects to HTTPS
- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] Document root is `public/`
- [ ] `https://your-domain/.env` returns 404/403, not the file
- [ ] `https://your-domain/admin` shows the login page and nothing else without signing in
- [ ] Add the absolute sitemap line to `public/robots.txt`: `Sitemap: https://your-domain.example/sitemap.xml`
- [ ] Submit the sitemap in Google Search Console
- [ ] If the site sits behind a proxy or CDN that terminates HTTPS, configure Laravel's trusted proxies (`bootstrap/app.php`, `$middleware->trustProxies(at: [...])`) with that provider's IP ranges. Otherwise leave it as it is.

The application sets security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and HSTS over HTTPS). A Content-Security-Policy isn't set by the app because the admin panel needs inline scripts. One can be added for public pages at the web-server level after testing.

## 10. Backups and restoration

Back up **both** of these on a schedule (daily recommended), and keep copies off the server:

1. **Database**: IONOS database backups, or `mysqldump --single-transaction -h HOST -u USER -p DATABASE > backup.sql`
2. **Uploaded files**: `storage/app/public` and `storage/app/private`
3. **The `.env` file and `APP_KEY`**, stored securely (for example in a password manager)

To restore: create an empty database, import the dump (`mysql ... < backup.sql`), restore the two storage folders, restore `.env`, then run `php artisan storage:link` and `php artisan optimize`.

Test a restore on a staging copy at least once before relying on the backups.

## 11. Deploying updates safely

1. Back up the database and uploads (section 10).
2. Build the new release locally (`composer install --no-dev -o`, `npm ci && npm run build`) and run the test suite (`php artisan test`).
3. `php artisan down --refresh=15` (shows the maintenance page).
4. Upload the changed files. Keep `.env` and `storage/` in place.
5. `php artisan migrate --force`
6. `php artisan filament:optimize && php artisan optimize`
7. `php artisan up`
8. Check the homepage, a project, a news article, the contact form and the admin login.

**Rollback:** keep the previous release folder (or a zip of it). To roll back, put the previous files back, restore the database backup taken in step 1 if migrations ran, then run `php artisan optimize` and `php artisan up`. Pointing the document root at release folders (`releases/2026-10-11/public`) makes switching back instant.

## 12. If the hosting plan is limited

- **No SSH or Composer:** build everything locally (including `vendor/` and `public/build`) and upload by SFTP. You still need some way to run `php artisan migrate`. Without one, ask IONOS whether the plan supports it, or choose a plan that does.
- **Document root can't point at `public/`:** don't work around this by moving files into the web root. Use a plan or IONOS product (for example a VPS or Deploy Now for PHP) that allows it.
- **PHP below 8.3:** switch the PHP version in the IONOS control panel. If 8.3+ isn't offered, the plan can't run this application.

## 13. Routine operation (for staff)

- Sign in at `/admin`.
- Core pages are created as drafts containing `[PLACEHOLDER: …]` text. The CMS **refuses to publish** anything that still contains a placeholder. Replace placeholders with approved copy, then submit or publish.
- Menu links to unpublished pages are hidden automatically.
- Contact form messages appear under **Enquiries → Contact messages**. Set a notification email address under **Site → Site settings**.
