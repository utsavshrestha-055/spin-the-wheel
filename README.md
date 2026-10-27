# Spinroom

Spinroom is a login-only Laravel 13 and Inertia/Vue app for multi-round elimination draws. It runs on PHP and MySQL with synchronous requests; the game does not require a queue worker, Redis, WebSockets, or a server process that stays running.

## Local setup

Requirements: PHP 8.3+, Composer, Node.js 20+, npm, and MySQL or SQLite.

1. Copy `.env.example` to `.env`, configure the database, and set credentials for the first admin:

   ```dotenv
   ADMIN_NAME="Draw Admin"
   ADMIN_EMAIL=admin@example.com
   ADMIN_PASSWORD="use-a-long-unique-password"
   ```

2. Install dependencies and generate the app key:

   ```sh
   composer install
   npm install
   php artisan key:generate
   ```

3. Run migrations and seed the database:

   ```sh
   php artisan migrate --seed
   ```

   The seeder creates an admin only when both admin environment values are set. It also adds 400 sample participants, including three priority players. Remove these sample rows before using a production roster.

4. Start the local PHP and Vite servers:

   ```sh
   php artisan serve
   npm run dev
   ```

   Build deployable assets with `npm run build`.

## Run a draw

- Sign in at `/login`; there is no public registration route.
- Add participants in **Participants**, or import CSV with a `username` header and optional `name`, `social_media`, and `priority` columns. Imports stream the file and upsert batches of 500 rows to limit memory and request time. Existing usernames are updated.
- Create a game to snapshot the active participants and their priorities. The control panel posts each spin to the server. The server chooses and stores the result in a database transaction, then returns that result and the wheel names. The client only animates the server result.
- Start the next round after the current round is complete. Its eligible pool is exactly the previous round's winners. Review and export results from **Game history**.

## Game settings

Set these in `.env`; `config/game.php` validates them when Laravel boots:

```dotenv
GAME_ROUNDS="15,3,1"
GAME_SPIN_DURATION_MS=6000
GAME_PRIORITY_CARRY_FORWARD=true
GAME_WHEEL_VISIBLE_SEGMENTS=24
```

Round counts must be positive and cannot increase from one round to the next. Priority zero means normal eligibility. Higher priority values take precedence if priority players outnumber a round's slots. Guaranteed priority winners are shuffled among the spin positions using secure server-side randomness.

## Hostinger shared hosting

1. Configure a MySQL database and the production `.env` values, including a unique `APP_KEY`, admin credentials, `QUEUE_CONNECTION=sync`, `CACHE_STORE=database`, and `SESSION_DRIVER=database`. Keep `.env` outside the public document root and do not upload it into `public/`.
2. On your local machine, run `npm ci` and `npm run build`. Upload the Laravel project and the generated `public/build` directory. Set the domain document root to the project's `public/` directory.
3. Install PHP dependencies on the server if Hostinger's Composer tool or terminal is available. Run migrations with `php artisan migrate --force` using that tool. Do not start queue workers or WebSocket/Reverb services.
4. This app has no scheduled tasks. Add a cron job for `php artisan schedule:run` only if scheduled work is added later. Large CSV imports are processed in bounded chunks so they fit typical shared-hosting PHP limits.

Run the feature tests locally with `php artisan test --compact` before uploading a release.
