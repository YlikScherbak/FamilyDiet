# syntax=docker/dockerfile:1
# Production-образ для одного контейнера (Fly.io): nginx + php-fpm під supervisord.
# nginx роздає зібраний Vue SPA і проксує /api та /api/doc у Symfony.

# ---------- 1. Фронтенд ----------
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ .
RUN npm run build

# ---------- 2. PHP-залежності ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --no-progress

# ---------- 3. Runtime ----------
FROM php:8.4-fpm-alpine AS runtime

RUN apk add --no-cache icu-dev libpq-dev nginx supervisor postgresql-client \
    && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql opcache \
    && apk del libpq-dev icu-dev \
    && apk add --no-cache icu-libs libpq

COPY docker/prod/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/prod/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/prod/supervisord.conf /etc/supervisord.conf
COPY docker/prod/init-db.sh /usr/local/bin/init-db
RUN chmod +x /usr/local/bin/init-db

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    DEFAULT_URI=https://familydiet.fly.dev

WORKDIR /var/www/app
COPY backend/ ./
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/dist /var/www/spa

# Прогрів кешу під prod: реального з'єднання з БД не потрібно, але DATABASE_URL має бути валідним рядком.
RUN DATABASE_URL="postgresql://app:app@127.0.0.1:5432/familydiet?serverVersion=16&charset=utf8" APP_SECRET=build \
    php bin/console cache:warmup --no-interaction \
    && php bin/console assets:install public --no-interaction \
    && chown -R www-data:www-data var

EXPOSE 8080
CMD ["supervisord", "-c", "/etc/supervisord.conf"]
