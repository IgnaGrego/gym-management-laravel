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
| CPU | 2 vCPU | Suficiente para tráfico bajo |
| RAM | 4 GB | Holgado para 3-5 apps livianas compartiendo recursos |
| Disco | 100 GB NVMe | Sobra con margen |
| OS | Ubuntu 24.04 LTS | Recomendado |
| Bandwidth | 4 TB | Más que suficiente para portfolio |

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
| `nginx` | `nginx:1.27-alpine` | Sirve la app; escucha el puerto `APP_PORT` |

En este proyecto el **Nginx vive dentro del compose** y publica el puerto
`APP_PORT` (default `8081`). Si más adelante se agregan más proyectos del
portfolio, se puede poner un **Nginx central en el host** que haga de reverse
proxy hacia el `APP_PORT` de cada proyecto (ver §5.3).

## 4. Flujo de una petición

Modo actual (IP + puerto):

```
Navegador → http://IP:APP_PORT → Nginx del compose (puerto 80 interno)
          → try_files → fastcgi a app:9000 (PHP-FPM)
          → Laravel procesa → consulta PostgreSQL → devuelve HTML
```

Modo futuro (dominio + Nginx central):

```
Navegador → DNS (gym.tu.com → IP VPS) → NGINX central (80/443)
          → identifica server_name → proxy_pass a 127.0.0.1:APP_PORT
          → Nginx del proyecto → Laravel → PostgreSQL → HTML
```

## 5. Pasos de puesta en producción

### 5.1 En la máquina local (una vez)

1. Subir el repositorio a GitHub (privado si contiene algo sensible).
2. Asegurar que `.env` no esté commiteado (`.gitignore` ya lo excluye).

### 5.2 En el VPS (primera vez)

1. Crear el VPS (Ubuntu 24.04 LTS) y anotar la IP.
2. Conectar por SSH: `ssh root@IP`.
3. Crear usuario no-root con sudo (buena práctica, ver §6).
4. Configurar firewall (ufw): permitir SSH (22) y HTTP/HTTPS (80, 443), y el
   puerto de la app (`ufw allow 8081`) si se va a acceder por IP + puerto.
5. Instalar Docker Engine + Compose plugin:
   `curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh`
6. Clonar el repo: `git clone https://github.com/usuario/repo.git /srv/apps/gym`.
7. Crear `/srv/apps/gym/.env` desde `.env.production.example` y completar:
   - `APP_KEY` (generar con `openssl rand -base64 32`, usar formato `base64:...`)
   - `APP_URL` → **http://IP:APP_PORT** si se accede por IP (¡incluir el puerto!),
     o `https://gym.tu.com` una vez configurado el dominio
   - `DB_PASSWORD` (fuerte)
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD`
   - credenciales SMTP reales (opcional)
8. `docker compose up -d --build` (lento la primera vez, normal).
9. Verificar: `curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:APP_PORT`
   (debe dar `200`) y `docker compose ps`.
10. Publicar los assets del admin (Filament) en el host, porque el Nginx lee
    los archivos desde el host (montado `./`), no desde la imagen:
    ```bash
    docker compose exec --user root app php artisan filament:assets
    docker cp gym-management-app-1:/var/www/html/public/css /srv/apps/gym/public/css
    docker cp gym-management-app-1:/var/www/html/public/js  /srv/apps/gym/public/js
    ```
    > El `--user root` es necesario porque el contenedor corre como `www-data`
    > y no puede escribir en `public/` (error "Permission denied").
11. Acceder por navegador: `http://IP:APP_PORT` y `/admin`.

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

# Forzar recreación de un contenedor (tras cambiar una config montada)
docker compose up -d --force-recreate nginx

# Migraciones (no bloquea: el servicio migrate ya lo hace al arrancar)
docker compose run --rm migrate

# Limpiar caches de Laravel (tras cambiar el .env o el código)
docker compose exec app php artisan optimize:clear
```

### Usuarios (tinker)

```bash
# Cambiar la contraseña del primer usuario ADMIN
docker compose exec app php artisan tinker --execute="
  \$u = App\Models\User::whereHas('roles', fn(\$q) => \$q->where('name','ADMIN'))->first();
  \$u->update(['password'=>bcrypt('NuevaPassword123!')]);
  echo \$u->email;"

# Crear un usuario CLIENT de prueba
docker compose exec app php artisan tinker --execute="
  \$user = App\Models\User::firstOrCreate(['email'=>'cliente@gym.com'],
    ['name'=>'Cliente Prueba','password'=>bcrypt('Cliente123!'),'is_active'=>true]);
  \$user->roles()->syncWithoutDetaching(App\Models\Role::where('name','CLIENT')->first());
  echo 'CLIENT: '.\$user->email;"
```

> Los roles posibles son `ADMIN`, `TRAINER`, `CLIENT`. El panel admin está en
> `/admin`; el portal del cliente en `/portal`.

## 8. Almacenamiento y datos

- `db_data` y `app_storage` son volúmenes Docker: sobreviven reinicios y `up -d`.
- Para backup de Postgres:
  `docker compose exec db pg_dump -U gym gym > backup.sql`
- Restore:
  `cat backup.sql | docker compose exec -T db psql -U gym gym`

## 9. Troubleshooting (errores encontrados en el deploy real)

Problemas reales y sus soluciones, para no repetirlos en deploy limpios.

### 9.1 `configure: error: Cannot find libpq-fe.h` / `pg_config... not found`

Al construir la imagen (`Dockerfile`), PHP no puede compilar `pdo_pgsql`.
**Causa:** falta el paquete de desarrollo. En Alpine se usa **`postgresql-dev`**.
**Además** mantener **`postgresql-client`**: el `entrypoint.sh` usa `pg_isready`
para esperar a PostgreSQL, y ese binario viene del paquete `client`, no del `dev`.
→ Se necesitan **ambos**.

### 9.2 `configure: error: Package 'sqlite3' not found`

**Causa:** `pdo_sqlite` requiere el paquete de desarrollo **`sqlite-dev`** en Alpine.
→ Agregar `sqlite-dev` a los `apk add` del `Dockerfile`.

### 9.3 `migrate` se queda en "Waiting for PostgreSQL..." para siempre

El contenedor `migrate` espera horas. **Causa:** el comando `pg_isready` no
existe porque se quitó `postgresql-client` (ver §9.1). El `until` nunca es true.
→ Garantizar `postgresql-client` en la imagen.

### 9.4 `Unable to prepare route [login] for serialization`

Las rutas GET y POST de `/login` tenían **ambas** el nombre `login`. Laravel
no permite nombres de ruta duplicados al cachear.
→ Renombrar la POST a `login.store` (GET conserva `login`).

### 9.5 Admin (Filament) se ve en negro y desloguea

**Dos causas posibles, verificar ambas:**

a) **Assets de Filament no publicados.** El panel no tiene CSS/JS. Fix:
   `docker compose exec --user root app php artisan filament:assets` y copiar
   al host (ver §5.2 paso 10). El `Dockerfile` ya incluye
   `php artisan filament:assets` para el próximo build.

b) **`APP_URL` mal configurada.** Si `APP_URL=https://IP` (o sin el puerto),
   los assets se piden en HTTPS/no existe el puerto → 404.
   → Usar `APP_URL=http://IP:APP_PORT` (con `http://` y el puerto).

### 9.6 `livewire.min.js` da 404 (admin en negro)

**Causa:** el `default.conf` de Nginx tiene una regla de assets estáticos
`location ~* \.(css|js|...)$` que **gana** sobre `location /livewire/`
(nginx prioriza las regex sobre los prefijos). Como `livewire.min.js` termina
en `.js`, se trata como archivo estático inexistente.
→ Usar **`location ^~ /livewire/`** — el `^~` fuerza al prefijo a ganar sobre
la regex. El `default.conf` ya tiene la corrección.

### 9.7 Los cambios de `default.conf` no surten efecto

Docker reutiliza el contenedor existente sin re-montar la config cambiada.
→ Usar `docker compose up -d --force-recreate nginx`.

## 10. Próximos pasos

1. Comprar VPS + dominio.
2. (Este repo) Ajustar compose para el patrón central si se decide compartir Nginx.
3. Seguir los pasos 5.2-5.4.
4. Agregar los demás proyectos del portfolio siguiendo el mismo patrón.
