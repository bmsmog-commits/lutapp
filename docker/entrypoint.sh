#!/bin/sh
# Lutapp container entrypoint.
#
# Runs at container START (not build) because Laravel's config cache bakes in
# environment variables, which only exist at runtime. Secrets are never in
# the image: they arrive as environment variables from the host platform.
#
# Deliberately NOT done here: database migrations. Run them once per release
# as a one-off command (see docs/deployment.md), so several replicas starting
# together can never race on the schema.
set -e

if [ -z "${APP_KEY:-}" ]; then
    echo "entrypoint: APP_KEY is not set; refusing to start (see docs/deployment.md)." >&2
    exit 1
fi

# Persistent storage is usually a mounted volume that starts empty.
mkdir -p storage/app/public storage/app/private \
    storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs

if [ "${LARAVEL_SKIP_OPTIMIZE:-false}" != "true" ]; then
    php artisan optimize
fi

exec "$@"
