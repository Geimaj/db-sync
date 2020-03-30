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

# install sqlsrv and zip
RUN chmod a+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions sqlsrv pdo_sqlsrv zip 
