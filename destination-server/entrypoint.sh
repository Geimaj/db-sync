#!/usr/bin/env bash
set -e

# Generate unique ssh keys for this container, if needed
if [ ! -f /etc/ssh/ssh_host_ed25519_key ]; then
    ssh-keygen -t ed25519 -f /etc/ssh/ssh_host_ed25519_key -N ''
fi
if [ ! -f /etc/ssh/ssh_host_rsa_key ]; then
    ssh-keygen -t rsa -b 4096 -f /etc/ssh/ssh_host_rsa_key -N ''
fi

# start sshd
exec /usr/sbin/sshd -D -e -c /etc/ssh/sshd_config