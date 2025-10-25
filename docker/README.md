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

### Environment Variables

Configure ports in `docker/.env`:
```
DATABASE_PORT=3306
WEBSERVER_PORT=80
ADMINER_PORT=8088
MAILSERVER_PORT=1025
MAILSERVER_GUI_PORT=8025
```