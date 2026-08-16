# Vital Gym

Sistema de gestión de gimnasios construido con Laravel, PostgreSQL y Filament.

Gestiona clientes, planes, membresías, pagos, turnos, reservas, asistencia,
ejercicios y rutinas, con un panel de administración y un portal para clientes.

## Live Demo

Probá la aplicación en línea: **https://gym.ignagrego.online**

| Rol | Email | Contraseña | Acceso |
| --- | --- | --- | --- |
| Admin demo | `admin@gym.com` | `Admin123!` | `/admin` |
| Cliente demo | `cliente@gym.com` | `Cliente123!` | `/portal` |

> La instancia es una **demo**: se resetea automáticamente cada 24 horas, así que
> podés experimentar libremente (crear clientes, planes, membresías, cuotas,
> etc.). Los datos no son reales y se descartan con cada reset. El panel de
> admin es rate-limited (máx. 120 peticiones/min por IP).

## Características

- **Panel de administración** (Filament): gestión de clientes, planes, membresías, pagos, turnos, reservas, asistencia, ejercicios y rutinas
- **Portal de clientes**: ver membresías, pagos, asistencia, reservar turnos, consultar rutina y registrar entrenamientos
- **Roles**: ADMIN, TRAINER, CLIENT con políticas de autorización server-side
- **Registro y aprobación** de clientes
- **Todo en español**

## Technology Stack

- Laravel (PHP)
- PostgreSQL
- Docker (imágenes multi-stage, producción con Nginx + PHP-FPM)
- Filament (panel de administración)
- Blade, Tailwind CSS y Alpine.js (interfaces web)
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

La suite incluye tests de features y de política de autorización (Pest), con
más de 500 casos que cubren caminos felices, validación, reglas de negocio,
autorización y casos de error.

## Documentation

- [Product](docs/product/product-definition-v0.1.md)
- [Domain](docs/domain/domain-model-v0.1.md)
- [Architecture](ARCHITECTURE.md)
- [Workflow](docs/workflow/analyst.md)
- [Specifications](docs/specs/)
- [Architecture Decision Records](docs/adr/)

## Development Rules

Development rules and the SDD workflow are documented in [AGENTS.md](AGENTS.md).
