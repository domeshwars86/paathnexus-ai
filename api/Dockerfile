FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

FROM php:8.4-cli AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libpq-dev libxml2-dev \
    && docker-php-ext-install bcmath mbstring pcntl pdo_pgsql xml \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html
COPY --from=dependencies --chown=www-data:www-data /app /var/www/html
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 8002
CMD ["sh", "-c", "php artisan serve --host=0.0.0.0 --port=${PORT:-8002}"]
