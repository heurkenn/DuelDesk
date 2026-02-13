FROM php:8.3-fpm-alpine

RUN set -eux; \
    apk add --no-cache \
      freetype \
      libjpeg-turbo \
      libpng \
      libcurl \
      ca-certificates; \
    apk add --no-cache --virtual .build-deps \
      freetype-dev \
      libjpeg-turbo-dev \
      libpng-dev \
      curl-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" pdo_mysql; \
    docker-php-ext-install -j"$(nproc)" gd; \
    docker-php-ext-install -j"$(nproc)" curl; \
    docker-php-ext-enable opcache; \
    apk del .build-deps

COPY php.ini /usr/local/etc/php/conf.d/zz-dueldesk.ini
COPY entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

WORKDIR /var/www/html
