# Sistema de gimnasio

Gym management application built with Laravel.

Manages clients, trainers, plans, memberships, payments, scheduling, bookings, attendance, exercises and routines.

## Technology Stack

- Laravel (PHP)
- PostgreSQL
- Redis (queues and caching when required)
- Docker
- Filament (administration panel)
- Blade, Tailwind CSS and Alpine.js (web interfaces)
- Pest / PHPUnit (testing)

## Getting Started

1. Install dependencies:

   ```bash
   composer install
   ```

2. Copy the environment file and generate an application key:

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

3. Create the database and run the migrations:

   ```bash
   php artisan migrate
   ```

4. Seed the fixed role catalog and the initial ADMIN user:

   ```bash
   php artisan db:seed
   ```

   The initial ADMIN user is provisioned from the `ADMIN_NAME`, `ADMIN_EMAIL`
   and `ADMIN_PASSWORD` environment variables (see `.env.example`). In
   production these variables are required; outside production, missing values
   fall back to the documented local-dev defaults (`admin@gym.test` /
   `password`).

5. Install and build the frontend assets:

   ```bash
   npm install      # install frontend dependencies
   npm run build    # production build -> public/build/manifest.json + hashed assets
   ```

   For local development with hot module replacement, use:

   ```bash
   npm run dev      # Vite development server
   ```

6. Start the development server:

   ```bash
   php artisan serve
   ```

   - Admin panel: `/admin` (requires ADMIN or TRAINER)
   - Client portal: `/portal` (requires CLIENT)
   - Login page: `/login`

## Testing

```bash
php artisan test
```

## Documentation

- [Product](docs/product/product-definition-v0.1.md)
- [Domain](docs/domain/domain-model-v0.1.md)
- [Architecture](ARCHITECTURE.md)
- [Workflow](docs/workflow/analyst.md)
- [Specifications](docs/specs/)
- [Architecture Decision Records](docs/adr/)

## Development Rules

Development rules and the SDD workflow are documented in [AGENTS.md](AGENTS.md).
