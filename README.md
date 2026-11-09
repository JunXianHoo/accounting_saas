# Accounting assessment

A shared-database, multi-company accounting application built with Laravel, Inertia, and Vue. It supports customer management, invoices and line items, payment recording, and tenant-scoped invoice reports.

## Run with Docker

With Docker and the Compose plugin running, start the development stack from the repository root.

```sh
docker compose up --build -d
docker compose ps
```

Open <http://localhost:8080/register> to create a company. Compose builds the frontend, generates a Laravel app key on first startup, and runs database migrations before starting the web app, Redis queue worker, and scheduler. Invoice emails are written to the worker log.

```sh
docker compose logs worker
docker compose exec app php artisan invoices:check-overdue
docker compose exec app php artisan test
```

After editing the source, run `docker compose up --build -d` to rebuild the image. Run `docker compose down` to stop the stack and retain the database, Redis data, and app key in Docker volumes. To use a different port, run `ACCOUNTING_PORT=8081 docker compose up --build -d` and open <http://localhost:8081/register>.

## Requirements for setup without Docker

- PHP 8.4.1 or later for the locked dependencies, with BCMath, PDO PostgreSQL, and PDO SQLite for tests
- Composer 2
- Node.js 22.18+ on the 22.x release line, 24.11+ on the 24.x release line, or 26+
- PostgreSQL and Redis

## Local setup

1. Start PostgreSQL and Redis. Create a PostgreSQL database named `accounting` and grant your local database role access to it. Check Redis with `redis-cli ping`.
2. On a fresh checkout, run these commands from the project root.

    ```sh
    composer install
    cp .env.example .env
    npm ci
    php artisan key:generate
    ```

    If you already have a local `.env` with an application key, keep that file and key.

3. Set these values in `.env`, substituting your PostgreSQL credentials.

    ```dotenv
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=accounting
    DB_USERNAME=your_local_postgres_role
    DB_PASSWORD=

    CACHE_STORE=redis
    QUEUE_CONNECTION=redis
    REDIS_CLIENT=predis
    REDIS_HOST=127.0.0.1
    REDIS_PORT=6379

    MAIL_MAILER=log
    ```

4. Run `php artisan migrate`. Start `php artisan serve` and `npm run dev` in separate terminals. Open <http://localhost:8000/register> to create a user and their company.
5. Start `php artisan queue:work redis` in another terminal to process invoice email jobs. With the log mailer, the rendered message is written to the Laravel log, normally `storage/logs/laravel.log`.
6. To exercise the scheduler locally, run `php artisan schedule:work`. Use `php artisan schedule:list` to inspect the schedule and `php artisan invoices:check-overdue` to run the check immediately. The configured daily check runs at 00:05 UTC (08:05 in Kuala Lumpur).

## Try the application

- Visit `/customers` to create an active customer.
- Open **Invoice Reports** at `/reports/sales` and click **Create invoice** to issue an invoice for that customer. Its number uses the issue date and a daily sequence for the company, such as `INV20261107001`.
- Open the invoice to edit or delete it before recording payments. Invoice edits preserve the assigned number. Record a partial payment with an optional reference, then pay the remaining balance.
- Visit `/dashboard` to see company totals. Monthly receipts use payment dates. Visit `/reports/sales` to filter by invoice number, payment status, customer, or issue-date range.
- Register a second account to create a separate company and verify that its invoice list and report start empty.

Invoice amounts use decimal arithmetic. Payment recording locks the invoice, rejects overpayments, and accepts dates from the invoice's issue date through today. Overdue is calculated from the due date and payment status. The daily command checks each company's overdue invoices against recorded payment totals and logs the count.

Dashboard totals are cached per company and day. Invoice creation, edits, and deletion, as well as payment creation and updates, invalidate the cache after commit. Invoice email jobs record successful sends in `emailed_at` and skip invoices that have already been emailed or have been deleted.

See the [database ERD](docs/erd.md) for tables and ownership constraints.

## Load demo data

Run the demo seeder explicitly in a local environment after applying migrations. With Docker, rebuild the image so it includes the seeder. Allow several minutes for the first seed run.

```sh
docker compose up --build -d
docker compose exec app php artisan db:seed --class=DemoDataSeeder
```

Without Docker, run `php artisan db:seed --class=DemoDataSeeder`.

| Company       | Login email          |
| ------------- | -------------------- |
| Harbor Studio | `demo1@example.test` |
| Cedar Works   | `demo2@example.test` |

Both demo accounts use `accounting-demo-password`. Each company starts with 200 customers, 2,000 invoices, 6,000 invoice items, and 2,500 payments. Invoice dates span the past year, with unpaid, partially paid, paid, and overdue examples. Open **Invoice Reports** to try the filters and pagination, and switch accounts to view the other company's data.

The seeder creates separate demo companies, skips companies it already seeded, and leaves existing records in place. It runs only in local and testing environments and does not send invoice emails.

## Verification

```sh
php artisan test
vendor/bin/phpstan analyse --memory-limit=512M
npm run types:check
npm run build
```

Pest uses in-memory SQLite, array caching, and a synchronous queue by default. Queue and mail tests use fakes where needed. The local worker steps above exercise the Redis queue and log mailer.
