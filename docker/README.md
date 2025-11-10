Getting Started with Docker for Development
===========================

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

## Steps

1. Clone repo: `git clone git@github.com:ChurchCRM/CRM.git`
2. Build code locally: `npm run deploy`
3. Start test containers: `npm run docker:test:start`
4. Run tests: `npm run test`
5. Stop docker: `npm run docker:test:stop`

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

### Environment Variables

Configure ports in `docker/.env`:
```
DATABASE_PORT=3306
WEBSERVER_PORT=80
ADMINER_PORT=8088
MAILSERVER_PORT=1025
MAILSERVER_GUI_PORT=8025
```