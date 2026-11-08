FROM php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143

RUN set -eux; \
    curl -fsSL -o /tmp/debian-archive-keyring.deb \
        http://archive.debian.org/debian/pool/main/d/debian-archive-keyring/debian-archive-keyring_2019.1+deb10u1_all.deb; \
    dpkg -i /tmp/debian-archive-keyring.deb; \
    rm -f /tmp/debian-archive-keyring.deb; \
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
