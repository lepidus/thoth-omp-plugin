#!/usr/bin/env bash
set -euo pipefail
test "${THOTH_DISPOSABLE:-}" = 1
test "$PWD" = /var/www/omp
test -f /thoth-state/client.json

mode=${1:-}
spec_pattern='plugins/generic/thoth/cypress/tests/functional/*.cy.js'
reporter_args=()
run_args=()
case "$mode" in
    --open)
        test $# = 1
        exec npx --no-install cypress open --e2e --browser electron \
          --config "{\"baseUrl\":\"http://127.0.0.1:8001\",\"specPattern\":\"$spec_pattern\",\"watchForFileChanges\":true,\"numTestsKeptInMemory\":50}"
        ;;
    --run)
        test $# -le 2
        if [[ $# = 2 ]]; then
            test "${2##*/}" = "$2"
            [[ "$2" = *.cy.js ]]
            test -f "plugins/generic/thoth/cypress/tests/functional/$2"
            run_args=(--spec "plugins/generic/thoth/cypress/tests/functional/$2")
        fi
        ;;
    --serve)
        test $# = 1
        bash plugins/generic/thoth/tests/environment/cypress-prepare.sh
        ;;
    ''|--image-dataset)
        bash plugins/generic/thoth/tests/environment/cypress-prepare.sh "$@"
        ;;
    *) echo 'Expected --serve, --run [spec.cy.js], --open or --image-dataset' >&2; exit 1 ;;
esac

if [[ "$mode" != --run ]]; then
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


    if [[ "$mode" = --serve ]]; then
        echo 'Disposable OMP ready; use environment.py open or run.'
        wait "$server_pid"
        exit
    fi
fi

# CI and the one-shot local command repeat without restoring data between runs.
runs=2
[[ "$mode" = --run ]] && runs=1
for ((run = 1; run <= runs; run++)); do
    echo "Thoth scenarios, run $run"
    if [[ -n "${CI_PROJECT_DIR:-}" ]]; then
        mkdir -p "$CI_PROJECT_DIR/results"
        reporter_args=(--reporter junit --reporter-options "mochaFile=$CI_PROJECT_DIR/results/spec-$run-[hash].xml")
    fi
    npx --no-install cypress run --headless --browser electron \
      "${reporter_args[@]}" "${run_args[@]}" \
      --config "{\"baseUrl\":\"http://127.0.0.1:8001\",\"specPattern\":\"$spec_pattern\"}"
done
