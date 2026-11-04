# E-Library API — Backend Overlay (Laravel 11/12)

This folder is NOT a full Laravel app. It contains only the E-Library files.
Drop it onto a fresh Laravel project (steps below).

## 1. Create the Laravel project (needs PHP 8.2+ and Composer)

```bash
composer create-project laravel/laravel elibrary-api
cd elibrary-api
php artisan install:api        # installs Sanctum, adds routes/api.php, adds HasApiTokens to User
```

## 2. Copy this overlay into the project

Copy everything from this folder into `elibrary-api/`, **overwriting** when asked
(`routes/api.php` will be replaced). Folders merge; nothing else is touched.

Then append the lines in `.env.elibrary.example` to your `.env`.

## 3. Configure the database (.env)

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=elibrary
DB_USERNAME=root
DB_PASSWORD=
```
Create an empty `elibrary` database in phpMyAdmin/XAMPP first.

## 4. Migrate, import 200+ books, run

```bash
php artisan migrate
php artisan books:import --count=220     # needs internet; pulls from Project Gutenberg
php artisan serve                        # http://127.0.0.1:8000/api/health
php artisan test                         # runs the feature tests
```

## API reference (Phase 1)

| Method | Endpoint | Auth | Purpose |
|---|---|---|---|
| GET | /api/health | none | Connectivity check |
| POST | /api/register | none | name, email, password, password_confirmation -> token |
| POST | /api/login | none | email, password -> token |
| POST | /api/logout | Bearer | Revoke current token |
| GET | /api/me | Bearer | Current user |
| POST | /api/guest/start | none | Start guest session -> token (send as `X-Guest-Token`) |
| GET | /api/books | none | `?q=&category=&author=&per_page=` |
| GET | /api/books/{id} | none | Book details |
| GET | /api/categories | none | Categories + counts |
| GET | /api/books/{id}/download | Bearer **or** X-Guest-Token | Logs download, returns file URL. 429 when over limit |
| GET | /api/downloads/status | Bearer **or** X-Guest-Token | limit / used / remaining |
| GET/POST | /api/library | Bearer | My Library list / add `{book_id}` |
| PATCH | /api/library/{id}/favorite | Bearer | Toggle favorite |
| DELETE | /api/library/{id} | Bearer | Remove from library |

Limits (guest 5, member 10) live in `config/elibrary.php` and are enforced in
`DownloadController`, never on the client.

## Deploying

- **Docker (local):** `docker compose up --build`
- **Wasmer Edge:** `wasmer.toml` is included. Run `wasmer login`, then `wasmer deploy`.
  Set secrets with `wasmer app secrets create APP_KEY <value>` (and DB credentials).
  Do not store book files on the server disk; only metadata is stored, files link to Gutenberg.
- **CI:** `.github/workflows/ci.yml` runs the tests and builds the Docker image on every push.

## Notes
- Requires Laravel 11+ (the User model's `password` => `hashed` cast hashes passwords on create).
- Gutendex metadata varies by book; the importer skips entries lacking an EPUB link or cover.
