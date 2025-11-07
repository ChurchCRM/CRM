# ChurchCRM Development Container

This directory contains the configuration for GitHub Codespaces and VS Code Dev Containers.

## What This Provides

When you open ChurchCRM in GitHub Codespaces or VS Code with the Dev Containers extension, this configuration will automatically:

1. **🐳 Start Required Services:**
   - MariaDB database server
   - Apache web server with PHP 8
   - MailHog fake SMTP server
   - Adminer database management UI

2. **📦 Install & Build Everything:**
   - Install Node.js dependencies (`npm ci`)
   - Build PHP dependencies with Composer
   - Compile frontend assets with Webpack
   - Set up proper file permissions

3. **🔧 Configure VS Code:**
   - Install useful extensions (PHP IntelliSense, Copilot, etc.)
   - Set up proper PHP and TypeScript settings
   - Configure port forwarding for web access

4. **🚀 Ready-to-Use Environment:**
   - ChurchCRM running at `http://localhost`
   - Default login: `admin` / `changeme`
   - All services connected and configured

## Quick Start

### GitHub Codespaces
1. Click "Code" → "Codespaces" → "Create codespace on [branch]"
2. Wait for automatic setup (2-3 minutes)
3. Open `http://localhost` and login with `admin` / `changeme`

### VS Code Dev Containers
1. Install the "Dev Containers" extension
2. Open this repo in VS Code
3. Click "Reopen in Container" when prompted
4. Wait for setup to complete

## Services & Ports

| Service | Port | Purpose | URL |
|---------|------|---------|-----|
| ChurchCRM Web | 80 | Main application | http://localhost |
| MariaDB | 3306 | Database server | N/A (internal) |
| MailHog UI | 8025 | Email testing | http://localhost:8025 |
| Adminer | 8088 | Database admin | http://localhost:8088 |

## Manual Setup

If you prefer to set up manually or the automatic setup fails:

```bash
# Run the setup script
./scripts/setup-dev-environment.sh

# Or step by step:
npm ci
npm run deploy
npm run docker:dev:start
```

## Troubleshooting

- **Services not starting:** Run `npm run docker:dev:logs` to check logs
- **Build failures:** Ensure Docker has enough memory (4GB+ recommended)
- **Port conflicts:** Stop other local web servers on port 80
- **Permission errors:** Run `chmod a+rwx src/logs`

## Development Workflow

```bash
# Make code changes...
npm run build:frontend    # Rebuild after JS/CSS changes
npm run test              # Run Cypress tests
npm run docker:dev:logs   # View logs
```

For complete documentation, see [CONTRIBUTING.md](../CONTRIBUTING.md).