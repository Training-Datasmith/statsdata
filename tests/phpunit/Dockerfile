FROM debian@sha256:734a72b3d6142a71993b4bc580ca01d8ca687f77a2c422d96a961d3376b179bc AS debian-keyring

FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

COPY --from=debian-keyring /usr/share/keyrings/debian-archive-keyring.gpg /etc/apt/trusted.gpg.d/debian-archive-keyring.gpg
COPY --from=debian-keyring /usr/share/keyrings/debian-archive-removed-keys.gpg /etc/apt/trusted.gpg.d/debian-archive-removed-keys.gpg

RUN set -eux; \
    printf '%s\n' \
        'deb http://archive.debian.org/debian stretch main' \
        'deb http://archive.debian.org/debian-security stretch/updates main' \
        > /etc/apt/sources.list; \
    printf '%s\n' \
        'Acquire::Check-Valid-Until "false";' \
        > /etc/apt/apt.conf.d/99archive; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        default-libmysqlclient-dev \
        default-mysql-client; \
    docker-php-ext-install pdo_mysql; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /work
