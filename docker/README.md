Getting Started with Docker for ChurchCRM
===========================

This directory contains Docker configurations for three scenarios:

| Scenario | Profile/File | Use Case |
|----------|--------------|----------|
| **Dev** | `docker-compose.dev.yaml` | Edit code locally, build tools in Docker, instant live reload |
| **Test** | `docker-compose.yaml --profile test` | Run pre-compiled code (you built locally with `npm run build`) |
| **CI** | `docker-compose.yaml + docker-compose.parallel.yaml --profile ci-*` | Automated parallel testing (GitHub Actions) |

Reference deployment templates are in `examples/`.

---

Dev Profile (Edit Locally, Build in Docker)
---

Use this when you want to **edit code locally** but don't have Node, PHP, and Composer installed on your host. All development tools run inside the container, code is bind-mounted so changes appear instantly.

### Quick Start

```bash
# From repo root:
npm run docker:dev:start                    # start containers
npm run docker:dev:build                    # one-time: npm ci + npm run build inside container

# Now edit code locally — changes appear instantly
# Restart container if needed:
npm run docker:dev:rebuild
```

First start: pulls/builds base image (includes dev tools). Subsequent starts are instant.

### Services

| Service | Image | Port | Notes |
|---------|-------|------|-------|
| `webserver-dev` | `churchcrm/crm:php8-debian-dev` (built once) | `80` | Apache + PHP 8.4 with dev tools (Node, Composer, Xdebug) |
| `database` | `mariadb:10.11` | `3306` | Seeded with demo data |
| `mailserver` | `axllent/mailpit` | `1025`, `8025` | Fake SMTP |

### Access

| URL | Credentials |
|-----|-------------|
| `http://localhost` | admin / changeme |
| `http://localhost:8025` (Mailpit) | View fake SMTP emails |

Access database via VS Code or your IDE (host: localhost:3306, user: churchcrm, password: changeme).

### Workflow

1. **Start containers** (first time builds image):
   ```bash
   npm run docker:dev:start
   ```

2. **One-time: build dependencies inside container**:
   ```bash
   npm run docker:dev:build    # runs: npm ci && npm run build
   ```

3. **Edit code locally** — any changes in `src/` appear instantly in the container

4. **Restart container if needed**:
   ```bash
   npm run docker:dev:rebuild  # just restarts, no rebuild
   ```

5. **Stop when done**:
   ```bash
   npm run docker:dev:stop     # keep volumes
   npm run docker:dev:down     # remove volumes
   ```

### Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:dev:start` | Start containers (builds image on first run) |
| `npm run docker:dev:stop` | Stop containers (keep volumes) |
| `npm run docker:dev:down` | Stop + remove containers and volumes |
| `npm run docker:dev:build` | One-time: npm ci + npm run build inside container |
| `npm run docker:dev:logs` | View live container logs |
| `npm run docker:dev:rebuild` | Restart webserver (after code edits) |
| `npm run docker:dev:reset:db` | Reset database to fresh seed data |
| `npm run docker:dev:reset` | Full teardown + restart everything |
| `npm run docker:dev:login:web` | Open shell in webserver container |
| `npm run docker:dev:login:db` | Open shell in database container |

### Code Changes

Because `src/` is bind-mounted, **changes are instant** — no rebuild needed. Just refresh your browser or restart the container:

- **PHP/config changes**: usually instant (browser refresh)
- **Node build changes** (CSS/JS): run `npm run docker:dev:build` if source files change
- **Apache config changes**: `npm run docker:dev:rebuild`

### Demo Data vs Fresh Install

By default, database is seeded with demo data. For a fresh setup wizard:

1. Remove `../cypress/data` bind-mount from `database` service
2. Run `npm run docker:dev:reset`

---

Test Profile (Run Pre-Compiled Code)
---

Use this when you **have Node, PHP, and Composer installed locally**. You build the app locally with `npm run build`, then Docker just runs the compiled code for verification.

### Requirements

* Docker + Docker Compose v2
* Node/NPM
* PHP 8.4+ with: `bcmath`, `curl`, `exif`, `gd`, `gettext`, `iconv`, `mbstring`, `mysqli`, `pdo_mysql`, `zip`
* Composer

### Quick Start

```bash
# From repo root:
npm ci                          # install Node deps
npm run build                   # compile code locally (generates src/vendor, webpack assets)
npm run docker:test:start       # start Docker containers with compiled code
npm run test                    # run test suite
npm run docker:test:stop        # stop containers
```

### Services

| Service | Port | Notes |
|---------|------|-------|
| `webserver-test` | `80` | Apache + PHP 8 runtime (no dev tools) |
| `database` | `3306` | MariaDB with demo data |
| `mailserver` | `1025`, `8025` | Fake SMTP |

### Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:test:start` | Start test containers (uses existing images) |
| `npm run docker:test:stop` | Stop running containers |
| `npm run docker:test:down` | Stop + remove containers and volumes |
| `npm run docker:test:rebuild` | Rebuild webserver image (no cache) |
| `npm run docker:test:reset:db` | Reset database to fresh seed data |
| `npm run docker:test:logs` | View container logs (live) |
| `npm run docker:test:login:web` | Open shell in webserver |
| `npm run docker:test:login:db` | Open shell in database |

---

CI / Parallel Testing (GitHub Actions)
---

The automated test suite runs in parallel with 3 separate test scenarios (root path, subdirectory, new-system setup wizard), each with its own database to avoid conflicts.

Uses: `docker-compose.yaml` + `docker-compose.parallel.yaml` with `--profile ci-root`, `--profile ci-subdir`, `--profile ci-new-system`

See `.github/workflows/build-test-package.yml` for the full workflow.

### CI-Only Commands

| Command | Purpose |
|---------|---------|
| `npm run docker:ci:root:start` | Start root path test suite |
| `npm run docker:ci:root:down` | Tear down root path test suite |
| `npm run docker:ci:subdir:start` | Start subdirectory path test suite |
| `npm run docker:ci:subdir:down` | Tear down subdirectory test suite |
| `npm run docker:ci:new-system:start` | Start fresh install (setup wizard) test |
| `npm run docker:ci:new-system:down` | Tear down fresh install test |
| `npm run docker:ci:new-system:subdir:start` | Start fresh install subdirectory test |
| `npm run docker:ci:new-system:subdir:down` | Tear down fresh install subdirectory test |

---

Comparison: Dev vs Test

| | Dev | Test |
|---|-----|------|
| **Host requires** | Docker only | Docker + Node + PHP + Composer |
| **Code location** | Bind-mounted (edit locally) | Bind-mounted (edit locally) |
| **Build runs** | Inside container | On host (`npm run build`) |
| **Best for** | Live development, no host tools | CI-like, verify compiled app |
| **Image includes** | Full dev toolchain (Node, Composer) | Minimal runtime only |
| **Speed: start** | Instant (second+ runs) | Instant |
| **Speed: code changes** | Instant reload | Edit → `npm run build` → restart |

---

Self-Hosted & Production References
---

Reference configurations for production-like deployments are in `examples/`:

- **nginx + PHP-FPM** (`examples/docker-compose.nginx.yaml`)
- **FrankenPHP** (`examples/docker-compose.frankenphp.yaml`)

See `examples/README.md` for details.
