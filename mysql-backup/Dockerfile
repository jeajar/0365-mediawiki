# mysql backup image
FROM alpine:3.9

# the mysql-client must be 10.3.15 or later
RUN apk add --update 'mariadb-client>10.3.15' mariadb-connector-c bash python3 samba-client shadow openssl && \
    rm -rf /var/cache/apk/* && \
    touch /etc/samba/smb.conf && \
    pip3 install awscli

# set us up to run as non-root user
RUN groupadd -g 1005 appuser && \
    useradd -r -u 1005 -g appuser appuser
# ensure smb stuff works correctly
RUN mkdir -p /var/cache/samba && chmod 0755 /var/cache/samba && chown appuser /var/cache/samba
USER appuser

# install the entrypoint
COPY functions.sh /
COPY entrypoint /entrypoint
COPY target.sh /scripts.d/target.sh

# start
ENTRYPOINT ["/entrypoint"]
