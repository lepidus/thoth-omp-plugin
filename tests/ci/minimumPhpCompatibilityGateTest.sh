#!/usr/bin/env bash

set -euo pipefail

ci_file=${1:-.gitlab-ci.yml}
job_name=plugin_compatibility_omp_php_7_4

if [[ ! -f "$ci_file" ]]; then
    printf 'CI configuration not found: %s\n' "$ci_file" >&2
    exit 1
fi

job=$(
    awk -v heading="${job_name}:" '
        $0 == heading {
            found = 1
            print
            next
        }

        found && /^[^[:space:]#][^:]*:/ {
            exit
        }

        found {
            print
        }

        END {
            if (!found) {
                exit 1
            }
        }
    ' "$ci_file"
) || {
    printf 'Missing CI gate: %s\n' "$job_name" >&2
    exit 1
}

assert_job_contract() {
    local expected=$1

    if ! grep -Fq -- "$expected" <<< "$job"; then
        printf 'Gate %s is missing contract: %s\n' "$job_name" "$expected" >&2
        exit 1
    fi
}

assert_job_contract 'extends: .unit_test_template'
assert_job_contract 'stage: plugin'
assert_job_contract 'needs: []'
assert_job_contract "PHP_VERSION: '7.4'"
assert_job_contract 'update-alternatives --set php /usr/bin/php$PHP_VERSION'
assert_job_contract 'php --version'
assert_job_contract 'test "$(php -r '\''echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;'\'')" = "$PHP_VERSION"'
assert_job_contract "git ls-files -z -- '*.php' | xargs -0 -r -n1 php -l"
assert_job_contract '!reference [.script_plugin_unit_test]'

printf 'Minimum PHP 7.4 CI gate contract passed.\n'
