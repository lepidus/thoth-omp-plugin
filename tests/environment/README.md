**English** | [Español](README-es.md) | [Português Brasileiro](README-pt_BR.md)

# Disposable Thoth environment for plugin tests

This environment starts a real Thoth API, Zitadel authentication and two PostgreSQL databases.
Bootstrap creates a publisher, an imprint and a service account restricted to that publisher,
with `PUBLISHER_USER` and `WORK_LIFECYCLE`. It uses no public Thoth APIs or credentials.
It also prepares a disposable OMP instance to run the Cypress scenarios described below.

## Local use

Requirements: Docker Engine with Compose v2 or later and Python 3.9 or later.
The Thoth image targets Linux/amd64; other hosts require emulation or a compatible image.
Run from the root of this worktree:

```sh
# Show the plan without modifying the environment.
python3 tests/environment/environment.py up

# Build the helper image, start and configure services, and run the API smoke test.
python3 tests/environment/environment.py up --apply

# Inspect containers and repeat creation/query/update/deletion through the API.
python3 tests/environment/environment.py status
python3 tests/environment/environment.py smoke --apply

# Remove only this worktree's containers, volumes, network and credentials.
python3 tests/environment/environment.py down --apply
```

The API is available at `http://127.0.0.1:18000/graphql`. Use `up --apply --port 18001`
for another concurrent environment. Each directory has its own Compose project.
Credentials and IDs are stored in `tests/environment/.state/client.json`, with file
permissions 0600 and directory permissions 0700. This file is excluded from Git and the build context.
The client token expires after two days; recreate the environment after that time.

Bootstrap is not idempotent. Do not restart individual containers or attempt to reuse
their databases: run `down --apply` followed by `up --apply`. If setup fails,
resources remain available for diagnosis; the same `down` command removes them.
The built image remains cached to speed up subsequent setup.
Each Cypress command execution restores the OMP dataset; each test case creates its own books
with unique identifiers, without depending on data left by other cases.

## Components and isolation

- Thoth 1.8.0: official image pinned by its amd64 digest. The image's source commit
  is `9ae1e56714096507d2d210b5b315361d626626c9`; its Git tree is identical to that of
  clone commit `4fa7eaa9ccb60d39c41ccd8feb257edf28c173ff` inspected for this task.
- Zitadel 3.2.2 and PostgreSQL 17: images pinned by digest.
- Python: initialization and verification scripts using only the standard library.
- Nginx: local gateway pinned by digest, binding only to loopback.

The network connecting the databases, Zitadel and Thoth is internal. The gateway connects
it to a second network to publish the port on the host; it forwards only to the local API.
This connection does not provide external egress for the API. The gateway is not needed in CI.
Do not configure file hosting: AWS credentials are fictitious, there are no buckets/CDN,
and the tests at this stage cover metadata only. Automatic distribution is disabled.
Do not use this environment for real data or credentials.

## Initialization and smoke test

The Zitadel service waits for PostgreSQL and creates the PAT directory before initialization.
The Thoth service waits for Zitadel readiness, runs upstream setup, creates the restricted
account, starts migrations/API and creates fixtures. It writes `ready` only after the smoke test.
The administrative PAT is removed from the volume after this process. OMP receives only
the restricted token. Bootstrap captures the private key without printing it.

The smoke test checks that the account is not SUPERUSER, can see only the expected publisher,
has lifecycle permission, rejects anonymous writes, and creates/queries/updates/deletes a
monograph. The test monograph is deleted; the publisher and imprint remain available.
This does not yet verify publication/activation, all plugin metadata, uploads,
the OMP interface or browser behavior.

## Cypress scenarios

The command below prepares a disposable OMP instance in another container, with a MySQL
`thoth_cypress` database on the `omp-db` service and no published ports. The dump must be
the OMP `stable-3_5_0` MySQL dataset, context `publicknowledge` (Public Knowledge Press),
accompanied by the `files/` and `public/` directories from the same snapshot.
No OMP checkout or database on the host is modified.

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /home/lepidus/Work/pkp/datasets/omp/stable-3_5_0/mysql
# After reviewing the plan, add --apply to the command above.
python3 tests/environment/environment.py down --apply
```

The suite covers four user journeys through the interface, with unique books per case:

- **Registration by button:** registers a published monograph and checks its link,
  title, type, imprint and Active status in Thoth.
- **Registration on publication:** publishes a draft with registration consent and an imprint,
  checking the publication in OMP and the Active work in Thoth.
- **Bulk registration:** selects two of three books from a dedicated group and confirms
  their registration; the third receives no registration request and remains unlinked.
- **Metadata update:** uses the button to synchronize an already linked draft whose remote
  work has an old title; checks the updated title on the same identifier.

The runner executes the suite twice without restoring data between runs. Thoth responses
are not mocked. To prepare the update case, the fixture creates a Forthcoming work
with an old title in the disposable API and persists its link in OMP.

Cypress configuration and commands come from OMP. The PHP router used exclusively for tests
injects the real HTTP client connected to `http://api:8000`; the plugin's production URL
rules remain unchanged. The token stays outside the web root and is not sent to Cypress.
Plugin configuration and fixture creation are preconditions, not behaviors covered by
these scenarios.

Active works cannot be deleted by the restricted user. Created data remains only in the
disposable databases until `down --apply`, which removes the entire project.
The Cypress container remains available after execution to inspect
`/tmp/thoth-omp-server.log` and screenshots in `/var/www/omp/cypress/screenshots`.
Do not reuse the disposable database as a development installation.

The organization follows the `customQuestions` plugin:

- `cypress/tests/functional/*.cy.js`: one journey per file and its assertions;
- `cypress/support/thoth.js`: setup and query helpers;
- `cypress/support/ThothTestData.php`: fixture creation and link/API queries;
- `tests/environment/`: Docker infrastructure, configuration and initialization.

Cypress configuration continues to belong to OMP. The regular pipeline includes
`templates/groups/omp/cypress_tests.yml` and adapts `plugin_integration_tests_omp`
in `.gitlab/thoth-cypress.yml`. It reuses the template's rules, cache, dependency validation
and artifacts; only services and setup/execution required by Thoth are changed.
The full `omp_integration_tests` suite is disabled.

The shared workflow runs the branch pipeline while no MR is open and switches to
running only the MR pipeline once one exists. A new commit cancels older Cypress
and disposable environment build jobs, marked as `interruptible`.
Other jobs retain their cancellation policy.

The BuildKit build in `.pre` publishes the helper image by SHA; Cypress waits for
that job and bootstrap of the Thoth/Zitadel/PostgreSQL services. A separate MySQL
instance receives `/tmp/dump.sql` and the corresponding files, already included in
the pinned OMP image. The shared entrypoint accepts `--image-dataset` in this case.
The restricted token is copied to `/thoth-state` outside the web root and removed
in `after_script`. The two runs generate separate JUnit XML files; screenshots and
logs follow the shared artifact contract. CI uses a dedicated network per job, without Docker-in-Docker.

The secret scanner retains its default rules. `.gitleaksignore` identifies only the
three historical findings for the fictitious PostgreSQL URL by commit/file/rule/line;
the current YAML definition has a `gitleaks:allow` comment. It does not exclude entire
files or detection rules. This instance does not enable custom rulesets.

Validation on 2026-09-17: OMP 3.5 from the pinned image, PHP 8.4.23, Node 20.19.2,
Cypress 14.5.4 and headless Electron 130. Execution queries the real Thoth instance
in all four scenarios and repeats the suite without restoring data between runs.
The dataset used was `omp/stable-3_5_0/mysql` from the local PKP datasets checkout.

## Opt-in GitLab smoke test

The root `.gitlab-ci.yml` loads `.gitlab/thoth-environment.yml` only when
`THOTH_ENVIRONMENT_PROBE=1`. Without this variable, it runs the normal templates, including the integrated Cypress tests.
No changes to shared templates are required.

- Build shared with Cypress: runner tagged `buildkit-rootless`, building without Docker-in-Docker
  and pushing the helper image to `$CI_REGISTRY_IMAGE/test-environment:$CI_COMMIT_SHA`.
- Smoke test: the OMP 3.5 image already used by CI, a runner tagged `atualizacoes2`,
  two PostgreSQL instances, Zitadel and Thoth as services. `FF_NETWORK_PER_BUILD=true`
  enables communication between the job's services.
- Credentials pass through the shared directory `/builds/thoth-environment-$CI_JOB_ID`,
  outside the checkout, and are removed in `after_script`. They are not published as artifacts.
- The Zitadel encryption key and CI database passwords are public values used only
  for this ephemeral environment; access tokens are generated during bootstrap.
- The published test image remains in the registry for reuse/auditing. Registry retention
  must be configured separately; the job does not delete images.

The per-job network does not block external egress as the internal Compose network does.
Scripts use only local aliases, but we do not claim egress isolation in CI.
The cgroup output reports only the limits visible to the job container; it does not
measure service limits or free host RAM. This smoke test does not measure capacity
for concurrent Cypress execution with all services.

The Python smoke-test client uses GraphQL directly. The PHP client and the test-only
override allowing the plugin's private URL belong to the OMP integration.
The production URL validator remains unchanged.

## Quick checks

```sh
python3 -m unittest discover -s tests/environment -p 'test_*.py' -v
```

These tests cover planning without mutations, invalid port rejection and HTTP/GraphQL
errors. The real smoke test depends on `up --apply` and `smoke --apply`.
