FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build


FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-scripts


FROM php:8.4-cli-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache bash icu-libs libpq libzip oniguruma unzip \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev oniguruma-dev \
    && docker-php-ext-install bcmath intl mbstring opcache pcntl pdo_pgsql pgsql zip \
    && apk del .build-deps

COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build
COPY . .

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PORT=8080

EXPOSE 8080

CMD ["sh", "-lc", "php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]