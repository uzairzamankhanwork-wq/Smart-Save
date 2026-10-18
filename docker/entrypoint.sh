#!/bin/sh
set -eu

port="${PORT:-10000}"
case "$port" in
    ''|*[!0-9]*)
        echo "PORT must be a number." >&2
        exit 1
        ;;
esac

sed -i "s/{{PORT}}/${port}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

if [ -z "${APP_URL:-}" ] && [ -n "${RENDER_EXTERNAL_HOSTNAME:-}" ]; then
    export APP_URL="https://${RENDER_EXTERNAL_HOSTNAME}"
fi

php artisan migrate --force

if [ -n "${SMARTSAVE_BOOTSTRAP_ADMIN_PASSWORD:-}" ]; then
    : "${SMARTSAVE_BOOTSTRAP_ADMIN_NAME:?Set the bootstrap admin name in Render environment variables.}"
    : "${SMARTSAVE_BOOTSTRAP_ADMIN_EMAIL:?Set the bootstrap admin email in Render environment variables.}"
    php artisan smartsave:create-admin \
        --bootstrap \
        --name="$SMARTSAVE_BOOTSTRAP_ADMIN_NAME" \
        --email="$SMARTSAVE_BOOTSTRAP_ADMIN_EMAIL"
fi

unset SMARTSAVE_BOOTSTRAP_ADMIN_NAME SMARTSAVE_BOOTSTRAP_ADMIN_EMAIL SMARTSAVE_BOOTSTRAP_ADMIN_PASSWORD

php artisan optimize

exec "$@"
