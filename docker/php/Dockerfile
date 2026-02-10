FROM php:8.3-fpm-alpine

RUN set -eux; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql; \
    docker-php-ext-enable opcache

COPY php.ini /usr/local/etc/php/conf.d/zz-dueldesk.ini
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

WORKDIR /var/www/html
