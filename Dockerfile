FROM php:8.4-apache

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev libonig-dev unzip git \
    && docker-php-ext-install pdo_mysql intl opcache zip mbstring \
    && pecl install apcu && docker-php-ext-enable apcu \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini

RUN echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-available/security-hardening.conf \
    && a2enconf security-hardening \
    && groupadd -g ${GID} app && useradd -u ${UID} -g ${GID} -m app \
    && sed -ri 's/^User .*/User app/;s/^Group .*/Group app/' /etc/apache2/apache2.conf 2>/dev/null || true \
    && echo "export APACHE_RUN_USER=app\nexport APACHE_RUN_GROUP=app" >> /etc/apache2/envvars

WORKDIR /var/www/html
