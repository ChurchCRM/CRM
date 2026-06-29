#!/usr/bin/env bash
# scripts/sbx-start.sh — One-command launcher for the ChurchCRM Docker Sandbox kit.
#
# Builds the multi-stage image (all Node/PHP/Composer steps inside Docker) and
# starts the full stack.  No Node, PHP, or Composer required on the host.
#
# Usage:
#   bash scripts/sbx-start.sh          # from repo root
#   npm run docker:sbx:start           # via npm

set -euo pipefail

# ── Resolve repo root (works regardless of CWD) ─────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

COMPOSE_FILE="docker/docker-compose.sbx.yaml"
WEBSERVER_PORT="${WEBSERVER_PORT:-80}"
ADMINER_PORT="${ADMINER_PORT:-8088}"
WEB_URL="http://localhost${WEBSERVER_PORT:+":${WEBSERVER_PORT}"}"
# Use plain localhost if port is 80
[ "${WEBSERVER_PORT}" = "80" ] && WEB_URL="http://localhost"

# ── Preflight: Docker must be present ───────────────────────────────────────
if ! command -v docker &>/dev/null; then
    echo "ERROR: Docker is not installed or not on PATH." >&2
    echo "       Install Docker from https://docs.docker.com/get-docker/" >&2
    exit 1
fi

if ! docker info &>/dev/null; then
    echo "ERROR: Docker daemon is not running." >&2
    echo "       Start Docker Desktop (or 'sudo systemctl start docker') and retry." >&2
    exit 1
fi

if ! docker compose version &>/dev/null; then
    echo "ERROR: Docker Compose plugin v2 is not available ('docker compose version' failed)." >&2
    echo "       Install Docker Desktop >= 3.6.0 or the standalone Compose plugin:" >&2
    echo "       https://docs.docker.com/compose/install/" >&2
    exit 1
fi

# ── Build and start ──────────────────────────────────────────────────────────
echo ""
echo "========================================================================"
echo "  ChurchCRM — Docker Sandbox (sbx) Start"
echo "========================================================================"
echo ""
echo "  Building image and starting services…"
echo "  (First run takes 5–15 min — downloads toolchain + builds assets inside Docker)"
echo ""

docker compose -f "${COMPOSE_FILE}" up -d --build

# ── Wait for the web server to respond ───────────────────────────────────────
echo ""
echo "  Waiting for web server to become ready…"

MAX_WAIT=120   # seconds
INTERVAL=5
elapsed=0

# Readiness check: prefer curl; fall back to a pure-bash TCP connect so the
# script works on minimal runners that have Docker but not curl.
check_ready() {
    if command -v curl &>/dev/null; then
        curl -sf "${WEB_URL}/" -o /dev/null 2>/dev/null
    else
        # bash /dev/tcp is a built-in that does not require any extra packages.
        bash -c "cat < /dev/null > /dev/tcp/localhost/${WEBSERVER_PORT}" 2>/dev/null
    fi
}

until check_ready; do
    if [ "${elapsed}" -ge "${MAX_WAIT}" ]; then
        echo ""
        echo "  ERROR: Server did not respond within ${MAX_WAIT}s."
        echo "         It may still be starting, or the build may have failed."
        echo "         Check logs with:"
        echo "         docker compose -f docker/docker-compose.sbx.yaml logs"
        echo ""
        exit 1
    fi
    printf "."
    sleep "${INTERVAL}"
    elapsed=$(( elapsed + INTERVAL ))
done

echo ""

# ── Done ─────────────────────────────────────────────────────────────────────
echo ""
echo "========================================================================"
echo "  ChurchCRM is running!"
echo "========================================================================"
echo ""
echo "  Web:      ${WEB_URL}"
echo "  Login:    admin / changeme"
echo ""
echo "  Adminer:  http://localhost:${ADMINER_PORT}"
echo "  DB:       Server=database  User=churchcrm  Password=changeme"
echo ""
echo "  Commands:"
echo "    npm run docker:sbx:logs    # live container logs"
echo "    npm run docker:sbx:stop    # stop (keep data)"
echo "    npm run docker:sbx:down    # stop + remove containers and volumes"
echo "    npm run docker:sbx:rebuild # full rebuild (e.g. after code changes)"
echo ""
