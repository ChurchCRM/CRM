#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

integration=false
case "${1:-}" in
  --help|-h)
    echo 'Usage: bash scripts/setup-dev.sh [--docker|--integration]'
    echo 'Default: native dependencies/build. --docker: provision tools in the existing dev image.'
    echo '--integration: native build plus Cypress, test images, and running test services.'
    exit 0 ;;
  --docker)
    [ "$#" -eq 1 ] || { echo 'Use one setup mode.' >&2; exit 2; }
    command -v node >/dev/null || { echo 'Install Node 24+ on the host to prepare the local Docker config.' >&2; exit 1; }
    node -e 'if (Number(process.versions.node.split(".")[0]) < 24) process.exit(1)' || { echo 'Node 24+ is required.' >&2; exit 1; }
    docker info >/dev/null
    docker compose version
    node scripts/docker-config.js docker/Config.php
    docker compose -f docker/docker-compose.dev.yaml up -d --build
    docker compose -f docker/docker-compose.dev.yaml exec -T webserver bash -c \
      'source /root/.nvm/nvm.sh && cd /home/ChurchCRM && bash scripts/setup-dev.sh'
    exit 0 ;;
  --integration) integration=true ;;
  '') ;;
  *) echo 'Usage: bash scripts/setup-dev.sh [--docker|--integration]' >&2; exit 2 ;;
esac
[ "$#" -le 1 ] || { echo 'Use one setup mode.' >&2; exit 2; }

for tool in node npm php composer git; do
  command -v "$tool" >/dev/null || { echo "Missing $tool. Install the native prerequisites in DEVELOPING.md, or use --docker." >&2; exit 1; }
done
node -e 'if (Number(process.versions.node.split(".")[0]) < 24) process.exit(1)' || { echo 'Node 24+ is required.' >&2; exit 1; }
php -r 'exit(version_compare(PHP_VERSION, "8.4", ">=") ? 0 : 1);' || { echo 'PHP 8.4+ is required.' >&2; exit 1; }
if "$integration"; then
  docker info >/dev/null
  docker compose version
fi

# Install the browser explicitly below so its download cannot break a native-only setup.
CYPRESS_INSTALL_BINARY=0 npm ci
npm run build
node scripts/check-environment.js php
npm run lint

if "$integration"; then
  if ! npx --no-install cypress install; then
    # Retry only this Cypress version, leaving unrelated cached versions intact.
    echo 'Cypress installation failed. Retrying with a forced download.' >&2
    npx --no-install cypress install --force
  fi
  npx --no-install cypress verify
  npm run docker:test:start
  base_url=${CYPRESS_BASE_URL:-http://localhost/}
  curl --fail --silent --show-error --connect-timeout 2 --max-time 10 \
    --retry 20 --retry-connrefused --retry-delay 2 --retry-max-time 60 \
    --output /dev/null "${base_url%/}/session/begin"
  node scripts/check-environment.js integration --services
fi
