#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER_EXE="${DOCKER_BIN:-docker}"
if [[ "$USE_SUDO_DOCKER" == "1" ]]; then
  DOCKER_BIN="sudo ${DOCKER_EXE}"
else
  DOCKER_BIN="${DOCKER_EXE}"
fi

PHPUNIT_VERSION="4.8.36"
PHPUNIT_SHA256="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
PHPUNIT_FINGERPRINT="D8406D0D82947747293778314AA394086372C20A"
PHP_IMAGE="php@sha256:6ce95208609dc66df163ab936c970b3b34cd901b85c747102c5999f08ade9143"
MARIADB_IMAGE="mariadb@sha256:dbe56e20372fc6d6b8e0e396866ba89c4c7f128c38c4f59aaa54d957db95790c"

SUITE_TMP="$(mktemp -d)"
chmod 0700 "$SUITE_TMP"
cleanup() {
  ${DOCKER_BIN} rm -f statsdata-mariadb-suite >/dev/null 2>&1 || true
  rm -rf "$SUITE_TMP"
}
trap cleanup EXIT

verify_phpunit_phar() {
  local phar="$1"
  local asc="$2"
  curl --fail --silent --show-error -o "$phar" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"
  curl --fail --silent --show-error -o "$asc" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar.asc"
  echo "${PHPUNIT_SHA256}  ${phar}" | sha256sum -c -
  export GNUPGHOME="$(mktemp -d)"
  chmod 0700 "$GNUPGHOME"
  curl --fail --silent --show-error -o "$GNUPGHOME/bergmann.asc" https://sebastian-bergmann.de/gpg.asc
  gpg --batch --import "$GNUPGHOME/bergmann.asc"
  local verify
  verify="$(gpg --batch --status-fd 1 --verify "$asc" "$phar" 2>&1)"
  echo "$verify" | grep -q '\[GNUPG:\] GOODSIG'
  echo "$verify" | grep -q "\[GNUPG:\] VALIDSIG ${PHPUNIT_FINGERPRINT}"
  rm -rf "$GNUPGHOME"
}

if ! command -v "$DOCKER_EXE" >/dev/null 2>&1; then
  echo "docker not found in PATH (set DOCKER_BIN or USE_SUDO_DOCKER=1)" >&2
  exit 1
fi

PHAR="${SUITE_TMP}/phpunit-${PHPUNIT_VERSION}.phar"
ASC="${SUITE_TMP}/phpunit-${PHPUNIT_VERSION}.phar.asc"
verify_phpunit_phar "$PHAR" "$ASC"

${DOCKER_BIN} build -t statsdata-php56-suite "$ROOT/tests/phpunit"

CID="statsdata-mariadb-suite"
${DOCKER_BIN} rm -f "$CID" >/dev/null 2>&1 || true
${DOCKER_BIN} run -d --rm --name "$CID" \
  -e MARIADB_ROOT_PASSWORD=root-secret \
  -e MARIADB_DATABASE=statsdata \
  -e MARIADB_USER=statsdata \
  -e MARIADB_PASSWORD=statsdata \
  -p "127.0.0.1::3306" \
  "$MARIADB_IMAGE" >/dev/null

# docker run -d prints container id, not port — discover mapped port
for _ in $(seq 1 50); do
  if ${DOCKER_BIN} exec "$CID" mariadb-admin ping -h127.0.0.1 -uroot -proot-secret --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
if ! ${DOCKER_BIN} exec "$CID" mariadb-admin ping -h127.0.0.1 -uroot -proot-secret --silent >/dev/null 2>&1; then
  echo "MariaDB did not become ready" >&2
  exit 1
fi

MAPPED_PORT="$(${DOCKER_BIN} port "$CID" 3306 | head -n1 | awk -F: '{print $NF}')"

${DOCKER_BIN} exec "$CID" mariadb -uroot -proot-secret -e \
  "ALTER USER 'statsdata'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('statsdata'); FLUSH PRIVILEGES;"

${DOCKER_BIN} run --rm --network host \
  -v "${ROOT}:/work:ro" \
  -v "${PHAR}:/phpunit.phar:ro" \
  -e STATS_ROOT=/work \
  -e PHPUNIT_PHAR=/phpunit.phar \
  -e DB_HOST=127.0.0.1 \
  -e DB_PORT="${MAPPED_PORT}" \
  -e DB_NAME=statsdata \
  -e DB_USER=statsdata \
  -e DB_PASSWORD=statsdata \
  statsdata-php56-suite \
  php /work/tests/phpunit/run-suite.php
