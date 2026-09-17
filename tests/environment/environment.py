#!/usr/bin/env python3
"""Manage only the Docker Compose project belonging to this test directory."""
import argparse
import datetime
import fcntl
import hashlib
import json
import os
from pathlib import Path
import secrets
import subprocess
import sys

ROOT = Path(__file__).resolve().parent
STATE = ROOT / ".state"
PROJECT = "thoth-e2e-" + hashlib.sha256(str(ROOT).encode()).hexdigest()[:10]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("command", choices=["up", "down", "status", "smoke", "cypress"])
    parser.add_argument("--apply", action="store_true", help="Execute changes; otherwise show the plan")
    parser.add_argument("--port", type=int, default=18000, help="Loopback port for a new environment")
    parser.add_argument("--dataset", type=Path, help="OMP stable-3_5_0 MySQL dataset (database.sql, files/, public/)")
    args = parser.parse_args()
    if args.command == "cypress":
        if not args.dataset or not all((args.dataset / name).exists() for name in ("database.sql", "files", "public")):
            parser.error("cypress requires --dataset with database.sql, files/ and public/")
    if not 1024 <= args.port <= 65535:
        parser.error("--port must be between 1024 and 65535")
    if args.command != "status" and not args.apply:
        print(f"Plan: {args.command}; Docker project={PROJECT}; state={STATE}")
        if args.command == "cypress":
            print(f"Restore {args.dataset.resolve()} into disposable omp-db/thoth_cypress only; context publicknowledge.")
            print("Run ThothRegistration twice; no OMP host checkout or database will be changed.")
        print("Use --apply to execute. Only this project's resources will be changed.")
        return
    if STATE.is_symlink():
        raise RuntimeError("Refusing a symlink as the state directory")
    STATE.mkdir(mode=0o700, exist_ok=True)
    os.chmod(STATE, 0o700)
    with open(STATE / "lock", "w") as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        config_path = STATE / "runtime.json"
        config = json.loads(config_path.read_text()) if config_path.exists() else None
        if args.command == "up":
            if config:
                raise RuntimeError("Environment already prepared; use status/smoke or down --apply first")
            config = {
                "ZITADEL_MASTERKEY": secrets.token_hex(16),
                "BOOTSTRAP_PAT_EXPIRES": (datetime.datetime.now(datetime.timezone.utc) + datetime.timedelta(days=2)).isoformat(),
                "THOTH_PORT": str(args.port),
            }
            with open(config_path, "w", opener=lambda p, flags: os.open(p, flags, 0o600)) as target:
                json.dump(config, target)
        env = dict(os.environ, **(config or {
            "ZITADEL_MASTERKEY": "unused-for-inspection-or-removal!",
            "BOOTSTRAP_PAT_EXPIRES": "2099-01-01T00:00:00Z",
        }))
        compose = ["docker", "compose", "-p", PROJECT, "-f", str(ROOT / "compose.json")]
        if args.command == "cypress":
            if not config or not (STATE / "client.json").is_file():
                raise RuntimeError("Run up --apply before cypress")
            env["OMP_DATASET"] = str(args.dataset.resolve())
            compose += ["-f", str(ROOT / "compose.cypress.json")]
        def run(*arguments, capture=False):
            return subprocess.run(compose + list(arguments), env=env, check=True,
                                  text=True, stdout=subprocess.PIPE if capture else None)
        if args.command == "up":
            run("up", "-d", "--build", "--wait", "--wait-timeout", "300")
            container = run("ps", "-q", "api", capture=True).stdout.strip()
            subprocess.run(["docker", "cp", container + ":/state/client.json", str(STATE / "client.json")], check=True)
            os.chmod(STATE / "client.json", 0o600)
            print(f"Ready: http://127.0.0.1:{args.port}; credentials: {STATE / 'client.json'}")
        elif args.command == "down":
            run("down", "--volumes", "--remove-orphans")
            for name in ("client.json", "runtime.json"):
                (STATE / name).unlink(missing_ok=True)
            print("Disposable containers, network, volumes and credentials removed; image retained.")
        elif args.command == "status":
            run("ps", "--all")
        elif args.command == "smoke":
            subprocess.run([sys.executable, str(ROOT / "smoke.py"), str(STATE / "client.json")], check=True)
        elif args.command == "cypress":
            run("build", "cypress")
            run("up", "-d", "--wait", "omp-db")
            # Retain the stopped container for screenshots/log inspection until down.
            run("run", "--no-deps", "cypress")


if __name__ == "__main__":
    try:
        main()
    except (OSError, RuntimeError, subprocess.CalledProcessError) as error:
        print(f"Environment command failed: {error}", file=sys.stderr)
        sys.exit(1)
