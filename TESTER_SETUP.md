# Tester Setup Guide — Lync (PUP-TBIDO)

This gets the app running on your own machine after pulling the repo. Follow it in order — most "it doesn't work" issues come from skipping a step.

## 1. Prerequisites

Make sure you have these installed before starting:

- PHP 8.3 or higher
- Composer
- Node.js + npm
- MySQL, running locally

## 2. Install dependencies

From the project root:

```bash
composer install
npm install
```

## 3. Set up your `.env` file

`.env` is never in git — everyone needs their own local copy.

```bash
cp .env.example .env
php artisan key:generate
```

Now open `.env` and edit the database block (mail is already set up — see below):

**Database** — uncomment and fill in these lines with your local MySQL credentials:

```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lync
DB_USERNAME=root
DB_PASSWORD=your_local_mysql_password
```

(Create the `lync` database in MySQL first if it doesn't exist yet.)

**Mail** — ask the team lead for the SMTP username and password and fill in `MAIL_USERNAME`, `MAIL_PASSWORD` and `MAIL_FROM_ADDRESS` in your `.env`. They are deliberately not in `.env.example`, which is committed to git. To test without sending real email, set `MAIL_MAILER=log` instead: messages are written to `storage/logs/laravel.log`.

## 4. Set up the database

```bash
php artisan migrate
php artisan db:seed
```

This creates all the tables and seeds ready-to-use accounts and sample data. Login accounts you get from this:

| Role | Email | Password |
|---|---|---|
| Admin | `admin@pup.edu.ph` | `password` |
| Founder (Startup) | `founder@test.com` | `password` |

Plus sample startups, coordinators, mentors, and roadblocks across every status/tab, so most screens have data to look at immediately.

## 5. Link storage

**Skip this step.** Uploaded files (photos, information-sheet documents, roadblock attachments) live under `storage/app/public` and are served by the app itself at `/storage/...`, which checks who is allowed to see each file. Running `php artisan storage:link` would let the web server hand those files out directly and skip the checks. If you ran it before, delete the `public/storage` link.

## 6. Word document templates

The Word/PDF export feature (`WordDocumentExporter`) fills real `.docx`
master templates that live in `resources/document-templates/` and are
committed to git — a fresh clone gets them automatically, no manual step
needed.

(This used to live in `storage/app/templates/`, which is gitignored, so
every fresh clone needed someone to manually pass around a
`word-templates.zip`. Moved to `resources/` — which git does track — so
that workaround is no longer necessary.)

The 13 files, matched exactly by name in `TEMPLATES` in
`app/Services/Exports/WordDocumentExporter.php` — don't rename them:

- `startup-information-sheet-template.docx`
- `startup-tech-assessment-trl-template.docx`
- `startup-tech-assessment-mrl-template.docx`
- `startup-tech-assessment-tmrl-template.docx`
- `startup-tech-assessment-srl-template.docx`
- `startup-post-tech-assessment-trl-template.docx`
- `startup-post-tech-assessment-mrl-template.docx`
- `startup-post-tech-assessment-tmrl-template.docx`
- `startup-post-tech-assessment-srl-template.docx`
- `doc6-startup-growth-strategy-template.docx`
- `doc7-weekly-checkins-template.docx`
- `doc8-prototype-validation-template.docx`
- `doc13-venture-exit-template.docx`

No LibreOffice install needed — there's a `convertToPdf()` method that
shells out to LibreOffice, but it's currently unused (disabled after
`soffice.bin` kept crashing on Macy's machine). Every export just returns
the filled `.docx` directly.

## 7. Build frontend assets

```bash
npm run build
```

(Use `npm run dev` instead if you want Vite to auto-rebuild while you're actively working on the frontend.)

## 8. Run the app

```bash
php artisan serve
```

Then open the URL it prints (usually `http://127.0.0.1:8000`).

If you'd rather run the dev server, Vite, and the log viewer together in one terminal, there's a shortcut:

```bash
composer run dev
```

## 9. Testing email verification

Register a new Founder account through the app using any real email address you can check — the verification email actually delivers there (mail is already configured, no setup needed).

## Common gotchas

- Forgot to `cp .env.example .env` at all → app won't boot, or uses stale settings.
- Copied `.env.example` but never edited the DB values → migrations fail.
- A founder document or roadblock file shows "not found" → you're logged out, or logged in as a different founder. Only the owning founder and admins can open those files.
- Document exports fail or error out → check `resources/document-templates/` actually has all 13 `.docx` files (see step 6); if it's empty something went wrong with the clone.
- Skipped `php artisan key:generate` → "no application encryption key has been specified" error.
- MySQL not actually running, or `lync` database doesn't exist yet → migration errors on step 4.
- Verification email doesn't arrive at all (not even spam) → the shared sending account may have been flagged/locked by Google; let Argee know so it can be reset.
