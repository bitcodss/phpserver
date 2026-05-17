FROM python:3.12-alpine

# docker CLI (static binary) — same version as cid-php74 used to ship.
RUN apk add --no-cache curl ca-certificates \
    && ARCH=$(uname -m) \
    && curl -fsSL https://download.docker.com/linux/static/stable/${ARCH}/docker-28.0.1.tgz | tar xz -C /tmp \
    && mv /tmp/docker/docker /usr/local/bin/docker \
    && rm -rf /tmp/docker \
    && chmod +x /usr/local/bin/docker

WORKDIR /app
COPY requirements.txt /app/
RUN pip install --no-cache-dir -r requirements.txt
COPY app.py /app/

# Match php74's www-data GID (33) so the broker socket created with
# --group phpaccess is readable by cid-php74's www-data over the shared volume.
RUN addgroup -g 33 phpaccess

# Run gunicorn as root (we need /var/run/docker.sock) but set the group to 33
# so the socket file is owned by root:phpaccess. umask 0117 -> mode 0660.
CMD ["gunicorn", "-b", "unix:/run/broker/broker.sock", \
     "--group", "phpaccess", "--umask", "0117", \
     "-w", "2", "--threads", "4", \
     "--access-logfile", "-", \
     "--error-logfile", "-", \
     "app:app"]
