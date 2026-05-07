FROM php:8.2-fpm-alpine

# Install nginx and supervisor
RUN apk add --no-cache nginx supervisor

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy nginx template, supervisor config, and startup script
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /start.sh

# Copy application files
COPY . /var/www/html/

# Setup directories and permissions
RUN mkdir -p /run/nginx \
    && chown -R www-data:www-data /var/www/html \
    && chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
