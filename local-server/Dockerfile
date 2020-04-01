FROM php:7.4-fpm

# include php-extension-installer script
ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

# install odbc for sql server
RUN apt-get update && apt-get install -y gnupg && \
    curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    curl https://packages.microsoft.com/config/ubuntu/18.04/prod.list > /etc/apt/sources.list.d/mssql-release.list && \
    apt-get update -y && \
    ACCEPT_EULA=Y apt-get install -y msodbcsql17 && \
    ACCEPT_EULA=Y apt-get install -y mssql-tools && \
    echo 'export PATH="$PATH:/opt/mssql-tools/bin"' >> ~/.bash_profile && \
    echo 'export PATH="$PATH:/opt/mssql-tools/bin"' >> ~/.bashrc && \
    # optional: for unixODBC development headers
    apt-get install -y unixodbc-dev

# install sqlsrv and zip php extensions
RUN chmod a+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions sqlsrv pdo_sqlsrv zip ssh2

# install php composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === 'e0012edf3e80b6978849f5eff0d4b4e4c79ff1609dd1e613307e16318854d24ae64f26d17af3ef0bf7cfb710ca74755a') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php --filename=composer --install-dir=/usr/local/bin && \
    php -r "unlink('composer-setup.php');"


CMD cd /app/src && \
    composer install --no-dev --no-interaction --optimize-autoloader && \
    php start.php


