**English** | [Español](README-es.md) | [Português Brasileiro](README-pt_BR.md)

# Cypress tests

The Cypress tests use disposable OMP and Thoth instances without changing your
local installation or writing to public Thoth APIs.

## Requirements

- Docker with Compose v2 and Python 3.9 or later.
- A Linux/amd64 host or compatible emulation.
- The OMP `stable-3_5_0` MySQL dataset, context `publicknowledge`, with
  `database.sql`, `files/` and `public/` from the same snapshot.

## Run

From the plugin root, replace `/path/to/omp-dataset` with your dataset directory:

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /path/to/omp-dataset --apply
```

To repeat the tests, run the `cypress` command again. It restores the disposable OMP
database and runs the suite twice. Without `--apply`, the commands only show the plan.

## Shut down

```sh
python3 tests/environment/environment.py down --apply
```

This removes the test services and data. To recreate an existing environment,
run `down` before `up`.

The same tests also run in GitLab CI.
