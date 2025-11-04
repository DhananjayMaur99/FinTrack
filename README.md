# FinTrack — Expense Tracker API

FinTrack is a lightweight RESTful API for personal expense tracking. This repository contains the Laravel backend implementing user authentication, categories, transactions and budgets. The project follows a modular, service-oriented structure so business logic is testable and decoupled from HTTP concerns.

This README explains how to get the project running locally, the important directories, common commands, and troubleshooting notes for issues you may encounter while developing.

## Quick links

-   Technical specification: `docs/FinTrack_Technical_Specification.md`
-   API routes: `routes/api.php`
-   Models: `app/Models`
-   Services: `app/Services` (business logic)

## Requirements

-   PHP 8.1+ (match the project's composer.json)
-   Composer
-   MySQL or MariaDB
-   Node.js & npm (for frontend assets if developed)

## Local setup (fast)

1. Clone the repository and install dependencies:

```bash
composer install
npm ci
```

2. Copy and configure environment file, then generate the app key:

```bash
cp .env.example .env
php artisan key:generate
# Edit .env to set DB_CONNECTION, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```

3. Run migrations and seeders (development):

```bash
php artisan migrate --force
php artisan db:seed
```

If you prefer to reset and seed from scratch:

```bash
php artisan migrate:fresh --seed
```

4. Run the local server:

```bash
php artisan serve
```

## Tests

Run PHPUnit tests with:

```bash
./vendor/bin/phpunit
```

There are factories and basic feature/unit tests under `tests/Feature` and `tests/Unit`.

## Project structure (high level)

-   `app/Http/Controllers/Api/` — API controllers (thin; delegate to services)
-   `app/Models/` — Eloquent models (User, Category, Transaction, Budget)
-   `app/Http/Requests/` — FormRequest validation classes
-   `app/Services/` — Business logic and aggregations
-   `database/migrations/` — Schema migrations
-   `database/factories/` — Model factories used in tests and seeders
-   `database/seeders/` — Seed data for development
-   `routes/api.php` — API route definitions (protected by Sanctum)

## Authentication

This project uses Laravel Sanctum for API token authentication. Protect API routes with the `auth:sanctum` middleware (already configured in `routes/api.php`).

If you haven't already published and run Sanctum's migrations the `personal_access_tokens` table will be missing. To publish and migrate:

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider" --tag="migrations"
php artisan migrate
```

If `vendor:publish` does not produce the migration file, you can create the standard `personal_access_tokens` migration in `database/migrations/` (copy the standard migration from the Sanctum docs) and run `php artisan migrate`.

## Common troubleshooting

-   Duplicate user email when seeding

    If `php artisan db:seed` fails with a duplicate email error, ensure the seeder creates users idempotently, e.g. using `User::updateOrCreate(['email' => 'test@example.com'], [...])`, or configure factories to generate unique emails with `$faker->unique()->safeEmail`.

-   Factories referencing non-existent classes (e.g. `Foreign::factory()`)

    Some factory files may contain a typo referencing `Foreign::factory()`. Replace those calls with the correct factories, typically `User::factory()` or `Category::factory()` in:

    -   `database/factories/CategoryFactory.php`
    -   `database/factories/TransactionFactory.php`
    -   `database/factories/BudgetFactory.php`

    Run a quick grep to find any remaining occurrences:

    ```bash
    grep -R "Foreign::factory" -n . || true
    ```

-   `personal_access_tokens` table missing

    See the Authentication section above — publish and run Sanctum migrations and confirm with:

    ```bash
    php artisan migrate:status
    ```

-   Authentication errors about interfaces (Authenticatable)

    If you see errors like `Argument #1 ($user) must be of type Illuminate\Contracts\Auth\Authenticatable, App\Models\User given`, make sure your `User` model extends `Illuminate\Foundation\Auth\User` (the `Authenticatable` base class) and uses the `HasApiTokens`, `Notifiable`, and `HasFactory` traits. Example:

    ```php
    use Illuminate\\Foundation\\Auth\\User as Authenticatable;

    class User extends Authenticatable
    {
    		use Laravel\\Sanctum\\HasApiTokens, Illuminate\\Notifications\\Notifiable, Illuminate\\Database\\Eloquent\\Factories\\HasFactory;
    }
    ```

## Recommended development workflow

1. Keep migrations and factories correct and idempotent.
2. Write small unit tests for services under `app/Services`.
3. Protect all API endpoints with `auth:sanctum` and scope data queries to the authenticated user (e.g. `Category::where('user_id', $user->id)`).
4. Use `php artisan migrate:fresh --seed` when you need a clean development DB.

## Contributing

Enhancements and bug fixes are welcome. Please open issues or PRs with focused changes.

When contributing:

-   Add or update tests for new behavior.
-   Keep controller methods thin — move logic into services.
-   Update `docs/FinTrack_Technical_Specification.md` if you change domain behavior or schema.

## Useful commands

```bash
# install dependencies
composer install
npm ci

# environment
cp .env.example .env
php artisan key:generate

# migrate and seed
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed

# run tests
./vendor/bin/phpunit

# serve locally
php artisan serve
```

## License

This project is provided under the MIT license. See the repository `LICENSE` for details.

