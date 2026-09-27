# syntax=docker/dockerfile:1.7

###############################################################################
# Stage 1 — Composer dependencies (cached, no autoload scripts yet)
###############################################################################
FROM composer:2.8 AS vendor
WORKDIR /app

ARG INSTALL_DEV=false

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install \
        $( [ "$INSTALL_DEV" = "true" ] || echo --no-dev ) \
        --no-interaction \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# Copy the full source, then generate an optimized autoloader.
COPY . .
RUN --mount=type=cache,target=/tmp/composer-cache \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer dump-autoload \
        $( [ "$INSTALL_DEV" = "true" ] || echo --no-dev ) \
        --classmap-authoritative \
        --no-scripts \
    && mkdir -p \
        bootstrap/cache \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs

###############################################################################
# Stage 2 — Frontend assets (Vite / Tailwind)
###############################################################################
FROM node:20-alpine AS frontend
WORKDIR /app

# Reverb/Echo connection details are baked into the JS bundle at build time,
# so they must be present here (not just as runtime env in compose).
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY package.json package-lock.json ./
RUN --mount=type=cache,target=/root/.npm npm ci

# app.css imports Flux CSS from vendor/, so the frontend stage needs it.
COPY --from=vendor /app/vendor ./vendor

COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

###############################################################################
# Stage 3 — FrankenPHP runtime
###############################################################################
FROM dunglas/frankenphp:1-php8.3 AS runtime

# PHP extensions required by the app.
RUN install-php-extensions \
        pdo_mysql \
        redis \
        intl \
        zip \
        opcache \
        pcntl

# Production PHP tuning (OPcache + JIT + FrankenPHP worker-friendly).
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /app

# App code + autoloader from the vendor stage.
COPY --from=vendor /app /app

# Built frontend assets.
COPY --from=frontend /app/public/build /app/public/build

# Server config + entrypoint.
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint

RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /data /config

# Run as the non-root www-data user provided by the base image.
USER www-data

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php -r "exit(@file_get_contents('http://127.0.0.1:80/up') ? 0 : 1);"

ENTRYPOINT ["/usr/local/bin/app-entrypoint"]
CMD ["frankenphp", "php-server", "--listen", "0.0.0.0:80", "--root", "/app/public"]
