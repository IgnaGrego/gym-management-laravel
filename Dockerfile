# ─────────────────────────────────────────────────────────────
# Stage 1: Build frontend assets (Node)
# ─────────────────────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci || npm install

COPY . .
RUN npm run build

# ─────────────────────────────────────────────────────────────
# Stage 2: Application (PHP-FPM)
# ─────────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine AS app

WORKDIR /var/www/html

# System deps + PHP extensions required by Laravel/Filament + pgsql
RUN apk add --no-cache \
        git \
        unzip \
        curl \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        postgresql-client \
        postgresql-dev \
        sqlite-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_pgsql \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        intl \
        zip \
        opcache \
    && docker-php-ext-enable opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .

# Composer production deps
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# Publish Filament vendor assets (admin panel CSS/JS) so the panel renders
# (must run after composer install; without it /admin is unstyled/black)
RUN php artisan filament:assets --no-interaction

# Copy compiled frontend assets from the assets stage
COPY --from=assets /app/public/build /var/www/html/public/build

# Storage permissions
RUN mkdir -p storage/framework/cache/data \
    && mkdir -p storage/framework/sessions \
    && mkdir -p storage/framework/views \
    && mkdir -p storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

# Entrypoint (migrate + seed + php-fpm)
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Runtime configuration (opcache tuned for production)
RUN echo 'opcache.enable=1' >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo 'opcache.enable_cli=0' >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo 'opcache.memory_consumption=128' >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo 'opcache.max_accelerated_files=20000' >> $PHP_INI_DIR/conf.d/opcache.ini \
    && echo 'opcache.validate_timestamps=0' >> $PHP_INI_DIR/conf.d/opcache.ini

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
