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

## Interactive use

After `up`, prepare OMP and open Cypress:

```sh
python3 tests/environment/environment.py prepare --dataset /path/to/omp-dataset --apply
python3 tests/environment/environment.py open --apply
```

Requires a local X11/XWayland session and `xauth`. The container accesses your X11
session; use only trusted images and tests.

Select a spec in the Cypress window. Test edits are available without rebuilding.
Closing Cypress keeps OMP running. To run a spec without the GUI, replace `example.cy.js`
with a filename from `cypress/tests/functional`:

```sh
python3 tests/environment/environment.py run --spec example.cy.js --apply
```

Omit `--spec` to run all tests once. `open` and `run` reuse the prepared database;
`prepare` restores it. Close Cypress before running another environment command.

## Shut down

```sh
python3 tests/environment/environment.py down --apply
```

This removes the test services and data. To recreate an existing environment,
run `down` before `up`.

The same tests also run in GitLab CI.
