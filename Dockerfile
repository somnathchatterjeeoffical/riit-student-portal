FROM php:8.2-apache

# Install and enable mysqli extension for MySQL database connectivity
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files to Apache document root
COPY . /var/www/html/

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads && chown -R www-data:www-data /var/www/html/uploads && chmod -R 775 /var/www/html/uploads

# Configure Apache port
ENV PORT=80
EXPOSE 80

CMD ["apache2-foreground"]
