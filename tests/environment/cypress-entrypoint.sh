#!/usr/bin/env bash
set -euo pipefail
test "${THOTH_DISPOSABLE:-}" = 1
test "$PWD" = /var/www/omp
test -f /dataset/database.sql
test -d /dataset/files
test -d /dataset/public
test -f /thoth-state/client.json

python3 plugins/generic/thoth/tests/environment/cypress-configure.py
# This hostname and database belong only to the Compose test project.
MYSQL_PWD=disposable-omp-only mysql --skip-ssl -h omp-db -u omp thoth_cypress < /dataset/database.sql
cp -a /dataset/files/. files/
cp -a /dataset/public/. public/
php -v | head -1
php lib/pkp/tools/installPluginVersion.php plugins/generic/thoth/version.xml
php lib/pkp/tools/appKey.php generate --force
php plugins/generic/thoth/cypress/support/ThothTestData.php configure
php -S 127.0.0.1:8001 plugins/generic/thoth/tests/environment/cypress-router.php > /tmp/thoth-omp-server.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT
python3 - <<'PY'
import time
import urllib.request
for attempt in range(50):
    try:
        urllib.request.urlopen('http://127.0.0.1:8001/index.php/publicknowledge/en/login', timeout=2)
        break
    except OSError:
        time.sleep(0.2)
else:
    raise SystemExit('OMP server did not become ready; inspect /tmp/thoth-omp-server.log')
PY

# Reuse OMP's Cypress configuration and support commands. Repeat without restoring data.
for run in 1 2; do
    echo "Registration scenario, run $run"
    npx --no-install cypress run --headless --browser electron \
      --config '{"baseUrl":"http://127.0.0.1:8001","specPattern":"plugins/generic/thoth/cypress/tests/functional/*.cy.js"}'
done
