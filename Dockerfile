FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && apt-get update && apt-get install -y --no-install-recommends cron \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

COPY crontab /etc/cron.d/light-fetch
RUN chmod 0644 /etc/cron.d/light-fetch && crontab /etc/cron.d/light-fetch

COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
