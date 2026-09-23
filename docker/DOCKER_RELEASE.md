# Docker Release Images

ChurchCRM releases are automatically built as Docker images and pushed to DockerHub on every GitHub release.

## Available Images

- **`churchcrm/crm:7.7.0-php8-apache`** / **`churchcrm/crm:latest-php8-apache`** — Apache + mod_php, serves HTTP directly on port 80. Simplest single-container option.
- **`churchcrm/crm:7.7.0-php8-fpm`** / **`churchcrm/crm:latest-php8-fpm`** — PHP-FPM only, listens on port 9000 via the FastCGI protocol (not HTTP). Requires a separate web server (e.g. nginx) in front — see below.

## Quick Start

```bash
# Simplest: Apache image serves HTTP directly
docker pull churchcrm/crm:latest-php8-apache
docker run -d -p 8080:80 churchcrm/crm:latest-php8-apache
```

The FPM image speaks FastCGI, not HTTP — `docker run -p 80:9000 ...` will not
serve a working site. It needs an nginx (or Apache) reverse proxy in front
that forwards PHP requests to port 9000 over FastCGI; see
[`docker/examples/docker-compose.nginx.yaml`](examples/docker-compose.nginx.yaml)
for the general shape (that example builds its own image from source, so
adapt its `php-fpm` service to use `image: churchcrm/crm:latest-php8-fpm`
instead of `build:`, and drop the shared `churchcrm-www` volume — the release
image already has the code baked in, and mounting an empty volume over
`/var/www/html` would hide it).

## Setup

To enable automatic builds, add DockerHub credentials to GitHub Secrets:

1. Create a Personal Access Token scoped to Read & Write on this repository only: https://hub.docker.com/settings/personal-access-tokens
2. Add GitHub Secrets (Settings → Secrets → Actions):
   - `DOCKERHUB_USERNAME`
   - `DOCKERHUB_TOKEN`

Full (non-pre-release) releases trigger automatic builds and push to DockerHub.

## Images Include

- Compiled ChurchCRM release code (in `/var/www/html`)
- PHP 8.4 with all required extensions
- Non-root user (www for PHP-FPM)
- Ready to run — no additional build steps

## Workflow

When you publish a GitHub Release (pre-releases are skipped, so `latest` always
tracks a stable release):
1. Workflow downloads the ChurchCRM-*.zip artifact
2. Builds Docker images for amd64 and arm64
3. Pushes to DockerHub with version and `latest` tags

Done. Images are ready for deployment.
