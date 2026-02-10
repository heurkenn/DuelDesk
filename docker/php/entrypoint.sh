#!/bin/sh
set -eu

UPLOAD_DIR="/var/www/html/public/uploads"

mkdir -p "$UPLOAD_DIR/games"

# Named volumes start as root-owned; make it writable for php-fpm's user.
chown -R www-data:www-data "$UPLOAD_DIR" 2>/dev/null || true
chmod -R u+rwX,g+rwX "$UPLOAD_DIR" 2>/dev/null || true

exec docker-php-entrypoint php-fpm
