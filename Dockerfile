# Use official PHP + Apache image
FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy project files into web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Install Composer (optional)
RUN apt-get update && apt-get install -y unzip git \
    && curl -sS https://getcomposer.org/installer \
    | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies (if composer.json exists)
RUN if [ -f composer.json ]; then composer install; fi
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
