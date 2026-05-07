FROM php:8.2-cli-alpine

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy application files
COPY . /var/www/html/

WORKDIR /var/www/html

# PHP built-in server — reads Railway's $PORT automatically
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-80} -t /var/www/html"]
