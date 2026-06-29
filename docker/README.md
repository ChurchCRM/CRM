Getting Started with Docker for ChurchCRM
===========================

This directory contains two types of Docker configurations:

| Configuration | Use Case |
|---------------|----------|
| `docker-compose.sbx.yaml` | **Docker Sandbox** — fully self-contained kit; all build steps inside Docker (no host Node/PHP required). |
| `docker-compose.yaml` | Development and testing (Apache + PHP). Used by the automated test suite and local development. |
| `docker-compose.nginx.yaml` | Self-hosted or production reference (nginx + PHP-FPM). Starting point for your own deployment. |
| `docker-compose.frankenphp.yaml` | Self-hosted or production reference (FrankenPHP). Simpler single-container alternative to nginx + PHP-FPM. |

---

Docker Sandbox (sbx)
---

Use this kit when Docker is available on the host but **Node, PHP, and Composer are not installed locally** — for example in an agent sandbox, a stripped-down CI runner, or any environment where you want a zero-dependency one-command start.

All build steps — `npm ci`, `composer install`, Webpack, Grunt, Biome — run **inside a multi-stage Docker build stage**. Nothing is compiled on the host.

## Quick Start

```bash
# From the repo root:
npm run docker:sbx:start
```

The first run builds the image (downloads toolchain + runs the full build inside Docker) and takes 5–15 minutes depending on network and CPU.  Subsequent runs reuse the cached image and start in seconds.

Alternatively, invoke Compose directly:

```bash
docker compose -f docker/docker-compose.sbx.yaml up -d --build
```

## Services

| Service | Image | Default Port | Notes |
|---------|-------|-------------|-------|
| `webserver-sbx` | `churchcrm/crm:php8-sbx` (built locally) | `80` | Apache + PHP 8.4 with compiled app baked in |
| `database` | `mariadb:10.11` | `3306` (internal) | Seeded with demo data on first start |
| `adminer` | `adminer` | `8088` | Database GUI |

## Access

| URL | Credentials |
|-----|-------------|
| `http://localhost` | admin / changeme |
| `http://localhost:8088` (Adminer) | Server: `database` \| User: `churchcrm` \| Password: `changeme` |

## Demo Data vs Fresh Install

By default, the database is seeded with ChurchCRM demo data so you can log in immediately.  To start with a fresh setup wizard instead:

1. Remove the `../cypress/data` bind-mount from the `database` service in `docker-compose.sbx.yaml`.
2. Run `docker compose -f docker/docker-compose.sbx.yaml down -v` to clear the database volume.
3. Run `docker compose -f docker/docker-compose.sbx.yaml up -d --build` to restart with an empty database.

## Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:sbx:start` | Build image and start all services |
| `npm run docker:sbx:stop` | Stop containers (preserves data volumes) |
| `npm run docker:sbx:down` | Stop and remove containers + volumes |
| `npm run docker:sbx:logs` | Live tail of container logs |
| `npm run docker:sbx:rebuild` | Full teardown + rebuild (after code changes) |

## How It Differs From Other Configurations

| | sbx | dev | devcontainer |
|-|-----|-----|-------------|
| Host requirements | Docker only | Docker + Node | VS Code + Dev Containers extension |
| Where build runs | Inside Docker multi-stage | Inside dev container (after manual exec) | Inside devcontainer |
| App source | Baked into image | Bind-mounted from host | Bind-mounted from host |
| Hot-reload JS/CSS | ✗ (requires rebuild) | ✓ (`npm run build:webpack:watch`) | ✓ |
| Xdebug | ✗ | ✓ | ✓ |
| Best for | Sandbox agents, quick demo, zero-setup evaluation | Active development | VS Code development |

## Files

| File | Description |
|------|-------------|
| `docker-compose.sbx.yaml` | Compose file (database + webserver + adminer) |
| `Dockerfile.sbx` | Multi-stage build (builder → runtime) |
| `scripts/sbx-start.sh` | Helper script with health wait + access URLs |

---

Development & Testing (Apache)
---

** THIS DOCKER CONFIGURATION IS INTENDED FOR DEVELOPMENT & TESTING PURPOSES ONLY**

ChurchCRM uses a single `docker-compose.yaml` with profiles to support different environments:
- **dev**: Full development environment (Node, NPM, Composer, Xdebug, Adminer)
- **test**: Minimal runtime for testing
- **ci**: CI/CD optimized (used by GitHub Actions)


Development
-------------

## Requirements

* Docker (with Compose v2+)
* GIT
* Node and NPM

## Steps

1. Clone ChurchCRM Repo: `git clone git@github.com:ChurchCRM/CRM.git`
2. `cd` into the project directory: `cd CRM`
3. Start dev containers: `npm run docker:dev:start`
    * **Note:** Containers run in background with `--profile dev`
4. Launch terminal in web container: `npm run docker:dev:login:web`
5. `cd` into project directory: `cd /home/ChurchCRM`
6. Build ChurchCRM: `npm run deploy`
7. Make logs writable: `chmod a+rwx src/logs`
8. Stop docker: `npm run docker:dev:stop`
9. View live logs: `npm run docker:dev:logs`

### Dev Profile Services
   - **database**: MariaDB server (port ${DATABASE_PORT:-3306})
   - **webserver-dev**: Apache + PHP 8 + dev tools (port ${WEBSERVER_PORT:-80})
   - **adminer**: Database GUI (port ${ADMINER_PORT:-8088})
      - Default credentials: `churchcrm` / `changeme`
   - **mailserver**: Fake SMTP (ports 1025, 8025 for UI)

### Docker Dev Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:dev:start` | Start dev containers (uses existing images) |
| `npm run docker:dev:stop` | Stop running containers |
| `npm run docker:dev:logs` | View container logs (live tail) |
| `npm run docker:dev:login:web` | Open shell in webserver container |
| `npm run docker:dev:login:db` | Open shell in database container |

Testing
-----------------

## Requirements

* Docker (with Compose v2+)
* GIT
* Node/NPM
* PHP 8.4+ with extensions: `bcmath`, `curl`, `fileinfo`, `filter`, `gd`, `gettext`, `iconv`, `mbstring`, `mysqli`, `PDO`, `pdo_mysql`, `session`, `sodium`, `zip`, `zlib`
* [Composer](https://getcomposer.org/) (PHP dependency manager)

> **Note:** PHP and Composer are required on the **build host** because `npm run deploy` compiles PHP
> dependencies via Composer locally before mounting them into Docker. If you want to avoid installing
> PHP locally, use the **Dev** workflow instead — it builds everything inside the Docker container.

## Steps

1. Clone repo: `git clone git@github.com:ChurchCRM/CRM.git`
2. Change into project directory: `cd CRM`
3. Install Node dependencies: `npm ci`
4. Build code locally: `npm run deploy`
5. Start test containers: `npm run docker:test:start`
6. Run tests: `npm run test`
7. Stop docker: `npm run docker:test:stop`

### Test Profile Services
   - **database**: MariaDB server (port 3306)
   - **webserver-test**: Minimal Apache + PHP 8 runtime (port 80)
   - **mailserver**: Fake SMTP (ports 1025, 8025)

### Docker Test Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:test:start` | Start test containers (uses existing images) |
| `npm run docker:test:stop` | Stop running containers |
| `npm run docker:test:restart` | Restart all test containers |
| `npm run docker:test:restart:db` | Restart only database (refreshes schema) |
| `npm run docker:test:rebuild` | Full rebuild: remove volumes, rebuild images, restart |
| `npm run docker:test:down` | Stop and remove containers with volumes |
| `npm run docker:test:logs` | View container logs (live tail) |
| `npm run docker:test:login:web` | Open shell in webserver container |
| `npm run docker:test:login:db` | Open shell in database container |

### Parallel Testing (Root + Subdirectory)

ChurchCRM supports both root path (`/`) and subdirectory (`/churchcrm/`) installations. The parallel testing infrastructure allows testing both configurations simultaneously without conflicts.

```bash
# Root path tests
npm run docker:ci:root:start
npm run test:root
npm run docker:ci:root:down

# Subdirectory tests  
npm run docker:ci:subdir:start
npm run test:subdir
npm run docker:ci:subdir:down
```

### Environment Variables

Configure ports in `docker/.env`:
```
DATABASE_PORT=3306
WEBSERVER_PORT=80
ADMINER_PORT=8088
MAILSERVER_PORT=1025
MAILSERVER_GUI_PORT=8025
```

---

Self-Hosted / Production (nginx + PHP-FPM)
---

The `docker-compose.nginx.yaml` and `nginx/default.conf` files provide a reference
configuration for deploying ChurchCRM with **nginx + PHP-FPM** instead of Apache.
This is the setup most commonly used in self-hosted environments (reverse-proxy
stacks, Kubernetes, etc.).

### Why nginx needs explicit routing

ChurchCRM is structured as multiple independent **Slim 4 PHP applications**, each
in its own subdirectory with its own `index.php` entry point:

| URL prefix | Entry point |
|------------|-------------|
| `/session/` | `session/index.php` — login, logout, 2FA |
| `/api/` | `api/index.php` — REST API |
| `/v2/` | `v2/index.php` — modern MVC pages |
| `/admin/` | `admin/index.php` — admin panel |
| `/finance/` | `finance/index.php` — finance module |
| `/kiosk/` | `kiosk/index.php` — check-in kiosk |
| `/plugins/` | `plugins/index.php` — plugin system |
| `/external/` | `external/index.php` — public integrations |
| `/setup/` | `setup/index.php` — first-run wizard |
| `/` | `index.php` — legacy PHP pages |

With **Apache**, each subdirectory's `.htaccess` file automatically routes
requests to the correct entry point.

With **nginx**, you must explicitly map each URL prefix to its entry point.
**Routing all requests to the root `index.php`** (a common mistake) causes an
infinite redirect loop because unauthenticated users are redirected to
`/session/begin`, but that path also goes to `index.php`, which redirects again.

### Quick start

```bash
# From the docker/ directory:
docker compose -f docker-compose.nginx.yaml up -d
```

Visit `http://localhost/` — you will see the setup wizard on first run.

### Files

| File | Description |
|------|-------------|
| `docker-compose.nginx.yaml` | Example Compose file (nginx + PHP-FPM + MariaDB) |
| `nginx/default.conf` | nginx server block with correct per-subdirectory routing |
| `Dockerfile.churchcrm-fpm-php8` | PHP-FPM image with all required extensions |

### Customising the nginx config

1. Copy `nginx/default.conf` to your deployment.
2. Replace `php-fpm:9000` with your PHP-FPM container hostname/port.
3. Set `root` to the path where ChurchCRM's `src/` contents are served from.
4. For a **subdirectory install** (e.g. `http://example.com/churchcrm/`):
   - Set `$sRootPath = '/churchcrm'` in `Include/Config.php`.
   - Prefix all `location` paths in the nginx config with `/churchcrm`.
   - See the commented example at the bottom of `nginx/default.conf`.

### Required PHP extensions

`bcmath`, `curl`, `exif`, `gd`, `gettext`, `iconv`, `intl`, `mbstring`, `mysqli`,
`opcache`, `pdo_mysql`, `sodium`, `xml`, `zip`

The `Dockerfile.churchcrm-fpm-php8` installs all of these.

---

### Self-Hosted / Production (FrankenPHP)

The `docker-compose.frankenphp.yaml` and `frankenphp/Caddyfile` files provide a
reference configuration for deploying ChurchCRM with **FrankenPHP** — an
all-in-one server that bundles Caddy and PHP in a single binary and container.
This results in a simpler two-service stack compared to nginx + PHP-FPM.

### Why FrankenPHP needs explicit routing

The same routing requirement applies as for nginx: ChurchCRM is structured as
multiple independent **Slim 4 PHP applications**, each in its own subdirectory
with its own `index.php` entry point.

| URL prefix | Entry point |
|------------|-------------|
| `/session/` | `session/index.php` — login, logout, 2FA |
| `/api/` | `api/index.php` — REST API |
| `/v2/` | `v2/index.php` — modern MVC pages |
| `/admin/` | `admin/index.php` — admin panel |
| `/finance/` | `finance/index.php` — finance module |
| `/kiosk/` | `kiosk/index.php` — check-in kiosk |
| `/plugins/` | `plugins/index.php` — plugin system |
| `/external/` | `external/index.php` — public integrations |
| `/setup/` | `setup/index.php` — first-run wizard |
| `/` | `index.php` — legacy PHP pages |

With **Apache**, each subdirectory's `.htaccess` file automatically routes
requests to the correct entry point.

With **FrankenPHP (Caddy)**, you must explicitly map each URL prefix to its
entry point in the `Caddyfile`. **Routing all requests to the root `index.php`**
(a common mistake) causes an infinite redirect loop because unauthenticated
users are redirected to `/session/begin`, but that path also goes to
`index.php`, which redirects again.

### Quick start

```bash
# From the docker/ directory:
docker compose -f docker-compose.frankenphp.yaml up -d
```

Visit `http://localhost/` — you will see the setup wizard on first run.

### Files

| File | Description |
|------|-------------|
| `docker-compose.frankenphp.yaml` | Example Compose file (FrankenPHP + MariaDB) |
| `frankenphp/Caddyfile` | Caddy server block with correct per-subdirectory routing |
| `Dockerfile.churchcrm-frankenphp` | FrankenPHP image with all required PHP extensions |

### Customising the Caddyfile

1. Copy `frankenphp/Caddyfile` to your deployment.
2. Update `root` to the path where ChurchCRM's `src/` contents are served from.
3. For a **subdirectory install** (e.g. `http://example.com/churchcrm/`):
   - Set `$sRootPath = '/churchcrm'` in `Include/Config.php`.
   - Prefix all `handle` paths in the Caddyfile with `/churchcrm`.
   - See the commented example at the bottom of `frankenphp/Caddyfile`.
4. To enable **automatic HTTPS**, replace `:80` with your domain name (Caddy
   provisions a Let's Encrypt certificate automatically).

### Required PHP extensions

`bcmath`, `curl`, `exif`, `gd`, `gettext`, `iconv`, `intl`, `mbstring`, `mysqli`,
`opcache`, `pdo_mysql`, `sodium`, `xml`, `zip`

The `Dockerfile.churchcrm-frankenphp` installs all of these via the
`install-php-extensions` helper bundled with the FrankenPHP base image.
