# Parallel Testing Infrastructure

This directory contains configuration files for running ChurchCRM tests in parallel for both root path and subdirectory installations.

## Overview

ChurchCRM can be installed in two ways:
1. **Root path** - Accessible at `http://yourdomain.com/`
2. **Subdirectory** - Accessible at `http://yourdomain.com/churchcrm/`

The parallel testing infrastructure allows testing both configurations simultaneously in CI/CD without conflicts.

## Key Components

### Docker Compose Files

- **docker-compose.yaml** - Base configuration with common services
- **docker-compose.parallel.yaml** - Parallel test configuration with separate databases and webservers
  - `database-root` + `webserver-root` - Root path testing (port 80)
  - `database-subdir` + `webserver-subdir` - Subdirectory testing (port 8080)

### Cypress Configurations

- **cypress.config.ts** - Root path testing config (baseUrl: `http://localhost/`)
- **cypress.subdir.config.ts** - Subdirectory testing config (baseUrl: `http://localhost:8080/churchcrm/`)

### PHP Configurations

- **Config.php** - Standard root path config (database: `database`)
- **Config.root.php** - Parallel root path config (database: `database-root`)
- **Config.subdir.php** - Standard subdirectory config (database: `database`)
- **Config.parallel.subdir.php** - Parallel subdirectory config (database: `database-subdir`)

## Usage

### Local Testing

#### Test Root Path Installation
```bash
# Start root path environment
npm run docker:ci:root:start

# Run tests
npm run test:root

# Stop environment
npm run docker:ci:root:down
```

#### Test Subdirectory Installation
```bash
# Start subdirectory environment
npm run docker:ci:subdir:start

# Run tests
npm run test:subdir

# Stop environment
npm run docker:ci:subdir:down
```

#### Test Both in Parallel
You can run both environments simultaneously since they use different ports and databases:

```bash
# Terminal 1 - Root path
npm run docker:ci:root:start
npm run test:root

# Terminal 2 - Subdirectory (in parallel)
npm run docker:ci:subdir:start
npm run test:subdir
```

### GitHub Actions

The parallel testing workflow is defined in `.github/workflows/build-test-package-parallel.yml`:

1. **Build Job** - Builds the application once
2. **Test-Root Job** - Tests root path installation (runs in parallel with test-subdir)
3. **Test-Subdir Job** - Tests subdirectory installation (runs in parallel with test-root)
4. **Package Job** - Creates release artifacts (only runs if both tests pass)

## Benefits

### No Database Conflicts
Each test environment uses its own isolated database:
- Root tests use `database-root` on port 3306
- Subdirectory tests use `database-subdir` on port 3307

### Faster CI/CD
Tests run in parallel, reducing total execution time by ~50%:
- Old approach: Build → Test Root → Test Subdir (~30 minutes)
- New approach: Build → (Test Root + Test Subdir in parallel) (~15 minutes)

### Better Coverage
Both installation methods are tested on every commit, ensuring ChurchCRM works correctly in all deployment scenarios.

## Port Allocations

| Service | Root Path | Subdirectory |
|---------|-----------|--------------|
| Webserver | 80 | 8080 |
| Database | 3306 | 3307 |
| Hostname (DB) | database-root | database-subdir |
| Hostname (Web) | crm-webserver-root | crm-webserver-subdir |

## Environment Variables

You can customize ports using environment variables:

```bash
# Root path
export WEBSERVER_PORT=80
export DATABASE_PORT=3306

# Subdirectory
export WEBSERVER_SUBDIR_PORT=8080
export DATABASE_SUBDIR_PORT=3307
```

## Troubleshooting

### Port Conflicts
If you get port binding errors, ensure no other services are using ports 80, 3306, 8080, or 3307.

### Database Connection Issues
Check that the Config.php file has the correct database hostname:
- Root path should use `database-root`
- Subdirectory should use `database-subdir`

### Test Failures
Review the Cypress logs and Docker logs:
```bash
# Root path logs
docker compose -f docker/docker-compose.yaml -f docker/docker-compose.parallel.yaml --profile ci-root logs

# Subdirectory logs
docker compose -f docker/docker-compose.yaml -f docker/docker-compose.parallel.yaml --profile ci-subdir logs
```

## Migration from Old Testing

If you're migrating from the old single-environment testing:

**Old:**
```bash
npm run docker:ci:start
npm run test
npm run docker:ci:down
```

**New (Root Path):**
```bash
npm run docker:ci:root:start
npm run test:root
npm run docker:ci:root:down
```

**New (Subdirectory):**
```bash
npm run docker:ci:subdir:start
npm run test:subdir
npm run docker:ci:subdir:down
```

The old scripts (`docker:ci:start`, `test`) still work and test the root path configuration only.
