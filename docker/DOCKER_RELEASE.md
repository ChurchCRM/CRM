# ChurchCRM Docker release images

Stable releases publish `churchcrm/crm:<version>-php8-apache` and
`churchcrm/crm:<version>-php8-fpm`. Only the release currently marked latest on
GitHub updates `latest-php8-apache` / `latest-php8-fpm`. Prefer a version tag or
image digest for production deployments. Re-running a build can replace a
version tag; DockerHub tag immutability is a separate registry policy.

## Runtime and ports

- Apache serves HTTP on **8080** and runs as `www-data` (UID/GID 33).
  This changes the initial PR's production port 80. Test/dev stages retain 80.
- FPM runs as UID/GID 33 and speaks **FastCGI on 9000**, not HTTP. Never publish
  9000 to an untrusted network. A separate web server must serve the matching
  static assets and forward PHP requests to FPM on the private container network.
- Both images contain the compiled release, PHP extensions, and locales. They
  omit Xdebug and purge the inherited PHP compiler toolchain from the final
  filesystem. The upstream base layers still contain the original toolchain;
  purging does not reclaim bytes in those layers.

To preview the Apache setup page locally:

```bash
docker run --rm --cap-drop ALL --security-opt no-new-privileges \
  -p 127.0.0.1:8080:8080 churchcrm/crm:latest-php8-apache
```

This preview does not configure a database or persist application data. A real
installation needs a reachable MariaDB/MySQL service, a configured
`Include/Config.php`, persistent uploaded images, and backups. Place a TLS reverse
proxy in front for remote access. Configure memory, CPU and PID limits according
to deployment size.

The files under `docker/examples/` illustrate alternative architectures; they
are not working release-image recipes. In particular, the nginx example refers
to an absent `nginx/default.conf` and needs an explicit static-asset deployment.
Removing its shared volume without provisioning nginx's files will break it.
An empty named volume normally receives image contents on first mount; a bind
mount obscures the contents, and a reused named volume keeps stale application
code across image upgrades. Do not persist the whole document root as an upgrade
strategy. Keep nginx and FPM on the same application version.

## Writable files and upgrades

Application files currently remain writable by UID 33 to support ChurchCRM's
installer, updater and plugin operations. Non-root execution therefore does not
prevent a compromised PHP process from changing application code. A read-only
root filesystem needs an explicit writable-path design and application-level
setup, upload, plugin and upgrade testing before it can become the default.
Mount an existing production `Include/Config.php` read-only where possible, keep
credentials out of images, and provision volume ownership for UID/GID 33.
Use image replacement for container upgrades; back up the database, configuration
and uploaded data first. A full persistence/restore acceptance test remains a
prerequisite for a production deployment recipe.

## Publishing

Configure `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` in GitHub Actions secrets.
Use a dedicated publishing identity with the narrowest repository write access
available for the DockerHub account/plan. Do not grant delete or organization
administration access. A personal token's Read/Write scope alone is not a claim
of per-repository restriction.

The workflow:

1. Rejects drafts, prereleases and unsupported version tags.
2. Downloads `ChurchCRM-<version>.zip` from the exact triggering release.
3. Checks its GitHub asset SHA-256 digest, then validates and extracts the
   `churchcrm/` wrapper while preserving `.htaccess`. This verifies transport and
   asset consistency, not independent producer authenticity.
4. Builds the explicit `prod` target for amd64 and arm64, with SBOM and provenance.
5. Publishes version tags and conditionally the stable latest tags.

A release published by a workflow using `GITHUB_TOKEN` does not trigger another
release workflow. For that path, or a retry, use **Build & Push Docker Images →
Run workflow** with the existing stable `release_tag`. Run a ref containing these
Dockerfiles and workflow helpers. Human publication of a draft triggers the
normal release event. Workflow runs are serialized; GitHub concurrency retains
only one pending run, so rerun any displaced release explicitly.

PHP image references pin PHP 8.4.25, Debian Trixie and the multi-platform digest.
Update both the tag and digest together, keep builder and runtime references
identical, and rerun production image tests. The `PHP_IMAGE` build argument must
remain a compatible PHP 8.4+ image of the same server flavor and Debian suite.
Pinning does not replace regular security updates. Debian packages are still
resolved from live repositories, so builds are not bit-for-bit reproducible.

## Validation

```bash
python3 docker/test-release-context.py
python3 docker/prepare-release-context.py ChurchCRM-7.7.0.zip build-context
mkdir -p build-context/apache
cp docker/apache/default.conf build-context/apache/default.conf
docker build --target prod -f docker/Dockerfile.churchcrm-apache-php8 -t churchcrm-check:apache build-context
docker build --target prod -f docker/Dockerfile.churchcrm-fpm-php8 -t churchcrm-check:fpm build-context
bash docker/test-production-image.sh churchcrm-check:apache apache
bash docker/test-production-image.sh churchcrm-check:fpm fpm
```

The PR validation workflow repeats these builds on amd64 without registry
credentials. Smoke tests check non-root startup under dropped capabilities,
extensions, compiler absence and Apache's first HTTP response. They do not cover
database setup, FPM request routing, uploads, restoration or application upgrades.
