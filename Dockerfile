FROM composer:2.5.4 AS build-php

WORKDIR /app
COPY . ./

ADD https://github.com/mlocati/docker-php-extension-installer/releases/download/2.0.2/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
  && install-php-extensions gd \
  && composer install --optimize-autoloader --no-interaction --no-progress --no-ansi \
  && composer dump-autoload --optimize

FROM trafex/php-nginx:3.0.0 AS serve

USER root

WORKDIR /app

COPY --chown=nginx config/default.conf /etc/nginx/conf.d/default.conf

RUN apk add --no-cache gcompat=1.1.0-r0 php81-gd php81-zip php81-mysqli php81-sqlite3 php81-gmp php81-bcmath \
  && rm -rf /var/www/html \
  && rm -rf /var/cache/apk/* \
  && mkdir -p /app \
  && chown -R nginx:nginx /app

COPY --chown=nginx --from=build-php /app .

RUN sed -i 's/;extension=gd/extension=gd/' /etc/php81/php.ini \
  && sed -i 's/;extension=zip/extension=zip/' /etc/php81/php.ini \
  && sed -i 's/;extension=mysqli/extension=mysqli/' /etc/php81/php.ini \
  && sed -i 's/;extension=sqlite3/extension=sqlite3/' /etc/php81/php.ini \
  && sed -i 's/;extension=gmp/extension=gmp/' /etc/php81/php.ini \
  && sed -i 's/;extension=bcmath/extension=bcmath/' /etc/php81/php.ini \
  && chmod -R 755 . \
  && chown -R nginx:nginx .

USER nobody

EXPOSE 80
