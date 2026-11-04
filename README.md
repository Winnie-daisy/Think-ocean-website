# Think Ocean CIC — website and CMS

Public website and content management system for Think Ocean CIC (*Healthy Oceans. Thriving Futures.*).

- **Stack:** Laravel 13, Filament 5 (admin at `/admin`), Blade + Tailwind CSS 4, Vite, MySQL/MariaDB
- **Deployment:** see [DEPLOYMENT.md](DEPLOYMENT.md)

## Local development

Requirements: PHP 8.3+, Composer 2, Node 20+.

```bash
composer install
cp .env.example .env            # then set DB_* (or use DB_CONNECTION=sqlite locally)
php artisan key:generate
php artisan migrate
php artisan db:seed             # core pages (as drafts) and menus
php artisan storage:link
php artisan cms:create-user     # first administrator
npm install && npm run build    # or `npm run dev` while working on styles
php artisan serve
```

Run the tests with `php artisan test`. They use an in-memory SQLite database and never touch your development data.

This machine's local `.env` currently uses SQLite (`database/database.sqlite`) because the local MySQL credentials weren't available during setup. All migrations are written for MySQL/MariaDB as well.

## How publishing works

The rules that protect the live site are enforced on the server, in `App\Services\ContentWorkflow` and the policies in `app/Policies`. Hiding buttons in the interface is only a convenience.

- **Pages, projects and news articles** each have a live version (the table columns) and, while live, an optional pending draft (`draft_content`).
- Editing live content only ever updates the draft. The public site keeps showing the approved version.
- Workflow: **Draft → Submitted for review → Published**, with **Return for changes** and **Reject** (both need a comment), **Unpublish** and **Discard draft**.
- Only **Editors** and **Administrators** can publish. **Contributors** create and edit their own drafts and submit them; they can't edit while a review is in progress. **Read-only Reviewers** can view content and history only.
- A model-level guard (`HasPublishingWorkflow`) throws if any code changes live content outside the workflow.
- Every step is recorded in the revision history (who, what, before/after, comment). Sensitive admin actions such as sign-ins, users, settings, media and publishing go to the activity log.
- Content containing `[PLACEHOLDER` can't be published. The seeded pages use this marker wherever the organisation must supply verified copy.
- Staff can preview drafts with the public templates at `/admin/preview/{type}/{id}` (sign-in required, not indexed).

## Roles

| Role | Can do |
|---|---|
| Administrator | Everything, including users, site settings and the activity log |
| Editor / Approver | Create and edit all content, review, publish and unpublish; media, menus, categories and tags; contact messages |
| Content Contributor | Create content, edit their own drafts, submit for review, upload media |
| Read-only Reviewer | View content and revision history |

Permissions are defined in `App\Enums\Role::permissions()`.

## Content model

- **Pages** are built from structured sections (hero, text, feature list, image with text, key facts with required sources, call to action, featured projects, latest news). Core pages (home, about, what we do, get involved, contact, projects, news, privacy, cookies, accessibility) have fixed addresses and can't be deleted.
- **Projects**: summary, description, featured image, gallery, category, status, optional verified location and dates, homepage feature flag, SEO fields.
- **News and insights**: excerpt, body, featured image, publication date (a future date schedules the article), categories, tags, SEO fields.
- **Media library**: images (JPG, PNG, WebP, GIF) and documents (PDF, Office, text, CSV), up to 10 MB. Each file's real content type is checked, and files are stored under random names. Responsive WebP versions are generated with GD, which also strips location data from photos. Private documents are kept off the public disk. SVG is not accepted.
- **Site settings**: organisation details, logo, favicon, contact details, social links and SEO defaults. Nothing is shown publicly until it's entered, so unverified details never appear.
- **Navigation**: header, footer and legal menus. Links to unpublished pages are hidden automatically.
- **Contact messages**: validated, rate limited (3 per minute and 20 per day per IP), protected by a honeypot and a timing check, stored privately, and tracked as unread, read, reviewed or spam.

## Project layout

```
app/Services/ContentWorkflow.php        publishing rules (single entry point)
app/Models/Concerns/HasPublishingWorkflow.php  live/draft separation and guard
app/Policies/                           server-side authorisation
app/Filament/                           admin resources, pages, shared form/table pieces
app/Http/Controllers/                   public site (published content only) + staff preview/download
resources/views/components, sections    Blade components and page-section templates
database/seeders/CmsSeeder.php          core pages (drafts) and menus
tests/Feature/                          workflow, permissions, public site, contact, uploads, migrations
```
