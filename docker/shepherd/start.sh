#!/bin/sh
set -eu
if [ "${SHEPHERD_SKIP_MIGRATION:-0}" != 1 ]; then
    php /opt/shepherd/migrate.php
fi
cp -R /opt/shepherd/core-plugins/. /var/www/html/shepherd/plugins/core/
exec frankenphp run --config /etc/caddy/Caddyfile
