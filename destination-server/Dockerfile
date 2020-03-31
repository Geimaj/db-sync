FROM postgres:12-alpine

COPY sshd_config /etc/ssh/sshd_config
COPY entrypoint.sh /entrypoint.sh

# install php to process script and setup sftp
RUN apk update && \
    apk upgrade && \
    apk add php7 openssh-server && \
    # create user
    mkdir -p /ftp/test && \
    echo -e "testpassword\ntestpassword" | adduser -h /ftp/test -s /bin/bash test && \
    chown test:test /ftp/test

EXPOSE 22
ENTRYPOINT /entrypoint.sh