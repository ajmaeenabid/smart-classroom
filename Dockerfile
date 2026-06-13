FROM php:8.2-apache

# Install MySQL PDO extension
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite (needed for .htaccess)
RUN a2enmod rewrite

# Allow .htaccess overrides
RUN sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Copy all project files into the web root
COPY . /var/www/html/

# Fix .htaccess RewriteBase for root deployment
RUN sed -i 's|RewriteBase /advance_classroom/|RewriteBase /|' /var/www/html/.htaccess

# Give Apache write permission on uploads folder
RUN chown -R www-data:www-data /var/www/html/uploads

EXPOSE 80
