#!/bin/sh
# Use Railway's PORT env var, fall back to 80 for local dev
PORT=${PORT:-80}

# Inject the port into nginx config (sed avoids envsubst escaping issues)
sed "s/__PORT__/$PORT/g" /etc/nginx/nginx.conf > /etc/nginx/nginx.conf.bak \
    && mv /etc/nginx/nginx.conf.bak /etc/nginx/nginx.conf

echo "Starting with PORT=$PORT"

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
