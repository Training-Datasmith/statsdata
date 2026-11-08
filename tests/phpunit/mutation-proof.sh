#!/usr/bin/env bash
set -euo pipefail

if [[ "${STATSDATA_MUTATION:-0}" != "1" ]]; then
  echo "Set STATSDATA_MUTATION=1 to run the mutation proof." >&2
  exit 1
fi

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
WORK="$(mktemp -d)"
chmod 0700 "$WORK"
rsync -a --delete --exclude '.git' "$ROOT/" "$WORK/"

MODULE="${WORK}/statsdata.php"
BACKUP="${WORK}/statsdata.php.orig"
cp "$MODULE" "$BACKUP"
restore_module() {
  cp "$BACKUP" "$MODULE"
}
trap restore_module EXIT

if [[ "${STATSDATA_SKIP_MUTATION:-0}" != "1" ]]; then
  perl -0pi -e 's/\(int\) \$params\[\x27cookie\x27\]->id_customer/\$params[\x27cookie\x27]->id_customer/g' "$MODULE"
fi

USE_SUDO_DOCKER="${USE_SUDO_DOCKER:-0}"
DOCKER_EXE="${DOCKER_BIN:-docker}"
if [[ "$USE_SUDO_DOCKER" == "1" ]]; then
  DOCKER_BIN="sudo ${DOCKER_EXE}"
else
  DOCKER_BIN="${DOCKER_EXE}"
fi

SUITE_TMP="$(mktemp -d)"
chmod 0700 "$SUITE_TMP"
cleanup_tmp() {
  ${DOCKER_BIN} rm -f statsdata-mariadb-mutation >/dev/null 2>&1 || true
  rm -rf "$SUITE_TMP" "$WORK"
}
trap cleanup_tmp EXIT

source_env() {
  PHPUNIT_VERSION="4.8.36"
  PHPUNIT_SHA256="ab8bc3017a64cf75a2112f5e089a1d5b0c4cc2116556d60c1af5e3f584e85c5e"
  PHPUNIT_FINGERPRINT="D8406D0D82947747293778314AA394086372C20A"
  MARIADB_IMAGE="mariadb@sha256:dbe56e20372fc6d6b8e0e396866ba89c4c7f128c38c4f59aaa54d957db95790c"
  PHAR="${SUITE_TMP}/phpunit-${PHPUNIT_VERSION}.phar"
  ASC="${SUITE_TMP}/phpunit-${PHPUNIT_VERSION}.phar.asc"
  curl --fail --silent --show-error -o "$PHAR" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar"
  curl --fail --silent --show-error -o "$ASC" "https://phar.phpunit.de/phpunit-${PHPUNIT_VERSION}.phar.asc"
  echo "${PHPUNIT_SHA256}  ${PHAR}" | sha256sum -c -
  export GNUPGHOME="$(mktemp -d)"
  chmod 0700 "$GNUPGHOME"
  curl --fail --silent --show-error -o "$GNUPGHOME/bergmann.asc" https://sebastian-bergmann.de/gpg.asc
  gpg --batch --import "$GNUPGHOME/bergmann.asc"
  verify="$(gpg --batch --status-fd 1 --verify "$ASC" "$PHAR" 2>&1)"
  echo "$verify" | grep -q '\[GNUPG:\] GOODSIG'
  echo "$verify" | grep -q "\[GNUPG:\] VALIDSIG ${PHPUNIT_FINGERPRINT}"
  rm -rf "$GNUPGHOME"
}
source_env

${DOCKER_BIN} build -t statsdata-php56-suite "$ROOT/tests/phpunit"

CID="statsdata-mariadb-mutation"
${DOCKER_BIN} rm -f "$CID" >/dev/null 2>&1 || true
${DOCKER_BIN} run -d --rm --name "$CID" \
  -e MARIADB_ROOT_PASSWORD=root-secret \
  -e MARIADB_DATABASE=statsdata \
  -e MARIADB_USER=statsdata \
  -e MARIADB_PASSWORD=statsdata \
  -p "127.0.0.1::3306" \
  "$MARIADB_IMAGE" >/dev/null

for _ in $(seq 1 50); do
  if ${DOCKER_BIN} exec "$CID" mariadb-admin ping -h127.0.0.1 -uroot -proot-secret --silent >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

MAPPED_PORT="$(${DOCKER_BIN} port "$CID" 3306 | head -n1 | awk -F: '{print $NF}')"
${DOCKER_BIN} exec "$CID" mariadb -uroot -proot-secret -e \
  "ALTER USER 'statsdata'@'%' IDENTIFIED VIA mysql_native_password USING PASSWORD('statsdata'); FLUSH PRIVILEGES;"

XML="${SUITE_TMP}/mutation.xml"
cat >"$XML" <<'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="/work/tests/phpunit/bootstrap.php" colors="false" stopOnFailure="true">
  <testsuites>
    <testsuite name="module-tests">
      <file>/work/tests/phpunit/StatsdataAuthenticationTest.php</file>
    </testsuite>
  </testsuites>
</phpunit>
EOF

set +e
OUTPUT="$(${DOCKER_BIN} run --rm --network host \
  -v "${WORK}:/work:ro" \
  -v "${PHAR}:/phpunit.phar:ro" \
  -v "${XML}:/phpunit.xml:ro" \
  -e STATS_ROOT=/work \
  -e DB_HOST=127.0.0.1 \
  -e DB_PORT="${MAPPED_PORT}" \
  -e DB_NAME=statsdata \
  -e DB_USER=statsdata \
  -e DB_PASSWORD=statsdata \
  statsdata-php56-suite \
  php -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 \
    /phpunit.phar --configuration /phpunit.xml --filter testCustomerIdIsCastBeforeTheGuestQuery 2>&1)"
CODE=$?
set -e

echo "$OUTPUT"

if [[ "$CODE" -eq 0 ]]; then
  echo "Mutation proof failed: expected test failure with cast removed" >&2
  exit 1
fi

if ! echo "$OUTPUT" | grep -q 'Failed asserting'; then
  echo "Mutation proof failed: expected assertion failure" >&2
  exit 1
fi

for forbidden in 'Errors:' 'PDOException' 'Cannot open file' 'Parse error'; do
  if echo "$OUTPUT" | grep -qF "$forbidden"; then
    echo "Mutation proof failed: forbidden token in output: ${forbidden}" >&2
    exit 1
  fi
done

echo "Mutation proof passed."
exit 0
