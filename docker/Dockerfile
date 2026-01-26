FROM php:8.3-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
  git unzip zip libzip-dev \
  && docker-php-ext-install pdo_mysql zip \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zookr.ini

WORKDIR /var/www/html

# Datadog PHP tracer (ddtrace)
RUN curl -L https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php -o /tmp/datadog-setup.php \
  && php /tmp/datadog-setup.php --php-bin=all \
  && rm -f /tmp/datadog-setup.php
