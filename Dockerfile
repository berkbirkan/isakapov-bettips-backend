FROM php:7.4-apache

# Fix locale problem
RUN apt-get update && apt-get install locales locales-all -y

ENV LC_ALL en_US.UTF-8
ENV LANG en_US.UTF-8
ENV LANGUAGE en_US.UTF-8

COPY ./app /var/www/html

# Apache mod_rewrite headers extensions
RUN a2enmod rewrite
RUN a2enmod headers

# Php config install extensions
RUN apt-get update && apt-get install -y \
        libicu-dev \
        libzip-dev \
        zip \
        unzip \
        --no-install-recommends && \
    docker-php-ext-configure intl &&  \
    docker-php-ext-install intl mysqli pdo pdo_mysql && \
    docker-php-ext-enable mysqli pdo pdo_mysql
