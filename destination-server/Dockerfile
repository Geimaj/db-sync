FROM postgres:12-alpine

COPY sshd_config /etc/ssh/sshd_config
COPY entrypoint.sh /entrypoint.sh

# install php to process script and setup sftp
RUN apk update && \
    apk upgrade && \
    apk add vim openssh-server php7 php7-openssl php7-dev php7-zip \
            php7-json php7-pear php-pgsql \
            libzip libzip-dev build-base && \ 
    pecl channel-update pecl.php.net && \
    # create user and setup sftp dir
    mkdir -p /ftp/test && \
    echo -e "testpassword\ntestpassword" | adduser -h /ftp/test -s /bin/bash test && \
    chown test:test /ftp/test && \
    # load environment vars in php
    sed -i 's/variables_order = "GPCS"/variables_order = "GPCSE"/' /etc/php7/php.ini

EXPOSE 22
EXPOSE 5050
ENTRYPOINT /entrypoint.sh