FROM node:24-bookworm-slim@sha256:a5f4366e834695d5f363b920c104953a535d3a9864d1bdf74566a0ebe01daee5 AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN --mount=type=secret,id=trusted_ca \
    if [ -f /run/secrets/trusted_ca ]; then export NODE_EXTRA_CA_CERTS=/run/secrets/trusted_ca; fi; npm ci
COPY . .
RUN npm run build:frontend

FROM dunglas/frankenphp:1-php8.4@sha256:b32a81ad4db40b528ada04087f47e22833f260266e73b3d88c62fd54e2a29aa6 AS php-base
LABEL org.opencontainers.image.title="Shepherd" \
      org.opencontainers.image.description="Shepherd on ChurchCRM 7.8.1" \
      org.opencontainers.image.version="7.8.1" \
      org.opencontainers.image.source="https://github.com/JDubb17WRX/Shepherd"

RUN apt-get update && apt-get install -y --no-install-recommends \
        gettext locales-all libcurl4-openssl-dev libfreetype6-dev libicu-dev \
        libjpeg-dev libonig-dev libpng-dev libxml2-dev libzip-dev \
    && install-php-extensions bcmath curl exif gd gettext iconv intl mbstring mysqli opcache pdo_mysql sodium xml zip \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf '%s\n' 'upload_max_filesize=10M' 'post_max_size=12M' 'memory_limit=256M' 'max_execution_time=120' >> "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

FROM php-base AS application
COPY --from=composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac /usr/bin/composer /usr/local/bin/composer
COPY --from=frontend /build /build
WORKDIR /build/src
RUN --mount=type=secret,id=trusted_ca \
    if [ -f /run/secrets/trusted_ca ]; then export COMPOSER_CAFILE=/run/secrets/trusted_ca; fi; composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
RUN find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l > /tmp/shepherd-php-lint.log

FROM php-base

WORKDIR /var/www/html/shepherd
COPY --from=application --chown=www-data:www-data /build/src/ ./
COPY --chown=www-data:www-data docker/shepherd/Config.php ./Include/Config.php
COPY --chown=www-data:www-data docker/shepherd/healthz.php ./healthz.php
COPY docker/shepherd/Caddyfile /etc/caddy/Caddyfile
COPY --from=application --chown=www-data:www-data /build/src/plugins/core/ /opt/shepherd/core-plugins/
COPY docker/shepherd/migrate.php /opt/shepherd/migrate.php
COPY --chmod=755 docker/shepherd/start.sh /opt/shepherd/start.sh

RUN php -l healthz.php > /dev/null \
    && mkdir -p Images/Person Images/Family uploads logs tmp_attach SQL /data /config \
    && chown -R www-data:www-data Images uploads logs tmp_attach SQL plugins /data /config

ENV XDG_DATA_HOME=/data \
    XDG_CONFIG_HOME=/config \
    CHURCHCRM_URL_REWRITING=1

USER www-data
EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
  CMD php -r '$s=@fsockopen("127.0.0.1",8080,$e,$m,3); if(!$s){exit(1);} fwrite($s,"GET /shepherd/healthz HTTP/1.0\r\nHost: localhost\r\n\r\n"); $r=stream_get_contents($s); fclose($s); exit(str_contains($r,"200 OK")?0:1);'
CMD ["/opt/shepherd/start.sh"]
