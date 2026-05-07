FROM php:8.2-fpm-alpine

# Install nginx and supervisor (to run both services)
RUN apk add --no-cache nginx supervisor

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy nginx and supervisor config
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy application files
COPY . /var/www/html/

# Create nginx run dir and fix permissions
RUN mkdir -p /run/nginx \
    && chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
