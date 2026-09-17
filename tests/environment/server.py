"""Bootstrap a fresh, isolated Zitadel + Thoth pair, then supervise the API."""
import datetime
import json
import os
from pathlib import Path
import signal
import socket
import subprocess
import sys

from common import graphql, request, wait_for

STATE = Path(os.environ.get("THOTH_STATE_DIR", "/state"))
ZITADEL = os.environ.get("ZITADEL_URL", "http://zitadel:8080")
API = "http://127.0.0.1:8000"


def save(name, value):
    path = STATE / name
    with open(path, "w", opener=lambda p, flags: os.open(p, flags, 0o600)) as target:
        json.dump(value, target)


def bootstrap():
    STATE.mkdir(parents=True, exist_ok=True)
    if (STATE / "ready").exists():
        raise RuntimeError("State already initialized; discard this environment before starting a new one")
    wait_for("Zitadel readiness", lambda: request(ZITADEL + "/debug/ready"))
    pat = wait_for("bootstrap PAT", lambda: (STATE / "admin.pat").read_text().strip() or None)
    print("Configuring disposable Zitadel project", flush=True)
    env = dict(os.environ, THOTH_PAT=pat)
    setup = subprocess.run(["thoth", "zitadel", "setup"], env=env,
                           capture_output=True, text=True, timeout=120)
    if setup.returncode:
        raise RuntimeError("Thoth Zitadel setup failed (output withheld because it can contain keys)")
    keys = [line.split("=", 1)[1] for line in setup.stdout.splitlines() if line.startswith("PRIVATE_KEY=")]
    if len(keys) != 1 or not keys[0]:
        raise RuntimeError("Bootstrap did not return exactly one API key")
    os.environ["PRIVATE_KEY"] = keys[0]
    management = ZITADEL + "/management/v1"
    org = wait_for("Zitadel management readiness", lambda: request(management + "/orgs/me", pat))["org"]["id"]
    projects = request(management + "/projects/_search", pat, {})["result"]
    project = next(p["id"] for p in projects if p["name"] == "Thoth")
    request(management + f"/projects/{project}/roles", pat,
            {"roleKey": "WORK_LIFECYCLE", "displayName": "Work lifecycle"})
    user = request(management + "/users/machine", pat,
                   {"userName": "omp-tests", "name": "OMP tests",
                    "accessTokenType": "ACCESS_TOKEN_TYPE_BEARER"})["userId"]
    request(management + f"/users/{user}/grants", pat,
            {"projectId": project, "roleKeys": ["PUBLISHER_USER", "WORK_LIFECYCLE"]})
    expiry = (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(days=2)).isoformat()
    token = request(management + f"/users/{user}/pats", pat, {"expirationDate": expiry})["token"]
    return pat, token, org


def main():
    os.umask(0o077)
    # This entrypoint is deliberately only for disposable environments.
    if os.environ.get("THOTH_DISPOSABLE") != "1":
        raise RuntimeError("THOTH_DISPOSABLE=1 is required")
    pat, token, org = bootstrap()
    def database_ready():
        with socket.create_connection(("thoth-db", 5432), timeout=3):
            return True
    wait_for("Thoth PostgreSQL", database_ready)
    print("Starting Thoth migrations and API", flush=True)
    child = subprocess.Popen(["thoth", "init"])
    def stop(signum, frame):
        child.terminate()
    signal.signal(signal.SIGTERM, stop)
    signal.signal(signal.SIGINT, stop)
    try:
        wait_for("authenticated Thoth API", lambda: graphql(API, pat, "{ me { userId } }"))
        print("Creating fixture publisher", flush=True)
        publisher = graphql(API, pat,
            'mutation($data: NewPublisher!) { createPublisher(data: $data) { publisherId } }',
            {"data": {"publisherName": "Cypress Publisher", "zitadelId": org}})["createPublisher"]["publisherId"]
        print("Creating fixture imprint", flush=True)
        imprint = graphql(API, pat,
            'mutation($data: NewImprint!) { createImprint(data: $data) { imprintId } }',
            {"data": {"publisherId": publisher, "imprintName": "Cypress Imprint"}})["createImprint"]["imprintId"]
        credentials = {"url": os.environ["THOTH_GRAPHQL_API"], "token": token,
                       "publisherId": publisher, "imprintId": imprint}
        from smoke import verify
        print("Checking scoped account and API operations", flush=True)
        verify(API, credentials)
        save("client.json", credentials)
        # No administrator token/private key is exported to the OMP job.
        (STATE / "admin.pat").unlink()
        (STATE / "ready").touch(mode=0o600)
        print("READY: Thoth configured with a scoped account, publisher and imprint", flush=True)
        return child.wait()
    finally:
        if child.poll() is None:
            child.terminate()
            try:
                child.wait(timeout=10)
            except subprocess.TimeoutExpired:
                child.kill()
                child.wait()


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as error:
        # Do not emit a traceback containing subprocess output or credentials.
        print(f"Environment failed: {type(error).__name__}: {error}", file=sys.stderr)
        sys.exit(1)
