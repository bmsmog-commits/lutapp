# syntax=docker/dockerfile:1
#
# Lutapp production image — FrankenPHP (Caddy + PHP 8.3) serving public/.
#
# No secrets are baked in: .env is excluded by .dockerignore, and all
# configuration (APP_KEY, DB_*, API_BIBLE_API_KEY, MAIL_*, …) is supplied as
# environment variables at runtime. See docs/deployment.md for the release
# process (migrations, scheduler, storage volume).

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22

# ---------------------------------------------------------------------------
# Base: the PHP runtime shared by the build and final stages, so Composer
# resolves and autoloads against exactly the PHP + extensions that run it.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION} AS base

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        bcmath \
        intl \
        zip \
        opcache

WORKDIR /app

# ---------------------------------------------------------------------------
# Composer dependencies (production only, from composer.lock).
# ---------------------------------------------------------------------------
FROM base AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --no-autoloader \
        --prefer-dist

COPY . .

# Release gate: fail the build if secrets or data slipped past .dockerignore.
RUN leaked="$(find . -path ./vendor -prune -o -type f \( \
            -name '.env' -o \( -name '.env.*' ! -name '.env.example' \) \
            -o -name '*.sqlite' -o -name '*.sqlite3' -o -name '*.sql' -o -name '*.sql.gz' \
            -o -name '*.dump' -o -name '*.pem' -o -name '*.key' \) -print)" \
    && if [ -n "$leaked" ]; then echo "Refusing to build; excluded files reached the image:" >&2; echo "$leaked" >&2; exit 1; fi

RUN composer dump-autoload --no-dev --optimize \
    && php artisan package:discover --ansi

# ---------------------------------------------------------------------------
# Frontend assets — same Node major as CI, from package-lock.json.
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

# Tolerate transient registry/network failures. Versions and integrity hashes
# still come exclusively from package-lock.json.
ENV npm_config_fetch_retries=5 \
    npm_config_fetch_retry_mintimeout=20000 \
    npm_config_fetch_retry_maxtimeout=120000

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js ./
COPY resources ./resources
# Tailwind scans Laravel's pagination views (resources/css/app.css @source).
COPY --from=vendor /app/vendor/laravel/framework/src/Illuminate/Pagination/resources/views \
     ./vendor/laravel/framework/src/Illuminate/Pagination/resources/views
RUN npm run build

# ---------------------------------------------------------------------------
# Runtime.
# ---------------------------------------------------------------------------
FROM base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    SERVER_NAME=:8080 \
    XDG_CONFIG_HOME=/config \
    XDG_DATA_HOME=/data

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/conf.d/zz-production.ini "$PHP_INI_DIR/conf.d/zz-production.ini"
COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/lutapp-entrypoint

COPY --from=vendor /app /app
COPY --from=assets /app/public/build /app/public/build

# Only storage/ and bootstrap/cache/ are writable by the app; Caddy needs its
# own config/data dirs. Everything else stays root-owned and read-only.
RUN mkdir -p storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs bootstrap/cache /config/caddy /data/caddy \
    && chown -R root:root /app \
    && chown -R www-data:www-data storage bootstrap/cache /config /data \
    && chmod -R ug+rwX storage bootstrap/cache \
    && php artisan storage:link --no-interaction

USER www-data

EXPOSE 8080

# Laravel's built-in health route (bootstrap/app.php: health: '/up').
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up > /dev/null || exit 1

ENTRYPOINT ["lutapp-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
