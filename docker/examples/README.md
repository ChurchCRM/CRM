# Docker Compose Reference Templates

This directory contains example Docker Compose configurations for **alternate deployment architectures**. These are **not used by the automated test suite** and are provided as starting points for your own deployments.

## Included Templates

### docker-compose.nginx.yaml
Production deployment with **nginx + PHP-FPM** (two containers) instead of Apache.

**When to use:**
- Self-hosted deployments preferring nginx + PHP-FPM
- Environments requiring separate web server and application process

**Quick start:**
```bash
docker compose -f examples/docker-compose.nginx.yaml up -d
```

Serves on `http://localhost`. Edit `examples/nginx/default.conf` to customize.

### docker-compose.frankenphp.yaml
Production deployment with **FrankenPHP** (single container bundling Caddy + PHP).

**When to use:**
- Minimal deployment footprint (one web service)
- Modern PHP deployment with automatic HTTPS support (Caddy)

**Quick start:**
```bash
docker compose -f examples/docker-compose.frankenphp.yaml up -d
```

Serves on `http://localhost`. Edit `examples/frankenphp/Caddyfile` to customize.

## Common Setup for Both

1. Copy `docker/Config.php` to `Include/Config.php` in your ChurchCRM source
2. Update database connection details to match the compose file
3. Set strong passwords before deploying to the internet

## For Development / Testing

- **Local dev with live reload:** Use `docker/docker-compose.yaml` (default)
- **CI/test builds:** Use `docker/docker-compose.yaml` with `--profile test` or `--profile ci`
- **Parallel CI testing:** Use `docker/docker-compose.yaml` + `docker/docker-compose.parallel.yaml`
