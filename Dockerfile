FROM php:8.1-apache
COPY . /var/ww/html/
Run docker-php-ext-intall mysqli pdo pdo_msql
EXPOSE 80
