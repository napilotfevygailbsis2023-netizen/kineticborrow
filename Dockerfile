FROM dunglas/frankenphp

# Install mysqli and other needed extensions
RUN install-php-extensions mysqli pdo pdo_mysql

# Copy app files
COPY . /app
