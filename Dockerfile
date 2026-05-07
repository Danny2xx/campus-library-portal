FROM php:8.2-cli-alpine

# Install PDO MySQL extension
RUN docker-php-ext-install pdo pdo_mysql

# Copy application files
COPY . /var/www/html/

WORKDIR /var/www/html

# Railway injects $PORT — PHP listens on it directly. No EXPOSE to avoid port mismatch.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t /var/www/html"]
