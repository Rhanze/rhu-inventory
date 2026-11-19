#!/bin/sh
set -eu
umask 077
mkdir -p /tmp/rhu-bootstrap /tmp/rhu-storage/framework/views /tmp/rhu-storage/framework/cache/data /tmp/rhu-storage/framework/sessions /tmp/rhu-storage/logs /tmp/rhu-storage/app/private
php /app/deployment/verify-vercel-environment.php
if [ -n "${MYSQL_SSL_CA_PEM:-}" ]; then
    php /app/deployment/database-ca.php
    export MYSQL_ATTR_SSL_CA=/tmp/rhu-bootstrap/mysql-ca.pem
fi
# Environment-specific caches are created at runtime, never baked with secrets into the image.
php artisan package:discover --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
if [ -n "${DEPLOYMENT_PROBE_MODE:-}" ]; then
    php /app/deployment/verify-services.php
fi
if [ "${DEPLOYMENT_ATTACHMENT_ABSENCE_MODE:-}" = "read" ]; then
    # A bounded read-only diagnostic must never prevent the application starting.
    if ! php /app/deployment/verify-attachment-absence.php; then
        printf '%s\n' 'Attachment absence probe: verification FAIL'
    fi
fi
exec "$@"
