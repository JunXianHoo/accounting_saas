# Accounting assessment

A multi-company accounting application built with Laravel, Inertia, and Vue. PostgreSQL stores accounts in a central database and gives each company its own database for customers, invoices, and payments. Stancl Tenancy switches connections using the signed-in user's company.

## Run with Docker

With Docker and the Compose plugin running, start the development stack from the repository root.

```sh
docker compose up --build -d
docker compose ps
```

Open <http://localhost:8080/register> to create a company. Compose builds the frontend, generates a Laravel app key on first startup, and runs migrations for the central database and any existing company databases. Registration creates and migrates a new company database before signing the user in. Invoice emails are written to the worker log.

If you already ran the shared-database version, start the multi-database version with fresh volumes by using an unused Compose project name. For example, run `ACCOUNTING_PORT=8081 docker compose -p accounting-assessment up --build -d` and open <http://localhost:8081/register>. Use `docker compose -p accounting-assessment` for subsequent commands for that project.

```sh
docker compose logs worker
docker compose exec app php artisan tenants:databases
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

1. Start PostgreSQL and Redis. Create a fresh PostgreSQL database named `accounting` for the central data. Use a local PostgreSQL role that owns that database and has `CREATEDB` so registration can create company databases. Check Redis with `redis-cli ping`.
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

4. Run `php artisan migrate` for the central database and `php artisan tenants:migrate` for any existing company databases. Start `php artisan serve` and `npm run dev` in separate terminals. Open <http://localhost:8000/register> to create a user and their company.
5. Start `php artisan queue:work redis` in another terminal to process invoice email jobs. With the log mailer, the rendered message is written to the Laravel log, normally `storage/logs/laravel.log`.
6. To exercise the scheduler locally, run `php artisan schedule:work`. Use `php artisan schedule:list` to inspect the schedule and `php artisan invoices:check-overdue` to run the check immediately. The configured daily check runs at 00:05 UTC (08:05 in Kuala Lumpur).

## Try the application

- Visit `/customers` to create or edit a customer. Delete customers with no invoices, or deactivate customers whose invoice history must be preserved.
- Open **Invoice Reports** at `/reports/sales` and click **Create invoice** to issue an invoice for that customer. Its number uses the issue date and a daily sequence for the company, such as `INV20261107001`.
- Open the invoice to edit or delete it before recording payments. Invoice edits preserve the assigned number. Record a partial payment with an optional reference, then pay the remaining balance.
- Visit `/dashboard` to see company totals. Monthly receipts use payment dates. In **Invoice Reports**, select an issue-date range for sales, an outstanding balance for unpaid invoices, or a customer for that customer's invoiced, paid, and outstanding totals.
- Register a second account to create a separate company and verify that its invoice list and report start empty.
- Open an invoice URL from the first company while signed in to the second. The app returns `404`.

Invoice amounts use decimal arithmetic. Payment recording locks the invoice, rejects overpayments, and accepts dates from the invoice's issue date through today. The app computes whether an invoice is currently overdue from its due date and payment status. The daily command checks each company's unpaid balances, records when it first detected each overdue invoice in `overdue_detected_at`, and logs the count. The timestamp is an audit record and never drives the current status.

Dashboard totals are cached per company and day. Invoice creation, edits, and deletion, as well as payment creation and updates, invalidate the cache after commit. Invoice email jobs record successful sends in `emailed_at` and skip invoices that have already been emailed or have been deleted.

Run `php artisan tenants:databases` to list companies and their database names, or `php artisan tenants:databases "Harbor Studio"` to filter by name. Use the returned database name in your PostgreSQL client. See the [database ERD](docs/erd.md) for tables and ownership constraints.

## Send invoice emails through Resend

The demo writes messages to the log. Real delivery uses Resend so a worker retry can reuse the provider's idempotency key. Apply the latest migrations and set these values in the environment used by the queue worker.

```dotenv
INVOICE_EMAIL_USE_RESEND=true
RESEND_API_KEY=your_resend_api_key
MAIL_FROM_ADDRESS=invoices@your-verified-domain.example
```

Restart the queue worker after changing its environment. The job saves the original email content and reuses it with the same idempotency key on each retry. Automatic sending stops 23 hours after the first attempt. If delivery is still uncertain at that point, check the Resend logs before resending.

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

Pest uses an in-memory central SQLite database and a separate temporary SQLite database for each company. Tests clean up company databases after each run. Queue and mail tests use fakes where needed. The local worker steps above exercise the Redis queue and log mailer.
