FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libzip-dev libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 bcmath gd intl opcache pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && COMPOSER_ALLOW_SUPERUSER=1 CACHE_STORE=array composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && composer check-platform-reqs --no-dev \
    && ln -s /var/www/html/storage/app/public public/storage \
    && chown -R www-data:www-data storage bootstrap/cache

COPY php-production.ini /usr/local/etc/php/conf.d/zz-portal.ini
RUN printf '[www]\npm = ondemand\npm.max_children = 6\npm.process_idle_timeout = 10s\npm.max_requests = 500\n' > /usr/local/etc/php-fpm.d/zz-portal.conf
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/portal-entrypoint
ENTRYPOINT ["portal-entrypoint"]
CMD ["php-fpm"]

FROM node:24-alpine AS assets
WORKDIR /var/www/html
COPY --from=php-base /var/www/html .
RUN npm ci --ignore-scripts --no-audit --no-fund && npm run build

FROM php-base AS app
COPY --from=assets /var/www/html/public/build /var/www/html/public/build

FROM caddy:2-alpine AS web
COPY Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
