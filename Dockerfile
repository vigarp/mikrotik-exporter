FROM php:8.2-cli-alpine

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY routeros_api.class.php .
COPY index.php .

# Expose HTTP port
EXPOSE 8080

# Run PHP Built-in Server
CMD ["php", "-S", "0.0.0.0:8080", "index.php"]
