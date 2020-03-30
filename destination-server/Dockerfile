FROM postgres:12-alpine

COPY vsftpd.conf /etc/vsftpd/vsftpd.conf

# install php to process script and setup sftp
RUN apk update && \
    apk upgrade && \
    apk add php7 vsftpd openrc && \
    # create user
    mkdir -p /ftp/test && \
    echo -e "testpassword\ntestpassword" | adduser -h /ftp/test -s /bin/bash test && \
    chown test:test /ftp/test 
    # rc-service vsftpd start && \
    # rc-update add vsftpd

CMD vsftpd -opasv_min_port=21000 -opasv_max_port=21010 /etc/vsftpd/vsftpd.conf
