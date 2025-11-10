# DevContainer Configuration Changes - November 2025

## Issues Identified from creation.log

### Primary Error (Line 16-17):
```
Error: The expected container does not exist.
Error code: 1302 (UnifiedContainersErrorFatalCreatingContainer)
```

**Root Cause**: The old configuration used `dockerComposeFile` pointing to services with profiles (`dev`, `test`, `ci`). Codespaces expected a pre-built container but none existed, causing it to fall back to a minimal Alpine recovery container.

### Secondary Issues:
1. Docker Compose services had profile restrictions that prevented startup
2. Source code mounting was implicit, not guaranteed
3. Build process ran inside Docker container startup (slow, fragile)
4. No verification that `src/` directory was actually present

---

## What Was Fixed

### 1. Removed Docker Compose Dependency for Container Creation

**Before:**
```json
{
  "dockerComposeFile": ["../docker/docker-compose.yaml", "docker-compose.override.yml"],
  "service": "webserver-dev",
  "workspaceFolder": "/home/ChurchCRM"
}
```

**After:**
```json
{
  "image": "mcr.microsoft.com/devcontainers/php:8.2-bullseye",
  "workspaceFolder": "/workspaces/${localWorkspaceFolderBasename}"
}
```

**Why**: Using a standalone image ensures the container always starts successfully. Docker Compose is still available via Docker-in-Docker feature for running services.

### 2. Added Required Development Features

```json
"features": {
  "ghcr.io/devcontainers/features/node:1": { "version": "lts" },
  "ghcr.io/devcontainers/features/git-lfs:1": {},
  "ghcr.io/devcontainers/features/docker-in-docker:2": { "version": "latest" },
  "ghcr.io/devcontainers/features/common-utils:2": { "installZsh": true }
}
```

**Why**: Ensures all tools (Node.js, Composer, Git LFS, Docker) are available immediately without relying on Docker Compose services.

### 3. Simplified Build Process

**Before**: Build ran during Docker container initialization (fragile, timed out)

**After**: Build runs in `postCreateCommand` after container is stable

```json
"postCreateCommand": {
  "setup": "bash .devcontainer/init.sh"
}
```

### 4. Updated init.sh to Match Build Requirements

The script now:
- ✓ Verifies `src/` directory exists (code checkout verification)
- ✓ Creates `docker/.env` if missing
- ✓ Runs `npm ci` (installs packages + postinstall hook for locale files)
- ✓ Runs `composer install --no-dev` (PHP dependencies)
- ✓ Runs `npm run build:frontend` (Webpack + Grunt + Prettier)
- ✓ Provides clear status messages and next steps

### 5. Docker Services Now Manual Start

**Philosophy**: Separate concerns - container setup vs. service runtime

**User Workflow:**
1. Container starts → code checkout verified → dependencies installed → build completes
2. User can immediately run `npm run build`, edit code, commit changes
3. When ready to test: `npm run docker:dev:start`
4. Docker services (MariaDB, Apache, MailHog, Adminer) start on demand

---

## Build Command Support

After setup completes, these commands work immediately:

| Command | What It Does | Requires Docker? |
|---------|--------------|------------------|
| `npm run build` | Full build (PHP + frontend) | ❌ No |
| `npm run build:frontend` | Webpack + Grunt + Prettier | ❌ No |
| `npm run build:php` | Composer install | ❌ No |
| `npm ci` | Reinstall packages | ❌ No |
| `npm run deploy` | Production build | ❌ No |
| `npm run docker:dev:start` | Start services | ✅ Yes (Docker-in-Docker) |
| `npm run test` | Cypress tests | ✅ Yes (needs services) |

---

## File Changes Summary

### devcontainer.json
- Switched from Docker Compose to standalone PHP 8.2 image
- Added Node.js, Git LFS, Docker-in-Docker features
- Simplified to `postCreateCommand` only
- Changed user from `root` to `vscode`

### init.sh
- Added source code verification
- Proper error handling with `set -e`
- Explicit build steps: npm ci → composer → build:frontend
- Informative output showing what's happening
- No emoji/Unicode issues (plain ASCII for compatibility)

### post-start.sh
- Simplified to lightweight reminder
- No Docker health checks (services not running)
- Just reminds user to start Docker when needed

### docker-compose.override.yml
- Still present for when user runs `npm run docker:dev:start`
- Removes profile restrictions
- Explicitly defines volume mounts

### README.md
- Documents what happens automatically
- Clear separation: setup vs. Docker services
- Troubleshooting section for common issues
- Explains what commands work with/without Docker

---

## Testing the Changes

### To verify the fix works:

1. **Create new Codespace**
   ```
   GitHub → Code → Codespaces → Create codespace
   ```

2. **Verify setup completes**
   - Should see "Setup Complete!" message
   - No Alpine recovery container fallback

3. **Verify builds work**
   ```bash
   npm run build              # Should work immediately
   npm run build:frontend     # Should work immediately
   ```

4. **Verify Docker works when started**
   ```bash
   npm run docker:dev:start   # Should start all services
   docker ps                  # Should show 4+ containers
   ```

5. **Access services**
   - http://localhost → ChurchCRM
   - http://localhost:8025 → MailHog
   - http://localhost:8088 → Adminer

---

## Key Principles Applied

1. **Fail Fast**: Verify `src/` exists before attempting anything else
2. **Clear Output**: User knows exactly what's happening at each step
3. **Separation of Concerns**: Container setup ≠ Service runtime
4. **Immediate Productivity**: Code ready to edit/build without Docker
5. **User Control**: Start Docker only when needed for testing
6. **Idempotent**: Re-running init.sh is safe

---

*Fixed: November 7, 2025*
