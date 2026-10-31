#!/bin/sh
set -eu
umask 077
mkdir -p /tmp/rhu-bootstrap /tmp/rhu-storage/framework/views /tmp/rhu-storage/framework/cache/data /tmp/rhu-storage/framework/sessions /tmp/rhu-storage/logs /tmp/rhu-storage/app/private
php /app/deployment/verify-vercel-environment.php
# Environment-specific caches are created at runtime, never baked with secrets into the image.
php artisan package:discover --no-interaction
php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
exec "$@"
