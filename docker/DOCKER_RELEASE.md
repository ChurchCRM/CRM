# Docker Release Images

ChurchCRM releases are automatically built as Docker images and pushed to DockerHub on every GitHub release.

## Available Images

- **`churchcrm/crm:7.7.0-php8-fpm`** — PHP-FPM + nginx (production)
- **`churchcrm/crm:latest-php8-fpm`** — Latest stable PHP-FPM
- **`churchcrm/crm:7.7.0-php8-apache`** — Apache (testing)
- **`churchcrm/crm:latest-php8-apache`** — Latest stable Apache

## Quick Start

```bash
# Pull and run
docker pull churchcrm/crm:latest-php8-fpm

# Run with compose (bring your own docker-compose.yaml)
docker run -d -p 80:9000 churchcrm/crm:latest-php8-fpm
```

## Setup

To enable automatic builds, add DockerHub credentials to GitHub Secrets:

1. Create Personal Access Token: https://hub.docker.com/settings/personal-access-tokens
2. Add GitHub Secrets (Settings → Secrets → Actions):
   - `DOCKERHUB_USERNAME`
   - `DOCKERHUB_TOKEN`

Releases trigger automatic builds and push to DockerHub.

## Images Include

- Compiled ChurchCRM release code (in `/var/www/html`)
- PHP 8.4 with all required extensions
- Non-root user (www for PHP-FPM)
- Ready to run — no additional build steps

## Workflow

When you publish a GitHub Release:
1. Workflow downloads the ChurchCRM-*.zip artifact
2. Builds Docker images for amd64 and arm64
3. Pushes to DockerHub with version and `latest` tags

Done. Images are ready for deployment.
