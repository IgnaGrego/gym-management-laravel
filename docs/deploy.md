# Deploy Plan — El Area Gym en VPS (patrón portfolio)

## 1. Objetivo

Subir El Area Gym a un VPS como **primer proyecto de un portfolio** de varios
repositorios personales. El plan contempla que más adelante se agregarán otros
proyectos al mismo VPS, por lo que la arquitectura usa el patrón de
**Nginx central + apps independientes** en lugar de aislar cada proyecto con su
propio Nginx.

## 2. Infraestructura objetivo

### 2.1 VPS

| Recurso | Valor | Nota |
| --- | --- | --- |
| CPU | 1 vCPU | Suficiente para tráfico bajo |
| RAM | 2 GB | Holgado para 3-5 apps livianas compartiendo recursos |
| Disco | 50 GB NVMe | Sobra |
| OS | Ubuntu 22.04 / 24.04 LTS | Recomendado |
| Bandwidth | Unmetered | Ideal para portfolio |

Si el portfolio supera ~5 proyectos con DB propias, escalar a 4 GB RAM.

### 2.2 Dominio y subdominios

Un solo dominio (ej: `tudominio.com`) con subdominios por proyecto:

```
gym.tudominio.com      → El Area Gym
portafolio.tudominio.com → otro proyecto
demo.tudominio.com     → otro proyecto
```

Los subdominios se crean como registros DNS `A` apuntando a la IP del VPS.

### 2.3 Arquitectura multi-proyecto

```
                NGINX central (único, puerto 80/443)
               /          |          \
      gym.tu.com     demo.tu.com    portafolio.tu.com
           │              │              │
         app-gym       app-demo      app-portafolio
           │              │
         postgres      (puede compartir postgres
       (propia o        o usar otra)
        compartida)
```

Reglas:

- **Un único Nginx** (reverse proxy) reparte por `server_name` (dominio).
- **Cada app en su contenedor** con su propia red o la compartida.
- **PostgreSQL**: opción A (recomendada) una sola instancia con múltiples
  bases de datos; opción B un Postgres por proyecto (más RAM).
- Cada proyecto expone su puerto interno (ej: `app:9000`) solo en la red
  interna; el exterior solo ve Nginx.

## 3. Stack por proyecto (El Area Gym)

| Servicio | Imagen | Rol |
| --- | --- | --- |
| `app` | `gym-management/app` (Dockerfile propio) | Laravel PHP-FPM |
| `migrate` | misma imagen | Corre migraciones + seeder 1 vez |
| `db` | `postgres:16-alpine` | Base de datos |
| `queue` | misma imagen | Cola de trabajos |
| `scheduler` | misma imagen | Tareas programadas (`memberships:expire`) |

Nginx **no vive dentro** del compose de cada proyecto: es el central del VPS.

## 4. Flujo de una petición

```
Navegador → DNS (gym.tu.com → IP VPS) → NGINX central (80/443)
          → identifica server_name → proxy_pass a app:9000
          → Laravel procesa → consulta PostgreSQL → devuelve HTML
```

## 5. Pasos de puesta en producción

### 5.1 En la máquina local (una vez)

1. Subir el repositorio a GitHub (privado si contiene algo sensible).
2. Asegurar que `.env` no esté commiteado (`.gitignore` ya lo excluye).

### 5.2 En el VPS (primera vez)

1. Crear el VPS (Ubuntu) y anotar la IP.
2. Conectar por SSH: `ssh root@IP`.
3. Crear usuario no-root con sudo (buena práctica).
4. Configurar firewall (ufw): permitir SSH (22) y HTTP/HTTPS (80, 443).
5. Instalar Docker Engine + Compose plugin.
6. Clonar el repo: `git clone git@github.com:usuario/repo.git /srv/apps/gym`.
7. Crear `/srv/apps/gym/.env` desde `.env.production.example` y completar:
   - `APP_KEY` (generar con `php artisan key:generate` o `openssl rand -base64 32`)
   - `APP_URL=https://gym.tu.com`
   - `DB_PASSWORD` (fuerte)
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD`
   - credenciales SMTP reales (opcional)
8. `docker compose build` (lento la primera vez, normal).
9. `docker compose up -d`.
10. Verificar: `curl -I http://IP` y `docker compose logs app`.

### 5.3 Nginx central (multi-proyecto)

Instalar Nginx en el host (no en contenedor) o usar un contenedor `nginx-proxy`.
Archivo de sitio por proyecto en `/etc/nginx/sites-available/gym.conf`:

```
server {
    server_name gym.tu.com;
    location / {
        proxy_pass http://127.0.0.1:PORT;  # puerto mapeado del compose
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Cada proyecto del portfolio mapea un puerto distinto en su compose
(`APP_PORT=8081`, `APP_PORT=8082`, ...) y Nginx reparte por dominio.
El compose de este proyecto usa `APP_PORT` (default `8081`).

### 5.4 HTTPS con Certbot

```
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d gym.tu.com
```

Repetir por cada subdominio. Certbot renueva certificados automáticamente.

## 6. Seguridad

- `APP_DEBUG=false` en producción (nunca exponer stack traces).
- `APP_ENV=production`.
- Puerto 5432 (PostgreSQL) **nunca** expuesto al exterior: solo red interna.
- Contraseñas fuertes en `.env`, nunca commiteadas.
- Usuario no-root + sudo en el VPS; deshabilitar login root por password (usar SSH keys).
- Firewall ufw con reglas mínimas.
- En el `.env` de producción las 3 variables `ADMIN_*` son obligatorias
  (AdminUserSeeder aborta si faltan) — ver `.env.production.example`.

## 7. Comandos útiles

```bash
# Levantar / ver logs
docker compose up -d
docker compose logs -f app
docker compose ps

# Reiniciar un servicio
docker compose restart app

# Migraciones (no bloquea: el servicio migrate ya lo hace al arrancar)
docker compose run --rm migrate
```

## 8. Almacenamiento y datos

- `db_data` y `app_storage` son volúmenes Docker: sobreviven reinicios y `up -d`.
- Para backup de Postgres:
  `docker compose exec db pg_dump -U gym gym > backup.sql`
- Restore:
  `cat backup.sql | docker compose exec -T db psql -U gym gym`

## 9. Próximos pasos

1. Comprar VPS + dominio.
2. (Este repo) Ajustar compose para el patrón central si se decide compartir Nginx.
3. Seguir los pasos 5.2-5.4.
4. Agregar los demás proyectos del portfolio siguiendo el mismo patrón.
